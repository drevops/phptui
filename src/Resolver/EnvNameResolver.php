<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Resolver;

use DrevOps\PhpTui\Block\Field;

/**
 * Names the environment variables that answer a field.
 *
 * A field is answered by `<PREFIX><FIELD_ID>` unless it declares its own name,
 * in which case that name is absolute and the prefix does not apply - the point
 * of an override is to reproduce a variable name that already exists elsewhere.
 * Declared aliases are absolute for the same reason and are consulted in
 * declaration order after the canonical name, so the canonical name wins
 * whenever more than one is set.
 *
 * The rule lives here rather than in its callers because it is needed both to
 * read the environment and to advertise it in machine-readable output; a second
 * copy would let the two drift, advertising a variable that is never read.
 *
 * @package DrevOps\PhpTui\Resolver
 */
class EnvNameResolver {

  /**
   * Construct a name resolver.
   *
   * @param string $envPrefix
   *   The prefix for mechanically named variables (e.g. "APP_").
   */
  public function __construct(protected string $envPrefix = '') {
  }

  /**
   * The variable that answers the field when several are set.
   *
   * @param \DrevOps\PhpTui\Block\Field $field
   *   The field.
   *
   * @return string
   *   The declared override, or the prefixed and uppercased field id.
   */
  public function canonical(Field $field): string {
    $declared = $this->declaredName($field);

    return $declared !== '' ? $declared : $this->envPrefix . strtoupper($field->id());
  }

  /**
   * The additional variables the field also answers to, in declaration order.
   *
   * @param \DrevOps\PhpTui\Block\Field $field
   *   The field.
   *
   * @return list<string>
   *   The alias names; empty when the field declares none.
   */
  public function aliases(Field $field): array {
    return $field->aliases();
  }

  /**
   * Every variable that answers the field, in precedence order.
   *
   * @param \DrevOps\PhpTui\Block\Field $field
   *   The field.
   *
   * @return list<string>
   *   The canonical name first, then each alias in declaration order.
   */
  public function all(Field $field): array {
    return [$this->canonical($field), ...$this->aliases($field)];
  }

  /**
   * Whether the canonical name is worth advertising to a machine consumer.
   *
   * A mechanically named field under an empty prefix would advertise a bare,
   * unnamespaced variable that collides with anything else in the environment,
   * so it is not offered as an answer route. Declared aliases are absolute and
   * carry no such risk, so they stand on their own.
   *
   * @param \DrevOps\PhpTui\Block\Field $field
   *   The field.
   *
   * @return bool
   *   TRUE when the field declares its own name or a prefix namespaces it.
   */
  public function isAdvertisable(Field $field): bool {
    if ($this->declaredName($field) !== '') {
      return TRUE;
    }

    return $this->envPrefix !== '';
  }

  /**
   * The variable name a field declares for itself.
   *
   * @param \DrevOps\PhpTui\Block\Field $field
   *   The field.
   *
   * @return string
   *   The name, empty when the mechanical one stands.
   */
  protected function declaredName(Field $field): string {
    return $field->envName();
  }

}
