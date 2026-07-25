<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Utils\Strings;
use DrevOps\Tui\Widget\Capability\StepCapableInterface;

/**
 * An inline switch between two labeled values.
 *
 * @package DrevOps\Tui\Widget
 */
class ToggleWidget extends AbstractWidget implements StepCapableInterface {

  /**
   * The option values in display order.
   *
   * @var list<string>
   */
  protected array $values;

  /**
   * The selected option index.
   */
  protected int $cursor = 0;

  /**
   * Construct a toggle widget.
   *
   * @param array<string,string> $labels
   *   Options as value => label, in display order.
   * @param string $default
   *   The initially selected value.
   */
  public function __construct(protected array $labels, string $default = '') {
    $this->values = array_keys($this->labels);
    $index = array_search($default, $this->values, TRUE);
    $this->cursor = $index === FALSE ? 0 : $index;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function keyScope(): Scope {
    return Scope::field(FieldType::Toggle);
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Key $key): void {
    $keys = $this->keys();

    if ($this->handleCancel($key)) {
      return;
    }

    if ($this->handleAccept($key)) {
      return;
    }

    if ($keys->matches($key, Action::Toggle)) {
      $this->stepBy(1);

      return;
    }

    if ($key->isChar()) {
      $this->applyChar($key->char ?? '');
    }
  }

  /**
   * {@inheritdoc}
   *
   * Each position moves to the adjacent value, wrapping at either end.
   */
  public function stepBy(int $delta): void {
    $count = count($this->values);
    if ($count < 2) {
      return;
    }

    $this->cursor = (($this->cursor + $delta) % $count + $count) % $count;
  }

  /**
   * Select the value whose label starts with the typed character.
   *
   * The first matching label wins, so labels sharing a first letter resolve to
   * the one declared first; the other stays reachable by flipping.
   *
   * @param string $char
   *   The typed character.
   */
  protected function applyChar(string $char): void {
    $char = Strings::lower($char);

    foreach ($this->values as $index => $value) {
      $label = $this->labels[$value] ?? $value;
      if ($label !== '' && Strings::lower(Strings::substr($label, 0, 1)) === $char) {
        $this->cursor = $index;

        return;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    return $this->values[$this->cursor] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  protected function renderBody(ThemeInterface $theme): string {
    $parts = [];

    foreach ($this->values as $index => $value) {
      $parts[] = $this->renderRadioRow($theme, $this->labels[$value] ?? $value, $index === $this->cursor);
    }

    return implode('  ', $parts);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function hints(): array {
    return [new Hint('toggle', Action::Toggle), ...parent::hints()];
  }

}
