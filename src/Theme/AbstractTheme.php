<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Theme;

use DrevOps\PhpTui\Block\Element\ActionsElementsInterface;
use DrevOps\PhpTui\Block\Element\BorderElementsInterface;
use DrevOps\PhpTui\Block\Element\BreadcrumbElementsInterface;
use DrevOps\PhpTui\Block\Element\ChromeElementsInterface;
use DrevOps\PhpTui\Block\Element\FieldElementsInterface;
use DrevOps\PhpTui\Block\Element\LegendElementsInterface;
use DrevOps\PhpTui\Block\Element\MarkupElementsInterface;
use DrevOps\PhpTui\Block\Element\PanelElementsInterface;
use DrevOps\PhpTui\Block\Element\ProgressElementsInterface;
use DrevOps\PhpTui\Input\KeyName;
use DrevOps\PhpTui\Translation\Translator;

/**
 * The floor a theme starts from: every element, drawn with nothing at all.
 *
 * It declares no support capability, so it may not paint and may not reach for
 * a glyph outside ASCII. What is left is the strings it was handed and the
 * stand-ins that read without them - which is the whole point: a form renders
 * in a terminal that supports nothing, and a theme adds what its terminal can
 * do rather than working around what it cannot.
 *
 * A theme extends this and overrides the elements it wants to style, declaring
 * {@see \DrevOps\PhpTui\Theme\Capability\ColorSchemeCapableInterface} or
 * {@see \DrevOps\PhpTui\Theme\Capability\UnicodeCapableInterface} for the
 * facilities those elements need.
 *
 * @package DrevOps\PhpTui\Theme
 */
abstract class AbstractTheme implements ThemeInterface, ActionsElementsInterface, BorderElementsInterface, BreadcrumbElementsInterface, ChromeElementsInterface, FieldElementsInterface, LegendElementsInterface, MarkupElementsInterface, PanelElementsInterface, ProgressElementsInterface {

  /**
   * The spinner animation frames that need no glyph outside ASCII.
   */
  protected const array SPINNER_ASCII = ['|', '/', '-', '\\'];

  /**
   * Construct a theme.
   *
   * The two things no theme can work out for itself, which is why both arrive
   * from outside: the frame it lays content out to, and what a consumer stated
   * about how it should look. Nothing here measures a terminal, so the floor is
   * told a width rather than discovering one, and a theme that knows its own
   * frame answers with that instead. The floor reads no option at all - a theme
   * that declares one reads it from here.
   *
   * @param int $width
   *   The columns available for the content it lays out.
   * @param array<string,mixed> $options
   *   Display options keyed by name, as a consumer stated them.
   */
  public function __construct(protected int $width = 0, protected array $options = []) {
  }

  /**
   * {@inheritdoc}
   */
  public function contentWidth(): int {
    return $this->width;
  }

  /**
   * {@inheritdoc}
   */
  public function keyGlyph(KeyName|string $key): string {
    // Its name, because a name is what the floor has: an arrow is outside ASCII
    // and a shorthand is a vocabulary a theme with a terminal behind it can
    // afford to teach.
    return $key instanceof KeyName ? $key->name : $key;
  }

  /**
   * {@inheritdoc}
   */
  public function chromeBorder(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function chromeOverflowMarker(bool $above): string {
    return $above ? '^' : 'v';
  }

  /**
   * {@inheritdoc}
   *
   * One column, which is the least that keeps two things apart: the floor draws
   * no decoration, but two windows run against each other read as one.
   */
  public function chromeGutter(): int {
    return 1;
  }

  /**
   * {@inheritdoc}
   */
  public function chromeIndent(int $depth): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function breadcrumbLabel(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function breadcrumbSeparator(): string {
    return '>';
  }

  /**
   * {@inheritdoc}
   */
  public function legendKey(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function legendDescription(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function legendSeparator(): string {
    return '*';
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSelector(bool $selected): string {
    return $selected ? '>' : ' ';
  }

  /**
   * {@inheritdoc}
   */
  public function fieldLabel(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldHelpMarker(): string {
    return '[?]';
  }

  /**
   * {@inheritdoc}
   */
  public function fieldValue(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldValueSeparator(): string {
    return ', ';
  }

  /**
   * {@inheritdoc}
   */
  public function fieldMask(): string {
    return '*';
  }

  /**
   * {@inheritdoc}
   */
  public function fieldBadge(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldDescription(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldOption(string $text, bool $chosen, bool $focused = FALSE): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldOptionMatch(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldOptionSelector(bool $selected): string {
    return $selected ? '>' : ' ';
  }

  /**
   * {@inheritdoc}
   */
  public function fieldOptionMarker(bool $chosen, bool $exclusive = FALSE): string {
    if ($exclusive) {
      return $chosen ? '(*)' : '( )';
    }

    return $chosen ? '[x]' : '[ ]';
  }

  /**
   * {@inheritdoc}
   */
  public function fieldOptionNote(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldOptionDescription(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldOptionSeparator(): string {
    return str_repeat('-', max(1, $this->contentWidth()));
  }

  /**
   * {@inheritdoc}
   */
  public function fieldOverflowMarker(bool $above): string {
    return $above ? '^' : 'v';
  }

  /**
   * {@inheritdoc}
   */
  public function fieldConstraint(string $text): string {
    // The one line that cannot be told apart by colour or slant here, so it
    // opens with a mark instead: nothing can strip a character.
    return '> ' . $text;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldError(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldCaret(): string {
    return '|';
  }

  /**
   * {@inheritdoc}
   */
  public function fieldDraft(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldGhost(string $text): string {
    // Text nobody typed reads as text somebody did unless something sets it
    // apart, and the floor has nothing to set it apart with.
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function fieldInput(string $before, string $after, string $ghost = ''): string {
    return $this->fieldDraft($before) . $this->fieldCaret() . $this->fieldDraft($after) . $this->fieldGhost($ghost);
  }

  /**
   * {@inheritdoc}
   */
  public function fieldScale(int $current, int $min, int $max, string $caption): string {
    // Clamped onto the scale, because a point outside it would otherwise ask
    // for a run of negative length.
    $points = max(1, $max - $min + 1);
    $filled = max(1, min($points, $current - $min + 1));

    $line = str_repeat('*', $filled) . str_repeat('-', $points - $filled) . ' ' . $current . '/' . $max;

    return $caption === '' ? $line : $line . ' ' . $caption;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldLoading(): string {
    // The word alone: an ellipsis is a mark a theme trails the word with, and
    // the floor has nothing to draw one in.
    return Translator::t('Loading');
  }

  /**
   * {@inheritdoc}
   */
  public function fieldState(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldCaption(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function panelSelector(bool $selected): string {
    return $selected ? '>' : ' ';
  }

  /**
   * {@inheritdoc}
   */
  public function panelTitle(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function panelDescend(): string {
    return '>';
  }

  /**
   * {@inheritdoc}
   */
  public function panelDescription(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function panelSummary(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function panelSummarySeparator(): string {
    return '-';
  }

  /**
   * {@inheritdoc}
   */
  public function markupTitle(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function markupLine(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function markupStrong(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function markupEmphasis(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function markupCode(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function markupLink(string $text, string $url): string {
    // A target nothing can follow is still address, so it is written out beside
    // the label rather than dropped with the styling that would have hidden it.
    return $text . ' (' . $url . ')';
  }

  /**
   * {@inheritdoc}
   */
  public function markupBullet(): string {
    return '-';
  }

  /**
   * {@inheritdoc}
   */
  public function actionSelector(bool $selected): string {
    return $selected ? '>' : ' ';
  }

  /**
   * {@inheritdoc}
   */
  public function actionButton(string $label): string {
    return '[ ' . $label . ' ]';
  }

  /**
   * {@inheritdoc}
   */
  public function actionSelected(string $label): string {
    // The frame is the same one every button carries: which of them has focus
    // is carried by paint, and the floor has none to spend on it.
    return $this->actionButton($label);
  }

  /**
   * {@inheritdoc}
   */
  public function actionSeparator(): string {
    return '  ';
  }

  /**
   * {@inheritdoc}
   */
  public function actionRefusal(string $reason): string {
    return $reason;
  }

  /**
   * {@inheritdoc}
   */
  public function borderTopLeft(Border $style): string {
    return '+';
  }

  /**
   * {@inheritdoc}
   */
  public function borderTopRight(Border $style): string {
    return '+';
  }

  /**
   * {@inheritdoc}
   */
  public function borderBottomLeft(Border $style): string {
    return '+';
  }

  /**
   * {@inheritdoc}
   */
  public function borderBottomRight(Border $style): string {
    return '+';
  }

  /**
   * {@inheritdoc}
   */
  public function borderLeftJunction(Border $style): string {
    return '+';
  }

  /**
   * {@inheritdoc}
   */
  public function borderRightJunction(Border $style): string {
    return '+';
  }

  /**
   * {@inheritdoc}
   */
  public function borderHorizontal(Border $style): string {
    return match ($style) {
      Border::Double => '=',
      Border::None, Border::Line, Border::Rounded => '-',
    };
  }

  /**
   * {@inheritdoc}
   */
  public function borderVertical(Border $style): string {
    return '|';
  }

  /**
   * {@inheritdoc}
   */
  public function borderPaint(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function progressSelector(bool $selected): string {
    return $selected ? '>' : ' ';
  }

  /**
   * {@inheritdoc}
   */
  public function progressCaption(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function progressSpinner(int $frame): string {
    return self::SPINNER_ASCII[abs($frame) % count(self::SPINNER_ASCII)];
  }

  /**
   * {@inheritdoc}
   */
  public function progressTrack(int $filled, int $width): string {
    $filled = max(0, min($width, $filled));

    return '[' . str_repeat('#', $filled) . str_repeat('-', $width - $filled) . ']';
  }

  /**
   * {@inheritdoc}
   */
  public function progressCount(int $current, int $total): string {
    return $current . '/' . $total;
  }

}
