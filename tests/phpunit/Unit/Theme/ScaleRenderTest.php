<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Testing\TuiTester;
use DrevOps\Tui\Tests\Traits\BuildsThemesTrait;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Mode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the theme's rating-scale rendering.
 */
#[CoversClass(DefaultTheme::class)]
#[Group('theme')]
final class ScaleRenderTest extends TestCase {

  use BuildsThemesTrait;

  public function testScaleFillsUpToTheChosenPoint(): void {
    $line = $this->theme(color: FALSE)->fieldScale(3, 1, 5, 'Fair');

    $this->assertSame('●●●○○ 3/5 Fair', $line);
  }

  public function testScaleWithoutCaptionIsPointsAndReadout(): void {
    $this->assertSame('●●●●● 5/5', $this->theme(color: FALSE)->fieldScale(5, 1, 5, ''));
  }

  public function testScaleAsciiFallback(): void {
    $line = $this->theme(color: FALSE, unicode: FALSE)->fieldScale(2, 1, 5, '');

    $this->assertSame('**--- 2/5', $line);
    $this->assertStringNotContainsString('●', $line);
  }

  public function testScaleAppliesTheAccentToTheFilledRun(): void {
    $this->assertStringContainsString("\033[1;36m", $this->theme()->fieldScale(2, 1, 5, ''));
  }

  public function testScaleCarriesTheThemeAccent(): void {
    // Ember's highlight is bold orange (1;38;5;208); the scale inherits it with
    // no per-theme override, so the accent flows through highlight().
    $theme = $this->builtin('ember', DefaultTheme::DEFAULT_WIDTH, ['color' => TRUE, 'unicode' => TRUE, 'mode' => Mode::Dark]);

    $this->assertStringContainsString("\033[1;38;5;208m", $theme->fieldScale(2, 1, 5, ''));
  }

  public function testScaleFoldsCaptionToOneLine(): void {
    $line = $this->theme(color: FALSE)->fieldScale(1, 1, 3, "Poor\nby any measure");

    $this->assertSame('●○○ 1/3 Poor by any measure', $line);
  }

  #[DataProvider('dataProviderScaleClampsPointOffTheScale')]
  public function testScaleClampsPointOffTheScale(int $current, int $filled, int $empty): void {
    // A direct call outside the range must clamp, not crash str_repeat().
    $line = $this->theme(color: FALSE)->fieldScale($current, 1, 5, '');

    $this->assertSame($filled, substr_count($line, '●'));
    $this->assertSame($empty, substr_count($line, '○'));
  }

  /**
   * Data provider for testScaleClampsPointOffTheScale().
   *
   * @return \Iterator<string, array{int, int, int}>
   *   The point, and the filled and empty counts it renders.
   */
  public static function dataProviderScaleClampsPointOffTheScale(): \Iterator {
    yield 'above the scale' => [99, 5, 0];
    yield 'below the scale' => [-4, 1, 4];
  }

  public function testSinglePointScaleStillRendersOne(): void {
    // A collapsed range is rejected at build time, but the renderer is public.
    $this->assertSame('● 2/2', $this->theme(color: FALSE)->fieldScale(2, 2, 2, ''));
  }

  public function testScaleWiderThanTheFrameIsBoundedByIt(): void {
    // Nothing wider than the frame can be drawn, so a public call with an
    // absurd range costs a truncated line rather than the whole heap.
    $line = $this->theme(color: FALSE)->fieldScale(1, 1, PHP_INT_MAX, '');

    $points = substr_count($line, '●') + substr_count($line, '○');
    $this->assertGreaterThan(0, $points);
    $this->assertLessThanOrEqual(DefaultTheme::DEFAULT_WIDTH, $points);
  }

  public function testCollapsedRowDrawsTheScale(): void {
    // A rating row shows its scale rather than a bare number, so the grade
    // reads the same whether or not the editor is open.
    $tester = (new TuiTester($this->ratingForm()))->options(['color' => FALSE]);
    $tester->run();

    $this->assertStringContainsString('●●●●○ 4/5 Good', Ansi::strip($tester->display()));
  }

  public function testCollapsedRowDrawsTheScaleInAscii(): void {
    $tester = (new TuiTester($this->ratingForm()))->options(['color' => FALSE, 'unicode' => FALSE]);
    $tester->run();

    $display = Ansi::strip($tester->display());
    $this->assertStringContainsString('****- 4/5 Good', $display);
    $this->assertStringNotContainsString('●', $display);
  }

  /**
   * A one-field form whose rating sits on a captioned point.
   *
   * @return \DrevOps\Tui\Builder\Form
   *   The form.
   */
  protected function ratingForm(): Form {
    return Form::create('Rating')
      ->panel('main', 'Rating', function (PanelBuilder $p): void {
        $p->rating('taste', 'Taste')->default(4)->captions([4 => 'Good']);
      });
  }

}
