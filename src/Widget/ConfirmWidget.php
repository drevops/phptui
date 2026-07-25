<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Translation\Translator;
use DrevOps\Tui\Widget\Capability\StepCapableInterface;

/**
 * A yes/no toggle.
 *
 * @package DrevOps\Tui\Widget
 */
class ConfirmWidget extends AbstractWidget implements StepCapableInterface {

  /**
   * Construct a confirm widget.
   *
   * @param bool $current
   *   The initial choice.
   */
  public function __construct(protected bool $current = FALSE) {
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
    $yes_label = $this->highlightLabel($theme, Translator::t('Yes'), $this->current);
    $no_label = $this->highlightLabel($theme, Translator::t('No'), !$this->current);

    return $theme->radio($this->current) . ' ' . $yes_label . '  ' . $theme->radio(!$this->current) . ' ' . $no_label;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function hints(): array {
    return [new Hint('yes/no', Action::Yes, Action::No), new Hint('toggle', Action::Toggle), ...parent::hints()];
  }

}
