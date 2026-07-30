<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

use DrevOps\Tui\Block\Field;

/**
 * Declares one field, then hands the panel back.
 *
 * Six of a field's capabilities are declared here, each with its own call; the
 * seventh, binding keys, comes from the kind of field rather than the field.
 *
 * @package DrevOps\Tui\Screen
 */
final class FieldBuilder {

  /**
   * Construct a field builder.
   *
   * @param \DrevOps\Tui\Screen\PanelBuilder $panelBuilder
   *   The panel to hand back.
   * @param \DrevOps\Tui\Block\Field $field
   *   The field being declared.
   */
  public function __construct(
    protected PanelBuilder $panelBuilder,
    protected Field $field,
  ) {
  }

  /**
   * Seed the answer this field starts with.
   *
   * @param mixed $value
   *   The value.
   *
   * @return $this
   *   The field builder.
   */
  public function default(mixed $value): self {
    $this->field->default($value);

    return $this;
  }

  /**
   * Offer an entry for edit mode to open onto.
   *
   * @param string $value
   *   The value it stands for.
   * @param string $label
   *   The label it draws.
   *
   * @return $this
   *   The field builder.
   */
  public function entry(string $value, string $label): self {
    $this->field->entry($value, $label);

    return $this;
  }

  /**
   * State what this field will accept, before anything is refused.
   *
   * @param string $constraint
   *   The constraint.
   *
   * @return $this
   *   The field builder.
   */
  public function constrain(string $constraint): self {
    $this->field->constrain($constraint);

    return $this;
  }

  /**
   * Refuse values, and say why.
   *
   * @param \Closure $validate
   *   Given the offered value, returns the reason it is refused or NULL.
   *
   * @return $this
   *   The field builder.
   */
  public function validate(\Closure $validate): self {
    $this->field->validate($validate);

    return $this;
  }

  /**
   * Decide whether this field is asked for at all.
   *
   * @param \Closure $when
   *   Returns whether it is.
   *
   * @return $this
   *   The field builder.
   */
  public function when(\Closure $when): self {
    $this->field->when($when);

    return $this;
  }

  /**
   * Set the long-form text behind this field's help key.
   *
   * @param string $help
   *   The help.
   *
   * @return $this
   *   The field builder.
   */
  public function help(string $help): self {
    $this->field->help($help);

    return $this;
  }

  /**
   * Finish this field and carry on declaring the panel.
   *
   * @return \DrevOps\Tui\Screen\PanelBuilder
   *   The panel builder.
   */
  public function done(): PanelBuilder {
    return $this->panelBuilder;
  }

}
