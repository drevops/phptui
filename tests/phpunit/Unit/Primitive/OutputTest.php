<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Primitive;

use DrevOps\Tui\Primitive\Output;
use DrevOps\Tui\Primitive\Status;
use DrevOps\Tui\Testing\BufferedTerminal;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Theme\ThemeManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the standalone output primitive.
 */
#[CoversClass(Output::class)]
#[Group('tui')]
final class OutputTest extends TestCase {

  public function testBoxWritesTheFramedLines(): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme()))->box('Pick your fruit.', 'Welcome');

    $output = $terminal->output();

    $this->assertStringContainsString('Welcome', $output);
    $this->assertStringContainsString('Pick your fruit.', $output);
    $this->assertStringContainsString('╭', $output);
    $this->assertStringEndsWith("\n", $output);
  }

  public function testBoxAcceptsALineList(): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme()))->box(['Apples', 'Pears']);

    $lines = explode("\n", trim($terminal->output(), "\n"));

    $this->assertCount(4, $lines);
    $this->assertStringContainsString('Apples', $lines[1]);
    $this->assertStringContainsString('Pears', $lines[2]);
  }

  public function testAnEmptyBoxWritesNothing(): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme()))->box('');

    $this->assertSame('', $terminal->output());
  }

  #[DataProvider('dataProviderStatusMethods')]
  public function testEachStatusMethodWritesItsOwnLine(string $method, string $glyph): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme()))->{$method}('Preserves are ready');

    $this->assertSame($glyph . ' Preserves are ready' . "\n", $terminal->output());
  }

  /**
   * Data provider.
   *
   * @return array<string, array{string, string}>
   *   The method name and the glyph it leads with.
   */
  public static function dataProviderStatusMethods(): array {
    return [
      'note' => ['note', '•'],
      'info' => ['info', '›'],
      'success' => ['success', '✓'],
      'warning' => ['warning', '!'],
      'error' => ['error', '✗'],
    ];
  }

  public function testStatusTakesTheKindDirectly(): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme()))->status(Status::Warning, 'Short on jars');

    $this->assertSame("! Short on jars\n", $terminal->output());
  }

  public function testDefinitionsWriteAnAlignedList(): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme()))->definitions(['Jars' => '12', 'Fruit' => 'Apricot']);

    $this->assertSame("  Jars   12\n  Fruit  Apricot\n", $terminal->output());
  }

  public function testEmptyDefinitionsWriteNothing(): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme()))->definitions([]);

    $this->assertSame('', $terminal->output());
  }

  public function testCallsChainInOrder(): void {
    $terminal = new BufferedTerminal();
    $output = new Output($terminal, $this->theme());

    $this->assertSame($output, $output->success('Packed')->note('Sealed'));
    $this->assertSame("✓ Packed\n• Sealed\n", $terminal->output());
  }

  public function testOutputCarriesNoControlSequencesWithoutColour(): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme(color: FALSE)))
      ->box('Pick your fruit.', 'Welcome')
      ->success('Preserves are ready')
      ->definitions(['Jars' => '12']);

    $this->assertStringNotContainsString("\033", $terminal->output());
  }

  public function testColourWrapsEveryPiece(): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme(color: TRUE)))
      ->box('Pick your fruit.', 'Welcome')
      ->success('Preserves are ready')
      ->definitions(['Jars' => '12']);

    $output = $terminal->output();

    // The border, the success line and the definition value each carry their
    // own colour from the theme.
    $this->assertStringContainsString("\033[36m", $output);
    $this->assertStringContainsString("\033[32m", $output);
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
  protected function theme(bool $color = FALSE, bool $unicode = TRUE): DefaultTheme {
    return ThemeManager::create('default', DefaultTheme::DEFAULT_WIDTH, ['color' => $color, 'unicode' => $unicode, 'mode' => Mode::Dark]);
  }

}
