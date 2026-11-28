<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Testing;

use DrevOps\PhpTui\Testing\Background;
use DrevOps\PhpTui\Testing\BufferedTerminal;
use DrevOps\PhpTui\Testing\Resize;
use DrevOps\PhpTui\Testing\Tick;
use DrevOps\PhpTui\Tests\Traits\OpensSocketPairsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the scripted-input, output-capturing terminal.
 */
#[CoversClass(BufferedTerminal::class)]
#[CoversClass(Resize::class)]
#[Group('testing')]
final class BufferedTerminalTest extends TestCase {

  use OpensSocketPairsTrait;

  public function testReadDequeuesOnePerCallThenEof(): void {
    $terminal = new BufferedTerminal(["\r", 'ab']);

    $this->assertSame("\r", $terminal->read());
    $this->assertSame('ab', $terminal->read());
    // Exhausted: every further read reports EOF.
    $this->assertSame(['', ''], [$terminal->read(), $terminal->read()]);
  }

  public function testRowsIsFixed(): void {
    $this->assertSame(24, (new BufferedTerminal())->rows());
    $this->assertSame(30, (new BufferedTerminal([], 30))->rows());
  }

  public function testColumnsIsFixed(): void {
    $this->assertSame(80, (new BufferedTerminal())->columns());
    $this->assertSame(120, (new BufferedTerminal([], 24, 120))->columns());
  }

  public function testSetupAndRestoreProduceNoOutput(): void {
    $terminal = new BufferedTerminal();

    $terminal->setup();
    $terminal->watchResize();
    $terminal->unwatchResize();
    $terminal->restore();

    $this->assertSame('', $terminal->output());
  }

  public function testWaitTimesOutAtEachTickAndReportsKeysOtherwise(): void {
    $terminal = new BufferedTerminal(['a', new Tick(), 'b']);

    $this->assertTrue($terminal->wait(1000)->input);
    $this->assertSame('a', $terminal->read());
    $this->assertFalse($terminal->wait(1000)->input);
    $this->assertTrue($terminal->wait(1000)->input);
    $this->assertSame('b', $terminal->read());
    // Exhausted: the wait reports input, so the read that follows sees EOF.
    $this->assertTrue($terminal->wait(1000)->input);
    $this->assertSame('', $terminal->read());
  }

  public function testStreamWithOutputEndsTheWaitAheadOfTheScript(): void {
    [$pipe, $child] = $this->socketPair();
    [$quiet, $silent] = $this->socketPair();
    $terminal = new BufferedTerminal([new Tick()]);

    fwrite($child, 'Pears sorted');
    $wake = $terminal->wait(1000, ['pipe' => $pipe, 'quiet' => $quiet]);

    $this->assertFalse($wake->input);
    $this->assertSame(['pipe' => $pipe], $wake->streams);

    fread($pipe, 64);
    $wake = $terminal->wait(1000, ['pipe' => $pipe, 'quiet' => $quiet]);

    // The tick is still the next scripted item.
    $this->assertFalse($wake->input);
    $this->assertSame([], $wake->streams);
    $this->assertTrue($terminal->wait(1000, [$pipe])->input);

    foreach ([$pipe, $child, $quiet, $silent] as $stream) {
      fclose($stream);
    }
  }

  public function testResizeInterruptsTheWaitAndReportsTheNewSize(): void {
    $terminal = new BufferedTerminal([new Resize(100, 30)]);

    $this->assertFalse($terminal->wait(1000)->input);
    $this->assertSame([100, 30], [$terminal->columns(), $terminal->rows()]);
    $this->assertTrue($terminal->remeasure());
    $this->assertFalse($terminal->remeasure());
  }

  public function testResizeToTheSameSizeIsNoChange(): void {
    $terminal = new BufferedTerminal([new Resize(80, 24)]);

    $this->assertFalse($terminal->wait(1000)->input);
    $this->assertFalse($terminal->remeasure());
  }

  public function testReadSkipsTicksAndAppliesResizes(): void {
    $terminal = new BufferedTerminal([new Tick(), new Resize(50, 10), 'x', new Tick()]);

    $this->assertSame('x', $terminal->read());
    $this->assertSame([50, 10], [$terminal->columns(), $terminal->rows()]);
    $this->assertTrue($terminal->remeasure());
    $this->assertSame('', $terminal->read());
  }

  public function testSuspendWithoutBackgroundContinuesInTheForeground(): void {
    $terminal = new BufferedTerminal(['x']);

    $this->assertTrue($terminal->isSuspendable());

    $terminal->suspend();

    $this->assertSame(1, $terminal->suspensions);
    $this->assertTrue($terminal->reclaim());
    $this->assertSame('x', $terminal->read());
  }

  public function testSuspendWithBackgroundIdlesThroughTicksAndResizesUntilTheNextItem(): void {
    $terminal = new BufferedTerminal([new Background(), new Tick(), new Resize(50, 10), 'x']);

    $terminal->suspend();
    $this->assertFalse($terminal->reclaim());

    $terminal->idle(1000);
    $this->assertFalse($terminal->reclaim());

    $terminal->idle(1000);
    $this->assertFalse($terminal->reclaim());
    $this->assertSame([50, 10], [$terminal->columns(), $terminal->rows()]);

    // The keys that follow are typed once the shell hands the terminal back.
    $terminal->idle(1000);
    $this->assertTrue($terminal->reclaim());
    $this->assertTrue($terminal->remeasure());
    $this->assertSame('x', $terminal->read());
  }

  public function testIdleReportsStreamOutputAheadOfTheScript(): void {
    [$pipe, $child] = $this->socketPair();
    fwrite($child, 'Figs sorted');
    $terminal = new BufferedTerminal([new Background(), new Tick()]);

    $terminal->suspend();

    $this->assertSame([$pipe], $terminal->idle(1000, [$pipe]));
    $this->assertFalse($terminal->reclaim());

    fread($pipe, 64);

    // The Tick is still scripted, so the session stays in the background.
    $this->assertSame([], $terminal->idle(1000, [$pipe]));
    $this->assertFalse($terminal->reclaim());

    fclose($pipe);
    fclose($child);
  }

  public function testIdleAtTheEndOfTheScriptHandsTheTerminalBack(): void {
    $terminal = new BufferedTerminal([new Background()]);

    $terminal->suspend();
    $terminal->idle(1000);

    $this->assertTrue($terminal->reclaim());
  }

  public function testSuspendabilityCanBeTurnedOff(): void {
    $terminal = new BufferedTerminal();
    $terminal->suspendable = FALSE;

    $this->assertFalse($terminal->isSuspendable());
  }

  public function testReadAndWaitSkipABackgroundOutsideASuspend(): void {
    $terminal = new BufferedTerminal([new Background(), 'x']);

    $this->assertTrue($terminal->wait(1000)->input);
    $this->assertSame('x', $terminal->read());
  }

  #[DataProvider('dataProviderResizeRefusesSizeBelowOne')]
  public function testResizeRefusesSizeBelowOne(int $columns, int $rows): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('A resized terminal must be at least 1 column wide and 1 row high.');

    new Resize($columns, $rows);
  }

  public static function dataProviderResizeRefusesSizeBelowOne(): \Iterator {
    yield 'no columns' => [0, 24];
    yield 'no rows' => [80, 0];
  }

  public function testCapturesRenderedOutput(): void {
    $terminal = new BufferedTerminal();

    $terminal->render('FRAME');

    $output = $terminal->output();
    $this->assertStringContainsString('FRAME', $output);
    // render() clears the screen before writing the frame.
    $this->assertStringContainsString("\033[2J", $output);
    // The capture is repeatable.
    $this->assertSame($output, $terminal->output());
  }

}
