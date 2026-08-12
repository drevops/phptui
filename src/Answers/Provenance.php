<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Answers;

use DrevOps\PhpTui\Translation\Translator;

/**
 * How an answer's value came to be.
 *
 * @package DrevOps\PhpTui\Answers
 */
enum Provenance: string {

  // The declared (or type) default; nothing supplied or computed it.
  case Default = 'default';

  // Detected from the project content in update mode.
  case Detected = 'detected';

  // Supplied by the user: an input, an env override or an interactive edit.
  case Edited = 'edited';

  // Computed by the question's derive rule.
  case Derived = 'derived';

  // Supplied by the user over a derive rule, pinning the derived value.
  case Override = 'override';

  /**
   * The badge label in the active language.
   *
   * Each case translates a literal rather than the backing value, so each
   * badge string is a discoverable chrome key in the catalog template.
   *
   * @return string
   *   The translated badge label.
   */
  public function label(): string {
    return match ($this) {
      self::Default => Translator::t('default'),
      self::Detected => Translator::t('detected'),
      self::Edited => Translator::t('edited'),
      self::Derived => Translator::t('derived'),
      self::Override => Translator::t('override'),
    };
  }

}
