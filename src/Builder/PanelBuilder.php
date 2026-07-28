<?php

declare(strict_types=1);

namespace DrevOps\Tui\Builder;

use DrevOps\Tui\Model\Buttons;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\Modal;
use DrevOps\Tui\Model\Panel;

/**
 * A fluent builder for a Panel and its fields and sub-panels.
 *
 * @package DrevOps\Tui\Builder
 */
final class PanelBuilder {

  /**
   * The panel description.
   */
  protected string $description = '';

  /**
   * The field builders, in declaration order.
   *
   * @var \DrevOps\Tui\Builder\FieldBuilder[]
   */
  protected array $fields = [];

  /**
   * The nested panel builders, in declaration order.
   *
   * @var \DrevOps\Tui\Builder\PanelBuilder[]
   */
  protected array $panels = [];

  /**
   * The modal presentation config, or NULL for an ordinary drill-in panel.
   */
  protected ?Modal $modal = NULL;

  /**
   * The sub-panel grid rows, or empty for the row list.
   *
   * @var list<int>
   */
  protected array $layout = [];

  /**
   * A hook run once before the panel first opens, or NULL for none.
   */
  protected ?\Closure $preload = NULL;

  /**
   * Construct a panel builder.
   *
   * @param string $id
   *   The unique panel id.
   * @param string $title
   *   The panel title.
   */
  public function __construct(protected string $id, protected string $title) {
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
    $this->description = $description;

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
    $this->modal = new Modal(new Buttons(TRUE, $submit_label, $cancel_label));

    return $this;
  }

  /**
   * Add a text field.
   *
   * @param string $id
   *   The field id.
   * @param string $label
   *   The label (defaults to the id).
   *
   * @return \DrevOps\Tui\Builder\FieldBuilder
   *   The field builder.
   */
  public function text(string $id, string $label = ''): FieldBuilder {
    return $this->field($id, $label, FieldType::Text);
  }

  /**
   * Add a template field: fill named slots in a fixed shape.
   *
   * Chain `->pattern()` with the shape to fill in - its fixed text renders as
   * context and each `{{name}}` slot is filled separately - and
   * `->slot()` to label or validate one slot. The answer is the
   * assembled string; its parts are read back with
   * {@see \DrevOps\Tui\Answers\Answers::parts()}.
   *
   * @param string $id
   *   The field id.
   * @param string $label
   *   The label (defaults to the id).
   *
   * @return \DrevOps\Tui\Builder\FieldBuilder
   *   The field builder.
   */
  public function template(string $id, string $label = ''): FieldBuilder {
    return $this->field($id, $label, FieldType::Template);
  }

  /**
   * Add a select field. Call ->multiple() to collect several values as a list.
   *
   * @param string $id
   *   The field id.
   * @param string $label
   *   The label (defaults to the id).
   *
   * @return \DrevOps\Tui\Builder\FieldBuilder
   *   The field builder.
   */
  public function select(string $id, string $label = ''): FieldBuilder {
    return $this->field($id, $label, FieldType::Select);
  }

  /**
   * Add a reorder field (rank a list by moving the highlighted item).
   *
   * @param string $id
   *   The field id.
   * @param string $label
   *   The label (defaults to the id).
   *
   * @return \DrevOps\Tui\Builder\FieldBuilder
   *   The field builder.
   */
  public function reorder(string $id, string $label = ''): FieldBuilder {
    return $this->field($id, $label, FieldType::Reorder);
  }

  /**
   * Add a confirm field.
   *
   * @param string $id
   *   The field id.
   * @param string $label
   *   The label (defaults to the id).
   *
   * @return \DrevOps\Tui\Builder\FieldBuilder
   *   The field builder.
   */
  public function confirm(string $id, string $label = ''): FieldBuilder {
    return $this->field($id, $label, FieldType::Confirm);
  }

  /**
   * Add a toggle field (an inline switch between two labeled values).
   *
   * @param string $id
   *   The field id.
   * @param string $label
   *   The label (defaults to the id).
   *
   * @return \DrevOps\Tui\Builder\FieldBuilder
   *   The field builder.
   */
  public function toggle(string $id, string $label = ''): FieldBuilder {
    return $this->field($id, $label, FieldType::Toggle);
  }

  /**
   * Add a suggest field.
   *
   * @param string $id
   *   The field id.
   * @param string $label
   *   The label (defaults to the id).
   *
   * @return \DrevOps\Tui\Builder\FieldBuilder
   *   The field builder.
   */
  public function suggest(string $id, string $label = ''): FieldBuilder {
    return $this->field($id, $label, FieldType::Suggest);
  }

  /**
   * Add a number field.
   *
   * @param string $id
   *   The field id.
   * @param string $label
   *   The label (defaults to the id).
   *
   * @return \DrevOps\Tui\Builder\FieldBuilder
   *   The field builder.
   */
  public function number(string $id, string $label = ''): FieldBuilder {
    return $this->field($id, $label, FieldType::Number);
  }

  /**
   * Add a calendar field: a navigable month picker returning an ISO date.
   *
   * @param string $id
   *   The field id.
   * @param string $label
   *   The label (defaults to the id).
   *
   * @return \DrevOps\Tui\Builder\FieldBuilder
   *   The field builder.
   */
  public function calendar(string $id, string $label = ''): FieldBuilder {
    return $this->field($id, $label, FieldType::Calendar);
  }

  /**
   * Add a textarea field.
   *
   * @param string $id
   *   The field id.
   * @param string $label
   *   The label (defaults to the id).
   *
   * @return \DrevOps\Tui\Builder\FieldBuilder
   *   The field builder.
   */
  public function textarea(string $id, string $label = ''): FieldBuilder {
    return $this->field($id, $label, FieldType::Textarea);
  }

  /**
   * Add a password field.
   *
   * @param string $id
   *   The field id.
   * @param string $label
   *   The label (defaults to the id).
   *
   * @return \DrevOps\Tui\Builder\FieldBuilder
   *   The field builder.
   */
  public function password(string $id, string $label = ''): FieldBuilder {
    return $this->field($id, $label, FieldType::Password);
  }

  /**
   * Add a search field: a fuzzy type-to-filter choice list.
   *
   * Call ->multiple() to collect several values.
   *
   * @param string $id
   *   The field id.
   * @param string $label
   *   The label (defaults to the id).
   *
   * @return \DrevOps\Tui\Builder\FieldBuilder
   *   The field builder.
   */
  public function search(string $id, string $label = ''): FieldBuilder {
    return $this->field($id, $label, FieldType::Search);
  }

  /**
   * Add a file picker field (browse the filesystem for a path).
   *
   * Call ->multiple() to collect several paths.
   *
   * @param string $id
   *   The field id.
   * @param string $label
   *   The label (defaults to the id).
   *
   * @return \DrevOps\Tui\Builder\FieldBuilder
   *   The field builder.
   */
  public function filePicker(string $id, string $label = ''): FieldBuilder {
    return $this->field($id, $label, FieldType::FilePicker);
  }

  /**
   * Add a pause field (an acknowledgement gate).
   *
   * @param string $id
   *   The field id.
   * @param string $label
   *   The label (defaults to the id).
   *
   * @return \DrevOps\Tui\Builder\FieldBuilder
   *   The field builder.
   */
  public function pause(string $id, string $label = ''): FieldBuilder {
    return $this->field($id, $label, FieldType::Pause);
  }

  /**
   * Add a note: a non-interactive informational card.
   *
   * The note renders its title and body inline but collects no value - the
   * selection cursor skips it and it never appears in the answers. The body is
   * set with ->description(); ->border() draws the card inside a box; ->table()
   * renders an aligned grid beneath it.
   *
   * @param string $id
   *   The field id.
   * @param string $title
   *   The card title (optional; an empty title renders the body alone).
   *
   * @return \DrevOps\Tui\Builder\FieldBuilder
   *   The field builder.
   */
  public function note(string $id, string $title = ''): FieldBuilder {
    // A note's title is genuinely optional, so unlike other fields it is not
    // filled from the id when omitted.
    return $this->field($id, $title, FieldType::Note, FALSE);
  }

  /**
   * Add a progress row that runs work when activated, showing an indicator.
   *
   * Chain `->steps(int)` for a determinate bar (omit it for a spinner) and
   * `->run(callable)` for the work; the callback drives the indicator through
   * the {@see \DrevOps\Tui\Primitive\ProgressReporter} it receives.
   *
   * @param string $id
   *   The field id.
   * @param string $label
   *   The caption shown beside the indicator (defaults to the id).
   *
   * @return \DrevOps\Tui\Builder\FieldBuilder
   *   The field builder.
   */
  public function progress(string $id, string $label = ''): FieldBuilder {
    return $this->field($id, $label, FieldType::Progress);
  }

  /**
   * Add a nested sub-panel.
   *
   * @param string $id
   *   The sub-panel id.
   * @param string $title
   *   The sub-panel title.
   * @param \Closure $build
   *   The callback receiving the sub-panel builder.
   *
   * @return $this
   *   The builder.
   */
  public function panel(string $id, string $title, \Closure $build): self {
    $panel = new self($id, $title);
    $build($panel);
    $this->panels[] = $panel;

    return $this;
  }

  /**
   * Arrange this panel's sub-panels as a grid of side-by-side columns.
   *
   * Each argument declares one visual row and names how many sub-panels sit
   * side by side in it; the sub-panels fill the rows in declaration order.
   * `layout(2)` puts two panels beside each other, `layout(2, 2)` makes four
   * windows, `layout(1, 2)` one full-width panel above two columns. Every
   * level of the panel tree declares its own layout, so a drilled-in panel
   * arranges its children independently.
   *
   * @param int ...$rows
   *   The sub-panel count of each visual row, top to bottom.
   *
   * @return $this
   *   The builder.
   */
  public function layout(int ...$rows): self {
    $this->layout = array_values($rows);

    return $this;
  }

  /**
   * Run a hook once before the panel first opens.
   *
   * The panel reads as loading while the hook runs, so a drill-in that has to
   * prepare shared data - one fetch feeding several of the panel's fields -
   * shows feedback instead of freezing. Runs once, then never again.
   *
   * @param \Closure $work
   *   An `fn(): void` doing the preparation.
   *
   * @return $this
   *   The builder.
   */
  public function preload(\Closure $work): self {
    $this->preload = $work;

    return $this;
  }

  /**
   * Build the immutable Panel.
   *
   * @return \DrevOps\Tui\Model\Panel
   *   The panel.
   *
   * @throws \DrevOps\Tui\Model\FormException
   *   When the declared layout does not match the sub-panels.
   */
  public function build(): Panel {
    LayoutGuard::assert($this->layout, count($this->panels), $this->id);

    return new Panel(
      $this->id,
      $this->title,
      $this->description,
      array_map(static fn(FieldBuilder $field): Field => $field->build(), $this->fields),
      array_map(static fn(PanelBuilder $panel): Panel => $panel->build(), $this->panels),
      $this->modal,
      $this->layout,
      $this->preload,
    );
  }

  /**
   * Create, register and return a field builder of a given type.
   *
   * @param string $id
   *   The field id.
   * @param string $label
   *   The label (defaults to the id).
   * @param \DrevOps\Tui\Model\FieldType $type
   *   The widget type.
   * @param bool $label_fallback
   *   Whether an empty label falls back to the id (the default); FALSE keeps an
   *   empty label empty, for a note whose title is optional.
   *
   * @return \DrevOps\Tui\Builder\FieldBuilder
   *   The field builder.
   */
  protected function field(string $id, string $label, FieldType $type, bool $label_fallback = TRUE): FieldBuilder {
    $field = new FieldBuilder($id, $label === '' && $label_fallback ? $id : $label, $type);
    $this->fields[] = $field;

    return $field;
  }

}
