<?php

declare(strict_types=1);

namespace DrevOps\Tui\Model;

/**
 * A single row in a select, search or suggest option list.
 *
 * A row is an Option, a Separator or a Heading (see {@see OptionKind}). Only
 * an Option row is selectable, and only when it is not disabled; Separator and
 * Heading rows, and disabled Option rows, are visual structure that navigation
 * skips and collection never returns.
 *
 * @package DrevOps\Tui\Model
 */
final readonly class Option {

  /**
   * Construct an option row.
   *
   * @param string $value
   *   The value collected when the option is selected (empty for structural
   *   rows).
   * @param string $label
   *   The displayed label.
   * @param string $description
   *   The option's description. Shown for the highlighted option as a secondary
   *   line beneath the choice list, and carried into the machine schema.
   * @param \DrevOps\Tui\Model\OptionKind $kind
   *   The row kind.
   * @param bool $disabled
   *   Whether a selectable Option row is shown but cannot be selected.
   * @param string $disabledReason
   *   The reason shown beside a disabled option.
   */
  public function __construct(
    public string $value,
    public string $label,
    public string $description = '',
    public OptionKind $kind = OptionKind::Option,
    public bool $disabled = FALSE,
    public string $disabledReason = '',
  ) {
  }

  /**
   * Whether this row can hold the cursor and be selected.
   *
   * @return bool
   *   TRUE for an enabled Option row; FALSE for separators, headings and
   *   disabled options.
   */
  public function selectable(): bool {
    return $this->kind === OptionKind::Option && !$this->disabled;
  }

  /**
   * Normalize a value => label map or a list of options into a list of options.
   *
   * The map form (`['standard' => 'Standard']`) is the ergonomic shorthand for
   * simple selectable options; richer rows (separators, headings, disabled
   * options) are passed as {@see Option} instances. A label defaults to its
   * value when empty.
   *
   * @param array<int|string,\DrevOps\Tui\Model\Option|string> $options
   *   Either a value => label map, a list of options, or a mix.
   *
   * @return list<\DrevOps\Tui\Model\Option>
   *   The normalized option list.
   */
  public static function list(array $options): array {
    $out = [];

    foreach ($options as $key => $value) {
      if ($value instanceof self) {
        $out[] = $value;

        continue;
      }

      $out[] = new self((string) $key, $value === '' ? (string) $key : (string) $value);
    }

    return $out;
  }

  /**
   * The values of the selectable rows, in display order.
   *
   * The one filtering every collection surface shares, so the field model, the
   * choice widgets and the schema generators agree on what is selectable.
   *
   * @param list<\DrevOps\Tui\Model\Option> $options
   *   The option rows.
   *
   * @return list<string>
   *   The selectable option values (excludes separators, headings and disabled
   *   options).
   */
  public static function selectableValues(array $options): array {
    $out = [];

    foreach ($options as $option) {
      if ($option->selectable()) {
        $out[] = $option->value;
      }
    }

    return $out;
  }

}
