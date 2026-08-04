<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Tests\Traits\ResetsRegistriesTrait;
use DrevOps\Tui\Theme\DefaultTheme;
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
    $this->expectExceptionMessage('must extend');

    ThemeManager::register('bogus', \stdClass::class);
  }

  public function testCreateFromClassName(): void {
    // A theme class name resolves directly, without registration.
    $this->assertInstanceOf(DefaultTheme::class, ThemeManager::create(DefaultTheme::class));
  }

  public function testCreatePassesOptions(): void {
    $theme = ThemeManager::create('default', 40, ['color' => FALSE, 'unicode' => FALSE]);

    $this->assertFalse($theme->hasColor());
    $this->assertFalse($theme->hasUnicode());

    $default = ThemeManager::create('default');
    $this->assertTrue($default->hasColor());
    $this->assertTrue($default->hasUnicode());
  }

}
