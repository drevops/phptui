<?php

declare(strict_types=1);

namespace DrevOps\Tui\Derive;

use DrevOps\Tui\Model\FormException;
use DrevOps\Tui\Utils\Strings;

/**
 * A derive rule: a `{{field}}` template and an optional named transform.
 *
 * Declared with named arguments - `new Derive('{{name}}', transform:
 * 'machine')` - and owning its computation. The transform name is validated at
 * construction, so a typo fails at declaration time instead of silently
 * passing values through.
 *
 * @package DrevOps\Tui\Derive
 */
final readonly class Derive {

  /**
   * Construct a derive rule.
   *
   * @param string $template
   *   The template with `{{field}}` tokens interpolated from current values.
   * @param string $transform
   *   The transform normalizing the result: one of the names in
   *   {@see Transform::names()} (empty for none).
   */
  public function __construct(public string $template, public string $transform = '') {
    if ($this->transform !== '' && !Transform::supports($this->transform)) {
      throw new FormException(sprintf('Unknown derive transform "%s".', $this->transform));
    }
  }

  /**
   * Compute the derived value from the current values.
   *
   * @param array<string,mixed> $values
   *   The current values keyed by field id.
   *
   * @return string
   *   The derived value.
   */
  public function compute(array $values): string {
    $interpolated = trim(Strings::interpolate($this->template, $values));

    return $this->transform === '' ? $interpolated : Transform::apply($interpolated, $this->transform);
  }

  /**
   * The rule as the raw array shape used by the JSON schema.
   *
   * @return array<string,string>
   *   The raw rule.
   */
  public function toArray(): array {
    if ($this->transform === '') {
      return ['template' => $this->template];
    }

    return [
      'template' => $this->template,
      'transform' => $this->transform,
    ];
  }

}
