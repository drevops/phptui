<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

use DrevOps\Tui\Block\Element\ActionsElementsInterface;
use DrevOps\Tui\Block\Element\BreadcrumbElementsInterface;
use DrevOps\Tui\Block\Element\ChromeElementsInterface;
use DrevOps\Tui\Block\Element\FieldElementsInterface;
use DrevOps\Tui\Block\Element\LegendElementsInterface;
use DrevOps\Tui\Block\Element\MarkupElementsInterface;
use DrevOps\Tui\Block\Element\PanelElementsInterface;
use DrevOps\Tui\Block\Element\ProgressElementsInterface;

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
 * {@see \DrevOps\Tui\Theme\Capability\ColorSchemeCapableInterface} or
 * {@see \DrevOps\Tui\Theme\Capability\UnicodeCapableInterface} for the
 * facilities those elements need.
 *
 * @package DrevOps\Tui\Theme
 */
abstract class AbstractTheme implements ActionsElementsInterface, BreadcrumbElementsInterface, ChromeElementsInterface, FieldElementsInterface, LegendElementsInterface, MarkupElementsInterface, PanelElementsInterface, ProgressElementsInterface {

  /**
   * The spinner animation frames that need no glyph outside ASCII.
   */
  protected const array SPINNER_ASCII = ['|', '/', '-', '\\'];

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
  public function fieldEntry(string $text, bool $chosen): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldEntrySelector(bool $selected): string {
    return $selected ? '>' : ' ';
  }

  /**
   * {@inheritdoc}
   */
  public function fieldEntryMarker(bool $chosen): string {
    return $chosen ? '[x]' : '[ ]';
  }

  /**
   * {@inheritdoc}
   */
  public function fieldEntryNote(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldEntryDescription(string $text): string {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldConstraint(string $text): string {
    return $text;
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
  public function panelTitle(string $text): string {
    return $text;
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
