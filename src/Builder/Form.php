<?php

declare(strict_types=1);

namespace DrevOps\Tui\Builder;

use DrevOps\Tui\Block\Buttons;
use DrevOps\Tui\Block\Capability\DependCapableInterface;
use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\FieldType;
use DrevOps\Tui\Block\Option;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Template;
use DrevOps\Tui\Block\Tree;
use DrevOps\Tui\Condition\ConditionInterface;
use DrevOps\Tui\FormException;
use DrevOps\Tui\Screen\Layout\GridLayout;
use DrevOps\Tui\Screen\Layout\PanelLayout;

/**
 * A fluent builder declaring a form: its panels, fields and own chrome.
 *
 * Declares only what belongs to a specific questionnaire - its title, panels
 * and fields, its start banner and submit/cancel buttons, its fix-ups and
 * env-variable prefix. The global TUI runtime (theme, key bindings, colour,
 * language) is configured on the {@see \DrevOps\Tui\Tui} facade, not here.
 *
 * @package DrevOps\Tui\Builder
 */
final class Form {

  /**
   * The region a panel goes in when no grid window is declared for it.
   */
  protected const string CONTENT = 'content';

  /**
   * The start banner (logo).
   */
  protected string $banner = '';

  /**
   * Whether the interactive TUI shows submit and cancel buttons.
   */
  protected bool $buttons = TRUE;

  /**
   * The submit button label.
   */
  protected string $submitLabel = 'Submit';

  /**
   * The cancel button label.
   */
  protected string $cancelLabel = 'Cancel';

  /**
   * The prefix namespacing per-question env-variable overrides.
   */
  protected string $envPrefix = '';

  /**
   * The post-settle fix-up rules.
   *
   * @var \DrevOps\Tui\Builder\Fixup[]
   */
  protected array $fixups = [];

  /**
   * The top-level panel builders, in declaration order.
   *
   * @var \DrevOps\Tui\Builder\PanelBuilder[]
   */
  protected array $panels = [];

  /**
   * The grid arranging the top-level panels, once one is declared.
   */
  protected ?GridLayout $layout = NULL;

  /**
   * The root panel holding every declared panel, once the form is finished.
   */
  protected ?Panel $root = NULL;

  /**
   * Construct a form builder.
   *
   * @param string $title
   *   The application title.
   */
  protected function __construct(protected string $title) {
  }

  /**
   * Create a form builder.
   *
   * @param string $title
   *   The application title.
   *
   * @return self
   *   The builder.
   */
  public static function create(string $title): self {
    return new self($title);
  }

  /**
   * Set the start banner.
   *
   * @param string $banner
   *   The banner (logo).
   *
   * @return $this
   *   The builder.
   */
  public function banner(string $banner): self {
    $this->banner = $banner;

    return $this;
  }

  /**
   * Configure the submit and cancel buttons.
   *
   * @param bool $show
   *   Whether to show the buttons.
   * @param string $submit_label
   *   The submit button label.
   * @param string $cancel_label
   *   The cancel button label.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the form's tree has already been built.
   */
  public function buttons(bool $show, string $submit_label = 'Submit', string $cancel_label = 'Cancel'): self {
    $this->assertUnbuilt('its buttons');

    $this->buttons = $show;
    $this->submitLabel = $submit_label;
    $this->cancelLabel = $cancel_label;

    return $this;
  }

  /**
   * Set the prefix namespacing per-question env-variable overrides.
   *
   * @param string $prefix
   *   The env-variable prefix (e.g. "MYAPP_"); empty for the facade default.
   *
   * @return $this
   *   The builder.
   */
  public function envPrefix(string $prefix): self {
    $this->envPrefix = $prefix;

    return $this;
  }

  /**
   * Add a post-settle fix-up rule.
   *
   * @param \DrevOps\Tui\Builder\Fixup $fixup
   *   The fix-up, evaluated once the answers have settled.
   *
   * @return $this
   *   The builder.
   */
  public function fixup(Fixup $fixup): self {
    $this->fixups[] = $fixup;

    return $this;
  }

  /**
   * Add a top-level panel.
   *
   * @param string $id
   *   The panel id.
   * @param string $title
   *   The panel title.
   * @param \Closure $build
   *   The callback receiving the panel builder.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the form's tree has already been built.
   */
  public function panel(string $id, string $title, \Closure $build): self {
    $this->assertUnbuilt('a panel');

    $panel = new PanelBuilder($id, $title);
    $build($panel);
    $this->panels[] = $panel;

    return $this;
  }

  /**
   * Arrange the top-level panels as a grid of side-by-side columns.
   *
   * Each argument declares one visual row and names how many panels sit side
   * by side in it; the panels fill the rows in declaration order. Mirrors
   * {@see \DrevOps\Tui\Builder\PanelBuilder::layout()}, which arranges a
   * drilled-in panel's own children.
   *
   * @param int ...$rows
   *   The panel count of each visual row, top to bottom.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the form's tree has already been built, or a visual row holds fewer
   *   than one panel.
   */
  public function layout(int ...$rows): self {
    $this->assertUnbuilt('a layout');

    $this->layout = new GridLayout(...$rows);

    return $this;
  }

  /**
   * The block tree this form declares.
   *
   * Every panel is a child of one root, so the whole declaration is reachable
   * from a single block - the panel a screen starts in, and the one headless
   * collection traverses. The tree is built once; later calls return the same
   * instance.
   *
   * @return \DrevOps\Tui\Block\Panel
   *   The root panel, carrying the form's own name.
   *
   * @throws \DrevOps\Tui\FormException
   *   When a declaration contradicts itself.
   */
  public function root(): Panel {
    if ($this->root instanceof Panel) {
      return $this->root;
    }

    $this->layout?->assertDeals(count($this->panels), $this->title);

    // The root is the form itself rather than a declared panel, so its id is
    // the form's title.
    $root = (new Panel($this->title, $this->title))->layout($this->layout ?? new PanelLayout());
    $root->buttons(new Buttons($this->buttons, $this->submitLabel, $this->cancelLabel));

    foreach ($this->panels as $index => $panel) {
      $panel->seal();
      // A grid draws each panel in a window of its own, and a window is a
      // region, so each panel is placed in the window at its index.
      $root->in($this->layout instanceof GridLayout ? $this->layout->windows()[$index] : self::CONTENT)->add($panel->block());
    }

    $this->assertUniqueFieldIds($root);
    $this->assertFieldSurfaces($root);
    $this->assertEntrySources($root);
    $this->assertToggleEntries($root);
    $this->assertReorderEntries($root);
    $this->assertTemplateShapes($root);
    $this->assertModalPanels($root);

    $this->nestConditionals($root);

    return $this->root = $root;
  }

  /**
   * The start banner this form opens on.
   *
   * @return string
   *   The banner, empty when it opens straight onto the first frame.
   */
  public function currentBanner(): string {
    return $this->banner;
  }

  /**
   * The prefix namespacing this form's per-question env-variable overrides.
   *
   * @return string
   *   The prefix, empty when the facade default stands.
   */
  public function currentEnvPrefix(): string {
    return $this->envPrefix;
  }

  /**
   * The rules this form applies once its answers have settled.
   *
   * @return \DrevOps\Tui\Builder\Fixup[]
   *   The rules, in declaration order.
   */
  public function currentFixups(): array {
    return $this->fixups;
  }

  /**
   * Assert that the tree has not been built yet.
   *
   * The tree is built once and reused, so a declaration arriving after the
   * build would never reach it; the form throws instead of dropping it.
   *
   * @param string $declaration
   *   What is being declared, as a fragment naming it.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the tree has already been built.
   */
  protected function assertUnbuilt(string $declaration): void {
    if ($this->root instanceof Panel) {
      throw new FormException(sprintf('Form "%s" declares %s after its tree was built; declare every panel before the form is collected or its tree is read.', $this->title, $declaration));
    }
  }

  /**
   * Assert that every field id is unique across the panel tree.
   *
   * @param \DrevOps\Tui\Block\Panel $root
   *   The root panel holding every declared panel.
   */
  protected function assertUniqueFieldIds(Panel $root): void {
    $seen = [];

    foreach (Tree::ids($root) as $id) {
      if (isset($seen[$id])) {
        throw new FormException(sprintf('Duplicate field id "%s".', $id));
      }

      $seen[$id] = TRUE;
    }
  }

  /**
   * Set the nesting depth of every conditionally visible block.
   *
   * A rule may name a field on any panel, and a section's rule covers what it
   * holds, so a block's depth is a fact about the whole form: it can only be
   * computed once every panel has been placed.
   *
   * @param \DrevOps\Tui\Block\Panel $root
   *   The root panel holding every declared panel.
   */
  protected function nestConditionals(Panel $root): void {
    $holders = [];
    $blocks = $this->conditionals($root, $holders);
    $by_id = [];

    foreach (Tree::fields($root) as $field) {
      $by_id[$field->id()] = $field;
    }

    $resolved = [];

    foreach ($blocks as $block) {
      $block->nest($this->nesting($block, $holders, $by_id, $resolved, []));
    }
  }

  /**
   * Every block a condition can hide, and the panel holding each.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to walk.
   * @param array<int,\DrevOps\Tui\Block\Panel> $holders
   *   The section each block sits in, keyed by the block's object id,
   *   populated as the walk proceeds.
   *
   * @return list<\DrevOps\Tui\Block\Capability\DependCapableInterface>
   *   The blocks, in declaration order.
   */
  protected function conditionals(Panel $panel, array &$holders): array {
    $blocks = [];

    foreach ($panel->blocks() as $block) {
      if (!$block instanceof DependCapableInterface) {
        continue;
      }

      $holders[spl_object_id($block)] = $panel;
      $blocks[] = $block;

      if ($block instanceof Panel) {
        $blocks = [...$blocks, ...$this->conditionals($block, $holders)];
      }
    }

    return $blocks;
  }

  /**
   * The nesting depth of one block: one step per rule in the chain to it.
   *
   * The chain runs through the sections as well as through the rules: a
   * section gated by a rule adds one step counted by everything it holds, and
   * a block with a rule of its own adds another.
   *
   * Measured by object rather than by id, because a section and a row it
   * holds may share the same id.
   *
   * @param \DrevOps\Tui\Block\Capability\DependCapableInterface $block
   *   The block to measure.
   * @param array<int,\DrevOps\Tui\Block\Panel> $holders
   *   The section each block sits in, keyed by the block's object id.
   * @param array<string,\DrevOps\Tui\Block\Field> $by_id
   *   Every field in the form, keyed by id.
   * @param array<int,int> $resolved
   *   The depths measured so far, so a block named by several rules is walked
   *   once.
   * @param array<int,bool> $walking
   *   The blocks on the current walk, keyed by object id.
   *
   * @return int
   *   The depth.
   */
  protected function nesting(DependCapableInterface $block, array $holders, array $by_id, array &$resolved, array $walking): int {
    $at = spl_object_id($block);

    if (array_key_exists($at, $resolved)) {
      return $resolved[$at];
    }

    // A reference back to a block already on the walk closes a cycle. A
    // cyclic rule has no stable depth, so the back edge contributes nothing
    // and the recursion stops.
    if (isset($walking[$at])) {
      return 0;
    }

    $walking[$at] = TRUE;
    $holder = $holders[$at] ?? NULL;
    $base = $holder instanceof Panel ? $this->nesting($holder, $holders, $by_id, $resolved, $walking) : 0;

    // A closure rule names no field, so nothing is known about what it
    // depends on and the block takes its section's depth.
    if (!$block->condition() instanceof ConditionInterface) {
      return $resolved[$at] = $base;
    }

    $deepest = $base;

    foreach ($block->condition()->fields() as $id) {
      if (isset($by_id[$id])) {
        $deepest = max($deepest, $this->nesting($by_id[$id], $holders, $by_id, $resolved, $walking));
      }
    }

    return $resolved[$at] = $deepest + 1;
  }

  /**
   * Assert that nothing is declared on a field that never draws it.
   *
   * @param \DrevOps\Tui\Block\Panel $root
   *   The root panel holding every declared panel.
   */
  protected function assertFieldSurfaces(Panel $root): void {
    foreach (Tree::fields($root) as $field) {
      if ($field->placeholderText() !== '' && !$field->type()->supportsPlaceholder()) {
        throw new FormException(sprintf('Field "%s" of type "%s" shows no placeholder; only text, number, textarea, password, suggest and search fields have an input buffer to ghost.', $field->id(), $field->type()->value));
      }

      if ($field->ratingCaptions() !== [] && $field->type() !== FieldType::Rating) {
        throw new FormException(sprintf('Field "%s" of type "%s" draws no scale to caption; captions apply to rating fields.', $field->id(), $field->type()->value));
      }
    }
  }

  /**
   * Assert that every field is offered exactly one set of rows it can hold.
   *
   * A field's rows come from one of four sources - declared entries, a
   * loader, a resolver or a query source - and each replaces the others. A
   * field offered two of them has no one set, and a field offered any of them
   * on a kind that shows no list has nowhere to put them.
   *
   * @param \DrevOps\Tui\Block\Panel $root
   *   The root panel holding every declared panel.
   */
  protected function assertEntrySources(Panel $root): void {
    foreach (Tree::fields($root) as $field) {
      $offered = [
        $field->entries() !== [],
        $field->loader() instanceof \Closure,
        $field->resolver() instanceof \Closure,
        $field->source() instanceof \Closure,
      ];

      if ($field->source() instanceof \Closure && !$field->type()->supportsQuerySource()) {
        throw new FormException(sprintf('Field "%s" of type "%s" cannot source its options from a query; only search and suggest fields show one.', $field->id(), $field->type()->value));
      }

      if (in_array(TRUE, $offered, TRUE) && !$field->type()->supportsOptions()) {
        throw new FormException(sprintf('Field "%s" of type "%s" shows no options; only select, search, suggest, toggle and reorder fields have a list.', $field->id(), $field->type()->value));
      }

      if (count(array_filter($offered)) > 1) {
        throw new FormException(sprintf('Field "%s" is offered more than one set of options; each replaces the others, so declare only one.', $field->id()));
      }

      if ($field->queryMinLength() > 0 && !$field->source() instanceof \Closure) {
        throw new FormException(sprintf('Field "%s" declares a minimum query length but no query source to apply it to.', $field->id()));
      }
    }
  }

  /**
   * Assert that every toggle field declares exactly two entries.
   *
   * @param \DrevOps\Tui\Block\Panel $root
   *   The root panel holding every declared panel.
   */
  protected function assertToggleEntries(Panel $root): void {
    foreach (Tree::fields($root) as $field) {
      if ($field->type() !== FieldType::Toggle) {
        continue;
      }

      if (!$this->settled($field)) {
        continue;
      }

      $entries = $field->entries();

      if (count($entries) !== 2) {
        throw new FormException(sprintf('Toggle field "%s" must have exactly two options, %d given.', $field->id(), count($entries)));
      }

      $default = $field->value();

      // A dynamic default is a closure resolved at runtime; every literal
      // default - whatever its type - must be one of the two option values,
      // otherwise the field would silently coerce it and select the first.
      if ($default instanceof \Closure) {
        continue;
      }

      $values = array_map(static fn(Option $entry): string => $entry->value, $entries);

      if (!is_string($default) || !in_array($default, $values, TRUE)) {
        throw new FormException(sprintf('Toggle field "%s" default must be one of: %s.', $field->id(), implode(', ', $values)));
      }
    }
  }

  /**
   * Assert that every reorder field declares at least two plain entries.
   *
   * A ranking arranges a flat list, so headings, separators and disabled rows
   * are not allowed in it, and fewer than two items leaves nothing to reorder.
   *
   * @param \DrevOps\Tui\Block\Panel $root
   *   The root panel holding every declared panel.
   */
  protected function assertReorderEntries(Panel $root): void {
    foreach (Tree::fields($root) as $field) {
      if ($field->type() !== FieldType::Reorder) {
        continue;
      }

      if (!$this->settled($field)) {
        continue;
      }

      $entries = $field->entries();

      foreach ($entries as $entry) {
        if (!$entry->isSelectable()) {
          throw new FormException(sprintf('Reorder field "%s" allows only plain options - no headings, separators or disabled rows.', $field->id()));
        }
      }

      if (count($entries) < 2) {
        throw new FormException(sprintf('Reorder field "%s" must have at least two options, %d given.', $field->id(), count($entries)));
      }
    }
  }

  /**
   * Assert that every template field declares the shape it fills in.
   *
   * @param \DrevOps\Tui\Block\Panel $root
   *   The root panel holding every declared panel.
   */
  protected function assertTemplateShapes(Panel $root): void {
    foreach (Tree::fields($root) as $field) {
      if ($field->type() !== FieldType::Template) {
        continue;
      }

      if (!$field->template() instanceof Template) {
        throw new FormException(sprintf('Field "%s" is a template field but declares no pattern; add ->pattern() with the shape to fill in.', $field->id()));
      }
    }
  }

  /**
   * Assert that no modal panel declares sub-panels.
   *
   * A modal is a single centered dialog, not a navigable tree - it may hold
   * fields and a description, but nesting panels (or another modal) inside it
   * has no defined layout.
   *
   * @param \DrevOps\Tui\Block\Panel $root
   *   The root panel holding every declared panel.
   */
  protected function assertModalPanels(Panel $root): void {
    foreach (Tree::panels($root) as $panel) {
      if ($panel->isModal() && $panel->children() !== []) {
        throw new FormException(sprintf('Modal panel "%s" cannot contain sub-panels.', $panel->id()));
      }
    }
  }

  /**
   * Whether a field's entries stand as declared.
   *
   * @param \DrevOps\Tui\Block\Field $field
   *   The field.
   *
   * @return bool
   *   FALSE while a loader, a resolver or a query source has not yet supplied
   *   the rows, so there is nothing yet to count or to check a default
   *   against.
   */
  protected function settled(Field $field): bool {
    return !$field->loader() instanceof \Closure && !$field->resolver() instanceof \Closure && !$field->source() instanceof \Closure;
  }

}
