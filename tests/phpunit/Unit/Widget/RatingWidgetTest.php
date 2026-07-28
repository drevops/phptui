<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Widget;

use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\WidgetRunner;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\AbstractWidget;
use DrevOps\Tui\Widget\RatingWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the rating widget.
 */
#[CoversClass(RatingWidget::class)]
#[CoversClass(AbstractWidget::class)]
#[Group('widget')]
final class RatingWidgetTest extends TestCase {

  public function testDefaultAndRender(): void {
    $widget = new RatingWidget(3);

    $this->assertSame(3, $widget->value());
    $this->assertStringContainsString('●●●○○ 3/5', Ansi::strip($widget->view(new DefaultTheme())));
  }

  public function testStepsAlongTheScale(): void {
    $widget = new RatingWidget(3);

    $widget->handle(Key::named(KeyName::Right));
    $this->assertSame(4, $widget->value());

    $widget->handle(Key::named(KeyName::Left));
    $this->assertSame(3, $widget->value());
  }

  #[DataProvider('dataProviderStepKeys')]
  public function testStepKeys(Key $key, int $expected): void {
    $widget = new RatingWidget(3);

    $widget->handle($key);

    $this->assertSame($expected, $widget->value());
  }

  /**
   * Data provider for testStepKeys().
   *
   * @return \Iterator<string, array{\DrevOps\Tui\Input\Key, int}>
   *   Each stepping key and the point it moves to from three.
   */
  public static function dataProviderStepKeys(): \Iterator {
    yield 'right' => [Key::named(KeyName::Right), 4];
    yield 'up' => [Key::named(KeyName::Up), 4];
    yield 'left' => [Key::named(KeyName::Left), 2];
    yield 'down' => [Key::named(KeyName::Down), 2];
  }

  #[DataProvider('dataProviderClampsAtEnds')]
  public function testClampsAtEnds(int $start, int $delta, int $expected): void {
    $widget = new RatingWidget($start);

    $widget->stepBy($delta);

    $this->assertSame($expected, $widget->value());
  }

  /**
   * Data provider for testClampsAtEnds().
   *
   * @return \Iterator<string, array{int, int, int}>
   *   The starting point, the step and the point it settles on.
   */
  public static function dataProviderClampsAtEnds(): \Iterator {
    yield 'stops at the top' => [5, 1, 5];
    yield 'stops at the bottom' => [1, -1, 1];
    yield 'a long step lands on the end' => [3, 99, 5];
    yield 'a long backward step lands on the end' => [3, -99, 1];
  }

  #[DataProvider('dataProviderSeedIsClamped')]
  public function testSeedIsClamped(int $seed, int $expected): void {
    $this->assertSame($expected, (new RatingWidget($seed))->value());
  }

  /**
   * Data provider for testSeedIsClamped().
   *
   * @return \Iterator<string, array{int, int}>
   *   The seed value and the point it is moved onto.
   */
  public static function dataProviderSeedIsClamped(): \Iterator {
    yield 'below the scale' => [-4, 1];
    yield 'above the scale' => [99, 5];
    yield 'on the scale' => [2, 2];
  }

  public function testDigitJumpsToPoint(): void {
    $widget = new RatingWidget(1);

    $widget->handle(Key::char('4'));
    $this->assertSame(4, $widget->value());

    // A digit the scale does not reach leaves the choice alone.
    $widget->handle(Key::char('9'));
    $this->assertSame(4, $widget->value());

    // Nor does a non-digit character move it.
    $widget->handle(Key::char('x'));
    $this->assertSame(4, $widget->value());
  }

  public function testDigitBelowScaleIsIgnored(): void {
    $widget = new RatingWidget(5, 3, 8);

    $widget->handle(Key::char('1'));

    $this->assertSame(5, $widget->value());
  }

  public function testCaptionOfTheChosenPoint(): void {
    $widget = new RatingWidget(1, 1, 5, [1 => 'Poor', 5 => 'Excellent']);
    $theme = new DefaultTheme();

    $this->assertStringContainsString('●○○○○ 1/5 Poor', Ansi::strip($widget->view($theme)));

    // An uncaptioned point renders the scale alone.
    $widget->stepBy(1);
    $this->assertStringContainsString('●●○○○ 2/5', Ansi::strip($widget->view($theme)));
    $this->assertStringNotContainsString('Poor', Ansi::strip($widget->view($theme)));
  }

  public function testCustomScale(): void {
    $widget = new RatingWidget(3, 0, 10);

    $this->assertStringContainsString('●●●●○○○○○○○ 3/10', Ansi::strip($widget->view(new DefaultTheme())));
  }

  public function testAsciiRendering(): void {
    $widget = new RatingWidget(2);
    $theme = new DefaultTheme(76, ['unicode' => FALSE, 'color' => FALSE]);

    $this->assertStringContainsString('**--- 2/5', $widget->view($theme));
  }

  public function testCaptionFoldsToOneLine(): void {
    $widget = new RatingWidget(1, 1, 5, [1 => "Poor\nby any measure"]);

    $this->assertStringContainsString('1/5 Poor by any measure', Ansi::strip($widget->view(new DefaultTheme())));
  }

  public function testAccept(): void {
    $widget = new RatingWidget(3);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Right), Key::named(KeyName::Enter)));

    $this->assertSame(4, $value);
    $this->assertTrue($widget->isComplete());
  }

  public function testCancel(): void {
    $widget = new RatingWidget(3);

    $widget->handle(Key::named(KeyName::Escape));

    $this->assertTrue($widget->isCancelled());
  }

  public function testHints(): void {
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new RatingWidget(1))->hints());

    $this->assertSame(['adjust', 'accept', 'cancel'], $labels);
  }

}
