<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Primitive;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use DrevOps\PhpTui\Primitive\Output;
use DrevOps\PhpTui\Primitive\Status;
use DrevOps\PhpTui\Testing\BufferedTerminal;
use DrevOps\PhpTui\Tests\Traits\BuildsThemesTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the standalone output primitive.
 */
#[CoversClass(Output::class)]
#[Group('primitive')]
#[AllowMockObjectsWithoutExpectations]
final class OutputTest extends TestCase {

  use BuildsThemesTrait;

  public function testBoxWritesTheFramedLines(): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme(color: FALSE)))->box('Welcome', 'Pick your fruit.');

    $output = $terminal->output();

    $this->assertStringContainsString('Welcome', $output);
    $this->assertStringContainsString('Pick your fruit.', $output);
    $this->assertStringContainsString('╭', $output);
    $this->assertStringEndsWith("\n", $output);
  }

  public function testBoxAcceptsLineList(): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme(color: FALSE)))->box('', ['Apples', 'Pears']);

    $lines = explode("\n", trim($terminal->output(), "\n"));

    $this->assertCount(4, $lines);
    $this->assertStringContainsString('Apples', $lines[1]);
    $this->assertStringContainsString('Pears', $lines[2]);
  }

  public function testAnEmptyBoxWritesNothing(): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme(color: FALSE)))->box('');

    $this->assertSame('', $terminal->output());
  }

  #[DataProvider('dataProviderEachStatusMethodWritesItsOwnLine')]
  public function testEachStatusMethodWritesItsOwnLine(string $method, string $glyph): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme(color: FALSE)))->{$method}('Preserves are ready');

    $this->assertSame($glyph . ' Preserves are ready' . "\n", $terminal->output());
  }

  /**
   * Data provider.
   *
   * @return \Iterator<string, array{string, string}>
   *   The method name and the glyph it leads with.
   */
  public static function dataProviderEachStatusMethodWritesItsOwnLine(): \Iterator {
    yield 'note' => ['note', '•'];
    yield 'info' => ['info', '›'];
    yield 'success' => ['success', '✓'];
    yield 'warning' => ['warning', '!'];
    yield 'error' => ['error', '✗'];
  }

  public function testCardWritesItsGridInsideTheBox(): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme(color: FALSE)))->card('Summary', 'Your order is packed.', ['Item', 'Count'], [['Apricot', '12']]);

    $output = $terminal->output();

    $this->assertStringContainsString('Summary', $output);
    $this->assertStringContainsString('Your order is packed.', $output);
    $this->assertStringContainsString('Apricot', $output);
    $this->assertStringContainsString('╭', $output);
  }

  public function testUnborderedCardWritesIndentedLines(): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme(color: FALSE)))->card('Summary', 'Packed.', bordered: FALSE);

    $this->assertSame("  Summary\n  Packed.\n", $terminal->output());
  }

  public function testTableWritesTheGridAlone(): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme(color: FALSE)))->table(['Item'], [['Apricot']]);

    $lines = explode("\n", trim($terminal->output(), "\n"));

    $this->assertCount(5, $lines);
    $this->assertStringContainsString('Item', $lines[1]);
    $this->assertStringContainsString('Apricot', $lines[3]);
  }

  public function testAnEmptyTableWritesNothing(): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme(color: FALSE)))->table([], []);

    $this->assertSame('', $terminal->output());
  }

  public function testTextWritesWrappedParagraph(): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme(color: FALSE)))->text('Everything is picked the morning it ships.');

    $this->assertSame("Everything is picked the morning it ships.\n", $terminal->output());
  }

  public function testRuleWritesSpanningLine(): void {
    $terminal = new BufferedTerminal();
    $theme = $this->theme(color: FALSE);

    (new Output($terminal, $theme))->rule();

    $this->assertSame(str_repeat('─', $theme->contentWidth()) . "\n", $terminal->output());
  }

  public function testBannerWritesTheLogoAndVersion(): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme(color: FALSE)))->banner('Produce Box', '1.2.3');

    $output = $terminal->output();

    $this->assertStringContainsString('Produce Box', $output);
    $this->assertStringContainsString('1.2.3', $output);
    $this->assertStringEndsWith("\n", $output);
  }

  public function testStatusTakesTheKindDirectly(): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme(color: FALSE)))->status(Status::Warning, 'Short on jars');

    $this->assertSame("! Short on jars\n", $terminal->output());
  }

  public function testDefinitionsWriteAnAlignedList(): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme(color: FALSE)))->definitions(['Jars' => '12', 'Fruit' => 'Apricot']);

    $this->assertSame("  Jars   12\n  Fruit  Apricot\n", $terminal->output());
  }

  public function testEmptyDefinitionsWriteNothing(): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme(color: FALSE)))->definitions([]);

    $this->assertSame('', $terminal->output());
  }

  public function testCallsChainInOrder(): void {
    $terminal = new BufferedTerminal();
    $output = new Output($terminal, $this->theme(color: FALSE));

    $this->assertSame($output, $output->success('Packed')->note('Sealed'));
    $this->assertSame("✓ Packed\n• Sealed\n", $terminal->output());
  }

  public function testOutputCarriesNoControlSequencesWithoutColour(): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme(color: FALSE)))
      ->box('Welcome', 'Pick your fruit.')
      ->success('Preserves are ready')
      ->definitions(['Jars' => '12']);

    $this->assertStringNotContainsString("\033", $terminal->output());
  }

  public function testColourWrapsEveryPiece(): void {
    $terminal = new BufferedTerminal();

    (new Output($terminal, $this->theme(color: TRUE)))
      ->box('Welcome', 'Pick your fruit.')
      ->success('Preserves are ready')
      ->definitions(['Jars' => '12']);

    $output = $terminal->output();

    // The border, the success line and the definition value each carry their
    // own colour from the theme.
    $this->assertStringContainsString("\033[36m", $output);
    $this->assertStringContainsString("\033[32m", $output);
  }

}
