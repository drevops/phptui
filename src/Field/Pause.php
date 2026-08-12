<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Field;

use DrevOps\PhpTui\Block\FieldType;
use DrevOps\PhpTui\Input\Action;
use DrevOps\PhpTui\Input\Hint;
use DrevOps\PhpTui\Input\Key;
use DrevOps\PhpTui\Input\KeyName;
use DrevOps\PhpTui\Input\Scope;
use DrevOps\PhpTui\Theme\ThemeInterface;
use DrevOps\PhpTui\Translation\Translator;

/**
 * An acknowledgement gate: Enter (or Space) accepts TRUE.
 *
 * @package DrevOps\PhpTui\Field
 */
class Pause extends AbstractField {

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
    $key = $this->keys()->primary(Action::Accept) ?? Key::named(KeyName::Enter);
    // A named key travels as its name and a typed one as what it writes, so
    // nothing an input layer holds a key in reaches the theme.
    $glyph = $theme->keyGlyph($key->name ?? $key->label());

    return Translator::t('Press @key to continue', ['@key' => $this->optionLabel($theme, $glyph, TRUE)]);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function hints(): array {
    return [new Hint('continue', Action::Accept), new Hint('cancel', Action::Cancel)];
  }

}
