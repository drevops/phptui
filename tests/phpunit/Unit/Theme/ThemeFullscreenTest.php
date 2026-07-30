<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
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

  public function testMeasureContentWidthFindsTheWidestRow(): void {
    $form = Form::create('Produce stand')
      ->panel('stand', 'Stand', function (PanelBuilder $p): void {
        $p->text('window', 'Preferred delivery window')->default('Morning');
      })
      ->build();

    $answers = new Answers(['window' => 'Morning'], []);

    // The widest row is the field: marker gutter (4) + label (25) + value (7).
    $this->assertSame(36, (new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None]))->measureContentWidth($form, $answers));

    // A border adds its two columns and gutters on each side.
    $this->assertSame(40, (new DefaultTheme(40, ['color' => FALSE, 'border' => Border::Line]))->measureContentWidth($form, $answers));

    // A provenance badge widens the row by its padded label.
    $edited = new Answers(['window' => 'Morning'], ['window' => Provenance::Edited]);
    $this->assertSame(45, (new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None]))->measureContentWidth($form, $edited));
  }

  public function testMeasureContentWidthCoversDescriptionsSummariesAndButtons(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $p): void {
        $p->text('a', 'A')->default('a value under the summary clip')->description('a rather long field description row');
      })
      ->build();

    $answers = new Answers(['a' => 'a value under the summary clip'], []);

    // The field description row (4 + 35) beats the field row (4 + 1 + 30 + 2
    // spacing = 37) and the hub summary row (4 + 30).
    $this->assertSame(39, (new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None]))->measureContentWidth($form, $answers));

    // Compact spacing drops descriptions and summaries: the field row itself
    // (4 + 1 + 30) loses to the button bar (24)... and wins at 35.
    $this->assertSame(35, (new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Compact]))->measureContentWidth($form, $answers));
  }

  public function testMeasureContentWidthCoversTheHelpMarkerButNotTheHelp(): void {
    $build = static fn(string $help) => Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $p) use ($help): void {
        $p->text('a', 'A rather long label that outruns the button bar')->help($help);
      })
      ->build();

    $theme = new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None]);
    $bare = $theme->measureContentWidth($build(''), new Answers());
    $short = $theme->measureContentWidth($build('Help.'), new Answers());

    // The marker announcing help costs the row its own width and a space, so a
    // measured frame has room for it.
    $this->assertGreaterThan($bare, $short);
    $this->assertLessThanOrEqual($bare + 4, $short);

    // That is all help ever costs the row, however far the help itself runs: it
    // opens on a page of its own and never draws in the panel.
    $this->assertSame($short, $theme->measureContentWidth($build(str_repeat('a rather long paragraph of help. ', 8)), new Answers()));
  }

  public function testMeasureContentWidthFloorsAtTheButtonBar(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $p): void {
        $p->text('a', 'A');
      })
      ->build();

    // Every row is narrower than the button bar: it sets the floor.
    $this->assertSame(24, (new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Compact]))->measureContentWidth($form, new Answers()));
  }

  public function testMeasureContentWidthSkipsHiddenButtons(): void {
    $form = Form::create('T')
      ->buttons(FALSE)
      ->panel('p', 'P', function (PanelBuilder $p): void {
        $p->text('a', 'A');
      })
      ->build();

    // With the buttons hidden their bar never renders, so it never measures:
    // the widest row is the one-letter field row itself.
    $this->assertSame(5, (new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Compact]))->measureContentWidth($form, new Answers()));
  }

}
