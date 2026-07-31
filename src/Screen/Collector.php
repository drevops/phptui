<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Tree;
use DrevOps\Tui\Condition\ConditionInterface;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Derive\Deriver;
use DrevOps\Tui\Discovery\DiscoverInterface;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\Engine\Source;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Handler\HandlerRegistry;
use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Translation\Translator;

/**
 * Collects a form's answers with no screen at all.
 *
 * Four capabilities survive here - collecting, constraining, refusing, and
 * depending on another answer - and the thirteen that do not are the ones about
 * drawing. That line is the point: the four are the form's meaning and the rest
 * is how it looks, which is why one declaration serves both paths.
 *
 * No screen, layout or region is built, and neither is any block that only
 * shows. Fields are built without their modes, because there is no cursor to
 * open anything.
 *
 * Where a value comes from is the whole of the difference. Each field takes the
 * first of a supplied value, a value detected outside the form, a value
 * computed from the others, and its declared default; the set then settles -
 * rows that follow the answers resolve, computed values recompute, conditions
 * decide who is there at all and fix-ups apply - until nothing moves. Only then
 * is a supplied value measured, because until the set has settled there is
 * nothing final to measure it against.
 *
 * @package DrevOps\Tui\Screen
 */
final class Collector {

  /**
   * Recomputes derived values until the chains settle.
   */
  protected Deriver $deriver;

  /**
   * What each field's answer-driven rows were last resolved from, and to.
   *
   * A settling pass that leaves the resolver's whole input as it was - the
   * answers and the rest of the run context - leaves the rows it produced
   * valid, so it is called again only when what it reads has actually changed.
   * The rows are remembered alongside that input because a field's rows are
   * settled state anyone may write: a description resolving them against a
   * context of its own retires this memo rather than leaving the collection
   * convinced they are still its own.
   *
   * @var array<string,array{answers:array<string,mixed>,run:array{string,bool,string},rows:list<\DrevOps\Tui\Model\Option>}>
   */
  protected array $memo = [];

  /**
   * Resolves a field id to the behaviour written once for that kind of answer.
   */
  protected HandlerRegistry $handlers;

  /**
   * Construct a collector.
   *
   * @param \DrevOps\Tui\Handler\HandlerRegistry|null $handlers
   *   The registry resolving a field id to the behaviour reusable across every
   *   form that asks for that kind of answer, or NULL when nothing is reused
   *   and each field speaks only for itself.
   * @param \DrevOps\Tui\Model\Fixup[] $fixups
   *   The rules applied once the answers have settled. They belong to the form
   *   rather than to any one block, so they arrive beside the tree.
   */
  public function __construct(?HandlerRegistry $handlers = NULL, protected array $fixups = []) {
    $this->handlers = $handlers ?? new HandlerRegistry();
    $this->deriver = new Deriver();
  }

  /**
   * Collect a panel's answers.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to collect, and any panels beneath it.
   * @param array<string,mixed> $supplied
   *   Values supplied for its fields, keyed by field id.
   * @param \DrevOps\Tui\Handler\Context|null $context
   *   The run this collection belongs to, or NULL for one that targets no
   *   directory and detects nothing.
   *
   * @return array<string,mixed>
   *   The answers, keyed by field id.
   *
   * @throws \InvalidArgumentException
   *   When a supplied value is refused. Collecting without a screen is a call
   *   in someone's code, so a value it cannot take is reported as a bad
   *   argument to that call.
   */
  public function collect(Panel $panel, array $supplied = [], ?Context $context = NULL): array {
    [$fields, $values, $sources, $active] = $this->settle($panel, $supplied, $context ?? new Context());

    $refusal = $this->refusal($fields, $values, $sources, $active);

    if ($refusal !== NULL) {
      throw new \InvalidArgumentException(sprintf('Cannot collect "%s": %s', $refusal[0], $refusal[1]));
    }

    return $this->activeAnswers($fields, $values, $active);
  }

  /**
   * Collect a panel's answers, each describing the question it answers.
   *
   * The same collection as {@see collect()}, handed back as the self-describing
   * set: every answer carries its provenance and a snapshot of its question, so
   * a summary needs no form configuration to print.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to collect, and any panels beneath it.
   * @param array<string,mixed> $supplied
   *   Values supplied for its fields, keyed by field id.
   * @param \DrevOps\Tui\Handler\Context|null $context
   *   The run this collection belongs to, or NULL for one that targets no
   *   directory and detects nothing.
   *
   * @return \DrevOps\Tui\Answers\Answers
   *   The answers.
   *
   * @throws \DrevOps\Tui\Engine\EngineException
   *   When a supplied value is refused. The answers were asked for as a whole,
   *   so one that cannot be taken fails the whole collection.
   */
  public function answers(Panel $panel, array $supplied = [], ?Context $context = NULL): Answers {
    [$fields, $values, $sources, $active] = $this->settle($panel, $supplied, $context ?? new Context());

    $refusal = $this->refusal($fields, $values, $sources, $active);

    if ($refusal !== NULL) {
      throw new EngineException(Translator::t('Invalid value for field "@id": @error', [
        '@id' => $refusal[0],
        '@error' => $refusal[1],
      ]));
    }

    return Answers::forTree($panel, $this->activeAnswers($fields, $values, $active), $this->provenance($fields, $sources, $active));
  }

  /**
   * Resolve every value and where it came from, refusing none of them.
   *
   * The same resolution {@see collect()} runs, stopped where the two paths part
   * company: a screen has somebody in front of it, so a value it cannot take is
   * something to say on the row holding it rather than grounds for failing the
   * call. The values arrive whole - a field a condition hides keeps the value
   * it settled on - so a condition satisfied later surfaces a row that already
   * knows its answer.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to resolve, and any panels beneath it.
   * @param array<string,mixed> $supplied
   *   Values supplied for its fields, keyed by field id.
   * @param \DrevOps\Tui\Handler\Context|null $context
   *   The run this resolution belongs to, or NULL for one that targets no
   *   directory and detects nothing.
   *
   * @return array{array<string,mixed>,array<string,\DrevOps\Tui\Answers\Provenance>,array<string,bool>}
   *   The settled values, how each came to be, and which fields are there.
   */
  public function seed(Panel $panel, array $supplied = [], ?Context $context = NULL): array {
    [$fields, $values, $sources, $active] = $this->settle($panel, $supplied, $context ?? new Context());

    return [$values, $this->provenance($fields, $sources, $active), $active];
  }

  /**
   * Resolve and settle every value, and work out who is there at all.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to collect.
   * @param array<string,mixed> $supplied
   *   Values supplied for its fields, keyed by field id.
   * @param \DrevOps\Tui\Handler\Context $context
   *   The run this collection belongs to.
   *
   * @return array{list<\DrevOps\Tui\Block\Field>,array<string,mixed>,array<string,\DrevOps\Tui\Engine\Source>,array<string,bool>}
   *   The fields in declaration order, the settled values, where each value
   *   came from, and which fields are there.
   */
  protected function settle(Panel $panel, array $supplied, Context $context): array {
    $fields = Tree::fields($panel);

    // With no cursor to open a field, nothing would ever ask a loader for its
    // rows - and a value cannot be measured against rows that never arrive.
    $this->loadEntries($fields);

    [$values, $sources] = $this->resolveAll($fields, $supplied, $context);
    $values = $this->transformSupplied($fields, $values, $sources);
    [$rules, $pinned] = $this->deriveRules($fields, $sources);
    [$active, $values] = $this->stabilize($this->shows($panel, $fields), $fields, $values, $rules, $pinned, $context, $this->suppliedFields($sources));
    $this->loadQueryEntries($fields, $values, $active);

    return [$fields, $values, $sources, $active];
  }

  /**
   * Whether each row the tree holds only shows something, keyed by id.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel being collected.
   * @param list<\DrevOps\Tui\Block\Field> $fields
   *   The fields, in declaration order.
   *
   * @return array<string,bool>
   *   TRUE for a row that carries no value, FALSE for one that does.
   */
  protected function shows(Panel $panel, array $fields): array {
    $shows = array_fill_keys(Tree::ids($panel), TRUE);

    foreach ($fields as $field) {
      if (!$field->type()->isDisplayOnly()) {
        $shows[$field->id()] = FALSE;
      }
    }

    return $shows;
  }

  /**
   * Resolve each field's initial value and where it came from.
   *
   * @param list<\DrevOps\Tui\Block\Field> $fields
   *   The fields, in declaration order.
   * @param array<string,mixed> $supplied
   *   Values supplied for its fields, keyed by field id.
   * @param \DrevOps\Tui\Handler\Context $context
   *   The run this collection belongs to.
   *
   * @return array{array<string,mixed>,array<string,\DrevOps\Tui\Engine\Source>}
   *   The values and their sources, each keyed by field id.
   */
  protected function resolveAll(array $fields, array $supplied, Context $context): array {
    $values = [];
    $sources = [];

    foreach ($fields as $field) {
      // A field that only shows carries no answer, so it never enters the
      // values: it is neither resolved nor allowed to colour what a later
      // field is resolved against.
      if ($field->type()->isDisplayOnly()) {
        continue;
      }

      $resolved = new Context($context->directory, $values, $context->update, $context->version);
      [$value, $source] = $this->resolveInitial($field, $supplied, $resolved);
      $sources[$field->id()] = $source;
      $values[$field->id()] = $value;
    }

    return [$values, $sources];
  }

  /**
   * Resolve one field's initial value and where it came from.
   *
   * @param \DrevOps\Tui\Block\Field $field
   *   The field.
   * @param array<string,mixed> $supplied
   *   Values supplied for its fields, keyed by field id.
   * @param \DrevOps\Tui\Handler\Context $context
   *   The run this collection belongs to.
   *
   * @return array{mixed,\DrevOps\Tui\Engine\Source}
   *   The value and its source.
   */
  protected function resolveInitial(Field $field, array $supplied, Context $context): array {
    if (array_key_exists($field->id(), $supplied)) {
      return [$supplied[$field->id()], Source::Input];
    }

    if ($context->update) {
      $detected = $this->discoverValue($field, $context);

      if ($detected !== NULL && $this->acceptsDetected($field, $detected)) {
        return [$detected, Source::Detected];
      }
    }

    $default = $field->value();

    return [$default instanceof \Closure ? $default($context) : $default, Source::Default];
  }

  /**
   * Detect a value that already exists outside the form.
   *
   * @param \DrevOps\Tui\Block\Field $field
   *   The field.
   * @param \DrevOps\Tui\Handler\Context $context
   *   The run this collection belongs to.
   *
   * @return mixed
   *   The detected value, or NULL when nothing detects one.
   */
  protected function discoverValue(Field $field, Context $context): mixed {
    $discover = $field->discovery();

    if ($discover instanceof DiscoverInterface) {
      return $discover->discover($context->directory);
    }

    if ($discover instanceof \Closure) {
      return $discover($context);
    }

    return NULL;
  }

  /**
   * Whether a detected value is safe to adopt.
   *
   * Detected values come from arbitrary files rather than from the declaration,
   * so one the field would refuse falls back to the default instead of
   * poisoning the answers.
   *
   * @param \DrevOps\Tui\Block\Field $field
   *   The field.
   * @param mixed $value
   *   The detected value.
   *
   * @return bool
   *   TRUE when the field would take it.
   */
  protected function acceptsDetected(Field $field, mixed $value): bool {
    // The field's own validator is deliberately not asked: it speaks for what
    // somebody offers, and nobody offered this.
    return $field->acceptsValue($value)
      && $field->requiredViolation($value) === NULL
      && $field->boundsViolation($value) === NULL
      && $field->pickerViolation($value) === NULL
      && $field->templateError($value) === NULL
      && $field->entryError($value) === NULL;
  }

  /**
   * Normalize the supplied values, so every later stage sees settled ones.
   *
   * Normalization happens before the set settles: conditions, computed values
   * and fix-ups must read the normalized value (a trimmed string, say) rather
   * than the raw one. Only supplied values are normalized - a default and a
   * computed value are the form's own, and a detected one was measured when it
   * was detected.
   *
   * @param list<\DrevOps\Tui\Block\Field> $fields
   *   The fields, in declaration order.
   * @param array<string,mixed> $values
   *   The resolved values keyed by field id.
   * @param array<string,\DrevOps\Tui\Engine\Source> $sources
   *   Where each value came from, keyed by field id.
   *
   * @return array<string,mixed>
   *   The values, with the supplied ones normalized.
   */
  protected function transformSupplied(array $fields, array $values, array $sources): array {
    foreach ($fields as $field) {
      if (($sources[$field->id()] ?? NULL) !== Source::Input) {
        continue;
      }

      $transform = $field->transformer() ?? $this->handlers->transformer($field->id());
      $values[$field->id()] = $transform instanceof \Closure ? $transform($values[$field->id()]) : $values[$field->id()];
    }

    return $values;
  }

  /**
   * The rules computing values, and the fields whose value is pinned.
   *
   * @param list<\DrevOps\Tui\Block\Field> $fields
   *   The fields, in declaration order.
   * @param array<string,\DrevOps\Tui\Engine\Source> $sources
   *   Where each value came from, keyed by field id.
   *
   * @return array{array<string,\DrevOps\Tui\Derive\Derive>,array<string,bool>}
   *   The rules and the pinned map, each keyed by field id.
   */
  protected function deriveRules(array $fields, array $sources): array {
    $rules = $this->ruleMap($fields);
    $pinned = [];

    foreach (array_keys($rules) as $id) {
      $pinned[$id] = in_array($sources[$id], [Source::Input, Source::Detected], TRUE);
    }

    return [$rules, $pinned];
  }

  /**
   * The rules computing a value, keyed by the field each computes.
   *
   * @param list<\DrevOps\Tui\Block\Field> $fields
   *   The fields, in declaration order.
   *
   * @return array<string,\DrevOps\Tui\Derive\Derive>
   *   The rules.
   */
  protected function ruleMap(array $fields): array {
    $rules = [];

    foreach ($fields as $field) {
      // A field that only shows is absent from the values, so a rule on it
      // would compute against something that is not there.
      if ($field->type()->isDisplayOnly()) {
        continue;
      }

      $derive = $field->derivation();

      if ($derive instanceof Derive) {
        $rules[$field->id()] = $derive;
      }
    }

    return $rules;
  }

  /**
   * The fields whose value was supplied, keyed by field id.
   *
   * @param array<string,\DrevOps\Tui\Engine\Source> $sources
   *   Where each value came from, keyed by field id.
   *
   * @return array<string,bool>
   *   TRUE for each field somebody answered.
   */
  protected function suppliedFields(array $sources): array {
    return array_map(static fn(Source $source): bool => $source === Source::Input, $sources);
  }

  /**
   * Settle computed values, who is there at all, and the fix-ups.
   *
   * @param array<string,bool> $shows
   *   Whether each row the tree holds only shows something, keyed by id.
   * @param list<\DrevOps\Tui\Block\Field> $fields
   *   The fields, in declaration order.
   * @param array<string,mixed> $values
   *   The resolved values keyed by field id.
   * @param array<string,\DrevOps\Tui\Derive\Derive> $rules
   *   The rules computing a value, keyed by field id.
   * @param array<string,bool> $pinned
   *   The fields whose value must not be recomputed, keyed by field id.
   * @param \DrevOps\Tui\Handler\Context $context
   *   The run this collection belongs to.
   * @param array<string,bool> $supplied
   *   The fields whose value was supplied, keyed by field id.
   *
   * @return array{array<string,bool>,array<string,mixed>}
   *   Which fields are there, and the settled values.
   */
  protected function stabilize(array $shows, array $fields, array $values, array $rules, array $pinned, Context $context, array $supplied): array {
    $active = [];

    foreach ($fields as $field) {
      $active[$field->id()] = TRUE;
    }

    // A settled state exits below, so the bound only guards a set that never
    // settles: field-count passes cover the longest chain, plus two for the
    // interplay between who is there and what the fix-ups then write.
    $limit = count($fields) + 2;

    for ($pass = 0; $pass <= $limit; $pass++) {
      // Rows resolve first: a set that follows the answers decides what the
      // conditions below then see, and what a value is still allowed to be.
      $values = $this->resolveEntries($fields, $values, $active, $context, $supplied);

      $derived = $this->deriver->derive($rules, $values, $pinned);

      $next_active = [];
      $answers = $this->activeAnswers($fields, $derived, $active);

      foreach ($fields as $field) {
        $next_active[$field->id()] = $field->isActive($answers);
      }

      $next_values = $this->applyFixups($shows, $derived, $this->activeAnswers($fields, $derived, $next_active));

      if ($next_active === $active && $next_values === $values) {
        return [$active, $values];
      }

      $active = $next_active;
      $values = $next_values;
    }

    // @codeCoverageIgnoreStart
    return [$active, $values];
    // @codeCoverageIgnoreEnd
  }

  /**
   * Resolve each field's rows once, replacing the loader that owed them.
   *
   * @param list<\DrevOps\Tui\Block\Field> $fields
   *   The fields, in declaration order.
   */
  protected function loadEntries(array $fields): void {
    foreach ($fields as $field) {
      $loader = $field->loader();

      if ($loader instanceof \Closure) {
        $field->settle($loader());
      }
    }
  }

  /**
   * Resolve every answer-driven row set, and restate the values against it.
   *
   * A resolver reads the answers, so it is asked again whenever they change and
   * skipped when they have not - a settling pass that alters nothing costs
   * nothing. The resolved set then decides the field's value: one that is no
   * longer offered is dropped, a ranking is completed and a toggle falls back,
   * so the answers never name a row that is not on offer. A value somebody
   * supplied is left alone for the refusal to report, rather than disappearing
   * without a word.
   *
   * @param list<\DrevOps\Tui\Block\Field> $fields
   *   The fields, in declaration order.
   * @param array<string,mixed> $values
   *   The current values keyed by field id.
   * @param array<string,bool> $active
   *   Which fields are there, keyed by field id.
   * @param \DrevOps\Tui\Handler\Context $context
   *   The run this collection belongs to.
   * @param array<string,bool> $supplied
   *   The fields whose value was supplied, keyed by field id.
   *
   * @return array<string,mixed>
   *   The values, restated against the resolved rows.
   *
   * @throws \DrevOps\Tui\Engine\EngineException
   *   When a resolver cannot answer.
   */
  protected function resolveEntries(array $fields, array $values, array $active, Context $context, array $supplied): array {
    $answers = $this->activeAnswers($fields, $values, $active);
    $resolved = new Context($context->directory, $answers, $context->update, $context->version);

    // Everything the resolver is handed, so a second run against another
    // directory - or one that detects what is already there - is not answered
    // from the memo of the first.
    $run = [$context->directory, $context->update, $context->version];

    foreach ($fields as $field) {
      $resolver = $field->resolver();

      if (!$resolver instanceof \Closure) {
        continue;
      }

      $memo = $this->memo[$field->id()] ?? NULL;

      if ($memo !== NULL && $memo['answers'] === $answers && $memo['run'] === $run && $memo['rows'] === $field->entries()) {
        continue;
      }

      try {
        $field->settle($resolver($resolved));
      }
      catch (\Throwable $throwable) {
        throw $this->entriesError($field, $throwable);
      }

      $this->memo[$field->id()] = ['answers' => $answers, 'run' => $run, 'rows' => $field->entries()];

      if ($supplied[$field->id()] ?? FALSE) {
        continue;
      }

      $values[$field->id()] = $field->reconcileValue($values[$field->id()] ?? NULL);
    }

    return $values;
  }

  /**
   * Look each supplied value up against the query that would find it.
   *
   * A query source describes a candidate set too large or too remote to hold,
   * so there is nothing to measure a value against until something is queried -
   * and with no screen nothing is typed. The supplied value is therefore the
   * query, and the field is measured against what that query answers, so a
   * value no query can produce is caught rather than passed through unchecked.
   *
   * Only rows that close a set are worth a call: a suggest field's candidates
   * are hints rather than a closed set, so its value is measured against them
   * in neither path. A field with no value is left alone, and the field's
   * minimum query length does not apply, because it throttles typing rather
   * than a single lookup.
   *
   * @param list<\DrevOps\Tui\Block\Field> $fields
   *   The fields, in declaration order.
   * @param array<string,mixed> $values
   *   The settled values keyed by field id.
   * @param array<string,bool> $active
   *   Which fields are there, keyed by field id.
   *
   * @throws \DrevOps\Tui\Engine\EngineException
   *   When a source cannot answer.
   */
  protected function loadQueryEntries(array $fields, array $values, array $active): void {
    foreach ($fields as $field) {
      $source = $field->source();

      if (!$source instanceof \Closure) {
        continue;
      }

      if (!$field->type()->constrainsToOptions()) {
        continue;
      }

      if (!($active[$field->id()] ?? FALSE)) {
        continue;
      }

      $rows = [];

      foreach ($this->queriesFor($field, $values[$field->id()] ?? NULL) as $query) {
        try {
          $resolved = Option::resolved($source($query, $values));
        }
        catch (\Throwable $throwable) {
          // On screen a source that cannot answer degrades to a message in the
          // field, but with no screen there is nobody to retype the query, so
          // the collection fails instead.
          throw $this->entriesError($field, $throwable);
        }

        foreach ($resolved as $row) {
          $rows[$row->value] = $row->label;
        }
      }

      $field->settle($rows);
    }
  }

  /**
   * The queries that look a field's supplied value up, one per item.
   *
   * @param \DrevOps\Tui\Block\Field $field
   *   The field.
   * @param mixed $value
   *   The settled value.
   *
   * @return list<string>
   *   The non-empty queries; empty when there is nothing to look up.
   */
  protected function queriesFor(Field $field, mixed $value): array {
    $items = $field->collectsList() ? $value : [is_scalar($value) ? (string) $value : ''];
    $queries = [];

    foreach (is_array($items) ? $items : [] as $item) {
      if (is_string($item) && $item !== '') {
        $queries[] = $item;
      }
    }

    return array_values(array_unique($queries));
  }

  /**
   * The error for consumer row code that could not answer.
   *
   * @param \DrevOps\Tui\Block\Field $field
   *   The field whose rows were being resolved.
   * @param \Throwable $throwable
   *   What the consumer code threw.
   *
   * @return \DrevOps\Tui\Engine\EngineException
   *   The error naming the field.
   */
  protected function entriesError(Field $field, \Throwable $throwable): EngineException {
    // Not every code is an integer - a database driver's SQLSTATE is a string -
    // and consumer code decides which exception arrives here, so it is coerced
    // rather than allowed to fail the report instead of making it.
    return new EngineException(Translator::t('Could not load options for field "@id": @error', [
      '@id' => $field->id(),
      '@error' => $throwable->getMessage(),
    ]), (int) $throwable->getCode(), $throwable);
  }

  /**
   * Apply the rules that write a value once the answers have settled.
   *
   * @param array<string,bool> $shows
   *   Whether each row the tree holds only shows something, keyed by id.
   * @param array<string,mixed> $values
   *   The current values keyed by field id.
   * @param array<string,mixed> $answers
   *   The answers the guards are measured against.
   *
   * @return array<string,mixed>
   *   The values after the rules.
   */
  protected function applyFixups(array $shows, array $values, array $answers): array {
    foreach ($this->fixups as $fixup) {
      if ($fixup->when instanceof ConditionInterface && !$fixup->when->matches($answers)) {
        continue;
      }

      // A row that only shows carries no value, so a rule can neither write to
      // one nor copy from one - reading a note's absent value would write NULL
      // over the target's settled value. A mistargeted rule is ignored.
      if ($shows[$fixup->set] ?? FALSE) {
        continue;
      }

      if ($shows[$fixup->from] ?? FALSE) {
        continue;
      }

      $values[$fixup->set] = $fixup->from !== '' ? ($values[$fixup->from] ?? NULL) : $fixup->to;
    }

    return $values;
  }

  /**
   * The first supplied value the form refuses, and why.
   *
   * Only supplied values are measured: a default and a computed value are the
   * form's own, and a detected one was measured when it was detected.
   *
   * @param list<\DrevOps\Tui\Block\Field> $fields
   *   The fields, in declaration order.
   * @param array<string,mixed> $values
   *   The settled values keyed by field id.
   * @param array<string,\DrevOps\Tui\Engine\Source> $sources
   *   Where each value came from, keyed by field id.
   * @param array<string,bool> $active
   *   Which fields are there, keyed by field id.
   *
   * @return array{string,string}|null
   *   The field's id and the reason, or NULL when nothing is refused.
   */
  protected function refusal(array $fields, array $values, array $sources, array $active): ?array {
    foreach ($fields as $field) {
      if (!($active[$field->id()] ?? FALSE)) {
        continue;
      }

      if (($sources[$field->id()] ?? NULL) !== Source::Input) {
        continue;
      }

      $reason = $this->rejects($field, $values[$field->id()]);

      if ($reason !== NULL) {
        return [$field->id(), $reason];
      }
    }

    return NULL;
  }

  /**
   * The reason a field refuses a value, or NULL when it takes it.
   *
   * Emptiness is answered first, so a required field says so rather than
   * letting NULL read as a wrong shape; the shape follows, so what is measured
   * afterwards is a value of the kind the field collects.
   *
   * @param \DrevOps\Tui\Block\Field $field
   *   The field.
   * @param mixed $value
   *   The value.
   *
   * @return string|null
   *   The reason, or NULL when nothing refuses it.
   */
  protected function rejects(Field $field, mixed $value): ?string {
    $missing = $field->requiredViolation($value);

    if ($missing !== NULL) {
      return $missing;
    }

    if (!$field->acceptsValue($value)) {
      return Translator::t('must be @constraint.', ['@constraint' => $field->valueKind()]);
    }

    return $field->refuses($value, $this->handlers->validator($field->id()));
  }

  /**
   * The values of the fields that are there, in declaration order.
   *
   * @param list<\DrevOps\Tui\Block\Field> $fields
   *   The fields, in declaration order.
   * @param array<string,mixed> $values
   *   The current values keyed by field id.
   * @param array<string,bool> $active
   *   Which fields are there, keyed by field id.
   *
   * @return array<string,mixed>
   *   The answers.
   */
  protected function activeAnswers(array $fields, array $values, array $active): array {
    $answers = [];

    foreach ($fields as $field) {
      if ($field->type()->isDisplayOnly()) {
        continue;
      }

      if ($active[$field->id()] ?? FALSE) {
        $answers[$field->id()] = $values[$field->id()] ?? NULL;
      }
    }

    return $answers;
  }

  /**
   * How each answer came to be, keyed by field id.
   *
   * @param list<\DrevOps\Tui\Block\Field> $fields
   *   The fields, in declaration order.
   * @param array<string,\DrevOps\Tui\Engine\Source> $sources
   *   Where each value came from, keyed by field id.
   * @param array<string,bool> $active
   *   Which fields are there, keyed by field id.
   *
   * @return array<string,\DrevOps\Tui\Answers\Provenance>
   *   The provenance of each answer.
   */
  protected function provenance(array $fields, array $sources, array $active): array {
    $provenance = [];

    foreach ($fields as $field) {
      if ($field->type()->isDisplayOnly()) {
        continue;
      }

      if (!($active[$field->id()] ?? FALSE)) {
        continue;
      }

      $source = $sources[$field->id()];
      $derived = $field->derivation() instanceof Derive;

      $provenance[$field->id()] = match (TRUE) {
        $source === Source::Detected => Provenance::Detected,
        $derived && $source === Source::Input => Provenance::Override,
        $derived => Provenance::Derived,
        $source === Source::Input => Provenance::Edited,
        default => Provenance::Default,
      };
    }

    return $provenance;
  }

}
