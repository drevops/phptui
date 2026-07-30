<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Field;

use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\FieldRunner;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Field\AbstractField;
use DrevOps\Tui\Field\Rating;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the rating field.
 */
#[CoversClass(Rating::class)]
#[CoversClass(AbstractField::class)]
#[Group('field')]
final class RatingTest extends TestCase {

  public function testDefaultAndRender(): void {
    $field = new Rating(3);

    $this->assertSame(3, $field->value());
    $this->assertStringContainsString('●●●○○ 3/5', Ansi::strip($field->view(new DefaultTheme())));
  }

  public function testStepsAlongTheScale(): void {
    $field = new Rating(3);

    $field->handle(Key::named(KeyName::Right));
    $this->assertSame(4, $field->value());

    $field->handle(Key::named(KeyName::Left));
    $this->assertSame(3, $field->value());
  }

  #[DataProvider('dataProviderStepKeys')]
  public function testStepKeys(Key $key, int $expected): void {
    $field = new Rating(3);

    $field->handle($key);

    $this->assertSame($expected, $field->value());
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
    $field = new Rating($start);

    $field->stepBy($delta);

    $this->assertSame($expected, $field->value());
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
    $this->assertSame($expected, (new Rating($seed))->value());
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
    $field = new Rating(1);

    $field->handle(Key::char('4'));
    $this->assertSame(4, $field->value());

    // A digit the scale does not reach leaves the choice alone.
    $field->handle(Key::char('9'));
    $this->assertSame(4, $field->value());

    // Nor does a non-digit character move it.
    $field->handle(Key::char('x'));
    $this->assertSame(4, $field->value());
  }

  public function testDigitBelowScaleIsIgnored(): void {
    $field = new Rating(5, 3, 8);

    $field->handle(Key::char('1'));

    $this->assertSame(5, $field->value());
  }

  public function testCaptionOfTheChosenPoint(): void {
    $field = new Rating(1, 1, 5, [1 => 'Poor', 5 => 'Excellent']);
    $theme = new DefaultTheme();

    $this->assertStringContainsString('●○○○○ 1/5 Poor', Ansi::strip($field->view($theme)));

    // An uncaptioned point renders the scale alone.
    $field->stepBy(1);
    $this->assertStringContainsString('●●○○○ 2/5', Ansi::strip($field->view($theme)));
    $this->assertStringNotContainsString('Poor', Ansi::strip($field->view($theme)));
  }

  public function testCustomScale(): void {
    $field = new Rating(3, 0, 10);

    $this->assertStringContainsString('●●●●○○○○○○○ 3/10', Ansi::strip($field->view(new DefaultTheme())));
  }

  public function testAsciiRendering(): void {
    $field = new Rating(2);
    $theme = new DefaultTheme(76, ['unicode' => FALSE, 'color' => FALSE]);

    $this->assertStringContainsString('**--- 2/5', $field->view($theme));
  }

  public function testCaptionFoldsToOneLine(): void {
    $field = new Rating(1, 1, 5, [1 => "Poor\nby any measure"]);

    $this->assertStringContainsString('1/5 Poor by any measure', Ansi::strip($field->view(new DefaultTheme())));
  }

  public function testAccept(): void {
    $field = new Rating(3);

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Right), Key::named(KeyName::Enter)));

    $this->assertSame(4, $value);
    $this->assertTrue($field->isComplete());
  }

  public function testCancel(): void {
    $field = new Rating(3);

    $field->handle(Key::named(KeyName::Escape));

    $this->assertTrue($field->isCancelled());
  }

  public function testHints(): void {
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new Rating(1))->hints());

    $this->assertSame(['adjust', 'accept', 'cancel'], $labels);
  }

}
