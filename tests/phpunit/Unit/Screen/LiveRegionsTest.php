<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Screen;

use DrevOps\PhpTui\Block\Log;
use DrevOps\PhpTui\Block\Panel;
use DrevOps\PhpTui\Block\Table;
use DrevOps\PhpTui\Screen\Region;
use DrevOps\PhpTui\Screen\ScreenRenderer;
use DrevOps\PhpTui\Terminal\Ansi;
use DrevOps\PhpTui\Testing\ScreenTester;
use DrevOps\PhpTui\Testing\Tick;
use DrevOps\PhpTui\Tests\Fixtures\Screen\Layout\BenchLayout;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests a live table and a following log redrawn between ticks.
 */
#[CoversClass(ScreenRenderer::class)]
#[CoversClass(Region::class)]
#[CoversClass(Table::class)]
#[CoversClass(Log::class)]
#[Group('screen')]
final class LiveRegionsTest extends TestCase {

  public function testFollowingRegionShowsNewestLinesAndMarksOlderOnes(): void {
    $log = (new Log('bench'))->write('Apple weighed', 'Pear weighed');
    $tester = $this->tester($this->panel(new Table('orders'), $log))->live(static function (int $count) use ($log): void {
      $log->write(sprintf('Crate %d weighed', $count));
    });

    $tester->run(new Tick(), new Tick(), new Tick());

    $first = $this->lines($tester->frame(0));
    $last = $this->lines($tester->frame(3));

    $this->assertTrue($this->contains($first, 'Apple weighed'));
    $this->assertTrue($this->contains($first, 'Pear weighed'));
    $this->assertFalse($this->contains($last, 'Apple weighed'));
    $this->assertFalse($this->contains($last, 'Pear weighed'));
    $this->assertTrue($this->contains($last, 'Crate 1 weighed'));
    $this->assertTrue($this->contains($last, 'Crate 3 weighed'));
    // The older lines are above the window, so its first row carries the mark.
    $this->assertTrue($this->contains($last, 'Crate 1 weighed', '▲'));
  }

  public function testLogLimitDropsLinesTheRegionWouldOtherwiseKeep(): void {
    $log = new Log('bench', 2);
    $tester = $this->tester($this->panel(new Table('orders'), $log))->live(static function (int $count) use ($log): void {
      $log->write(sprintf('Crate %d weighed', $count));
    });

    $tester->run(new Tick(), new Tick(), new Tick());

    $last = $this->lines($tester->frame(-1));

    $this->assertSame(['Crate 2 weighed', 'Crate 3 weighed'], $log->lines());
    $this->assertFalse($this->contains($last, 'Crate 1 weighed'));
    // Everything the log holds fits, so nothing is marked as above it.
    $this->assertFalse($this->contains($last, '▲'));
  }

  public function testReplacingRowsKeepsTheMarkOnTheSameRow(): void {
    $table = (new Table('orders', ['Fruit', 'Crates']))->rows(['apple' => ['Apple', 1], 'pear' => ['Pear', 1]])->select('pear');
    $tester = $this->tester($this->panel($table, new Log('bench')))->live(static function (int $count) use ($table): void {
      $table->rows(['fig' => ['Fig', $count], 'apple' => ['Apple', $count + 1], 'pear' => ['Pear', $count + 2]]);
    });

    $tester->run(new Tick());

    $this->assertTrue($this->contains($this->lines($tester->frame(0)), '❯ │ Pear'));
    $this->assertTrue($this->contains($this->lines($tester->frame(1)), '❯ │ Pear  │ 3'));
    $this->assertFalse($this->contains($this->lines($tester->frame(1)), '❯ │ Fig'));
    $this->assertSame('pear', $table->selected());
  }

  /**
   * A tester over a panel, sized so a frame reads the same everywhere.
   */
  protected function tester(Panel $panel): ScreenTester {
    return (new ScreenTester($panel))->rows(20)->cols(60)->options(['unicode' => TRUE, 'color' => FALSE]);
  }

  /**
   * A panel with the table on its board and the log in its following region.
   */
  protected function panel(Table $table, Log $log): Panel {
    $panel = (new Panel('bench', 'Packing bench'))->layout(new BenchLayout());
    $panel->in('board')->add($table);
    $panel->in('log')->add($log);

    return $panel;
  }

  /**
   * A frame's rows, stripped of escape sequences.
   *
   * @return list<string>
   *   The rows.
   */
  protected function lines(string $frame): array {
    return explode("\n", Ansi::strip($frame));
  }

  /**
   * Whether 1 row holds every one of the given fragments.
   *
   * @param list<string> $lines
   *   The rows.
   */
  protected function contains(array $lines, string ...$fragments): bool {
    foreach ($lines as $line) {
      $found = array_filter($fragments, static fn(string $fragment): bool => str_contains($line, $fragment));

      if (count($found) === count($fragments)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
