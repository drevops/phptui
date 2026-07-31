<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Tests\Fixtures\Theme\CapableTheme;
use DrevOps\Tui\Tests\Fixtures\Theme\FloorTheme;
use DrevOps\Tui\Theme\Capability\ColorSchemeCapableInterface;
use DrevOps\Tui\Theme\Capability\ColorSchemeCapableTrait;
use DrevOps\Tui\Theme\Capability\UnicodeCapableInterface;
use DrevOps\Tui\Theme\Capability\UnicodeCapableTrait;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Mode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests what a theme declares it supports, and what each declaration grants.
 */
#[CoversClass(DefaultTheme::class)]
#[CoversTrait(ColorSchemeCapableTrait::class)]
#[CoversTrait(UnicodeCapableTrait::class)]
#[Group('theme')]
final class SupportTest extends TestCase {

  public function testColourAndItsSchemeAreOneDeclaration(): void {
    // The two questions are never asked apart - a colour is chosen against a
    // background - so one declaration grants both.
    $dark = new DefaultTheme(80, ['mode' => Mode::Dark]);
    $light = new DefaultTheme(80, ['mode' => Mode::Light]);

    $this->assertInstanceOf(ColorSchemeCapableInterface::class, $dark);
    $this->assertTrue($dark->isDark());
    $this->assertFalse($light->isDark());
    $this->assertTrue((new DefaultTheme(80, ['color' => TRUE]))->hasColor());
    $this->assertFalse((new DefaultTheme(80, ['color' => FALSE]))->hasColor());
  }

  public function testUnicodeIsDeclaredAndCanBeTurnedOff(): void {
    $this->assertInstanceOf(UnicodeCapableInterface::class, new DefaultTheme());
    $this->assertTrue((new DefaultTheme(80, ['unicode' => TRUE]))->hasUnicode());
    $this->assertFalse((new DefaultTheme(80, ['unicode' => FALSE]))->hasUnicode());
  }

  public function testThemeDeclaringNothingSupportsNothing(): void {
    $declared = class_implements(FloorTheme::class);

    $this->assertNotFalse($declared);
    $this->assertArrayNotHasKey(ColorSchemeCapableInterface::class, $declared);
    $this->assertArrayNotHasKey(UnicodeCapableInterface::class, $declared);
  }

  public function testDeclaredCapabilityPaintsAgainstTheBackgroundItReads(): void {
    $dark = new CapableTheme(TRUE, TRUE, TRUE);
    $light = new CapableTheme(TRUE, TRUE, FALSE);

    $this->assertSame("\033[36mOrchard\033[0m", $dark->breadcrumbLabel('Orchard'));
    $this->assertSame("\033[34mOrchard\033[0m", $light->breadcrumbLabel('Orchard'));
  }

  public function testTurningColourOffHandsBackWhatTheElementWasGiven(): void {
    $this->assertSame('Orchard', (new CapableTheme(FALSE))->breadcrumbLabel('Orchard'));
  }

  public function testSelectingAnItemAddsWeightRatherThanReplacingItsColour(): void {
    $theme = new CapableTheme();

    $this->assertSame("\033[32mApple\033[0m", $theme->fieldEntry('Apple', FALSE));
    $this->assertSame("\033[1;32mApple\033[0m", $theme->fieldEntry('Apple', TRUE));
  }

  public function testAnElementPicksItsGlyphFromWhatTheThemeSupports(): void {
    $unicode = new DefaultTheme(80, ['color' => FALSE, 'unicode' => TRUE]);
    $ascii = new DefaultTheme(80, ['color' => FALSE, 'unicode' => FALSE]);

    $this->assertSame('›', $unicode->breadcrumbSeparator());
    $this->assertSame('>', $ascii->breadcrumbSeparator());
    $this->assertSame('›', (new CapableTheme(FALSE, TRUE))->breadcrumbSeparator());
    $this->assertSame('>', (new CapableTheme(FALSE, FALSE))->breadcrumbSeparator());
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
