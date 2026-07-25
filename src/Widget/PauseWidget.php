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

/**
 * An acknowledgement gate: Enter (or Space) accepts TRUE.
 *
 * @package DrevOps\Tui\Widget
 */
class PauseWidget extends AbstractWidget {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function keyScope(): Scope {
    return Scope::field(FieldType::Pause);
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Key $key): void {
    $keys = $this->keys();

    if ($this->handleCancel($key)) {
      return;
    }

    if ($keys->matches($key, Action::Accept)) {
      $this->accept(TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function renderBody(ThemeInterface $theme): string {
    $key = $this->keys()->primary(Action::Accept);
    $glyph = $key instanceof Key ? $theme->keyHint($key) : $theme->enter();

    return Translator::t('Press @key to continue', ['@key' => $theme->highlight($glyph)]);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function hints(): array {
    return [new Hint('continue', Action::Accept), new Hint('cancel', Action::Cancel)];
  }

}
