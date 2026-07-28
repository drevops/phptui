<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * A retro MS-DOS theme: the bright 16-colour CGA palette on the blue screen.
 *
 * The look of EDIT.COM, QBasic and Norton Commander - bright white headings,
 * cyan values and yellow highlights inside a double-line box on the classic DOS
 * blue, in the period-correct 16-colour SGR set rather than 256-colour. It
 * declares its colours by overriding the appearance atoms directly, defaults to
 * a double-line border and washes the screen blue, and inherits the default
 * theme's layout and glyphs.
 *
 * @package DrevOps\Tui\Theme
 */
class DosTheme extends DefaultTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function title(string $text): string {
    return $this->paint(Sgr::of(Sgr::Bold, Sgr::BrightWhite), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function value(string $text, bool $selected = FALSE): string {
    return $this->paint($this->emphasize(Sgr::of(Sgr::BrightCyan), $selected), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function indicator(string $text): string {
    return $this->paint(Sgr::of(Sgr::BrightYellow), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function highlight(string $text): string {
    return $this->paint(Sgr::of(Sgr::Bold, Sgr::BrightWhite), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function highlightMatch(string $text): string {
    return $this->paint(Sgr::of(Sgr::BrightYellow), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function description(string $text, bool $selected = FALSE): string {
    // The inherited dim grey is too dark to read on the blue wash; the CGA
    // light grey (colour 7) is the period-correct body text and clears it.
    return $this->paint($this->emphasize(Sgr::of(Sgr::White), $selected), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function hint(string $text, bool $selected = FALSE): string {
    // CGA had no italic, so the inherited style would leave the hint identical
    // to the body text; bright cyan is the period-correct help colour and lifts
    // off the blue wash.
    return $this->paint($this->emphasize(Sgr::of(Sgr::BrightCyan), $selected), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function footer(string $text): string {
    return $this->paint(Sgr::of(Sgr::White), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function breadcrumb(string $text): string {
    return $this->paint(Sgr::of(Sgr::White), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function heading(string $text): string {
    return $this->paint(Sgr::of(Sgr::Bold, Sgr::White), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function border(string $text): string {
    return $this->paint(Sgr::of(Sgr::BrightWhite), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function marker(bool $selected): string {
    return $selected ? $this->paint(Sgr::of(Sgr::Bold, Sgr::BrightWhite), $this->unicode ? '❯' : '>') : ' ';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function radio(bool $on): string {
    return $on ? $this->paint(Sgr::of(Sgr::Bold, Sgr::BrightWhite), $this->unicode ? '●' : '(*)') : ($this->unicode ? '○' : '( )');
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function caret(): string {
    return $this->paint(Sgr::of(Sgr::Bold, Sgr::BrightWhite), $this->unicode ? '█' : '|');
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function borderStyle(): Border {
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
