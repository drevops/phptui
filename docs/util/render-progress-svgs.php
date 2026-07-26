#!/usr/bin/env php
<?php

/**
 * @file
 * Render the progress primitive's animated and static SVGs deterministically.
 *
 * The progress primitive is not a keystroke-driven widget: it is a single line
 * the theme redraws in place with a carriage return while a callback runs. So
 * this drives the real {@see \DrevOps\Tui\Primitive\Progress} against an
 * in-memory terminal, splits the captured output into frames on the carriage
 * return, and lays them into an asciicast for the shared svg-term renderer -
 * both an animation (every frame) and a single static frame - in all four glyph
 * and colour display modes. Each dark SVG derives its light twin in the same
 * pass. The result is reproducible on any machine (and in CI).
 *
 * Dependencies: node, npm (for the svg-term renderer shared with update-assets).
 *
 * Usage:
 * @code
 * php docs/util/render-progress-svgs.php            # both subjects
 * php docs/util/render-progress-svgs.php spinner    # one subject by name
 * @endcode
 */

declare(strict_types=1);

use DrevOps\Tui\Primitive\Progress;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\TerminalControl;
use DrevOps\Tui\Testing\BufferedTerminal;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Theme\ThemeManager;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/svg-slowdown.php';
require_once __DIR__ . '/svg-light-twin.php';

// Seconds each captured frame is held, mirroring the widget renderer's cadence.
const HOLD_FIRST = 1.1;
const HOLD_STEP = 0.65;
const HOLD_LAST = 2.2;

// The rendered progress line plus a blank row of breathing space above and below.
const PROGRESS_ROWS = 3;

// The four glyph and colour display modes, keyed by the filename suffix. Unicode
// and colour are the unmarked default.
const DISPLAY_MODES = [
  '' => ['color' => TRUE, 'unicode' => TRUE],
  '-ascii' => ['color' => TRUE, 'unicode' => FALSE],
  '-no-ansi' => ['color' => FALSE, 'unicode' => TRUE],
  '-ascii-no-ansi' => ['color' => FALSE, 'unicode' => FALSE],
];

/**
 * The progress subjects and the advances that drive them.
 *
 * Each mirrors a playground/15-progress-* script - same caption and steps - so
 * the rendered cards match the code a reader runs. A NULL total is the
 * indeterminate spinner; a known total is the determinate bar.
 *
 * @return array<string, array{total: int|null, caption: string, labels: list<string|null>}>
 *   The subject specs keyed by asset name.
 */
function progressSpecs(): array {
  return [
    'spinner' => [
      'total' => NULL,
      'caption' => 'Counting the baskets',
      'labels' => [NULL, NULL, NULL, NULL, NULL, NULL],
    ],
    'bar' => [
      'total' => 6,
      'caption' => 'Packing the order',
      'labels' => ['packed Apple', 'packed Carrot', 'packed Tomato', 'packed Spinach', 'packed Pear', 'packed Beet'],
    ],
  ];
}

/**
 * Drive one subject and write its animated and static SVGs, in every mode.
 *
 * @param string $name
 *   The asset name (spinner or bar).
 * @param array{total: int|null, caption: string, labels: list<string|null>} $spec
 *   The subject spec.
 * @param string $assets_dir
 *   The output directory.
 * @param string $util_dir
 *   The tooling directory holding the svg-term renderer.
 * @param string $tmp_dir
 *   A scratch directory for the intermediate cast.
 */
function renderProgress(string $name, array $spec, string $assets_dir, string $util_dir, string $tmp_dir): void {
  foreach (DISPLAY_MODES as $suffix => $mode) {
    $theme = ThemeManager::create('default', DefaultTheme::DEFAULT_WIDTH, ['color' => $mode['color'], 'unicode' => $mode['unicode'], 'mode' => Mode::Dark]);
    $frames = captureFrames($theme, $spec);

    if (count($frames) < 2) {
      throw new \RuntimeException(sprintf('Progress "%s" (%s) produced %d frame(s); an animation needs at least two.', $name, $suffix === '' ? 'default' : $suffix, count($frames)));
    }

    $width = castWidth($frames);

    $cast_file = $tmp_dir . '/progress-' . $name . '-animated' . $suffix . '.cast';
    file_put_contents($cast_file, buildCast($frames, $width));
    $animated = $assets_dir . '/progress-' . $name . '-dark-animated' . $suffix . '.svg';
    renderCast($cast_file, $animated, $util_dir);
    file_put_contents($animated, slowAnimation((string) file_get_contents($animated), ANIMATION_SLOWDOWN));
    deriveLightTwin($animated);

    // A single mid-run frame: a distinctive spinner glyph, or a half-filled bar.
    $frame = $frames[intdiv(count($frames), 2)];
    $static_cast = $tmp_dir . '/progress-' . $name . '-static' . $suffix . '.cast';
    file_put_contents($static_cast, buildCast([$frame], $width));
    $static = $assets_dir . '/progress-' . $name . '-dark-static' . $suffix . '.svg';
    renderCast($static_cast, $static, $util_dir, 0);
    deriveLightTwin($static);
  }

  printf("  progress-%s-{dark,light}-{animated,static}*.svg (4 display modes)\n", $name);
}

/**
 * Drive the real primitive and split its output into whole rendered frames.
 *
 * The primitive redraws its line with a carriage return, so splitting on it
 * yields one entry per repaint; the cursor, erase and settle control sequences
 * are stripped to leave the visible line, and the finish repaint (a duplicate of
 * the last state) is folded away.
 *
 * @param \DrevOps\Tui\Theme\DefaultTheme $theme
 *   The theme that draws the line.
 * @param array{total: int|null, caption: string, labels: list<string|null>} $spec
 *   The subject spec.
 *
 * @return list<string>
 *   The visible frame strings, in order.
 */
function captureFrames(DefaultTheme $theme, array $spec): array {
  $terminal = new BufferedTerminal();
  $progress = new Progress($terminal, $theme, TRUE, $spec['total'], $spec['caption']);

  $progress->run(static function (Progress $p) use ($spec): void {
    foreach ($spec['labels'] as $label) {
      $p->advance($label);
    }
  });

  $noise = [TerminalControl::hideCursor(), TerminalControl::showCursor(), TerminalControl::eraseToLineEnd(), TerminalControl::eraseLine(), "\n"];

  $frames = [];
  foreach (explode("\r", $terminal->output()) as $part) {
    $line = str_replace($noise, '', $part);

    if (trim(Ansi::strip($line)) === '') {
      continue;
    }
    if ($frames !== [] && end($frames) === $line) {
      continue;
    }

    $frames[] = $line;
  }

  return $frames;
}

/**
 * The width in columns the frames need, from the widest visible line.
 *
 * @param list<string> $frames
 *   The frames.
 *
 * @return int
 *   The column count.
 */
function castWidth(array $frames): int {
  $width = 0;

  foreach ($frames as $frame) {
    $width = max($width, Ansi::width($frame));
  }

  return $width;
}

/**
 * Assemble an asciicast v2 that redraws the single line as an animation.
 *
 * @param list<string> $frames
 *   The captured frames.
 * @param int $width
 *   The terminal width in columns.
 *
 * @return string
 *   The cast file contents.
 */
function buildCast(array $frames, int $width): string {
  // Two spare columns past the content: writing to the very last column leaves
  // the cursor in the terminal's pending-wrap state, and the emulator drops that
  // final glyph. The slack keeps every line clear of the edge.
  $lines = [json_encode(['version' => 2, 'width' => $width + 2, 'height' => PROGRESS_ROWS])];

  $last = count($frames) - 1;
  $time = 0.0;
  foreach ($frames as $index => $frame) {
    // Each frame returns to column zero on the middle row and erases the tail a
    // shorter frame would leave; the first frame also hides the cursor.
    $prefix = $index === 0 ? TerminalControl::hideCursor() . "\n" : "\r";
    $lines[] = json_encode([round($time, 3), 'o', $prefix . $frame . Ansi::ESC . '[K']);

    if ($index < $last) {
      $time += $index === 0 ? HOLD_FIRST : HOLD_STEP;
    }
  }

  $lines[] = json_encode([round($time + HOLD_LAST, 3), 'o', ' ']);

  return implode("\n", $lines) . "\n";
}

/**
 * Render a cast to an SVG with the shared svg-term renderer.
 *
 * @param string $cast_file
 *   The input cast path.
 * @param string $svg_file
 *   The output SVG path.
 * @param string $util_dir
 *   The directory holding svg-term-render.js.
 * @param int|null $at
 *   A millisecond timestamp to capture a single static frame, or NULL for the
 *   whole animation.
 */
function renderCast(string $cast_file, string $svg_file, string $util_dir, ?int $at = NULL): void {
  if (is_file($svg_file)) {
    unlink($svg_file);
  }

  $cmd = sprintf(
    'node %s %s %s --line-height 1.1%s 2>&1',
    escapeshellarg($util_dir . '/svg-term-render.js'),
    escapeshellarg($cast_file),
    escapeshellarg($svg_file),
    $at !== NULL ? sprintf(' --at %d', $at) : ''
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
$tmp_dir = dirname(__DIR__, 2) . '/.artifacts/tmp/progress-svgs';

if (!is_dir($tmp_dir)) {
  mkdir($tmp_dir, 0755, TRUE);
}

$specs = progressSpecs();
$only = array_slice($argv, 1);
$names = $only === [] ? array_keys($specs) : $only;

info('Rendering ' . count($names) . ' progress subject(s)...');

foreach ($names as $name) {
  if (!isset($specs[$name])) {
    throw new \RuntimeException('Unknown progress subject: ' . $name);
  }

  renderProgress($name, $specs[$name], $assets_dir, $util_dir, $tmp_dir);
}

info('Done.');
