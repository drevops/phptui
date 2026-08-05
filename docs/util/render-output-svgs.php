#!/usr/bin/env php
<?php

/**
 * @file
 * Render the output primitive's static SVGs deterministically.
 *
 * The output primitives write finished lines and return - there is nothing to
 * animate, unlike the progress primitive that redraws one line in place - so
 * each subject renders as a single static frame rather than an animation. This
 * drives the real {@see \DrevOps\Tui\Primitive\Output} against an in-memory
 * terminal, lays the captured lines into an asciicast for the shared svg-term
 * renderer, and does it in all four glyph and colour display modes. Each dark
 * SVG derives its light twin in the same pass. The result is reproducible on
 * any machine (and in CI).
 *
 * Dependencies: node, npm (for the svg-term renderer shared with update-assets).
 *
 * Usage:
 * @code
 * php docs/util/render-output-svgs.php          # every subject
 * php docs/util/render-output-svgs.php box      # one subject by name
 * @endcode
 */

declare(strict_types=1);

use DrevOps\Tui\Primitive\Output;
use DrevOps\Tui\Primitive\Status;
use DrevOps\Tui\Terminal\Ansi;
use DrevOps\Tui\Terminal\TerminalControl;
use DrevOps\Tui\Testing\BufferedTerminal;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Theme\ThemeManager;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/svg-light-twin.php';

// A blank row of breathing space above and below the rendered lines.
const OUTPUT_PADDING_ROWS = 2;

// Seconds the single frame is held, so the cast has a duration to capture at.
const OUTPUT_HOLD = 2.2;

// The four glyph and colour display modes, keyed by the filename suffix. Unicode
// and colour are the unmarked default.
const DISPLAY_MODES = [
  '' => ['color' => TRUE, 'unicode' => TRUE],
  '-ascii' => ['color' => TRUE, 'unicode' => FALSE],
  '-no-ansi' => ['color' => FALSE, 'unicode' => TRUE],
  '-ascii-no-ansi' => ['color' => FALSE, 'unicode' => FALSE],
];

/**
 * The output subjects and the calls that draw them.
 *
 * Each mirrors a playground/18-output-* script - same content and calls - so
 * the rendered cards match the code a reader runs.
 *
 * @return array<string, callable(\DrevOps\Tui\Primitive\Output): void>
 *   The subject writers keyed by asset name.
 */
function outputSpecs(): array {
  return [
    'box' => static function (Output $out): void {
      $out->box([
        'Everything below is picked the morning it ships.',
        '',
        'Nothing is charged until the box leaves the packing shed.',
      ], 'Welcome to the produce box');
    },
    'status' => static function (Output $out): void {
      $out->info('Checking the morning harvest')
        ->success('Apricots picked and weighed')
        ->warning('Only two crates of pears left')
        ->error('The cherry shelf is empty')
        ->note('Anything short is refunded, never substituted');
    },
    'table' => static function (Output $out): void {
      $out->table(['Item', 'Crates', 'Picked'], [
        ['Apricot', '4', 'Tuesday'],
        ['Peach', '2', 'Tuesday'],
        ['Carrot', '6', 'Wednesday'],
      ]);
    },
    'card' => static function (Output $out): void {
      $out->card('Loaded for delivery', 'Everything below leaves the shed at seven.', ['Item', 'Crates'], [
        ['Apricot', '4'],
        ['Peach', '2'],
      ]);
    },
    'text' => static function (Output $out): void {
      $out->banner('Produce Box', '1.2.3');
      $out->rule();
      $out->text('Every box is picked the morning it ships. Nothing is charged until the crates leave the packing shed.');
      $out->rule();
    },
    'definitions' => static function (Output $out): void {
      $out->box('Your order is packed.', 'Summary');
      $out->definitions([
        'Order' => 'Summer Box',
        'Fruit' => 'Apricot, Peach, Plum',
        'Vegetables' => 'Carrot, Spinach, Tomato',
        'Quantity' => '6 baskets',
        'Note' => 'Everything is picked the morning it ships, packed in the shed, and loaded onto the van before seven.',
      ]);
      $out->status(Status::Success, 'Leaving the packing shed at 07:00');
    },
  ];
}

/**
 * Draw one subject and write its static SVG, in every display mode.
 *
 * @param string $name
 *   The asset name (box, status or definitions).
 * @param callable(\DrevOps\Tui\Primitive\Output): void $spec
 *   The subject writer.
 * @param string $assets_dir
 *   The output directory.
 * @param string $util_dir
 *   The tooling directory holding the svg-term renderer.
 * @param string $tmp_dir
 *   A scratch directory for the intermediate cast.
 */
function renderOutput(string $name, callable $spec, string $assets_dir, string $util_dir, string $tmp_dir): void {
  foreach (DISPLAY_MODES as $suffix => $mode) {
    $theme = ThemeManager::create('default', ThemeInterface::DEFAULT_WIDTH, ['color' => $mode['color'], 'unicode' => $mode['unicode'], 'mode' => Mode::Dark]);
    $lines = captureLines($theme, $spec);

    if ($lines === []) {
      throw new \RuntimeException(sprintf('Output "%s" (%s) produced no lines.', $name, $suffix === '' ? 'default' : $suffix));
    }

    $cast_file = $tmp_dir . '/output-' . $name . $suffix . '.cast';
    file_put_contents($cast_file, buildCast($lines));

    $static = $assets_dir . '/output-' . $name . '-dark-static' . $suffix . '.svg';
    renderCast($cast_file, $static, $util_dir);
    deriveLightTwin($static);
  }

  printf("  output-%s-{dark,light}-static*.svg (4 display modes)\n", $name);
}

/**
 * Drive the real primitive and capture the lines it writes.
 *
 * @param \DrevOps\Tui\Theme\DefaultTheme $theme
 *   The theme that draws the pieces.
 * @param callable(\DrevOps\Tui\Primitive\Output): void $spec
 *   The subject writer.
 *
 * @return list<string>
 *   The written lines, in order.
 */
function captureLines(DefaultTheme $theme, callable $spec): array {
  $terminal = new BufferedTerminal();
  $spec(new Output($terminal, $theme));

  $output = rtrim($terminal->output(), "\n");

  return $output === '' ? [] : explode("\n", $output);
}

/**
 * Assemble an asciicast v2 that paints the lines as one static frame.
 *
 * @param list<string> $lines
 *   The captured lines.
 *
 * @return string
 *   The cast file contents.
 */
function buildCast(array $lines): string {
  $width = 0;

  foreach ($lines as $line) {
    $width = max($width, Ansi::width($line));
  }

  // Two spare columns past the content: writing to the very last column leaves
  // the cursor in the terminal's pending-wrap state, and the emulator drops that
  // final glyph. The slack keeps every line clear of the edge.
  $header = ['version' => 2, 'width' => $width + 2, 'height' => count($lines) + OUTPUT_PADDING_ROWS];

  // A raw terminal needs the carriage return to reset the column on each row,
  // and the leading one opens the blank row above the content.
  $payload = TerminalControl::hideCursor() . "\r\n" . implode("\r\n", $lines);

  return implode("\n", [
    json_encode($header),
    json_encode([0.0, 'o', $payload]),
    json_encode([OUTPUT_HOLD, 'o', ' ']),
  ]) . "\n";
}

/**
 * Render a cast to a single static SVG with the shared svg-term renderer.
 *
 * @param string $cast_file
 *   The input cast path.
 * @param string $svg_file
 *   The output SVG path.
 * @param string $util_dir
 *   The directory holding svg-term-render.js.
 */
function renderCast(string $cast_file, string $svg_file, string $util_dir): void {
  if (is_file($svg_file)) {
    unlink($svg_file);
  }

  $cmd = sprintf(
    'node %s %s %s --line-height 1.1 --at 0 2>&1',
    escapeshellarg($util_dir . '/svg-term-render.js'),
    escapeshellarg($cast_file),
    escapeshellarg($svg_file)
  );
  $output = shell_exec($cmd);

  if (!file_exists($svg_file) || filesize($svg_file) === 0) {
    throw new \RuntimeException('Failed to render SVG: ' . $svg_file . "\n" . ($output ?? ''));
  }
}

/**
 * Print an informational message unless quietened.
 *
 * @param string $message
 *   The message.
 */
function info(string $message): void {
  if (getenv('SCRIPT_QUIET') !== '1') {
    print $message . PHP_EOL;
  }
}

// Entrypoint.
ini_set('display_errors', '1');

if (PHP_SAPI !== 'cli') {
  die('This script can be only ran from the command line.');
}

$util_dir = __DIR__;
$assets_dir = dirname(__DIR__) . '/assets';
$tmp_dir = dirname(__DIR__, 2) . '/.artifacts/tmp/output-svgs';

if (!is_dir($tmp_dir)) {
  mkdir($tmp_dir, 0755, TRUE);
}

$specs = outputSpecs();
$only = array_slice($argv, 1);
$names = $only === [] ? array_keys($specs) : $only;

info('Rendering ' . count($names) . ' output subject(s)...');

foreach ($names as $name) {
  if (!isset($specs[$name])) {
    throw new \RuntimeException('Unknown output subject: ' . $name);
  }

  renderOutput($name, $specs[$name], $assets_dir, $util_dir, $tmp_dir);
}

info('Done.');
