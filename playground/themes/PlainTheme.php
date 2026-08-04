<?php

declare(strict_types=1);

namespace Playground\Themes;

use DrevOps\Tui\Theme\AbstractTheme;
use DrevOps\Tui\Utils\Strings;

/**
 * A theme built on the floor rather than on the default one.
 *
 * AbstractTheme is the floor: it implements every element and declares no
 * capability, so it hands back the strings it was given and the ASCII stand-ins
 * that read without them. A subclass of it may not paint and may not reach for
 * a glyph outside ASCII, and gets no frame, no air between rows and no wash
 * behind a dialog - not because those are switched off, but because a driver
 * asks for each of them and does without what the theme never promised.
 *
 * That is the whole of what a theme has to be, which is why a form still
 * renders in a terminal that offers nothing at all. Styling starts here and
 * adds what a terminal can do, rather than working around what it cannot.
 */
final class PlainTheme extends AbstractTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldSelector(bool $selected): string {
    // Two plain characters: an element on the floor takes and hands back
    // scalars, so a mark it invents has to read with no colour behind it.
    return $selected ? '=>' : '  ';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function panelTitle(string $text): string {
    // A title arrives already translated, so the case fold has to reach past
    // ASCII: strtoupper() would leave every non-Latin title untouched.
    return Strings::upper($text);
  }

}
