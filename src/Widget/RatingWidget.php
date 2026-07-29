<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Widget\Capability\StepCapableInterface;

/**
 * A graded answer: a point chosen from a scale, accepted as an int.
 *
 * The arrows walk the scale one point at a time and stop at either end - a
 * grade has a floor and a ceiling, so unlike a two-state switch the ends do
 * not wrap round. A digit selects its point outright when the scale reaches it.
 *
 * The ends are plain integers rather than a bounds object because a scale is
 * closed by construction: there is no point to draw beyond either end, so
 * neither may be left open.
 *
 * @package DrevOps\Tui\Widget
 */
class RatingWidget extends AbstractWidget implements StepCapableInterface {

  /**
   * The chosen point on the scale.
   */
  protected int $point;

  /**
   * Construct a rating widget.
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
   * Each position is one point, and the scale stops at the end it reaches.
   */
  public function stepBy(int $delta): void {
    $this->point = $this->clamp($this->point + $delta);
  }

  /**
   * Jump to the point a typed digit names.
   *
   * A digit the scale does not reach leaves the choice alone, so typing on a
   * scale that starts above nine - or runs well past it - is inert rather than
   * surprising.
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
    return $theme->renderScale($this->point, $this->min, $this->max, $this->captions[$this->point] ?? '');
  }

  /**
   * {@inheritdoc}
   *
   * The stepping keys lead: nothing about a row of points says which keys move
   * along it.
   */
  #[\Override]
  public function hints(): array {
    return [new Hint('adjust', Action::Increment, Action::Decrement), ...parent::hints()];
  }

}
