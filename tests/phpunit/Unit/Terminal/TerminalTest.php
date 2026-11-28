<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Terminal;

use DrevOps\PhpTui\Terminal\Terminal;
use DrevOps\PhpTui\Terminal\Wake;
use DrevOps\PhpTui\Testing\BufferedTerminal;
use DrevOps\PhpTui\Tests\Fixtures\Terminal\ProbeTerminal;
use DrevOps\PhpTui\Tests\Traits\IsolatesEnvTrait;
use DrevOps\PhpTui\Tests\Traits\OpensSocketPairsTrait;
use DrevOps\PhpTui\Theme\Mode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\TestCase;

/**
 * Tests the terminal output and capability detection.
 */
#[CoversClass(Terminal::class)]
#[CoversClass(Wake::class)]
#[Group('terminal')]
final class TerminalTest extends TestCase {

  use IsolatesEnvTrait;
  use OpensSocketPairsTrait;

  protected function tearDown(): void {
    $this->restoreEnv();
    parent::tearDown();
  }

  public function testWriteAndRender(): void {
    $stream = fopen('php://memory', 'rw');
    $this->assertIsResource($stream);

    $terminal = new Terminal($stream);
    $terminal->write('hello');
    $terminal->render('FRAME');

    rewind($stream);
    $contents = (string) stream_get_contents($stream);
    fclose($stream);

    $this->assertStringContainsString('hello', $contents);
    $this->assertStringContainsString('FRAME', $contents);
    $this->assertStringContainsString("\033[2J", $contents);
  }

  public function testFlushDoesNotDisturbTheStream(): void {
    $stream = fopen('php://memory', 'rw');
    $this->assertIsResource($stream);

    $terminal = new Terminal($stream);
    $terminal->write('frame');
    $terminal->flush();

    rewind($stream);
    $contents = (string) stream_get_contents($stream);
    fclose($stream);

    $this->assertSame('frame', $contents);
  }

  public function testIsOutputTtyIsFalseForNonTtyStream(): void {
    $stream = fopen('php://memory', 'rw');
    $this->assertIsResource($stream);

    $this->assertFalse((new Terminal($stream))->isOutputTty());

    fclose($stream);
  }

  public function testRenderWashesTheBackground(): void {
    $terminal = new BufferedTerminal();
    $terminal->setup('44');
    $terminal->render("A\nB");

    $contents = $terminal->output();

    // The wash opens the background and erases each line to its end.
    $this->assertStringContainsString("\033[44m", $contents);
    $this->assertStringContainsString("\033[K", $contents);
    $this->assertStringContainsString("\033[2J", $contents);
  }

  public function testRows(): void {
    $this->assertGreaterThan(0, (new Terminal())->rows());
  }

  public function testColumns(): void {
    $this->assertGreaterThan(0, (new Terminal())->columns());
  }

  public function testSizeFromEnvironmentOverrides(): void {
    $this->putEnv('COLUMNS', '120');
    $this->putEnv('LINES', '40');

    $terminal = new ProbeTerminal();

    $this->assertSame(120, $terminal->columns());
    $this->assertSame(40, $terminal->rows());
  }

  public function testSizeIgnoresNonPositiveEnvironment(): void {
    $this->putEnv('COLUMNS', '0');
    $this->putEnv('LINES', 'abc');

    $terminal = new ProbeTerminal("34 132\n");

    $this->assertSame(132, $terminal->columns());
    $this->assertSame(34, $terminal->rows());
  }

  #[DataProvider('dataProviderSizeFromProbe')]
  public function testSizeFromProbe(?string $reply, bool $windows, int $width, int $height): void {
    $this->putEnv('COLUMNS', NULL);
    $this->putEnv('LINES', NULL);

    $terminal = new ProbeTerminal($reply, $windows);

    $this->assertSame($width, $terminal->columns());
    $this->assertSame($height, $terminal->rows());
  }

  public static function dataProviderSizeFromProbe(): \Iterator {
    yield 'stty reply' => ["34 132\n", FALSE, 132, 34];
    yield 'stty garbage falls back' => ['not a size', FALSE, 80, 24];
    yield 'no reply falls back' => [NULL, FALSE, 80, 24];
    yield 'mode con report' => ["Status for device CON:\n----------------------\n    Lines:          50\n    Columns:        110\n    Keyboard rate:  31\n", TRUE, 110, 50];
    yield 'mode con report with crlf' => ["Status for device CON:\r\n----------------------\r\n    Lines:          9001\r\n    Columns:        96\r\n", TRUE, 96, 9001];
    yield 'mode con garbage falls back' => ['no dashes here', TRUE, 80, 24];
    yield 'mode con no reply falls back' => [NULL, TRUE, 80, 24];
  }

  public function testClear(): void {
    $stream = fopen('php://memory', 'rw');
    $this->assertIsResource($stream);

    $terminal = new Terminal($stream);
    $terminal->clear();

    rewind($stream);
    $contents = (string) stream_get_contents($stream);
    fclose($stream);

    $this->assertStringContainsString("\033[2J", $contents);
  }

  public function testRead(): void {
    $output = fopen('php://memory', 'r+');
    $input = fopen('php://memory', 'r+');
    $this->assertIsResource($output);
    $this->assertIsResource($input);

    fwrite($input, 'abc');
    rewind($input);

    $terminal = new Terminal($output, $input);
    $this->assertSame('abc', $terminal->read(32));
    // A read past the end of the stream reports no bytes.
    $this->assertSame('', $terminal->read(32));

    fclose($output);
    fclose($input);
  }

  #[DataProvider('dataProviderWaitOnSelectableInput')]
  public function testWaitOnSelectableInput(string $written, bool $expected): void {
    [$input, $writer] = $this->socketPair();

    if ($written !== '') {
      fwrite($writer, $written);
    }

    $wake = (new ProbeTerminal(input: $input))->wait(20000);

    $this->assertSame($expected, $wake->input);
    $this->assertSame([], $wake->streams);

    fclose($input);
    fclose($writer);
  }

  public static function dataProviderWaitOnSelectableInput(): \Iterator {
    yield 'input is ready' => ['a', TRUE];
    yield 'nothing arrives before the timeout' => ['', FALSE];
  }

  public function testWaitWithNegativeTimeoutReturnsAtOnce(): void {
    [$input, $writer] = $this->socketPair();

    $this->assertFalse((new ProbeTerminal(input: $input))->wait(-1)->input);

    fclose($input);
    fclose($writer);
  }

  #[DataProvider('dataProviderWaitReportsInputWhereItCannotSelect')]
  public function testWaitReportsInputWhereItCannotSelect(bool $windows, bool $memory): void {
    [$input, $writer] = $this->socketPair();
    $stream = $memory ? fopen('php://memory', 'r+') : $input;

    $this->assertTrue((new ProbeTerminal(windows: $windows, input: $stream))->wait(1000)->input);

    fclose($input);
    fclose($writer);
  }

  public static function dataProviderWaitReportsInputWhereItCannotSelect(): \Iterator {
    yield 'windows' => [TRUE, FALSE];
    yield 'memory stream' => [FALSE, TRUE];
  }

  #[DataProvider('dataProviderWaitReportsStreamsWithOutput')]
  public function testWaitReportsStreamsWithOutput(bool $key, bool $output, bool $expected_input, bool $expected_stream): void {
    [$input, $keyboard] = $this->socketPair();
    [$pipe, $child] = $this->socketPair();
    [$quiet, $silent] = $this->socketPair();

    if ($key) {
      fwrite($keyboard, 'a');
    }

    if ($output) {
      fwrite($child, 'Pears sorted');
    }

    $wake = (new ProbeTerminal(input: $input))->wait(20000, ['pipe' => $pipe, 'quiet' => $quiet]);

    $this->assertSame($expected_input, $wake->input);
    $this->assertSame($expected_stream ? ['pipe' => $pipe] : [], $wake->streams);

    foreach ([$input, $keyboard, $pipe, $child, $quiet, $silent] as $stream) {
      fclose($stream);
    }
  }

  public static function dataProviderWaitReportsStreamsWithOutput(): \Iterator {
    yield 'only the stream has output' => [FALSE, TRUE, FALSE, TRUE];
    yield 'key and stream output' => [TRUE, TRUE, TRUE, TRUE];
    yield 'only a key' => [TRUE, FALSE, TRUE, FALSE];
    yield 'nothing before the timeout' => [FALSE, FALSE, FALSE, FALSE];
  }

  #[DataProvider('dataProviderWaitPollsStreamsBesideInputThatCannotSelect')]
  public function testWaitPollsStreamsBesideInputThatCannotSelect(bool $output): void {
    [$pipe, $child] = $this->socketPair();

    if ($output) {
      fwrite($child, 'Plums sorted');
    }

    $wake = (new ProbeTerminal(input: fopen('php://memory', 'r+')))->wait(1000, [$pipe]);

    $this->assertTrue($wake->input);
    $this->assertSame($output ? [$pipe] : [], $wake->streams);

    fclose($pipe);
    fclose($child);
  }

  public static function dataProviderWaitPollsStreamsBesideInputThatCannotSelect(): \Iterator {
    yield 'stream has output' => [TRUE];
    yield 'stream is quiet' => [FALSE];
  }

  public function testWaitIgnoresStreamsOnWindows(): void {
    [$input, $keyboard] = $this->socketPair();
    [$pipe, $child] = $this->socketPair();
    fwrite($child, 'Cherries sorted');

    $wake = (new ProbeTerminal(windows: TRUE, input: $input))->wait(1000, [$pipe]);

    $this->assertTrue($wake->input);
    $this->assertSame([], $wake->streams);

    foreach ([$input, $keyboard, $pipe, $child] as $stream) {
      fclose($stream);
    }
  }

  public function testWaitReportsNothingForStreamsThatCannotSelect(): void {
    $memory = fopen('php://memory', 'r+');
    $this->assertIsResource($memory);

    $wake = (new ProbeTerminal(input: fopen('php://memory', 'r+')))->wait(1000, [$memory]);

    $this->assertTrue($wake->input);
    $this->assertSame([], $wake->streams);
  }

  public function testRemeasureReportsWhetherTheSizeChanged(): void {
    $this->putEnv('COLUMNS', NULL);
    $this->putEnv('LINES', NULL);

    $terminal = new ProbeTerminal("24 80\n", signals: FALSE);
    $this->assertSame(80, $terminal->columns());

    $this->assertFalse($terminal->remeasure());

    $terminal->answer("30 100\n");

    $this->assertTrue($terminal->remeasure());
    $this->assertSame(100, $terminal->columns());
    $this->assertSame(30, $terminal->rows());
  }

  public function testWatchResizeDoesNothingWithoutSignals(): void {
    $terminal = new ProbeTerminal("24 80\n", signals: FALSE);

    $terminal->watchResize();
    $this->assertFalse($terminal->isWatching());

    $terminal->unwatchResize();
    $this->assertFalse($terminal->isWatching());
  }

  #[RequiresFunction('pcntl_signal')]
  #[RequiresFunction('posix_kill')]
  public function testRemeasureWhileWatchingWaitsForTheSignal(): void {
    $this->putEnv('COLUMNS', NULL);
    $this->putEnv('LINES', NULL);

    $async = pcntl_async_signals();
    $original = pcntl_signal_get_handler(SIGWINCH);
    $own = static function (): void {
    };
    pcntl_signal(SIGWINCH, $own);
    $terminal = new ProbeTerminal("24 80\n");

    try {
      $this->assertSame(80, $terminal->columns());

      $terminal->watchResize();
      // A second call keeps the handler already installed.
      $terminal->watchResize();
      $this->assertTrue($terminal->isWatching());
      $this->assertTrue(pcntl_async_signals());

      // The first remeasure probes, catching a resize from before the watch.
      $terminal->answer("26 90\n");
      $this->assertTrue($terminal->remeasure());
      $this->assertSame(90, $terminal->columns());

      $terminal->answer("30 100\n");
      $this->assertFalse($terminal->remeasure());

      posix_kill(posix_getpid(), SIGWINCH);
      pcntl_signal_dispatch();

      $this->assertTrue($terminal->remeasure());
      $this->assertSame(100, $terminal->columns());
      $this->assertFalse($terminal->remeasure());

      $terminal->unwatchResize();
      $this->assertFalse($terminal->isWatching());
      $this->assertSame($async, pcntl_async_signals());
      // The handler installed before the watch is the one left in place.
      $this->assertSame($own, pcntl_signal_get_handler(SIGWINCH));
    }
    finally {
      $terminal->unwatchResize();
      pcntl_signal(SIGWINCH, $original);
      pcntl_async_signals($async);
    }
  }

  public function testIsSuspendableIsFalseOffTty(): void {
    $output = fopen('php://memory', 'r+');
    $input = fopen('php://memory', 'r+');
    $this->assertIsResource($output);
    $this->assertIsResource($input);

    $this->assertFalse((new Terminal($output, $input))->isSuspendable());
  }

  public function testSuspendDoesNothingWithoutJobControl(): void {
    $terminal = new ProbeTerminal('S+', signals: FALSE, tty: TRUE);

    $this->assertFalse($terminal->isSuspendable());

    $terminal->suspend();

    $this->assertSame([], $terminal->raised);
    $this->assertTrue($terminal->reclaim());
    $this->assertSame([], $terminal->commands);
  }

  public function testReclaimWithoutSuspendHoldsTheTerminal(): void {
    $terminal = new ProbeTerminal('S', tty: TRUE);

    $this->assertTrue($terminal->reclaim());
    $this->assertSame([], $terminal->commands);
  }

  #[RequiresFunction('pcntl_signal')]
  #[RequiresFunction('posix_kill')]
  public function testSuspendContinuedInTheForegroundIsReclaimedAtOnce(): void {
    $async = pcntl_async_signals();
    $original = pcntl_signal_get_handler(SIGCONT);
    $own = static function (): void {
    };
    pcntl_signal(SIGCONT, $own);
    $terminal = new ProbeTerminal("S+\n", tty: TRUE);

    try {
      $this->assertTrue($terminal->isSuspendable());

      $terminal->suspend();

      $this->assertSame([SIGTSTP], $terminal->raised);
      $this->assertTrue(pcntl_async_signals());
      $this->assertTrue($terminal->reclaim());
      $this->assertSame(['ps -o stat= -p ' . getmypid() . ' 2>/dev/null'], $terminal->commands);
      // The handler installed before the suspend is the one left in place.
      $this->assertSame($own, pcntl_signal_get_handler(SIGCONT));
      $this->assertSame($async, pcntl_async_signals());
    }
    finally {
      pcntl_signal(SIGCONT, $original);
      pcntl_async_signals($async);
    }
  }

  #[RequiresFunction('pcntl_signal')]
  #[RequiresFunction('posix_kill')]
  public function testSuspendContinuedInTheBackgroundWaitsForTheNextContinue(): void {
    $async = pcntl_async_signals();
    $original = pcntl_signal_get_handler(SIGCONT);
    $terminal = new ProbeTerminal("S\n", tty: TRUE);

    try {
      $terminal->suspend();
      // A second suspend keeps the handler already installed.
      $terminal->suspend();

      $this->assertSame([SIGTSTP, SIGTSTP], $terminal->raised);
      $this->assertFalse($terminal->reclaim());

      // No continue arrived since, so nothing is probed.
      $this->assertFalse($terminal->reclaim());
      $this->assertCount(1, $terminal->commands);

      $terminal->answer("S+\n");
      posix_kill(posix_getpid(), SIGCONT);
      pcntl_signal_dispatch();

      $this->assertTrue($terminal->reclaim());
      $this->assertCount(2, $terminal->commands);
      $this->assertSame($original, pcntl_signal_get_handler(SIGCONT));
    }
    finally {
      pcntl_signal(SIGCONT, $original);
      pcntl_async_signals($async);
    }
  }

  #[RequiresFunction('pcntl_signal')]
  #[RequiresFunction('posix_kill')]
  public function testBackgroundJobBroughtToTheForegroundIsFoundWithoutContinue(): void {
    $async = pcntl_async_signals();
    $original = pcntl_signal_get_handler(SIGCONT);
    $terminal = new ProbeTerminal("S\n", tty: TRUE);
    $terminal->time = 5000000000;

    try {
      $terminal->suspend();
      $this->assertFalse($terminal->reclaim());

      // The shell's fg sends no SIGCONT to a running job, so only the clock
      // brings the next probe.
      $terminal->answer("S+\n");
      $terminal->time += 999999999;
      $this->assertFalse($terminal->reclaim());
      $this->assertCount(1, $terminal->commands);

      $terminal->time += 1;
      $this->assertTrue($terminal->reclaim());
      $this->assertCount(2, $terminal->commands);
    }
    finally {
      pcntl_signal(SIGCONT, $original);
      pcntl_async_signals($async);
    }
  }

  #[DataProvider('dataProviderIsForegroundState')]
  public function testIsForegroundState(?string $output, bool $expected): void {
    $this->assertSame($expected, Terminal::isForegroundState($output));
  }

  public static function dataProviderIsForegroundState(): \Iterator {
    yield 'foreground' => ["S+\n", TRUE];
    yield 'foreground running' => ['R+', TRUE];
    yield 'background' => ["S\n", FALSE];
    yield 'background stopped' => ['T', FALSE];
    yield 'no output' => [NULL, TRUE];
    yield 'blank output' => ["  \n", TRUE];
  }

  public function testIdleReturnsAfterTheSleep(): void {
    $terminal = new ProbeTerminal();
    $start = hrtime(TRUE);

    $this->assertSame([], $terminal->idle(-5));
    $this->assertSame([], $terminal->idle(1000));

    $this->assertGreaterThanOrEqual(1000000, hrtime(TRUE) - $start);
  }

  public function testIdleReportsTheStreamsWithOutput(): void {
    [$pipe, $child] = $this->socketPair();
    [$quiet, $silent] = $this->socketPair();
    $terminal = new ProbeTerminal();

    $this->assertSame([], $terminal->idle(1000, ['pipe' => $pipe, 'quiet' => $quiet]));

    fwrite($child, 'Figs sorted');

    $this->assertSame(['pipe' => $pipe], $terminal->idle(1000, ['pipe' => $pipe, 'quiet' => $quiet]));

    fclose($pipe);
    fclose($child);
    fclose($quiet);
    fclose($silent);
  }

  public function testQueryBackgroundReturnsNullOffTty(): void {
    $output = fopen('php://memory', 'r+');
    $input = fopen('php://memory', 'r+');
    $this->assertIsResource($output);
    $this->assertIsResource($input);

    // A non-TTY input has no queryable background.
    $this->assertNull((new Terminal($output, $input))->queryBackground());

    fclose($output);
    fclose($input);
  }

  #[DataProvider('dataProviderDetectUnicode')]
  public function testDetectUnicode(?string $lc_all, ?string $lc_ctype, ?string $lang, bool $expected): void {
    $this->putEnv('LC_ALL', $lc_all);
    $this->putEnv('LC_CTYPE', $lc_ctype);
    $this->putEnv('LANG', $lang);

    $this->assertSame($expected, Terminal::detectUnicode());
  }

  public static function dataProviderDetectUnicode(): \Iterator {
    yield 'utf lang' => [NULL, NULL, 'en_US.UTF-8', TRUE];
    yield 'non-utf lang' => [NULL, NULL, 'C', FALSE];
    yield 'lc_all wins over lang' => ['en_AU.UTF-8', NULL, 'C', TRUE];
    yield 'lc_ctype checked before lang' => [NULL, 'POSIX', 'en_US.UTF-8', FALSE];
    yield 'none set falls back to ascii' => [NULL, NULL, NULL, FALSE];
  }

  #[DataProvider('dataProviderDetectColor')]
  public function testDetectColor(?string $no_color, ?string $term, bool $expected): void {
    $this->putEnv('NO_COLOR', $no_color);
    $this->putEnv('TERM', $term);

    $this->assertSame($expected, Terminal::detectColor());
  }

  public static function dataProviderDetectColor(): \Iterator {
    yield 'normal terminal' => [NULL, 'xterm-256color', TRUE];
    yield 'no_color set' => ['1', 'xterm', FALSE];
    yield 'dumb terminal' => [NULL, 'dumb', FALSE];
    yield 'no_color empty is treated as unset' => ['', 'xterm', TRUE];
  }

  #[DataProvider('dataProviderDetectMode')]
  public function testDetectMode(?string $osc_response, ?string $colorfgbg, Mode $expected): void {
    $this->putEnv('COLORFGBG', $colorfgbg);

    $this->assertSame($expected, Terminal::detectMode($osc_response));
  }

  public static function dataProviderDetectMode(): \Iterator {
    $dark = Mode::Dark;
    $light = Mode::Light;

    yield 'osc black is dark' => ["\033]11;rgb:0000/0000/0000\007", NULL, $dark];
    yield 'osc white is light' => ["\033]11;rgb:ffff/ffff/ffff\007", NULL, $light];
    yield 'osc 8-bit black' => ['rgb:00/00/00', NULL, $dark];
    yield 'osc 8-bit white' => ['rgb:ff/ff/ff', NULL, $light];
    yield 'osc single-digit white' => ['rgb:f/f/f', NULL, $light];
    yield 'osc mid grey is light at the boundary' => ['rgb:8080/8080/8080', NULL, $light];
    yield 'osc dark grey is dark' => ['rgb:3030/3030/3030', NULL, $dark];
    yield 'osc rgba prefix ignores alpha' => ['rgba:0000/0000/0000/ffff', NULL, $dark];
    yield 'osc st terminator' => ["\033]11;rgb:ffff/ffff/ffff\033\\", NULL, $light];
    yield 'osc bright green is light' => ['rgb:0000/ffff/0000', NULL, $light];
    yield 'osc pure blue is dark' => ['rgb:0000/0000/ffff', NULL, $dark];
    yield 'unparseable osc falls through to colorfgbg' => ['garbage', '0;15', $light];
    yield 'unparseable osc falls through to dark' => ['garbage', NULL, $dark];
    yield 'null osc colorfgbg dark bg' => [NULL, '15;0', $dark];
    yield 'null osc colorfgbg light bg' => [NULL, '0;15', $light];
    yield 'null osc colorfgbg decoration field' => [NULL, '1;15;0', $dark];
    yield 'null osc colorfgbg index 7 is light' => [NULL, '0;7', $light];
    yield 'null osc colorfgbg index 8 is dark' => [NULL, '15;8', $dark];
    yield 'null osc colorfgbg default bg is dark' => [NULL, 'default;default', $dark];
    yield 'null osc colorfgbg empty is dark' => [NULL, '', $dark];
    yield 'null osc no colorfgbg is dark' => [NULL, NULL, $dark];
  }

}
