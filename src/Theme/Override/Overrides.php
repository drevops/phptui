<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Theme\Override;

/**
 * The patch a theme consults before answering for an element.
 *
 * It holds only what was stated. An element nobody mentioned has no entry here
 * at all, which is what keeps this a patch rather than a replacement: the theme
 * goes on answering for everything else exactly as it did.
 *
 * @package DrevOps\PhpTui\Theme\Override
 */
final class Overrides {

  /**
   * The glyph pairs, keyed by the element they replace.
   *
   * @var array<string,\DrevOps\PhpTui\Theme\Override\Glyph>
   */
  protected array $glyphs = [];

  /**
   * The plain strings, keyed by the element they replace.
   *
   * @var array<string,string>
   */
  protected array $texts = [];

  /**
   * The SGR parameters, keyed by the element they repaint.
   *
   * @var array<string,string>
   */
  protected array $styles = [];

  /**
   * State the glyph an element draws.
   *
   * @param \DrevOps\PhpTui\Theme\Override\ThemeElement $element
   *   The element.
   * @param string $glyph
   *   The glyph.
   * @param string $ascii
   *   Its ASCII stand-in.
   */
  public function setGlyph(ThemeElement $element, string $glyph, string $ascii): void {
    $this->glyphs[$element->value] = new Glyph($glyph, $ascii);
  }

  /**
   * State the text an element draws.
   *
   * @param \DrevOps\PhpTui\Theme\Override\ThemeElement $element
   *   The element.
   * @param string $text
   *   The text.
   */
  public function setText(ThemeElement $element, string $text): void {
    $this->texts[$element->value] = $text;
  }

  /**
   * State the colour an element is painted in.
   *
   * @param \DrevOps\PhpTui\Theme\Override\ThemeElement $element
   *   The element.
   * @param string $sgr
   *   The SGR parameters.
   */
  public function setStyle(ThemeElement $element, string $sgr): void {
    $this->styles[$element->value] = $sgr;
  }

  /**
   * The glyph stated for an element.
   *
   * @param \DrevOps\PhpTui\Theme\Override\ThemeElement $element
   *   The element.
   *
   * @return \DrevOps\PhpTui\Theme\Override\Glyph|null
   *   The pair, or NULL when nobody stated one.
   */
  public function glyph(ThemeElement $element): ?Glyph {
    return $this->glyphs[$element->value] ?? NULL;
  }

  /**
   * The text stated for an element.
   *
   * @param \DrevOps\PhpTui\Theme\Override\ThemeElement $element
   *   The element.
   *
   * @return string|null
   *   The text, or NULL when nobody stated one.
   */
  public function text(ThemeElement $element): ?string {
    return $this->texts[$element->value] ?? NULL;
  }

  /**
   * The colour stated for an element.
   *
   * @param \DrevOps\PhpTui\Theme\Override\ThemeElement $element
   *   The element.
   *
   * @return string|null
   *   The SGR parameters, or NULL when nobody stated any.
   */
  public function style(ThemeElement $element): ?string {
    return $this->styles[$element->value] ?? NULL;
  }

}
