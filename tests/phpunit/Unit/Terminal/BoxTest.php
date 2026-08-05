<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Terminal;

use DrevOps\Tui\Terminal\Ansi;
use DrevOps\Tui\Terminal\Box;
use DrevOps\Tui\Theme\Border;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the pure box-drawing geometry helper.
 */
#[CoversClass(Box::class)]
#[Group('terminal')]
final class BoxTest extends TestCase {

  #[DataProvider('dataProviderChars')]
  public function testChars(Border $style, bool $unicode, string $key, string $expected): void {
    $this->assertSame($expected, Box::chars($style, $unicode)[$key]);
  }

  public static function dataProviderChars(): \Iterator {
    yield 'line top-left' => [Border::Line, TRUE, 'tl', '┌'];
    yield 'line horizontal' => [Border::Line, TRUE, 'h', '─'];
    yield 'line vertical' => [Border::Line, TRUE, 'v', '│'];
    yield 'line junction' => [Border::Line, TRUE, 'ml', '├'];
    yield 'rounded top-left' => [Border::Rounded, TRUE, 'tl', '╭'];
    yield 'rounded bottom-right' => [Border::Rounded, TRUE, 'br', '╯'];
    yield 'double top-left' => [Border::Double, TRUE, 'tl', '╔'];
    yield 'double horizontal' => [Border::Double, TRUE, 'h', '═'];
    yield 'ascii line corner' => [Border::Line, FALSE, 'tl', '+'];
    yield 'ascii line fill' => [Border::Line, FALSE, 'h', '-'];
    yield 'ascii line vertical' => [Border::Line, FALSE, 'v', '|'];
    yield 'ascii double fill' => [Border::Double, FALSE, 'h', '='];
    yield 'line top tee' => [Border::Line, TRUE, 'tt', '┬'];
    yield 'line bottom tee' => [Border::Line, TRUE, 'bt', '┴'];
    yield 'line cross' => [Border::Line, TRUE, 'cx', '┼'];
    yield 'rounded cross' => [Border::Rounded, TRUE, 'cx', '┼'];
    yield 'double top tee' => [Border::Double, TRUE, 'tt', '╦'];
    yield 'double bottom tee' => [Border::Double, TRUE, 'bt', '╩'];
    yield 'double cross' => [Border::Double, TRUE, 'cx', '╬'];
    yield 'ascii cross' => [Border::Line, FALSE, 'cx', '+'];
    yield 'ascii double cross' => [Border::Double, FALSE, 'cx', '+'];
  }

  #[DataProvider('dataProviderRule')]
  public function testRule(string $left, string $right, string $fill, int $width, string $expected): void {
    $this->assertSame($expected, Box::rule($left, $right, $fill, $width));
  }

  public static function dataProviderRule(): \Iterator {
    yield 'even span' => ['+', '+', '-', 6, '+----+'];
    yield 'unicode span' => ['┌', '┐', '─', 4, '┌──┐'];
    // Narrower than the two corners still yields both corners, with no fill.
    yield 'degenerate' => ['+', '+', '-', 1, '++'];
  }

  #[DataProvider('dataProviderFit')]
  public function testFit(string $content, int $width, string $expected): void {
    $this->assertSame($expected, Box::fit($content, $width));
  }

  public static function dataProviderFit(): \Iterator {
    yield 'pads when short' => ['ab', 5, 'ab   '];
    yield 'exact width' => ['abc', 3, 'abc'];
    yield 'clips when long' => ['abcdef', 3, 'abc'];
    yield 'empty pads full' => ['', 3, '   '];
  }

  public function testFitClipDropsAnsiStyling(): void {
    // A too-wide styled string is clipped to plain text at the visible width.
    $this->assertSame('abc', Box::fit(Ansi::style('abcdef', '36'), 3));
  }

  public function testFitKeepsAnsiWhenItAlreadyFits(): void {
    // A short styled string keeps its styling and is padded to the width.
    $styled = Ansi::style('ab', '36');

    $this->assertSame($styled . '   ', Box::fit($styled, 5));
  }

}
