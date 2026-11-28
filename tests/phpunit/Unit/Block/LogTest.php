<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Block;

use DrevOps\PhpTui\Block\Log;
use DrevOps\PhpTui\FormException;
use DrevOps\PhpTui\Tests\Fixtures\Theme\BlankTheme;
use DrevOps\PhpTui\Theme\DefaultTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the log block: lines written over time, capped at a line limit.
 */
#[CoversClass(Log::class)]
#[Group('block')]
final class LogTest extends TestCase {

  public function testLogHandsBackItsIdAndDefaultLimit(): void {
    $log = new Log('bench');

    $this->assertSame('bench', $log->id());
    $this->assertSame(Log::LIMIT, $log->lineLimit());
    $this->assertSame([], $log->lines());
  }

  #[DataProvider('dataProviderLimitBelowOneThrows')]
  public function testLimitBelowOneThrows(int $limit): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage(sprintf('Log "bench" declares a limit of %d lines; a log holds at least 1 line.', $limit));

    new Log('bench', $limit);
  }

  public static function dataProviderLimitBelowOneThrows(): array {
    return [
      'zero' => [0],
      'negative' => [-3],
    ];
  }

  #[DataProvider('dataProviderWriteSplitsEveryLineEnding')]
  public function testWriteSplitsEveryLineEnding(array $writes, array $expected): void {
    $this->assertSame($expected, (new Log('bench'))->write(...$writes)->lines());
  }

  public static function dataProviderWriteSplitsEveryLineEnding(): array {
    return [
      'one line' => [['Apple weighed'], ['Apple weighed']],
      'several arguments' => [['Apple weighed', 'Pear weighed'], ['Apple weighed', 'Pear weighed']],
      'line feed' => [["Apple weighed\nPear weighed"], ['Apple weighed', 'Pear weighed']],
      'carriage return and line feed' => [["Apple weighed\r\nPear weighed"], ['Apple weighed', 'Pear weighed']],
      'lone carriage return' => [["Apple weighed\rPear weighed"], ['Apple weighed', 'Pear weighed']],
      'empty line kept' => [['Apple weighed', ''], ['Apple weighed', '']],
      'control bytes stripped' => [["Apple \x1b[31mweighed\x07"], ['Apple [31mweighed']],
    ];
  }

  public function testWriteDropsOldestLinesPastTheLimit(): void {
    $log = (new Log('bench', 3))->write('Apple', 'Pear')->write("Plum\nFig");

    $this->assertSame(['Pear', 'Plum', 'Fig'], $log->lines());
    $this->assertSame(['Plum', 'Fig', 'Kiwi'], $log->write('Kiwi')->lines());
  }

  public function testLoweringTheLimitDropsOldestLines(): void {
    $log = (new Log('bench'))->write('Apple', 'Pear', 'Plum')->limit(2);

    $this->assertSame(2, $log->lineLimit());
    $this->assertSame(['Pear', 'Plum'], $log->lines());
  }

  public function testClearEmptiesTheLog(): void {
    $this->assertSame([], (new Log('bench'))->write('Apple')->clear()->lines());
  }

  public function testLogDrawsEveryLineItHolds(): void {
    $log = (new Log('bench', 2))->write('Apple weighed', 'Pear weighed', 'Plum weighed');

    $this->assertSame("Pear weighed\nPlum weighed", $log->render(new DefaultTheme(80, ['color' => FALSE])));
  }

  public function testEmptyLogDrawsNothing(): void {
    $this->assertSame('', (new Log('bench'))->render(new DefaultTheme(80, ['color' => FALSE])));
  }

  public function testThemeWithoutMarkupThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('cannot draw a log');

    (new Log('bench'))->render(new BlankTheme());
  }

}
