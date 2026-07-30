<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Theme\ColorCapableInterface;
use DrevOps\Tui\Theme\SchemeCapableInterface;
use DrevOps\Tui\Theme\UnicodeCapableInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests what a theme declares it supports, and what each declaration grants.
 */
#[CoversClass(DefaultTheme::class)]
#[Group('theme')]
final class SupportTest extends TestCase {

  public function testTerminalsColourSchemeIsReadableOnlyWhereItIsDeclared(): void {
    $dark = new DefaultTheme(80, ['mode' => Mode::Dark]);
    $light = new DefaultTheme(80, ['mode' => Mode::Light]);

    $this->assertInstanceOf(SchemeCapableInterface::class, $dark);
    $this->assertTrue($dark->isDark());
    $this->assertFalse($light->isDark());
  }

  public function testColourIsDeclaredAndCanBeTurnedOff(): void {
    $this->assertInstanceOf(ColorCapableInterface::class, new DefaultTheme());
    $this->assertTrue((new DefaultTheme(80, ['color' => TRUE]))->hasColor());
    $this->assertFalse((new DefaultTheme(80, ['color' => FALSE]))->hasColor());
  }

  public function testUnicodeIsDeclaredAndCanBeTurnedOff(): void {
    $this->assertInstanceOf(UnicodeCapableInterface::class, new DefaultTheme());
    $this->assertTrue((new DefaultTheme(80, ['unicode' => TRUE]))->hasUnicode());
    $this->assertFalse((new DefaultTheme(80, ['unicode' => FALSE]))->hasUnicode());
  }

  public function testAnElementPicksItsGlyphFromWhatTheThemeSupports(): void {
    $unicode = new DefaultTheme(80, ['color' => FALSE, 'unicode' => TRUE]);
    $ascii = new DefaultTheme(80, ['color' => FALSE, 'unicode' => FALSE]);

    $this->assertSame('›', $unicode->breadcrumbSeparator());
    $this->assertSame('>', $ascii->breadcrumbSeparator());
  }

  public function testWithoutColourAnElementHandsBackWhatItWasGiven(): void {
    // The floor: a theme that paints nothing still draws, which is why a form
    // renders in a terminal that supports nothing.
    $plain = new DefaultTheme(80, ['color' => FALSE]);

    $this->assertSame('Orchard', $plain->breadcrumbLabel('Orchard'));
    $this->assertSame('4/10', $plain->progressCount(4, 10));
  }

  public function testThemeSpinsThroughItsOwnFramesAndWrapsAtTheEnd(): void {
    $unicode = new DefaultTheme(80, ['color' => FALSE, 'unicode' => TRUE]);
    $ascii = new DefaultTheme(80, ['color' => FALSE, 'unicode' => FALSE]);

    // Ten frames against four: the element takes a frame number rather than a
    // glyph precisely so the theme owns how many there are.
    $this->assertSame($unicode->progressSpinner(0), $unicode->progressSpinner(10));
    $this->assertSame($ascii->progressSpinner(0), $ascii->progressSpinner(4));
    $this->assertNotSame($unicode->progressSpinner(4), $ascii->progressSpinner(4));
  }

  public function testBarFillsInProportionAndNeverPastItsWidth(): void {
    $theme = new DefaultTheme(80, ['color' => FALSE, 'unicode' => TRUE]);

    $this->assertSame('[████░░░░░░]', $theme->progressTrack(4, 10));
    $this->assertSame('[░░░░░░░░░░]', $theme->progressTrack(0, 10));
    $this->assertSame('[██████████]', $theme->progressTrack(99, 10));
  }

}
