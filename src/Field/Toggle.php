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
use DrevOps\PhpTui\Utils\Strings;

/**
 * An inline switch between two labeled values.
 *
 * @package DrevOps\PhpTui\Field
 */
class Toggle extends AbstractField implements StepCapableInterface {

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
   * Construct a toggle field.
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
      $parts[] = $this->renderExclusiveRow($theme, $this->labels[$value] ?? $value, $index === $this->cursor);
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
