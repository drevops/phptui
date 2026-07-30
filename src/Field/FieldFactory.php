<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field;

use DrevOps\Tui\Handler\HandlerRegistry;
use DrevOps\Tui\Input\KeyMap;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\NumberBounds;
use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Model\Template as TemplateModel;
use DrevOps\Tui\Translation\Translator;
use DrevOps\Tui\Field\Capability\PlaceholderCapableInterface;
use DrevOps\Tui\Field\Capability\QueryOptionsCapableInterface;

/**
 * Builds the field for a field, seeded with the field's current value.
 *
 * Each field is wired with the field's validator and transformer - the
 * declared closure, else the handler registry's reusable static one - so an
 * interactive edit enforces the same behaviour a headless collection does.
 *
 * @package DrevOps\Tui\Field
 */
class FieldFactory {

  /**
   * The resolved key bindings to inject into each field.
   */
  protected KeyMap $keymap;

  /**
   * Construct a field factory.
   *
   * @param \DrevOps\Tui\Input\KeyMap|null $keymap
   *   The resolved key bindings; NULL uses the default preset.
   * @param bool $externalEditorAvailable
   *   Whether an external editor is launchable here. A textarea field opts in
   *   per-field; the handoff shows only when one is also available.
   * @param \DrevOps\Tui\Handler\HandlerRegistry|null $handlers
   *   The registry resolving a field id to its reusable static
   *   validate()/transform() behaviour; NULL leaves only the declared closures.
   */
  public function __construct(?KeyMap $keymap = NULL, protected bool $externalEditorAvailable = FALSE, protected ?HandlerRegistry $handlers = NULL) {
    $this->keymap = $keymap ?? KeyMapManager::create();
  }

  /**
   * Build the field a declaration asks for, wired with its key bindings.
   *
   * The declaration is named apart from what is built from it: this is the one
   * place both are in hand at once, and they are not interchangeable - one
   * states what was asked for, the other collects the answer.
   *
   * @param \DrevOps\Tui\Model\Field $definition
   *   The field declaration.
   * @param mixed $current
   *   The current value to seed the field with.
   * @param array<string,mixed> $answers
   *   The answers collected so far, passed to a text completion closure.
   *
   * @return \DrevOps\Tui\Field\FieldInterface
   *   The field.
   */
  public function create(Field $definition, mixed $current, array $answers = []): FieldInterface {
    $field = match ($definition->type) {
      FieldType::Confirm => new Confirm((bool) $current),
      FieldType::Toggle => new Toggle($this->labels($definition), $this->text($current)),
      FieldType::Select => new Select($this->options($definition), $this->seed($definition, $current), $definition->multiple, $definition->pageSize, $definition->selectionBounds),
      FieldType::Reorder => new Reorder($this->options($definition), Field::stringList($current), $definition->pageSize),
      FieldType::Suggest => new Suggest($definition->selectableValues(), $this->text($current), $definition->pageSize, $this->suggestDescriptions($definition), $definition->ghost),
      FieldType::Search => new Search($this->options($definition), $this->seed($definition, $current), $definition->multiple, $definition->pageSize, $definition->selectionBounds),
      FieldType::FilePicker => new FilePicker($definition->pickerStart, $this->seed($definition, $current), $definition->pickerConstraints, $definition->pickerShowHidden, $definition->multiple, $definition->pageSize, $definition->selectionBounds),
      FieldType::Number => new Number($this->number($current), $definition->bounds),
      FieldType::Rating => $this->rating($definition, $current),
      FieldType::Calendar => new Calendar($this->text($current), $definition->dateBounds),
      FieldType::Textarea => new Textarea($this->text($current), $definition->externalEditor && $this->externalEditorAvailable),
      FieldType::Password => new Password($this->text($current), $definition->revealable, $definition->confirm),
      FieldType::Pause => new Pause(),
      FieldType::Text => new Text($this->text($current), $this->completionsFor($definition, $answers)),
      FieldType::Template => new Template($this->template($definition), $this->text($current)),
      // A note is presentational: the theme renders it and the cursor skips it,
      // so it is never edited and needs no field.
      FieldType::Note => throw new \LogicException('Note fields are presentational and have no editor field.'),
      // A progress row runs its work on activation and draws itself in its
      // panel row; it has no editor to open.
      FieldType::Progress => throw new \LogicException('A progress row is not edited.'),
    };

    if ($field instanceof QueryOptionsCapableInterface && $definition->optionsSource instanceof \Closure) {
      $field->driveByQuery($definition->queryMinLength);
    }

    // The declaration always wins over the registry's convention-resolved
    // behaviour, mirroring the engine's headless resolution.
    $field->setHandlers($this->guarded($definition, $definition->validate ?? $this->handlers?->validator($definition->id)), $definition->transform ?? $this->handlers?->transformer($definition->id));

    if ($field instanceof PlaceholderCapableInterface) {
      $field->setPlaceholder($definition->placeholder);
    }

    return $field->setKeys($this->keymap->forField($definition->type, $definition->multiple));
  }

  /**
   * A validator that rejects an empty value before the field's own runs.
   *
   * Composed here rather than inside the fields so every type enforces
   * emptiness identically, in the same order the engine validates a supplied
   * input.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   * @param \Closure|null $validator
   *   The field's resolved validator, or NULL when it declares none.
   *
   * @return \Closure|null
   *   The guarded validator, the resolved one unchanged for an optional field,
   *   or NULL when there is nothing to enforce.
   */
  protected function guarded(Field $field, ?\Closure $validator): ?\Closure {
    if (!$field->required) {
      return $validator;
    }

    return static fn(mixed $value): ?string => $field->requiredViolation($value) ?? ($validator instanceof \Closure ? $validator($value) : NULL);
  }

  /**
   * Coerce a current value to the string a text-seeded field starts from.
   *
   * @param mixed $current
   *   The current value.
   *
   * @return string
   *   The string value; empty when the value is not a string.
   */
  protected function text(mixed $current): string {
    return is_string($current) ? $current : '';
  }

  /**
   * Coerce a current value to the digit string the integer field starts from.
   *
   * @param mixed $current
   *   The current value.
   *
   * @return string
   *   The value as integer digits; empty when the value is not numeric.
   */
  protected function number(mixed $current): string {
    return is_int($current) || is_float($current) ? (string) (int) $current : '';
  }

  /**
   * Build a rating field over the field's scale, seeded with its value.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   * @param mixed $current
   *   The current value to seed the field with.
   *
   * @return \DrevOps\Tui\Field\Rating
   *   The field.
   */
  protected function rating(Field $field, mixed $current): Rating {
    $scale = $field->bounds;

    if (!$scale instanceof NumberBounds || $scale->min === NULL || $scale->max === NULL) {
      // @codeCoverageIgnoreStart
      throw new \LogicException(sprintf('Field "%s" is a rating field carrying no closed scale.', $field->id));
      // @codeCoverageIgnoreEnd
    }

    return new Rating(is_int($current) || is_float($current) ? (int) $current : $scale->min, $scale->min, $scale->max, $this->captions($field));
  }

  /**
   * A rating's captions, localized to the active language.
   *
   * Translated once here rather than at each draw, the way the option labels
   * are, so the caption a field shows is the caption the panel row shows.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   *
   * @return array<int,string>
   *   The localized caption of each point, keyed by the point.
   */
  protected function captions(Field $field): array {
    return array_map(static fn(string $caption): string => $caption === '' ? '' : Translator::t($caption), $field->ratingCaptions);
  }

  /**
   * The field seed value for a field: a scalar, or a list when multiple.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   * @param mixed $current
   *   The current value to seed the field with.
   *
   * @return string|list<string>
   *   The string current value for a single field, or the list of string
   *   values for a multiple one.
   */
  protected function seed(Field $field, mixed $current): string|array {
    return $field->multiple ? Field::stringList($current) : $this->text($current);
  }

  /**
   * Resolve a text field's completion source to a concrete candidate list.
   *
   * A closure source is called with the answers collected so far; the result is
   * coerced to a list of strings, so a mistyped source degrades to no
   * completion rather than erroring.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   * @param array<string,mixed> $answers
   *   The answers collected so far.
   *
   * @return list<string>
   *   The candidate strings; empty when the field declares no completion.
   */
  protected function completionsFor(Field $field, array $answers): array {
    $source = $field->completion instanceof \Closure ? ($field->completion)($answers) : $field->completion;

    return Field::stringList($source);
  }

  /**
   * The shape a template field fills in.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   *
   * @return \DrevOps\Tui\Model\Template
   *   The template.
   */
  protected function template(Field $field): TemplateModel {
    if (!$field->template instanceof TemplateModel) {
      // @codeCoverageIgnoreStart
      throw new \LogicException(sprintf('Field "%s" is a template field carrying no template.', $field->id));
      // @codeCoverageIgnoreEnd
    }

    return $field->template;
  }

  /**
   * The selectable value => label map for a field's options.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   *
   * @return array<string,string>
   *   The localized labels keyed by value, for fields that take a flat option
   *   map.
   */
  protected function labels(Field $field): array {
    $out = [];

    foreach ($this->options($field) as $option) {
      if ($option->selectable()) {
        $out[$option->value] = $option->label;
      }
    }

    return $out;
  }

  /**
   * A field's options with their labels and disabled reasons translated.
   *
   * Translating once here, rather than at each field draw, keeps the list a
   * field searches identical to the list it shows, so a match runs against the
   * same text the user reads.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   *
   * @return list<\DrevOps\Tui\Model\Option>
   *   The options in display order, localized to the active language.
   */
  protected function options(Field $field): array {
    return array_map(static fn(Option $option): Option => new Option(
      $option->value,
      Translator::t($option->label),
      $option->description !== '' ? Translator::t($option->description) : '',
      $option->kind,
      $option->disabled,
      $option->disabledReason !== '' ? Translator::t($option->disabledReason) : '',
    ), $field->options);
  }

  /**
   * The description shown for each selectable option value, keyed by value.
   *
   * For the value-based suggest field, which carries no option rows: the
   * localized per-option description keyed by its value.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   *
   * @return array<string,string>
   *   The description for each selectable option value.
   */
  protected function suggestDescriptions(Field $field): array {
    $out = [];

    foreach ($this->options($field) as $option) {
      if (!$option->selectable()) {
        continue;
      }

      $out[$option->value] = $option->description;
    }

    return $out;
  }

}
