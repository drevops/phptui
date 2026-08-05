<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field;

use DrevOps\Tui\Block\FieldType;
use DrevOps\Tui\Field\Capability\StepCapableInterface;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Translation\Translator;

/**
 * A yes/no toggle.
 *
 * @package DrevOps\Tui\Field
 */
class Confirm extends AbstractField implements StepCapableInterface {

  /**
   * The chosen answer.
   */
  protected bool $current;

  /**
   * Construct a confirm field.
   *
   * @param bool $default
   *   The initial choice.
   */
  public function __construct(bool $default = FALSE) {
    $this->current = $default;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function keyScope(): Scope {
    return Scope::field(FieldType::Confirm);
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

    if ($keys->matches($key, Action::Yes)) {
      $this->current = TRUE;

      return;
    }

    if ($keys->matches($key, Action::No)) {
      $this->current = FALSE;
    }
  }

  /**
   * {@inheritdoc}
   *
   * The domain is the yes/no pair, so any odd step flips the value.
   */
  public function stepBy(int $delta): void {
    if ($delta % 2 !== 0) {
      $this->current = !$this->current;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    return $this->current;
  }

  /**
   * {@inheritdoc}
   */
  protected function renderBody(ThemeInterface $theme): string {
    return $this->renderExclusiveRow($theme, Translator::t('Yes'), $this->current) . '  ' . $this->renderExclusiveRow($theme, Translator::t('No'), !$this->current);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function hints(): array {
    return [
      new Hint('answer yes or no', Action::Yes, Action::No),
      new Hint('toggle', Action::Toggle),
      ...parent::hints(),
    ];
  }

}
