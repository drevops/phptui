<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Builder;

use DrevOps\PhpTui\Block\BlockInterface;
use DrevOps\PhpTui\Block\Buttons;
use DrevOps\PhpTui\Block\FieldType;
use DrevOps\PhpTui\Block\Markup;
use DrevOps\PhpTui\Block\Panel;
use DrevOps\PhpTui\Block\Progress;
use DrevOps\PhpTui\Condition\ConditionInterface;
use DrevOps\PhpTui\FormException;
use DrevOps\PhpTui\Screen\Layout\GridLayout;
use DrevOps\PhpTui\Screen\Layout\LayoutInterface;
use DrevOps\PhpTui\Screen\Layout\LayoutManager;
use DrevOps\PhpTui\Screen\Layout\PanelLayout;

/**
 * A fluent builder for a panel: what it holds, and how it is arranged.
 *
 * Every level of the hierarchy has a default, so a three-field panel names no
 * layout and no region: the fields go into the default region in the order
 * they are written. A panel names a layout only when it needs one, and a
 * block then names the region it goes in rather than depending on the order
 * it was declared in.
 *
 * A block is declared by the label it draws, and the id it answers to is
 * derived from that label - `text('Order name')` collects into `order_name`.
 * An id that has to be a particular string is declared after the label.
 * {@see \DrevOps\PhpTui\Builder\Name} holds the rule.
 *
 * @package DrevOps\PhpTui\Builder
 */
final class PanelBuilder {

  /**
   * The panel being declared.
   */
  protected Panel $panel;

  /**
   * The field builders, in declaration order.
   *
   * @var \DrevOps\PhpTui\Builder\FieldBuilder[]
   */
  protected array $fields = [];

  /**
   * The nested panel builders, in declaration order.
   *
   * @var \DrevOps\PhpTui\Builder\PanelBuilder[]
   */
  protected array $panels = [];

  /**
   * The region blocks are added to until another is named.
   */
  protected string $region;

  /**
   * Whether anything has been placed in the panel's layout yet.
   */
  protected bool $placed = FALSE;

  /**
   * Construct a panel builder.
   *
   * @param string $id
   *   The unique panel id.
   * @param string $title
   *   The panel title.
   */
  public function __construct(protected string $id, protected string $title) {
    $this->panel = (new Panel($id, $title))->layout(new PanelLayout());
    $this->region = $this->firstRegion();
  }

  /**
   * The panel this builder is declaring.
   *
   * @return \DrevOps\PhpTui\Block\Panel
   *   The panel, whose identity never changes, so it can be placed in a region
   *   as it is declared.
   */
  public function block(): Panel {
    return $this->panel;
  }

  /**
   * Finish the declaration, so everything it holds is finished too.
   *
   * @throws \DrevOps\PhpTui\FormException
   *   When the declared grid does not match the panels it arranges.
   */
  public function seal(): void {
    $layout = $this->panel->currentLayout();

    if ($layout instanceof GridLayout) {
      $layout->assertDeals(count($this->panels), $this->id);
    }

    foreach ($this->fields as $field) {
      $field->seal();
    }

    foreach ($this->panels as $panel) {
      $panel->seal();
    }
  }

  /**
   * Set the panel description.
   *
   * @param string $description
   *   The description.
   *
   * @return $this
   *   The builder.
   */
  public function description(string $description): self {
    $this->panel->description($description);

    return $this;
  }

  /**
   * Set the conditional-visibility rule.
   *
   * A section is shown and hidden exactly as a field is, and the rule covers
   * everything it holds: while the condition does not hold, its questions are
   * not asked, not drawn and not in the answers.
   *
   * @param \DrevOps\PhpTui\Condition\ConditionInterface $condition
   *   The condition gating the panel, evaluated as the answers settle.
   *
   * @return $this
   *   The builder.
   */
  public function when(ConditionInterface $condition): self {
    $this->panel->when($condition);

    return $this;
  }

  /**
   * Present this panel as a centered modal dialog over its parent.
   *
   * The panel's fields (and its description text) render in a bordered box
   * floating over the dimmed parent; the dialog is dismissed through its own
   * submit/cancel buttons, whose labels are configurable here.
   *
   * @param string $submit_label
   *   The submit (accept) button label.
   * @param string $cancel_label
   *   The cancel (dismiss) button label.
   *
   * @return $this
   *   The builder.
   */
  public function modal(string $submit_label = 'Submit', string $cancel_label = 'Cancel'): self {
    $this->panel->buttons(new Buttons(TRUE, $submit_label, $cancel_label))->modal();

    return $this;
  }

  /**
   * Add blocks to a named region from here on.
   *
   * @param string $name
   *   The region name.
   *
   * @return $this
   *   The builder.
   */
  public function in(string $name): self {
    // Called now rather than when a block is added, so an undeclared name
    // fails at the line that declared it.
    $this->panel->in($name);
    $this->region = $name;

    return $this;
  }

  /**
   * Add a text field.
   *
   * @param string $label
   *   The label the field draws.
   * @param string $id
   *   The id it answers to; derived from the label when not declared.
   *
   * @return \DrevOps\PhpTui\Builder\FieldBuilder
   *   The field builder.
   */
  public function text(string $label, string $id = ''): FieldBuilder {
    return $this->field($label, $id, FieldType::Text);
  }

  /**
   * Add a template field: fill named slots in a fixed shape.
   *
   * Chain `->pattern()` with the shape to fill in - its fixed text renders as
   * context and each `{{name}}` slot is filled separately - and
   * `->slot()` to label or validate one slot. The answer is the
   * assembled string; its parts are read back with
   * {@see \DrevOps\PhpTui\Answers\Answers::parts()}.
   *
   * @param string $label
   *   The label the field draws.
   * @param string $id
   *   The id it answers to; derived from the label when not declared.
   *
   * @return \DrevOps\PhpTui\Builder\FieldBuilder
   *   The field builder.
   */
  public function template(string $label, string $id = ''): FieldBuilder {
    return $this->field($label, $id, FieldType::Template);
  }

  /**
   * Add a select field. Call ->multiple() to collect several values as a list.
   *
   * @param string $label
   *   The label the field draws.
   * @param string $id
   *   The id it answers to; derived from the label when not declared.
   *
   * @return \DrevOps\PhpTui\Builder\FieldBuilder
   *   The field builder.
   */
  public function select(string $label, string $id = ''): FieldBuilder {
    return $this->field($label, $id, FieldType::Select);
  }

  /**
   * Add a reorder field (rank a list by moving the highlighted item).
   *
   * @param string $label
   *   The label the field draws.
   * @param string $id
   *   The id it answers to; derived from the label when not declared.
   *
   * @return \DrevOps\PhpTui\Builder\FieldBuilder
   *   The field builder.
   */
  public function reorder(string $label, string $id = ''): FieldBuilder {
    return $this->field($label, $id, FieldType::Reorder);
  }

  /**
   * Add a confirm field.
   *
   * @param string $label
   *   The label the field draws.
   * @param string $id
   *   The id it answers to; derived from the label when not declared.
   *
   * @return \DrevOps\PhpTui\Builder\FieldBuilder
   *   The field builder.
   */
  public function confirm(string $label, string $id = ''): FieldBuilder {
    return $this->field($label, $id, FieldType::Confirm);
  }

  /**
   * Add a toggle field (an inline switch between two labeled values).
   *
   * @param string $label
   *   The label the field draws.
   * @param string $id
   *   The id it answers to; derived from the label when not declared.
   *
   * @return \DrevOps\PhpTui\Builder\FieldBuilder
   *   The field builder.
   */
  public function toggle(string $label, string $id = ''): FieldBuilder {
    return $this->field($label, $id, FieldType::Toggle);
  }

  /**
   * Add a suggest field.
   *
   * @param string $label
   *   The label the field draws.
   * @param string $id
   *   The id it answers to; derived from the label when not declared.
   *
   * @return \DrevOps\PhpTui\Builder\FieldBuilder
   *   The field builder.
   */
  public function suggest(string $label, string $id = ''): FieldBuilder {
    return $this->field($label, $id, FieldType::Suggest);
  }

  /**
   * Add a number field.
   *
   * @param string $label
   *   The label the field draws.
   * @param string $id
   *   The id it answers to; derived from the label when not declared.
   *
   * @return \DrevOps\PhpTui\Builder\FieldBuilder
   *   The field builder.
   */
  public function number(string $label, string $id = ''): FieldBuilder {
    return $this->field($label, $id, FieldType::Number);
  }

  /**
   * Add a rating field: a graded answer picked from a scale of points.
   *
   * The scale runs from `->min()` to `->max()` (one to five by default) and the
   * answer is the chosen point as an integer. Chain `->captions()` to name what
   * the points mean.
   *
   * @param string $label
   *   The label the field draws.
   * @param string $id
   *   The id it answers to; derived from the label when not declared.
   *
   * @return \DrevOps\PhpTui\Builder\FieldBuilder
   *   The field builder.
   */
  public function rating(string $label, string $id = ''): FieldBuilder {
    return $this->field($label, $id, FieldType::Rating);
  }

  /**
   * Add a calendar field: a navigable month picker returning an ISO date.
   *
   * @param string $label
   *   The label the field draws.
   * @param string $id
   *   The id it answers to; derived from the label when not declared.
   *
   * @return \DrevOps\PhpTui\Builder\FieldBuilder
   *   The field builder.
   */
  public function calendar(string $label, string $id = ''): FieldBuilder {
    return $this->field($label, $id, FieldType::Calendar);
  }

  /**
   * Add a textarea field.
   *
   * @param string $label
   *   The label the field draws.
   * @param string $id
   *   The id it answers to; derived from the label when not declared.
   *
   * @return \DrevOps\PhpTui\Builder\FieldBuilder
   *   The field builder.
   */
  public function textarea(string $label, string $id = ''): FieldBuilder {
    return $this->field($label, $id, FieldType::Textarea);
  }

  /**
   * Add a password field.
   *
   * @param string $label
   *   The label the field draws.
   * @param string $id
   *   The id it answers to; derived from the label when not declared.
   *
   * @return \DrevOps\PhpTui\Builder\FieldBuilder
   *   The field builder.
   */
  public function password(string $label, string $id = ''): FieldBuilder {
    return $this->field($label, $id, FieldType::Password);
  }

  /**
   * Add a search field: a fuzzy type-to-filter choice list.
   *
   * Call ->multiple() to collect several values.
   *
   * @param string $label
   *   The label the field draws.
   * @param string $id
   *   The id it answers to; derived from the label when not declared.
   *
   * @return \DrevOps\PhpTui\Builder\FieldBuilder
   *   The field builder.
   */
  public function search(string $label, string $id = ''): FieldBuilder {
    return $this->field($label, $id, FieldType::Search);
  }

  /**
   * Add a file picker field (browse the filesystem for a path).
   *
   * Call ->multiple() to collect several paths.
   *
   * @param string $label
   *   The label the field draws.
   * @param string $id
   *   The id it answers to; derived from the label when not declared.
   *
   * @return \DrevOps\PhpTui\Builder\FieldBuilder
   *   The field builder.
   */
  public function filePicker(string $label, string $id = ''): FieldBuilder {
    return $this->field($label, $id, FieldType::FilePicker);
  }

  /**
   * Add a pause field (an acknowledgement gate).
   *
   * @param string $label
   *   The label the field draws.
   * @param string $id
   *   The id it answers to; derived from the label when not declared.
   *
   * @return \DrevOps\PhpTui\Builder\FieldBuilder
   *   The field builder.
   */
  public function pause(string $label, string $id = ''): FieldBuilder {
    return $this->field($label, $id, FieldType::Pause);
  }

  /**
   * Add a titled note: markup written title first.
   *
   * Sugar over {@see markup()} for the common shape of a card: the title
   * comes first and the body follows through `->body()`. It builds the same
   * block, so every markup call is available on it.
   *
   * @param string $title
   *   The title the card draws.
   * @param string $id
   *   The id it answers to; derived from the title when not declared.
   *
   * @return \DrevOps\PhpTui\Block\Markup
   *   The markup block.
   */
  public function note(string $title, string $id = ''): Markup {
    return $this->markup('', $title, $id);
  }

  /**
   * Add markup: formatted content only.
   *
   * Chain `->border()` to draw it inside a card and `->table()` to lay it out
   * as a grid; both are presentation choices over the same block. `->when()`
   * gates it on an earlier answer, so a warning can appear only when an
   * answer calls for it.
   *
   * @param string $body
   *   The content; newlines separate lines.
   * @param string $title
   *   An optional title above the body.
   * @param string $id
   *   The id it answers to; derived from the title, or from the body when it
   *   carries none.
   *
   * @return \DrevOps\PhpTui\Block\Markup
   *   The markup block.
   */
  public function markup(string $body, string $title = '', string $id = ''): Markup {
    $markup = new Markup(Name::id($title === '' ? $body : $title, $id), $body, $title);
    $this->add($markup);

    return $markup;
  }

  /**
   * Add a progress row that runs work when activated, showing an indicator.
   *
   * Chain `->steps(int)` for a determinate bar (omit it for a spinner) and
   * `->work(\Closure)` for the work; the callback drives the indicator through
   * the {@see \DrevOps\PhpTui\Primitive\ProgressReporter} it receives.
   *
   * @param string $caption
   *   The caption shown beside the indicator.
   * @param string $id
   *   The id it answers to; derived from the caption when not declared.
   *
   * @return \DrevOps\PhpTui\Block\Progress
   *   The progress block.
   */
  public function progress(string $caption, string $id = ''): Progress {
    $progress = new Progress(Name::id($caption, $id), $caption);
    $this->add($progress);

    return $progress;
  }

  /**
   * Add a nested sub-panel.
   *
   * @param string $title
   *   The sub-panel title.
   * @param string|\Closure $id
   *   The id it answers to, or the callback when the id is derived from the
   *   title.
   * @param \Closure|null $build
   *   The callback receiving the sub-panel builder.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\PhpTui\FormException
   *   When no callback is given to build the sub-panel with.
   */
  public function panel(string $title, string|\Closure $id, ?\Closure $build = NULL): self {
    [$id, $build] = Name::panel($title, $id, $build);

    $panel = new self($id, $title);
    $build($panel);
    $this->panels[] = $panel;
    $this->descend($panel->block());

    return $this;
  }

  /**
   * Arrange this panel: by a named layout, or its sub-panels as a grid.
   *
   * A name picks the arrangement of the panel's own blocks - a shipped layout,
   * or any one a consumer registered - and each of its regions then takes the
   * blocks that name it through
   * {@see \DrevOps\PhpTui\Builder\PanelBuilder::in()}.
   *
   * Counts arrange the sub-panels instead: each argument declares one visual
   * row and names how many sub-panels sit side by side in it, filled in
   * declaration order. `layout(2)` puts two panels beside each other,
   * `layout(2, 2)` makes four windows, `layout(1, 2)` one full-width panel
   * above two columns. Every level of the panel tree declares its own, so a
   * drilled-in panel arranges its children independently.
   *
   * @param int|string ...$rows
   *   The layout name, or the sub-panel count of each visual row, top to
   *   bottom.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\PhpTui\FormException
   *   When a name is mixed with counts, more than one name is given, a visual
   *   row holds fewer than one sub-panel, or the panel already holds blocks
   *   the named layout has nowhere to put.
   */
  public function layout(int|string ...$rows): self {
    $counts = [];
    $names = [];

    foreach ($rows as $row) {
      if (is_int($row)) {
        $counts[] = $row;

        continue;
      }

      $names[] = $row;
    }

    if ($names === []) {
      return $this->arrange(new GridLayout(...$counts));
    }

    if ($counts !== []) {
      throw new FormException(sprintf('Panel "%s" declares a layout name beside a grid of sub-panels; a panel is arranged one way or the other.', $this->id));
    }

    if (count($names) > 1) {
      throw new FormException(sprintf('Panel "%s" declares %d layouts; a panel is arranged by one.', $this->id, count($names)));
    }

    return $this->arrange(LayoutManager::create($names[0]));
  }

  /**
   * Run a hook once before the panel first opens.
   *
   * The panel shows a loading state while the hook runs, so a drill-in that
   * has to prepare shared data - one fetch feeding several of the panel's
   * fields - shows feedback instead of freezing. The hook runs once.
   *
   * @param \Closure $work
   *   An `fn(): void` doing the preparation.
   *
   * @return $this
   *   The builder.
   */
  public function preload(\Closure $work): self {
    $this->panel->preload($work);

    return $this;
  }

  /**
   * Add a block to the current region.
   *
   * @param \DrevOps\PhpTui\Block\BlockInterface $block
   *   The block.
   *
   * @return $this
   *   The builder.
   */
  public function add(BlockInterface $block): self {
    $this->panel->in($this->region)->add($block);
    $this->placed = TRUE;

    return $this;
  }

  /**
   * Place a sub-panel in the region its layout assigns.
   *
   * A grid draws each of its sub-panels in a window of its own, and a window
   * is a region, so the region a sub-panel goes in is settled here rather
   * than derived later from declaration order.
   *
   * @param \DrevOps\PhpTui\Block\Panel $panel
   *   The sub-panel.
   *
   * @throws \DrevOps\PhpTui\FormException
   *   When the grid has no window left to draw it in.
   */
  protected function descend(Panel $panel): void {
    $layout = $this->panel->currentLayout();

    if (!$layout instanceof GridLayout) {
      $this->add($panel);

      return;
    }

    $window = $layout->windows()[count($this->panels) - 1] ?? NULL;

    if ($window === NULL) {
      throw new FormException(sprintf('The grid of "%s" declares %d window(s) for %d panel(s).', $this->id, count($layout->windows()), count($this->panels)));
    }

    $this->panel->in($window)->add($panel);
    $this->placed = TRUE;

    // A block declared after the sub-panels goes below the windows, so the
    // current region becomes the trailing one.
    $this->region = $layout->trailing();
  }

  /**
   * Arrange the panel with a layout.
   *
   * @param \DrevOps\PhpTui\Screen\Layout\LayoutInterface $layout
   *   The layout.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\PhpTui\FormException
   *   When the panel already holds blocks the layout has nowhere to put.
   */
  protected function arrange(LayoutInterface $layout): self {
    if ($this->placed) {
      throw new FormException(sprintf('Panel "%s" declares a layout after placing blocks in the one it had; declare the layout first, so every block knows the regions it may go in.', $this->id));
    }

    $this->panel->layout($layout);
    $this->region = $this->firstRegion();

    return $this;
  }

  /**
   * The region a block goes in when it names none.
   *
   * @return string
   *   The first region the panel's layout declares.
   *
   * @throws \DrevOps\PhpTui\FormException
   *   When the layout declares no region, so there is nowhere for a block to
   *   go.
   */
  protected function firstRegion(): string {
    $names = $this->panel->currentLayout()->names();

    if ($names === []) {
      throw new FormException(sprintf('Panel "%s" is arranged by a layout declaring no region, so it has nowhere to put a block.', $this->id));
    }

    return $names[0];
  }

  /**
   * Create, register and place a field builder of a given type.
   *
   * @param string $label
   *   The label the field draws.
   * @param string $id
   *   The id it answers to; derived from the label when not declared.
   * @param \DrevOps\PhpTui\Block\FieldType $type
   *   The field type.
   *
   * @return \DrevOps\PhpTui\Builder\FieldBuilder
   *   The field builder.
   */
  protected function field(string $label, string $id, FieldType $type): FieldBuilder {
    $field = new FieldBuilder(Name::id($label, $id), $label, $type);
    $this->fields[] = $field;
    $this->add($field->block());

    return $field;
  }

}
