<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Terminal\Ansi;

/**
 * A single row in a select, search or suggest option list.
 *
 * A row is an Option, a Separator or a Heading (see {@see OptionKind}). Only
 * an Option row is selectable, and only when it is not disabled; Separator and
 * Heading rows, and disabled Option rows, are visual structure that navigation
 * skips and collection never returns.
 *
 * Every row reaches this constructor, whether a field declares it or a loader,
 * a resolver or a query source returns it. Row text is filtered here only.
 *
 * @package DrevOps\Tui\Block
 */
final readonly class Option {

  /**
   * The value collected when the option is selected.
   */
  public string $value;

  /**
   * The displayed label.
   */
  public string $label;

  /**
   * What the option means, shown beside the list and in the machine schema.
   */
  public string $description;

  /**
   * The reason shown beside a disabled option.
   */
  public string $disabledReason;

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
   * @param \DrevOps\Tui\Block\OptionKind $kind
   *   The row kind.
   * @param bool $disabled
   *   Whether a selectable Option row is shown but cannot be selected.
   * @param string $disabled_reason
   *   The reason shown beside a disabled option.
   */
  public function __construct(
    string $value,
    string $label,
    string $description = '',
    public OptionKind $kind = OptionKind::Option,
    public bool $disabled = FALSE,
    string $disabled_reason = '',
  ) {
    $this->value = Ansi::sanitize($value);
    $this->label = Ansi::sanitize($label);
    $this->description = Ansi::sanitize($description);
    $this->disabledReason = Ansi::sanitize($disabled_reason);
  }

  /**
   * Whether this row can hold the cursor and be selected.
   *
   * @return bool
   *   TRUE for an enabled Option row; FALSE for separators, headings and
   *   disabled options.
   */
  public function isSelectable(): bool {
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
   * @param array<int|string,\DrevOps\Tui\Block\Option|string> $options
   *   Either a value => label map, a list of options, or a mix.
   *
   * @return list<\DrevOps\Tui\Block\Option>
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
   * Normalize what a callable returning options handed back.
   *
   * An option loader and a query source are each declared to return a
   * value => label map, but both are consumer code running mid-session, so a
   * mistyped one degrades to no options rather than erroring where there is no
   * good way to report it.
   *
   * @param mixed $result
   *   The callable's return value.
   *
   * @return list<\DrevOps\Tui\Block\Option>
   *   The normalized option list; empty when the result was not a map of
   *   strings.
   */
  public static function resolved(mixed $result): array {
    return self::list(array_filter(is_array($result) ? $result : [], is_string(...)));
  }

  /**
   * The values of the selectable rows, in display order.
   *
   * The one filtering every collection surface shares, so the field model, the
   * choice fields and the schema generators agree on what is selectable.
   *
   * @param list<\DrevOps\Tui\Block\Option> $options
   *   The option rows.
   *
   * @return list<string>
   *   The selectable option values (excludes separators, headings and disabled
   *   options).
   */
  public static function selectableValues(array $options): array {
    $out = [];

    foreach ($options as $option) {
      if ($option->isSelectable()) {
        $out[] = $option->value;
      }
    }

    return $out;
  }

}
