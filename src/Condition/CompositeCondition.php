<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Condition;

/**
 * A composite condition: all / any / not over other conditions.
 *
 * Construct via {@see Condition::all()}, {@see Condition::any()} and
 * {@see Condition::not()}.
 *
 * @package DrevOps\PhpTui\Condition
 */
final readonly class CompositeCondition implements ConditionInterface {

  /**
   * Construct a composite condition.
   *
   * @param \DrevOps\PhpTui\Condition\CompositeOperator $compositeOperator
   *   How the conditions combine.
   * @param list<\DrevOps\PhpTui\Condition\ConditionInterface> $conditions
   *   The combined conditions (exactly one for Not).
   */
  public function __construct(protected CompositeOperator $compositeOperator, protected array $conditions) {
  }

  /**
   * {@inheritdoc}
   */
  public function matches(array $answers): bool {
    if ($this->compositeOperator === CompositeOperator::Not) {
      $first = $this->conditions[0] ?? NULL;

      return !$first instanceof ConditionInterface || !$first->matches($answers);
    }

    foreach ($this->conditions as $condition) {
      $matched = $condition->matches($answers);

      if ($this->compositeOperator === CompositeOperator::Any && $matched) {
        return TRUE;
      }

      if ($this->compositeOperator === CompositeOperator::All && !$matched) {
        return FALSE;
      }
    }

    return $this->compositeOperator === CompositeOperator::All;
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    $fields = [];

    foreach ($this->conditions as $condition) {
      $fields = array_merge($fields, $condition->fields());
    }

    return array_values(array_unique($fields));
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    if ($this->compositeOperator === CompositeOperator::Not) {
      $first = $this->conditions[0] ?? NULL;

      return ['not' => $first instanceof ConditionInterface ? $first->toArray() : []];
    }

    return [$this->compositeOperator->value => array_map(static fn(ConditionInterface $condition): array => $condition->toArray(), $this->conditions)];
  }

}
