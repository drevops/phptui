<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Tests\Fixtures\Theme\FloorOptionTheme;
use DrevOps\Tui\Tests\Fixtures\Theme\FloorTheme;
use DrevOps\Tui\Tests\Fixtures\Theme\OceanTheme;
use DrevOps\Tui\Tests\Traits\ResetsRegistriesTrait;
use DrevOps\Tui\Theme\AbstractTheme;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\MidnightTheme;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Theme\ThemeManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the theme registry and factory.
 */
#[CoversClass(ThemeManager::class)]
#[Group('theme')]
final class ThemeManagerTest extends TestCase {

  use ResetsRegistriesTrait;

  protected function tearDown(): void {
    $this->restoreRegistries();
    parent::tearDown();
  }

  #[DataProvider('dataProviderCreate')]
  public function testCreate(string $name, array $options, \Closure $styled, string $code): void {
    $theme = ThemeManager::create($name, 76, $options);

    $this->assertInstanceOf(DefaultTheme::class, $theme);
    $this->assertSame(Ansi::style('X', $code), $styled($theme));
  }

  public static function dataProviderCreate(): \Iterator {
    yield 'default is dark' => ['default', [], static fn(DefaultTheme $t): string => $t->markupTitle('X'), '1;36'];
    yield 'empty is dark' => ['', [], static fn(DefaultTheme $t): string => $t->markupTitle('X'), '1;36'];
    // The dark/light palette is a mode option, not a separate theme.
    yield 'light mode' => ['default', ['mode' => Mode::Light], static fn(DefaultTheme $t): string => $t->markupTitle('X'), '1;34'];
    yield 'light mode value' => ['default', ['mode' => Mode::Light], static fn(DefaultTheme $t): string => $t->fieldValue('X'), '32'];
    // Each curated built-in theme resolves by name to its own accent.
    yield 'midnight resolves' => ['midnight', [], static fn(DefaultTheme $t): string => $t->markupTitle('X'), '1;38;5;141'];
    yield 'frost resolves' => ['frost', [], static fn(DefaultTheme $t): string => $t->markupTitle('X'), '1;38;5;117'];
    yield 'ember resolves' => ['ember', [], static fn(DefaultTheme $t): string => $t->markupTitle('X'), '1;38;5;208'];
    yield 'mono resolves' => ['mono', [], static fn(DefaultTheme $t): string => $t->markupTitle('X'), '1;97'];
    yield 'dos resolves' => ['dos', [], static fn(DefaultTheme $t): string => $t->markupTitle('X'), '1;97'];
  }

  public function testCreateUnknownThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown theme "bogus"');

    ThemeManager::create('bogus');
  }

  public function testCreateNonThemeClassThrows(): void {
    $this->expectException(\InvalidArgumentException::class);

    ThemeManager::create(\stdClass::class);
  }

  public function testRegister(): void {
    $this->snapshotRegistry(ThemeManager::class);
    ThemeManager::register('registered', DefaultTheme::class);

    $this->assertInstanceOf(DefaultTheme::class, ThemeManager::create('registered'));
  }

  public function testRegisterNonThemeClassThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('must implement');

    ThemeManager::register('bogus', \stdClass::class);
  }

  public function testRegisterUninstantiableThemeClassThrows(): void {
    // The floor is a theme by type and a class nothing can build, so it is
    // named here rather than fatalling on the first create().
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Theme class "' . AbstractTheme::class . '" cannot be instantiated.');

    ThemeManager::register('floor', AbstractTheme::class);
  }

  public function testCreateUninstantiableThemeClassThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Theme class "' . AbstractTheme::class . '" cannot be instantiated.');

    ThemeManager::create(AbstractTheme::class);
  }

  public function testCreateFromClassName(): void {
    // A theme class name resolves directly, without registration.
    $this->assertInstanceOf(DefaultTheme::class, ThemeManager::create(DefaultTheme::class));
  }

  #[DataProvider('dataProviderCreateResolves')]
  public function testCreateResolves(string $name, string $expected): void {
    $this->snapshotRegistry(ThemeManager::class);
    ThemeManager::register('orchard', FloorTheme::class);

    $built = ThemeManager::create($name, 40);

    $this->assertSame($built::class, $expected);
  }

  public static function dataProviderCreateResolves(): \Iterator {
    // Every way of naming a theme resolves, and what the theme is built on is
    // none of the factory's business.
    yield 'shipped name' => ['midnight', MidnightTheme::class];
    yield 'empty name' => ['', DefaultTheme::class];
    yield 'registered name' => ['orchard', FloorTheme::class];
    yield 'class name' => [OceanTheme::class, OceanTheme::class];
    yield 'class name on the floor' => [FloorTheme::class, FloorTheme::class];
  }

  public function testCreateBuildsThemesOnTheFloor(): void {
    $theme = ThemeManager::create(FloorTheme::class, 40);

    // The width is the one thing no theme can work out for itself, so the floor
    // is told it exactly as the theme that ships is.
    $this->assertSame(40, $theme->contentWidth());
  }

  public function testCreateHandsTheFloorTheStatedOptions(): void {
    $theme = ThemeManager::create(FloorOptionTheme::class, 40, ['lead' => '# ']);

    $this->assertInstanceOf(FloorOptionTheme::class, $theme);
    $this->assertSame('# Basket', $theme->fieldLabel('Basket'));
  }

  public function testCreatePassesOptions(): void {
    $theme = ThemeManager::create('default', 40, ['color' => FALSE, 'unicode' => FALSE]);
    $default = ThemeManager::create('default');

    $this->assertInstanceOf(DefaultTheme::class, $theme);
    $this->assertInstanceOf(DefaultTheme::class, $default);
    $this->assertFalse($theme->hasColor());
    $this->assertFalse($theme->hasUnicode());
    $this->assertTrue($default->hasColor());
    $this->assertTrue($default->hasUnicode());
  }

}
