<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Tests\Traits\BuildsThemesTrait;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\EmberTheme;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Theme\ThemeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the theme's spinner and progress-bar rendering.
 */
#[CoversClass(DefaultTheme::class)]
#[CoversClass(EmberTheme::class)]
#[Group('theme')]
final class ProgressRenderTest extends TestCase {

  use BuildsThemesTrait;

  public function testSpinnerCyclesFramesInTheAccent(): void {
    $theme = $this->theme();

    $this->assertStringContainsString('⠋', $theme->renderSpinner(0, 'Scanning'));
    $this->assertStringContainsString('⠙', $theme->renderSpinner(1, 'Scanning'));
    // The frame index wraps around the frame set.
    $this->assertSame($theme->renderSpinner(0, 'x'), $theme->renderSpinner(10, 'x'));
    // The default dark accent (bold cyan) wraps the glyph, and the caption
    // rides along.
    $this->assertStringContainsString("\033[1;36m", $theme->renderSpinner(0, 'Scanning'));
    $this->assertStringContainsString('Scanning', $theme->renderSpinner(0, 'Scanning'));
  }

  public function testSpinnerAsciiFallback(): void {
    $theme = $this->theme(color: FALSE, unicode: FALSE);

    $this->assertStringContainsString('|', $theme->renderSpinner(0, ''));
    $this->assertStringContainsString('/', $theme->renderSpinner(1, ''));
    $this->assertStringNotContainsString('⠋', $theme->renderSpinner(0, ''));
  }

  public function testEmptyCaptionSpinnerIsGlyphOnly(): void {
    $this->assertSame('⠋', $this->theme(color: FALSE)->renderSpinner(0, ''));
  }

  public function testProgressBarFillsToTheRatio(): void {
    $line = $this->theme(color: FALSE)->renderProgressBar(3, 6, 'Packing', 'plums');

    $this->assertStringContainsString('Packing', $line);
    $this->assertStringContainsString('3/6', $line);
    $this->assertStringContainsString('plums', $line);
    // Half of the 24-cell bar is filled at 3/6.
    $this->assertSame(12, substr_count($line, '█'));
    $this->assertSame(12, substr_count($line, '░'));
  }

  public function testProgressBarZeroTotalRendersFull(): void {
    $line = $this->theme(color: FALSE)->renderProgressBar(0, 0, 'Packing', '');

    $this->assertStringContainsString('0/0', $line);
    $this->assertSame(24, substr_count($line, '█'));
  }

  public function testProgressBarAsciiFallback(): void {
    $line = $this->theme(color: FALSE, unicode: FALSE)->renderProgressBar(1, 4, 'Packing', '');

    $this->assertStringContainsString('#', $line);
    $this->assertStringContainsString('-', $line);
    $this->assertStringNotContainsString('█', $line);
  }

  public function testProgressBarAppliesTheAccentToTheFill(): void {
    $this->assertStringContainsString("\033[1;36m", $this->theme()->renderProgressBar(1, 2, 'Packing', ''));
  }

  public function testProgressBarClampsAnOverfilledCount(): void {
    // A direct call past the total must clamp rather than crash str_repeat().
    $line = $this->theme(color: FALSE)->renderProgressBar(5, 2, 'Packing', '');

    $this->assertStringContainsString('5/2', $line);
    $this->assertSame(24, substr_count($line, '█'));
    $this->assertStringNotContainsString('░', $line);
  }

  public function testBuiltinThemeRendersProgressInItsOwnAccent(): void {
    // Ember's highlight is bold orange (1;38;5;208); the spinner and bar
    // inherit it with no per-theme override, so the accent flows through
    // highlight().
    $theme = $this->builtin('ember', ThemeInterface::DEFAULT_WIDTH, ['color' => TRUE, 'unicode' => TRUE, 'mode' => Mode::Dark]);

    $this->assertStringContainsString("\033[1;38;5;208m", $theme->renderSpinner(0, 'x'));
    $this->assertStringContainsString("\033[1;38;5;208m", $theme->renderProgressBar(1, 2, 'x', ''));
  }

  public function testLoadingMarkNamesWhatItIsDoing(): void {
    $line = $this->theme()->fieldLoading();

    $this->assertStringContainsString('Loading…', $line);
    // Dimmed grey, so it reads as the field's aside rather than its answer.
    $this->assertStringContainsString("\033[2;90m", $line);
  }

  public function testLoadingMarkFallsBackToAscii(): void {
    $this->assertSame('Loading...', $this->theme(color: FALSE, unicode: FALSE)->fieldLoading());
    $this->assertSame('Loading…', $this->theme(color: FALSE)->fieldLoading());
  }

}
