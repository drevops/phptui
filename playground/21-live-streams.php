<?php

/**
 * @file
 * Live streams: a form that redraws the moment a child process writes.
 *
 * `->watch()` adds a stream to the wait for a key, so output on it runs a
 * callback at once instead of on the next tick. Here a child process sorts
 * crates at the packing bench and reports each one on its output pipe; the
 * callback shows the latest report in a card beside the trail. Once the child
 * finishes, its pipe reaches its end and is no longer watched. The fields keep
 * answering keys the whole time.
 *
 * Usage:
 *   php playground/21-live-streams.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Block\Markup;
use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Screen\Axis;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// The child sorts one crate every 0.7 seconds and reports it on its own line.
$sorter = <<<'PHP'
foreach (['Apples', 'Pears', 'Plums', 'Cherries', 'Peaches', 'Melons'] as $index => $fruit) {
  usleep(700000);
  echo sprintf('Sorted crate %d of 6: %s', $index + 1, $fruit), PHP_EOL;
}
echo 'All crates sorted', PHP_EOL;
PHP;

$child = proc_open([PHP_BINARY, '-r', $sorter], [1 => ['pipe', 'w']], $pipes);

if (!is_resource($child)) {
  fwrite(STDERR, 'The sorter could not be started.' . PHP_EOL);
  exit(1);
}

$pipe = $pipes[1];
// A read on a blocking pipe would hold the screen until the child writes again.
stream_set_blocking($pipe, FALSE);

$bench = new Markup('bench', 'Waiting for the sorter...');
$pending = '';

$form = Form::create('Packing bench')
  ->panel('Packing', function (PanelBuilder $p): void {
    $p->select('Box size')->options(['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'])->default('medium');
    $p->confirm('Add a recipe card?')->default(TRUE);
  });

$tui = (new Tui($form))->place('header', $bench, tail: TRUE)->flow('header', Axis::Columns)->watch($pipe, function ($stream) use ($bench, &$pending): void {
  $pending .= (string) fread($stream, 8192);
  $lines = explode(PHP_EOL, $pending);
  // The last piece is a line the child has not finished writing yet.
  $pending = (string) array_pop($lines);
  $lines = array_filter($lines, static fn(string $line): bool => $line !== '');

  if ($lines !== []) {
    $bench->body((string) end($lines));
  }
});

$code = 0;

try {
  // Interactive on a terminal; headless otherwise, where nothing is watched.
  echo $tui->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  $code = 130;
}

// Stop a sorter still running when the form ends.
fclose($pipe);
proc_terminate($child);
proc_close($child);

exit($code);
