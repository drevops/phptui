<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Render;

use DrevOps\Tui\Render\Terminal;
use DrevOps\Tui\Testing\BufferedTerminal;
use DrevOps\Tui\Tests\Fixtures\Render\ProbeTerminal;
use DrevOps\Tui\Tests\Traits\IsolatesEnvTrait;
use DrevOps\Tui\Theme\Mode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the terminal output and capability detection.
 */
#[CoversClass(Terminal::class)]
#[Group('tui')]
final class TerminalTest extends TestCase {

  use IsolatesEnvTrait;

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

  public function testHeight(): void {
    $this->assertGreaterThan(0, (new Terminal())->height());
  }

  public function testWidth(): void {
    $this->assertGreaterThan(0, (new Terminal())->width());
  }

  public function testSizeFromEnvironmentOverrides(): void {
    $this->putEnv('COLUMNS', '120');
    $this->putEnv('LINES', '40');

    $terminal = new ProbeTerminal();

    $this->assertSame(120, $terminal->width());
    $this->assertSame(40, $terminal->height());
  }

  public function testSizeIgnoresNonPositiveEnvironment(): void {
    $this->putEnv('COLUMNS', '0');
    $this->putEnv('LINES', 'abc');

    $terminal = new ProbeTerminal("34 132\n");

    $this->assertSame(132, $terminal->width());
    $this->assertSame(34, $terminal->height());
  }

  #[DataProvider('dataProviderSizeFromProbe')]
  public function testSizeFromProbe(?string $reply, bool $windows, int $width, int $height): void {
    $this->putEnv('COLUMNS', NULL);
    $this->putEnv('LINES', NULL);

    $terminal = new ProbeTerminal($reply, $windows);

    $this->assertSame($width, $terminal->width());
    $this->assertSame($height, $terminal->height());
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
