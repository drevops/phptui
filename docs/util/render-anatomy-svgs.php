<?php

/**
 * @file
 * Renders the annotated widget anatomy diagrams.
 *
 * Each diagram is a real widget frame, driven through the library's own
 * keystroke harness, with vector callouts drawn onto the canvas beside it. The
 * callouts are geometry rather than characters, so nothing is injected into the
 * frame and no font metric can shift a border.
 *
 * Usage:
 * @code
 * php render-anatomy-svgs.php [<name>]
 * @endcode
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Testing\TuiTester;
use DrevOps\Tui\Theme\Mode;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/svg-light-twin.php';

/**
 * The width of one character cell, in SVG user units.
 */
const CELL_WIDTH = 10.0;

/**
 * The height of one rendered row, in SVG user units.
 */
const CELL_HEIGHT = 18.37;

/**
 * The columns reserved to the right of the frame for the callout labels.
 */
const LABEL_COLUMNS = 30;

/**
 * The diagrams to render, keyed by name.
 *
 * @param string $tree
 *   The sample project directory the file picker browses.
 *
 * @return array<string,array<string,mixed>>
 *   Each spec carries the form, the keystrokes, the row budget and the
 *   callouts, each callout naming the cell it points at.
 */
function anatomySpecs(string $tree): array {
  $enter = Key::named(KeyName::Enter);
  $down = Key::named(KeyName::Down);
  $space = Key::named(KeyName::Space);
  $tab = Key::named(KeyName::Tab);
  $bs = Key::named(KeyName::Backspace);
  $open = [$enter, $enter];

  return [
    'row' => [
      'form' => Form::create('Orchard order')->panel('main', 'Basket', function (PanelBuilder $p): void {
        $p->select('basket', 'Basket')->description('Pick the produce for this delivery.')->hint('Space toggles, Enter confirms.')->multiple()->default(['apple'])->options(['apple' => 'Apple', 'carrot' => 'Carrot']);
        $p->number('weight', 'Basket weight (g)')->description('Weighed at the packing bench.')->default(1200)->min(200)->max(9000);
      }),
      'keys' => [$enter],
      'rows' => 13,
      'callouts' => [
        ['col' => 2, 'row' => 1, 'label' => 'breadcrumb'],
        ['col' => 2, 'row' => 4, 'label' => 'marker'],
        ['col' => 4, 'row' => 4, 'label' => 'label'],
        ['col' => 12, 'row' => 4, 'label' => 'value'],
        ['col' => 6, 'row' => 5, 'label' => 'description'],
        ['col' => 6, 'row' => 6, 'label' => 'instruction'],
        ['col' => 4, 'row' => 7, 'label' => 'overflow'],
        ['col' => 2, 'row' => 10, 'label' => 'key legend'],
      ],
    ],
    'editor' => [
      'form' => Form::create('Orchard order')->panel('main', 'Basket', function (PanelBuilder $p): void {
        $p->select('basket', 'Basket')->description('Pick the produce for this delivery.')->multiple()->minSelections(2)->maxSelections(3)
          ->option('apple', 'Apple', description: 'Crisp and sweet, the everyday choice.')
          ->option('carrot', 'Carrot', description: 'Stays crisp for weeks when kept cold.')
          ->option('tomato', 'Tomato', disabled: TRUE, disabled_reason: 'out of season');
      }),
      'keys' => [...$open, $space, $down, $space],
      'rows' => 14,
      'callouts' => [
        ['col' => 13, 'row' => 4, 'label' => 'selector'],
        ['col' => 15, 'row' => 4, 'label' => 'entry'],
        ['col' => 11, 'row' => 5, 'label' => 'entry marker'],
        ['col' => 22, 'row' => 6, 'label' => 'entry note'],
        ['col' => 11, 'row' => 7, 'label' => 'constraint'],
        ['col' => 11, 'row' => 8, 'label' => 'entry detail'],
        ['col' => 6, 'row' => 9, 'label' => 'description'],
      ],
    ],
    'filepicker' => [
      'form' => Form::create('Orchard order')->panel('main', 'Price list', function (PanelBuilder $p) use ($tree): void {
        $p->filePicker('price_list', 'Price list')->description('The CSV the orchard sends each week.')->startIn($tree)->filesOnly()->extensions(['csv'])->maxSize(2097152);
      }),
      'keys' => [...$open, $down],
      'rows' => 13,
      'callouts' => [
        ['col' => 15, 'row' => 4, 'label' => 'location'],
        ['col' => 17, 'row' => 5, 'label' => 'entry'],
        ['col' => 15, 'row' => 6, 'label' => 'entry marker'],
        ['col' => 15, 'row' => 8, 'label' => 'constraint'],
      ],
    ],
    'text' => [
      'form' => Form::create('Orchard order')->panel('main', 'Crate', function (PanelBuilder $p): void {
        $p->template('crate', 'Crate label')->description('Identifies the crate on the loading dock.')->pattern('{{orchard}}-{{fruit}}-{{grade}}')->default('valley-pear-a')->slot('orchard', 'Orchard')->slot('fruit', 'Fruit')->slot('grade', 'Grade');
      }),
      'keys' => [...$open, $tab],
      'rows' => 10,
      'callouts' => [
        ['col' => 16, 'row' => 4, 'label' => 'buffer'],
        ['col' => 27, 'row' => 4, 'label' => 'caret'],
        ['col' => 16, 'row' => 5, 'label' => 'activity'],
      ],
    ],
    'error' => [
      'form' => Form::create('Orchard order')->panel('main', 'Weight', function (PanelBuilder $p): void {
        $p->number('weight', 'Basket weight (g)')->description('Weighed at the packing bench.')->default(1200)->min(200)->max(9000)->step(100);
      }),
      'keys' => [...$open, $bs, $bs, $bs, $bs, '1', $enter],
      'rows' => 10,
      'callouts' => [
        ['col' => 22, 'row' => 4, 'label' => 'buffer'],
        ['col' => 23, 'row' => 4, 'label' => 'caret'],
        ['col' => 22, 'row' => 5, 'label' => 'error'],
      ],
    ],
  ];
}

/**
 * Render one diagram's dark and light SVGs.
 *
 * @param string $name
 *   The diagram name.
 * @param array<string,mixed> $spec
 *   The diagram spec.
 * @param string $assets_dir
 *   The directory the assets are written to.
 * @param string $util_dir
 *   The directory holding the node renderer.
 * @param string $tmp_dir
 *   The scratch directory for the intermediate cast.
 */
function renderAnatomy(string $name, array $spec, string $assets_dir, string $util_dir, string $tmp_dir): void {
  $tester = (new TuiTester($spec['form']))->options(['color' => TRUE, 'unicode' => TRUE, 'mode' => Mode::Dark])->rows($spec['rows']);
  $tester->run(...$spec['keys']);

  $clear = Ansi::ESC . '[2J' . Ansi::ESC . '[H';
  $parts = explode($clear, $tester->output());
  $frames = array_values(array_filter($parts, static fn(string $s): bool => trim(Ansi::strip($s)) !== ''));

  if ($frames === []) {
    throw new \RuntimeException(sprintf('Diagram "%s" produced no frame.', $name));
  }

  $frame = str_replace("\n", "\r\n", str_replace("\r", '', $frames[count($frames) - 1]));
  $width = 0;

  foreach (explode("\r\n", $frame) as $line) {
    $width = max($width, Ansi::width($line));
  }

  $cast = json_encode(['version' => 2, 'width' => $width, 'height' => $spec['rows']]) . "\n" . json_encode([0.0, 'o', $clear . $frame]) . "\n";
  $cast_file = $tmp_dir . '/anatomy-' . $name . '.cast';
  file_put_contents($cast_file, $cast);

  $dark_file = $assets_dir . '/anatomy-' . $name . '-dark-static.svg';
  renderCast($cast_file, $dark_file, $util_dir);
  $light_file = deriveLightTwin($dark_file);

  annotate($dark_file, $spec['callouts'], 'rgb(130,138,150)', 'rgb(200,206,214)');
  annotate($light_file, $spec['callouts'], 'rgb(140,146,154)', 'rgb(60,66,74)');
}

/**
 * Draw the callouts onto a rendered frame.
 *
 * The frame keeps its own width; the canvas grows to the right so a leader can
 * run from the cell it names out to a label in the margin.
 *
 * @param string $file
 *   The SVG to annotate, rewritten in place.
 * @param array<int,array<string,mixed>> $callouts
 *   The callouts, each naming a cell by column and row.
 * @param string $leader_color
 *   The stroke colour for the leaders.
 * @param string $label_color
 *   The fill colour for the labels.
 */
function annotate(string $file, array $callouts, string $leader_color, string $label_color): void {
  $svg = file_get_contents($file);

  if ($svg === FALSE || !preg_match('/<svg[^>]*width="([\d.]+)"[^>]*height="([\d.]+)"/', $svg, $m)) {
    throw new \RuntimeException('Could not read the canvas size of ' . $file);
  }

  $frame_width = (float) $m[1];
  $height = (float) $m[2];
  $label_x = $frame_width + CELL_WIDTH * 2;
  $canvas_width = $frame_width + CELL_WIDTH * LABEL_COLUMNS;

  // The labels are spread down the canvas in the order their cells appear, so
  // several callouts on one row still get a line each.
  $count = count($callouts);
  $step = $count > 1 ? ($height - CELL_HEIGHT) / ($count - 1) : 0.0;
  $marks = '';
  $seen = [];

  foreach (array_values($callouts) as $index => $callout) {
    $row = (int) $callout['row'];
    // A leader runs out through the gap under its own row rather than across
    // the glyphs; callouts sharing a row stack their runs within that gap so
    // they stay apart.
    $rank = $seen[$row] ?? 0;
    $seen[$row] = $rank + 1;

    $cell_x = ((float) $callout['col'] + 0.5) * CELL_WIDTH;
    $cell_y = ((float) $callout['row'] + 0.5) * CELL_HEIGHT;
    $gap_y = ((float) $row + 1.0) * CELL_HEIGHT + 1.6 + $rank * 2.0;
    $label_y = CELL_HEIGHT * 0.5 + $step * $index;

    $marks .= sprintf('<circle cx="%.2f" cy="%.2f" r="2.6" fill="none" stroke="%s" stroke-width="1.1"/>', $cell_x, $cell_y, $leader_color);
    $marks .= sprintf('<path d="M %.2f %.2f L %.2f %.2f L %.2f %.2f L %.2f %.2f" fill="none" stroke="%s" stroke-width="0.8" stroke-linejoin="round" stroke-opacity="0.4"/>', $cell_x, $cell_y + 3.4, $cell_x, $gap_y, $frame_width + CELL_WIDTH, $gap_y, $label_x - 5.0, $label_y, $leader_color);
    $marks .= sprintf('<text x="%.2f" y="%.2f" fill="%s" font-family="Consolas, &quot;Courier New&quot;, Courier, monospace" font-size="12">%s</text>', $label_x, $label_y + 4.0, $label_color, htmlspecialchars((string) $callout['label'], ENT_XML1));
  }

  // The frame renders inside a nested svg of its own, so wrapping rather than
  // editing it keeps every generated coordinate inside untouched.
  $wrapped = sprintf('<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="%.2f" height="%.2f">%s<g>%s</g></svg>', $canvas_width, $height, $svg, $marks);

  file_put_contents($file, $wrapped);
}

/**
 * Render a cast file to an SVG.
 *
 * @param string $cast_file
 *   The cast file.
 * @param string $svg_file
 *   The SVG to write.
 * @param string $util_dir
 *   The directory holding the node renderer.
 */
function renderCast(string $cast_file, string $svg_file, string $util_dir): void {
  if (is_file($svg_file)) {
    unlink($svg_file);
  }

  $cmd = sprintf('node %s %s %s --line-height 1.1 --at 0 2>&1', escapeshellarg($util_dir . '/svg-term-render.js'), escapeshellarg($cast_file), escapeshellarg($svg_file));
  $output = shell_exec($cmd);

  if (!file_exists($svg_file) || filesize($svg_file) === 0) {
    throw new \RuntimeException('Failed to render SVG: ' . $svg_file . "\n" . ($output ?? ''));
  }
}

// Entrypoint.
ini_set('display_errors', '1');

if (PHP_SAPI !== 'cli') {
  die('This script can be only ran from the command line.');
}

$util_dir = __DIR__;
$assets_dir = dirname(__DIR__) . '/assets';
$tmp_dir = dirname(__DIR__, 2) . '/.artifacts/tmp/anatomy-svgs';
$tree = dirname(__DIR__, 2) . '/playground/sample-project';

if (!is_dir($tmp_dir)) {
  mkdir($tmp_dir, 0755, TRUE);
}

$specs = anatomySpecs($tree);
$only = $argv[1] ?? '';

foreach ($specs as $name => $spec) {
  if ($only !== '' && $only !== $name) {
    continue;
  }

  renderAnatomy($name, $spec, $assets_dir, $util_dir, $tmp_dir);
  print 'Rendered anatomy-' . $name . PHP_EOL;
}
