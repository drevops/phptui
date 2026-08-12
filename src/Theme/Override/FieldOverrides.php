<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Theme\Override;

/**
 * The field's elements, as a consumer patches them.
 *
 * The block's prefix is implied by the group, so `selector()` here is the
 * field's selector and nothing else's - and the option's is a call of its own,
 * because the two are different marks.
 *
 * @package DrevOps\PhpTui\Theme\Override
 */
final class FieldOverrides {

  /**
   * Construct a group over the patch it writes into.
   *
   * @param \DrevOps\PhpTui\Theme\Override\Overrides $overrides
   *   The patch being collected.
   */
  public function __construct(
    protected Overrides $overrides,
  ) {
  }

  /**
   * Draw the mark saying which field has focus with this glyph.
   *
   * @param string $glyph
   *   The glyph.
   * @param string $ascii
   *   Its ASCII stand-in.
   *
   * @return $this
   *   The group.
   */
  public function selector(string $glyph, string $ascii): self {
    $this->overrides->setGlyph(ThemeElement::FieldSelector, $glyph, $ascii);

    return $this;
  }

  /**
   * Draw the mark saying a field has help with this glyph.
   *
   * @param string $glyph
   *   The glyph.
   * @param string $ascii
   *   Its ASCII stand-in.
   *
   * @return $this
   *   The group.
   */
  public function helpMarker(string $glyph, string $ascii): self {
    $this->overrides->setGlyph(ThemeElement::FieldHelpMarker, $glyph, $ascii);

    return $this;
  }

  /**
   * Stand this text between the parts of an answer that has more than one.
   *
   * One argument rather than two: what joins the parts of an answer is a
   * phrase the reader parses, not a glyph a terminal may fail to draw.
   *
   * @param string $text
   *   The separator.
   *
   * @return $this
   *   The group.
   */
  public function valueSeparator(string $text): self {
    $this->overrides->setText(ThemeElement::FieldValueSeparator, $text);

    return $this;
  }

  /**
   * Draw the mark saying which option has focus with this glyph.
   *
   * @param string $glyph
   *   The glyph.
   * @param string $ascii
   *   Its ASCII stand-in.
   *
   * @return $this
   *   The group.
   */
  public function optionSelector(string $glyph, string $ascii): self {
    $this->overrides->setGlyph(ThemeElement::FieldOptionSelector, $glyph, $ascii);

    return $this;
  }

  /**
   * Draw the mark recording a picked option with this glyph.
   *
   * The mark an option carries once it is picked; an option nobody picked
   * keeps whatever the theme draws for it, so a patch stays a patch.
   *
   * @param string $glyph
   *   The glyph.
   * @param string $ascii
   *   Its ASCII stand-in.
   *
   * @return $this
   *   The group.
   */
  public function optionMarker(string $glyph, string $ascii): self {
    $this->overrides->setGlyph(ThemeElement::FieldOptionMarker, $glyph, $ascii);

    return $this;
  }

  /**
   * Draw the mark showing where the next keystroke lands with this glyph.
   *
   * @param string $glyph
   *   The glyph.
   * @param string $ascii
   *   Its ASCII stand-in.
   *
   * @return $this
   *   The group.
   */
  public function caret(string $glyph, string $ascii): self {
    $this->overrides->setGlyph(ThemeElement::FieldCaret, $glyph, $ascii);

    return $this;
  }

}
