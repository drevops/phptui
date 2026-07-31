<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\Viewport;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Spacing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the fullscreen frame stretch, block alignment and content measuring.
 */
#[CoversClass(DefaultTheme::class)]
#[Group('theme')]
final class ThemeFullscreenTest extends TestCase {

  #[DataProvider('dataProviderChromeHeight')]
  public function testChromeHeight(array $options, bool $has_footer, int $expected): void {
    $this->assertSame($expected, (new DefaultTheme(40, $options))->chromeHeight($has_footer));
  }

  public static function dataProviderChromeHeight(): \Iterator {
    yield 'borderless normal' => [['border' => Border::None, 'spacing' => Spacing::Normal], TRUE, 3];
    yield 'borderless normal no footer' => [['border' => Border::None, 'spacing' => Spacing::Normal], FALSE, 3];
    yield 'borderless compact' => [['border' => Border::None, 'spacing' => Spacing::Compact], TRUE, 2];
    yield 'boxed no footer' => [['border' => Border::Line, 'spacing' => Spacing::Normal], FALSE, 5];
    yield 'boxed with footer' => [['border' => Border::Line, 'spacing' => Spacing::Normal], TRUE, 6];
    yield 'boxed padded with footer' => [['border' => Border::Line, 'spacing' => Spacing::Padded], TRUE, 8];
  }

  #[DataProvider('dataProviderFullscreenBorderlessAlignsTheBlock')]
  public function testFullscreenBorderlessAlignsTheBlock(string $halign, string $valign, array $expected): void {
    $theme = new DefaultTheme(10, ['color' => FALSE, 'fullscreen' => TRUE, 'halign' => $halign, 'valign' => $valign, 'border' => Border::None, 'spacing' => Spacing::Normal]);

    $frame = $theme->renderFrame(['H'], ['ab'], ['F'], new Viewport(0, FALSE, FALSE), 4);

    $this->assertSame($expected, explode("\n", Ansi::strip($frame)));
  }

  public static function dataProviderFullscreenBorderlessAlignsTheBlock(): \Iterator {
    // The body window stretches to its budget (four rows plus the two
    // indicator rows), the block anchored per alignment; the header, the
    // footer gap and the footer wrap it unchanged.
    yield 'top left' => ['left', 'top', ['H', 'ab', '', '', '', '', '', '', 'F']];
    yield 'middle center' => ['center', 'middle', ['H', '', '', '    ab', '', '', '', '', 'F']];
    yield 'bottom right' => ['right', 'bottom', ['H', '', '', '', '', '', '        ab', '', 'F']];
  }

  public function testFullscreenFrameHeightMatchesTheBudget(): void {
    $theme = new DefaultTheme(10, ['color' => FALSE, 'fullscreen' => TRUE]);

    $frame = $theme->renderFrame(['H'], ['ab'], ['F'], new Viewport(0, FALSE, FALSE), 4);

    // Header + footer + chrome + viewport height = the exact frame height.
    $this->assertCount(1 + 1 + $theme->chromeHeight(TRUE) + 4, explode("\n", $frame));
  }

  public function testNonFullscreenFrameHugsItsContent(): void {
    $theme = new DefaultTheme(10, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]);

    $frame = $theme->renderFrame(['H'], ['ab'], ['F'], new Viewport(0, FALSE, FALSE), 4);

    $this->assertSame(['H', 'ab', '', 'F'], explode("\n", Ansi::strip($frame)));
  }

  public function testFullscreenBoxedStretchesAndCentersInsideTheBorder(): void {
    $theme = new DefaultTheme(12, ['color' => FALSE, 'fullscreen' => TRUE, 'halign' => 'center', 'border' => Border::Line]);

    $frame = $theme->renderFrame(['H'], ['ab'], ['F'], new Viewport(0, FALSE, FALSE), 3);
    $lines = explode("\n", $frame);

    // 4 rules + header + footer + the stretched window (3 + 2 indicators).
    $this->assertCount(1 + 1 + $theme->chromeHeight(TRUE) + 3, $lines);

    foreach ($lines as $line) {
      $this->assertSame(12, Ansi::width($line));
    }

    // Inner width is 8, so a 2-column block centered in it indents 3 columns
    // past the border gutter.
    $this->assertStringContainsString('│    ab    │', $frame);
  }

  public function testFullscreenEditorStretchesToTheGivenRows(): void {
    $theme = new DefaultTheme(20, ['color' => FALSE, 'fullscreen' => TRUE, 'border' => Border::Line]);

    // No hints draw no footer: the box is the title, rules and the stretched
    // body window, filling the rows exactly.
    $this->assertCount(15, explode("\n", $theme->renderEditor('Name', 'val', [], NULL, 15)));
  }

  public function testEditorIgnoresRowsOutsideFullscreen(): void {
    $theme = new DefaultTheme(20, ['color' => FALSE, 'border' => Border::Line, 'spacing' => Spacing::Normal]);

    // 3 rules + the title + a one-line body window: content-sized.
    $this->assertCount(5, explode("\n", $theme->renderEditor('Name', 'val', [], NULL, 15)));
  }

  public function testFullscreenBorderlessEditorStretchesToTheGivenRows(): void {
    $theme = new DefaultTheme(20, ['color' => FALSE, 'fullscreen' => TRUE, 'border' => Border::None]);

    $lines = explode("\n", $theme->renderEditor('Name', 'val', [], NULL, 15));

    // The borderless editor fills the rows too, keeping its label-over-rule
    // header at the top of the stretched frame.
    $this->assertCount(15, $lines);
    $this->assertStringContainsString('Name', Ansi::strip($lines[0]));
    $this->assertStringContainsString('val', Ansi::strip(implode("\n", $lines)));
  }

}
