<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Primitive;

use DrevOps\Tui\Primitive\Progress;
use DrevOps\Tui\Testing\BufferedTerminal;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Theme\ThemeManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the progress primitive (spinner and determinate bar).
 */
#[CoversClass(Progress::class)]
#[Group('tui')]
final class ProgressTest extends TestCase {

  public function testReturnsTheCallbackResult(): void {
    $progress = new Progress(new BufferedTerminal(), $this->theme(), TRUE, NULL, 'Scanning');

    $this->assertSame('done', $progress->run(static fn(): string => 'done'));
  }

  public function testActiveSpinnerAnimatesThenClearsItsLine(): void {
    $terminal = new BufferedTerminal();
    $progress = new Progress($terminal, $this->theme(), TRUE, NULL, 'Scanning');

    $progress->run(static function (Progress $p): void {
      $p->advance();
    });

    $output = $terminal->output();

    // The cursor is hidden while it runs and shown again at the end.
    $this->assertStringContainsString("\033[?25l", $output);
    $this->assertStringContainsString("\033[?25h", $output);
    // The transient spinner wipes its line on finish.
    $this->assertStringContainsString("\033[2K", $output);
    // The first frame is drawn before the work, the second on the advance.
    $this->assertStringContainsString('⠋', $output);
    $this->assertStringContainsString('⠙', $output);
    // The theme's accent (default dark: bold cyan) wraps the glyph.
    $this->assertStringContainsString("\033[1;36m", $output);
    $this->assertStringContainsString('Scanning', $output);
  }

  public function testActiveBarFillsAndSettlesOnItsOwnLine(): void {
    $terminal = new BufferedTerminal();
    $progress = new Progress($terminal, $this->theme(), TRUE, 2, 'Packing');

    $progress->run(static function (Progress $p): void {
      $p->advance('apples');
      $p->advance('pears');
    });

    $output = $terminal->output();

    $this->assertStringContainsString("\033[?25l", $output);
    $this->assertStringContainsString("\033[?25h", $output);
    $this->assertStringContainsString('Packing', $output);
    // The count reaches the total and the last label shows.
    $this->assertStringContainsString('2/2', $output);
    $this->assertStringContainsString('pears', $output);
    // The completed bar settles on its own line, with the theme accent.
    $this->assertStringContainsString("\n", $output);
    $this->assertStringContainsString('█', $output);
    $this->assertStringContainsString("\033[1;36m", $output);
  }

  public function testInactivePrintsOnePlainLineAndNoControlSequences(): void {
    $terminal = new BufferedTerminal();
    $progress = new Progress($terminal, $this->theme(), FALSE, 2, 'Packing');

    $result = $progress->run(static function (Progress $p): string {
      // Advances are silent off a TTY.
      $p->advance('apples');

      return 'value';
    });

    $output = $terminal->output();

    $this->assertSame('value', $result);
    $this->assertSame("Packing\n", $output);
    $this->assertStringNotContainsString("\033", $output);
  }

  public function testAsciiSpinnerFallsBack(): void {
    $terminal = new BufferedTerminal();
    $progress = new Progress($terminal, $this->theme(unicode: FALSE), TRUE, NULL, 'Scanning');

    $progress->run(static function (Progress $p): void {
      $p->advance();
    });

    $output = $terminal->output();

    $this->assertStringContainsString('|', $output);
    $this->assertStringContainsString('/', $output);
    $this->assertStringNotContainsString('⠋', $output);
  }

  public function testAdvanceBeyondTotalClampsTheCount(): void {
    $terminal = new BufferedTerminal();
    $progress = new Progress($terminal, $this->theme(), TRUE, 1, 'Packing');

    $progress->run(static function (Progress $p): void {
      $p->advance();
      $p->advance();
      $p->advance();
    });

    $output = $terminal->output();

    $this->assertStringContainsString('1/1', $output);
    $this->assertStringNotContainsString('2/1', $output);
  }

  public function testNegativeTotalClampsToZero(): void {
    $terminal = new BufferedTerminal();
    $progress = new Progress($terminal, $this->theme(), TRUE, -5, 'Packing');

    $progress->run(static fn(): null => NULL);

    $this->assertStringContainsString('0/0', $terminal->output());
  }

  public function testCleansUpWhenTheWorkThrows(): void {
    $terminal = new BufferedTerminal();
    $progress = new Progress($terminal, $this->theme(), TRUE, NULL, 'Scanning');

    $message = '';

    try {
      $progress->run(static function (): never {
        throw new \RuntimeException('boom');
      });
    }
    catch (\RuntimeException $exception) {
      $message = $exception->getMessage();
    }

    // The exception propagates and the cursor is restored despite the throw.
    $this->assertSame('boom', $message);
    $this->assertStringContainsString("\033[?25h", $terminal->output());
  }

  /**
   * A default theme in the given display modes, fixed to dark.
   *
   * @param bool $color
   *   Whether colour is on.
   * @param bool $unicode
   *   Whether Unicode glyphs are on.
   *
   * @return \DrevOps\Tui\Theme\DefaultTheme
   *   The theme.
   */
  protected function theme(bool $color = TRUE, bool $unicode = TRUE): DefaultTheme {
    return ThemeManager::create('default', DefaultTheme::DEFAULT_WIDTH, ['color' => $color, 'unicode' => $unicode, 'mode' => Mode::Dark]);
  }

}
