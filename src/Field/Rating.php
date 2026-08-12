<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Field;

use DrevOps\PhpTui\Block\FieldType;
use DrevOps\PhpTui\Field\Capability\StepCapableInterface;
use DrevOps\PhpTui\Input\Action;
use DrevOps\PhpTui\Input\Hint;
use DrevOps\PhpTui\Input\Key;
use DrevOps\PhpTui\Input\Scope;
use DrevOps\PhpTui\Theme\ThemeInterface;

/**
 * A graded answer: a point chosen from a scale, accepted as an int.
 *
 * The arrows step one point at a time and stop at either end; the ends do
 * not wrap. A typed digit selects its point when the scale includes it.
 *
 * @package DrevOps\PhpTui\Field
 */
class Rating extends AbstractField implements StepCapableInterface {

  /**
   * The chosen point on the scale.
   */
  protected int $point;

  /**
   * Construct a rating field.
   *
   * @param int $default
   *   The initially chosen point; clamped onto the scale.
   * @param int $min
   *   The lowest point of the scale.
   * @param int $max
   *   The highest point of the scale.
   * @param array<int,string> $captions
   *   The caption of a point, keyed by the point; points may be uncaptioned.
   */
  public function __construct(int $default, protected int $min = 1, protected int $max = 5, protected array $captions = []) {
    $this->point = $this->clamp($default);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function keyScope(): Scope {
    return Scope::field(FieldType::Rating);
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Key $key): void {
    $keys = $this->keys();

    if ($keys->matches($key, Action::Increment)) {
      $this->stepBy(1);

      return;
    }

    if ($keys->matches($key, Action::Decrement)) {
      $this->stepBy(-1);

      return;
    }

    if ($this->handleCancel($key)) {
      return;
    }

    if ($this->handleAccept($key)) {
      return;
    }

    if ($key->isChar()) {
      $this->applyChar($key->char ?? '');
    }
  }

  /**
   * {@inheritdoc}
   *
   * Each position is one point, clamped at the ends of the scale.
   */
  public function stepBy(int $delta): void {
    $this->point = $this->clamp($this->point + $delta);
  }

  /**
   * Jump to the point a typed digit names.
   *
   * A digit the scale does not include is ignored. One digit names one point,
   * so only the points 0 to 9 are reachable by typing; the rest are reached
   * with the movement keys.
   *
   * @param string $char
   *   The typed character.
   */
  protected function applyChar(string $char): void {
    if (!ctype_digit($char)) {
      return;
    }

    $point = (int) $char;
    if ($point >= $this->min && $point <= $this->max) {
      $this->point = $point;
    }
  }

  /**
   * Move a point onto the scale.
   *
   * @param int $point
   *   The candidate point.
   *
   * @return int
   *   The point, moved onto the nearest end it overshoots.
   */
  protected function clamp(int $point): int {
    return max($this->min, min($this->max, $point));
  }

  /**
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    return $this->point;
  }

  /**
   * {@inheritdoc}
   */
  protected function renderBody(ThemeInterface $theme): string {
    return $this->elements($theme)->fieldScale($this->point, $this->min, $this->max, $this->captions[$this->point] ?? '');
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function hints(): array {
    return [new Hint('adjust', Action::Increment, Action::Decrement), ...parent::hints()];
  }

}
