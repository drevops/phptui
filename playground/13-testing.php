<?php

/**
 * @file
 * Test harness: drive the interactive TUI from scripted keystrokes.
 *
 * TuiTester runs the real panel loop against an in-memory terminal - no TTY,
 * no recording, fully deterministic. Keystrokes go in as Key objects or raw
 * byte strings; out come the collected answers, the captured output and the
 * final rendered frame, ready for assertions. This script is the harness
 * outside PHPUnit; in a test, the same calls sit behind assertSame().
 *
 * Usage:
 *   php playground/13-testing.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\Input\Key;
use DrevOps\PhpTui\Input\KeyName;
use DrevOps\PhpTui\Terminal\Ansi;
use DrevOps\PhpTui\Testing\TuiTester;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Produce order')
  ->panel('New order', function (PanelBuilder $p): void {
    $p->text('Order name')->default('Weekly');
    $p->select('Fruit')->default('banana')->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
    ]);
    $p->confirm('Organic only?')->default(FALSE);
  });

// The same script a person would type: drill into the panel, open the name,
// append to the default, accept, then leave the panel and submit. Named keys
// encode to their canonical bytes; raw strings like "\r" work too.
$tester = new TuiTester($form);
$answers = $tester->run(
  // Enter the "New order" panel from the hub.
  Key::named(KeyName::Enter),
  // Open the name field's inline editor.
  Key::named(KeyName::Enter),
  // The caret sits after "Weekly": type the rest of the name.
  ' ', 'B', 'o', 'x',
  // Accept the field.
  Key::named(KeyName::Enter),
  // Back out of the panel to the hub.
  Key::named(KeyName::Escape),
  // Move onto the Submit button and press it.
  Key::named(KeyName::Down),
  Key::named(KeyName::Enter),
);

// The collected answers - in a PHPUnit test these become assertions, e.g.
// $this->assertSame('Weekly Box', $answers->value('order_name')).
echo 'name    = ' . var_export($answers->value('order_name'), TRUE) . PHP_EOL;
echo 'fruit   = ' . var_export($answers->value('fruit'), TRUE) . PHP_EOL;
echo 'organic = ' . var_export($answers->value('organic_only'), TRUE) . PHP_EOL;
echo PHP_EOL;

// How the run ended: submitted, cancelled via the Cancel button, or
// interrupted with Ctrl-C.
echo 'cancelled:   ' . var_export($tester->isCancelled(), TRUE) . PHP_EOL;
echo 'interrupted: ' . var_export($tester->isInterrupted(), TRUE) . PHP_EOL;
echo PHP_EOL;

// display() is everything the run ever rendered with the ANSI stripped -
// made for substring assertions on what the user saw at any point.
echo 'rendered the edit: ' . var_export(str_contains($tester->display(), 'Weekly Box'), TRUE) . PHP_EOL;
echo PHP_EOL;

// output() is the raw capture; each repaint starts with the clear-screen
// sequence, so the last chunk is the final frame.
$frames = array_filter(explode("\033[2J\033[H", $tester->output()));
echo '--- final frame ---' . PHP_EOL;
echo Ansi::strip((string) end($frames)) . PHP_EOL;
