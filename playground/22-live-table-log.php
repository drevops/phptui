<?php

/**
 * @file
 * Live table and log: crates weighed at the bench, redrawn on a timer.
 *
 * A table block holds 1 row per fruit and a log block holds the latest
 * weighings. Every tick replaces the table rows and writes a line to the log.
 * The selected row stays on the same fruit as the rows reorder, and the log
 * keeps its last 20 lines in the region layouts/BenchLayout.php declares to
 * follow its tail. "n" and "p" move the selection from the panels.
 *
 * Usage:
 *   php playground/22-live-table-log.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Answers\Answers;
use DrevOps\PhpTui\Block\Log;
use DrevOps\PhpTui\Block\Table;
use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\Input\Command;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;
use Playground\Layouts\BenchLayout;

require __DIR__ . '/../vendor/autoload.php';
// The require makes the class loadable; a real consumer would autoload it.
require __DIR__ . '/layouts/BenchLayout.php';

$fruit = ['apple' => 'Apple', 'pear' => 'Pear', 'plum' => 'Plum', 'cherry' => 'Cherry'];
$weights = array_fill_keys(array_keys($fruit), 0);
$crates = array_fill_keys(array_keys($fruit), 0);

// The heaviest fruit goes first, so the rows move as the weights change.
$rows = static function () use ($fruit, &$weights, &$crates): array {
  arsort($weights);
  $rows = [];

  foreach ($weights as $key => $grams) {
    $rows[$key] = [$fruit[$key], $crates[$key], $grams];
  }

  return $rows;
};

$table = (new Table('crates', ['Fruit', 'Crates', 'Weight (g)']))->rows($rows())->select('apple');
$log = (new Log('weighings', 20))->write('Waiting for the first crate...');

$form = Form::create('Weighing station')
  ->panel('Bench', function (PanelBuilder $p) use ($table, $log): void {
    $p->layout(BenchLayout::class);
    $p->confirm('Organic only?')->default(TRUE);
    $p->add($table);
    $p->in('log')->add($log);
  });

$tui = (new Tui($form))
  ->keys('default', [
    new Command('next', function (Answers $answers) use ($table): void {
      $table->move(1);
    }, 'n'),
    new Command('previous', function (Answers $answers) use ($table): void {
      $table->move(-1);
    }, 'p'),
  ])
  ->live(function (int $count) use ($fruit, &$weights, &$crates, $rows, $table, $log): void {
    // Every tick weighs 1 more crate, cycling through the fruit on the bench.
    $keys = array_keys($fruit);
    $key = $keys[($count - 1) % count($keys)];
    $grams = 1200 + (($count * 137) % 900);

    $crates[$key]++;
    $weights[$key] += $grams;

    $table->rows($rows());
    $log->write(sprintf('Crate %d: %s, %d g', $count, $fruit[$key], $grams));
  }, 500);

try {
  // Interactive on a terminal; headless otherwise, where nothing ticks.
  echo $tui->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
