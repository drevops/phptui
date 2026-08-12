<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Theme;

/**
 * A retro MS-DOS theme: the bright 16-colour CGA palette on the blue screen.
 *
 * The look of EDIT.COM, QBasic and Norton Commander - bright white headings,
 * cyan values and yellow highlights inside a double-line box on the classic DOS
 * blue, in the period-correct 16-colour SGR set rather than 256-colour. It
 * states its colours, defaults to a double-line border and washes the screen
 * blue, and inherits every element from the default theme.
 *
 * @package DrevOps\PhpTui\Theme
 */
class DosTheme extends DefaultTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function accent(): string {
    return Sgr::of(Sgr::Bold, Sgr::BrightWhite);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function value(string $text, bool $emphatic = FALSE): string {
    return $this->paint($this->emphasize(Sgr::of(Sgr::BrightCyan), $emphatic), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function indicator(string $text): string {
    return $this->paint(Sgr::of(Sgr::BrightYellow), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function description(string $text): string {
    // The inherited dim grey is too dark to read on the blue wash; the CGA
    // light grey (colour 7) is the period-correct body text and clears it.
    return $this->paint(Sgr::of(Sgr::White), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function guidance(): string {
    // CGA had no italic, so the inherited style would leave the guidance voice
    // identical to the body text; bright cyan is the period-correct help colour
    // and lifts off the blue wash.
    return Sgr::of(Sgr::BrightCyan);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function footer(string $text): string {
    return $this->paint(Sgr::of(Sgr::White), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function heading(string $text): string {
    return $this->paint(Sgr::of(Sgr::Bold, Sgr::White), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function border(string $text): string {
    return $this->paint(Sgr::of(Sgr::BrightWhite), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldOptionMatch(string $text): string {
    return $this->paint(Sgr::of(Sgr::BrightYellow), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function breadcrumbLabel(string $text): string {
    return $this->paint(Sgr::of(Sgr::White), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function borderStyle(): Border {
    // The MS-DOS look is a bordered window (EDIT.COM / Norton Commander), so
    // default to a double-line box when the form declares no border of its own.
    if (!isset($this->options['border'])) {
      return Border::Double;
    }

    return parent::borderStyle();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function background(): ?string {
    // The blue screen is the theme's signature (EDIT.COM / QBasic), painted in
    // either mode; with colour off there is no screen to paint.
    return $this->color ? Sgr::of(Sgr::OnBlue) : NULL;
  }

}
