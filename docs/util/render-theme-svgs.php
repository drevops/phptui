#!/usr/bin/env php
<?php

/**
 * @file
 * Render the built-in theme preview SVGs deterministically.
 *
 * Each built-in theme is captured on the drilled-in Preview panel of the form
 * the playground/09-themes-* scripts declare, through the library's own
 * scripted-keystroke harness - no terminal, reproducible anywhere. The
 * adaptive themes render in four variants each - the dark and light palette,
 * borderless and inside the rounded border frame; the dos theme paints its
 * own double-line window on its own blue surface regardless of mode, so it
 * ships only the dark/light pair. Light palettes render on a light surface
 * and dos on the CGA blue one via the shared svg-term renderer.
 *
 * Output follows the shared naming convention:
 * theme-<name>-<dark|light>-static[-bordered].svg.
 *
 * Dependencies: node, npm (for the svg-term renderer shared with
 * update-assets).
 *
 * Usage:
 * @code
 * php docs/util/render-theme-svgs.php             # all themes
 * php docs/util/render-theme-svgs.php frost dos   # one or more by name
 * @endcode
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Terminal\Ansi;
use DrevOps\Tui\Testing\TuiTester;
use DrevOps\Tui\Theme\Mode;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

// The adaptive built-in themes, rendered in every mode/border variant; dos is
// handled apart because it ignores the mode and draws its own window.
const ADAPTIVE_THEMES = ['midnight', 'frost', 'ember', 'mono'];

/**
 * The preview form every theme renders - the playground/09-themes-* form.
 *
 * @return \DrevOps\Tui\Builder\Form
 *   The form.
 */
function previewForm(): Form {
  return Form::create('Theme preview')
    ->panel('preview', 'Preview', function (PanelBuilder $p): void {
      $p->text('name', 'Box name')->default('Weekly Box')->description('Shown in the header.');
      $p->select('grade', 'Grade')->default('premium')->description('Quality grade.')->options([
        'basic' => 'Basic',
        'premium' => 'Premium',
        'organic' => 'Organic',
      ]);
      $p->select('extras', 'Extras')->multiple()->default(['herbs', 'nuts'])->description('Added extras.')->options([
        'herbs' => 'Herbs',
        'nuts' => 'Nuts',
        'seeds' => 'Seeds',
        'flowers' => 'Flowers',
      ]);
      $p->confirm('gift', 'Gift wrap')->default(TRUE)->description('Wrap the box as a gift.');
    });
}

/**
 * Capture the drilled-in Preview frame for one theme/mode/border variant.
 *
 * @param string $theme
 *   The theme name.
 * @param \DrevOps\Tui\Theme\Mode $mode
 *   The palette mode.
 * @param bool $bordered
 *   Whether the rounded border frames the panel.
 * @param int $rows
 *   The terminal height.
 *
 * @return string
 *   The final rendered frame, cleaned for an asciicast.
 */
function captureFrame(string $theme, Mode $mode, bool $bordered, int $rows): string {
  // The bordered twin rides the default padded rounded box; the plain twin
  // opts out of the frame explicitly to show the theme's bare rows. Dos is
  // never opted out: its double-line window is the theme's own unset-border
  // default and the point of its preview.
  $options = ['color' => TRUE, 'unicode' => TRUE, 'mode' => $mode];

  if (!$bordered && $theme !== 'dos') {
    $options['border'] = 'none';
    $options['spacing'] = 'normal';
  }

  $tester = (new TuiTester(previewForm()))->theme($theme)->options($options)->rows($rows);
  // One Enter drills the hub into the Preview panel; the captured frame shows
  // the themed labels, values, descriptions and the selection marker.
  $tester->run(Key::named(KeyName::Enter));

  $frames = splitThemeFrames($tester->output());

  if ($frames === []) {
    throw new \RuntimeException(sprintf('Theme "%s" (%s%s) produced no frame.', $theme, $mode->name, $bordered ? ', bordered' : ''));
  }

  return $frames[count($frames) - 1];
}

/**
 * Split captured output into whole rendered frames.
 *
 * Every frame the panel loop draws is prefixed by the clear-screen sequence,
 * so splitting on it yields one entry per repaint; the leading setup chunk
 * and any blank tail are dropped. Bare line feeds gain carriage returns for
 * the SVG terminal emulator.
 *
 * @param string $output
 *   The captured terminal output.
 *
 * @return list<string>
 *   The frame byte strings, in order.
 */
function splitThemeFrames(string $output): array {
  $clear = Ansi::ESC . '[2J' . Ansi::ESC . '[H';
  $parts = explode($clear, $output);
  $frames = array_values(array_filter($parts, static fn(string $s): bool => trim(Ansi::strip($s)) !== ''));

  return array_map(static fn(string $frame): string => str_replace("\n", "\r\n", str_replace("\r", '', $frame)), $frames);
}

/**
 * The width in columns a frame needs, from its widest visible line.
 *
 * @param string $frame
 *   The frame.
 *
 * @return int
 *   The column count.
 */
function themeFrameWidth(string $frame): int {
  $width = 0;

  foreach (explode("\n", str_replace("\r", '', $frame)) as $line) {
    $width = max($width, Ansi::width($line));
  }

  return $width;
}

/**
 * Write a single-frame cast and render it to a static SVG.
 *
 * @param string $frame
 *   The frame to render.
 * @param int $rows
 *   The terminal height.
 * @param string $svg_file
 *   The output SVG path.
 * @param string $util_dir
 *   The tooling directory holding the svg-term renderer.
 * @param string $tmp_dir
 *   A scratch directory for the intermediate cast.
 * @param string $surface
 *   The renderer surface flag: '', '--light' or '--dos'.
 */
function renderThemeFrame(string $frame, int $rows, string $svg_file, string $util_dir, string $tmp_dir, string $surface): void {
  $clear = Ansi::ESC . '[2J' . Ansi::ESC . '[H';
  $cast = json_encode(['version' => 2, 'width' => themeFrameWidth($frame), 'height' => $rows]) . "\n"
    . json_encode([0.0, 'o', $clear . $frame]) . "\n";
  $cast_file = $tmp_dir . '/' . basename($svg_file, '.svg') . '.cast';
  file_put_contents($cast_file, $cast);

  // Clear any prior output first, so a failed render leaves no stale file.
  if (is_file($svg_file)) {
    unlink($svg_file);
  }

  $cmd = sprintf(
    'node %s %s %s --line-height 1.1 --at 0%s 2>&1',
    escapeshellarg($util_dir . '/svg-term-render.js'),
    escapeshellarg($cast_file),
    escapeshellarg($svg_file),
    $surface === '' ? '' : ' ' . $surface
  );
  $output = shell_exec($cmd);

  if (!file_exists($svg_file) || filesize($svg_file) === 0) {
    throw new \RuntimeException('Failed to render SVG: ' . $svg_file . "\n" . ($output ?? ''));
  }
}

/**
 * Verify a generated theme SVG shows the preview content.
 *
 * @param string $svg_file
 *   Path to the generated SVG.
 */
function verifyThemeSvg(string $svg_file): void {
  $content = (string) file_get_contents($svg_file);
  $text = html_entity_decode(strip_tags($content));

  foreach (['Box', 'name', 'Grade', 'Extras'] as $word) {
    if (!str_contains($text, $word)) {
      throw new \RuntimeException(sprintf('Generated SVG "%s" is missing expected text "%s".', basename($svg_file), $word));
    }
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
$tmp_dir = dirname(__DIR__, 2) . '/.artifacts/tmp/theme-svgs';

if (!is_dir($tmp_dir)) {
  mkdir($tmp_dir, 0755, TRUE);
}

$only = array_slice($argv, 1);
$names = $only === [] ? [...ADAPTIVE_THEMES, 'dos'] : $only;

info('Rendering ' . count($names) . ' theme preview(s)...');

foreach ($names as $name) {
  if (!in_array($name, ADAPTIVE_THEMES, TRUE) && $name !== 'dos') {
    throw new \RuntimeException('Unknown theme: ' . $name);
  }

  // The dos window wraps the whole field list only on the classic 80-column
  // screen; the adaptive themes fit the narrower default width. Dos keeps
  // its own window and the default padded spacing, so it carries the two
  // padding rows in its budget.
  $rows = $name === 'dos' ? 18 : 15;
  $surface_dark = $name === 'dos' ? '--dos' : '';
  $surface_light = $name === 'dos' ? '--dos' : '--light';

  // Borderless dark and light.
  renderThemeFrame(captureFrame($name, Mode::Dark, FALSE, $rows), $rows, $assets_dir . '/theme-' . $name . '-dark-static.svg', $util_dir, $tmp_dir, $surface_dark);
  renderThemeFrame(captureFrame($name, Mode::Light, FALSE, $rows), $rows, $assets_dir . '/theme-' . $name . '-light-static.svg', $util_dir, $tmp_dir, $surface_light);
  verifyThemeSvg($assets_dir . '/theme-' . $name . '-dark-static.svg');
  verifyThemeSvg($assets_dir . '/theme-' . $name . '-light-static.svg');

  // The rounded-border twins; dos draws its own double-line window instead.
  // The default padded box needs the border and padding rows in the budget.
  if ($name !== 'dos') {
    renderThemeFrame(captureFrame($name, Mode::Dark, TRUE, $rows + 4), $rows + 4, $assets_dir . '/theme-' . $name . '-dark-static-bordered.svg', $util_dir, $tmp_dir, $surface_dark);
    renderThemeFrame(captureFrame($name, Mode::Light, TRUE, $rows + 4), $rows + 4, $assets_dir . '/theme-' . $name . '-light-static-bordered.svg', $util_dir, $tmp_dir, $surface_light);
    verifyThemeSvg($assets_dir . '/theme-' . $name . '-dark-static-bordered.svg');
    verifyThemeSvg($assets_dir . '/theme-' . $name . '-light-static-bordered.svg');
  }

  info('  theme-' . $name . '-*-static*.svg');
}

info('Done.');
