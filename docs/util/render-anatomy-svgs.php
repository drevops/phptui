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
 * The columns reserved on each side of the frame for the callout labels.
 *
 * Wide enough for the longest part name plus the run out to it.
 */
const MARGIN_COLUMNS = 19;

/**
 * The line height the frames are drawn at.
 *
 * Matches the other terminal assets, and cannot be loosened to give the
 * leaders more room: the box-drawing borders are glyphs like any other, so
 * spreading the rows apart leaves the frame drawn in disconnected segments.
 */
const LINE_HEIGHT = 1.1;

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
      'rows' => 16,
      'callouts' => [
        ['col' => 14, 'row' => 4, 'label' => 'selector'],
        ['col' => 16, 'row' => 4, 'label' => 'entry'],
        ['col' => 12, 'row' => 5, 'label' => 'entry marker'],
        ['col' => 23, 'row' => 6, 'label' => 'entry note'],
        ['col' => 12, 'row' => 7, 'label' => 'constraint'],
        ['col' => 12, 'row' => 8, 'label' => 'entry detail'],
        ['col' => 6, 'row' => 9, 'label' => 'description'],
      ],
    ],
    'filepicker' => [
      'form' => Form::create('Orchard order')->panel('main', 'Price list', function (PanelBuilder $p) use ($tree): void {
        $p->filePicker('price_list', 'Price list')->description('The CSV the orchard sends each week.')->startIn($tree)->filesOnly()->extensions(['csv'])->maxSize(2097152);
      }),
      'keys' => [...$open, $down],
      'rows' => 16,
      'callouts' => [
        ['col' => 16, 'row' => 4, 'label' => 'location'],
        ['col' => 18, 'row' => 5, 'label' => 'entry'],
        ['col' => 16, 'row' => 6, 'label' => 'entry marker'],
        ['col' => 16, 'row' => 8, 'label' => 'constraint'],
      ],
    ],
    'text' => [
      'form' => Form::create('Orchard order')->panel('main', 'Crate', function (PanelBuilder $p): void {
        $p->template('crate', 'Crate label')->description('Identifies the crate on the loading dock.')->pattern('{{orchard}}-{{fruit}}-{{grade}}')->default('valley-pear-a')->slot('orchard', 'Orchard')->slot('fruit', 'Fruit')->slot('grade', 'Grade');
      }),
      'keys' => [...$open, $tab],
      'rows' => 10,
      'callouts' => [
        ['col' => 17, 'row' => 4, 'label' => 'buffer'],
        ['col' => 28, 'row' => 4, 'label' => 'caret'],
        ['col' => 17, 'row' => 5, 'label' => 'activity'],
      ],
    ],
    'error' => [
      'form' => Form::create('Orchard order')->panel('main', 'Weight', function (PanelBuilder $p): void {
        $p->number('weight', 'Basket weight (g)')->description('Weighed at the packing bench.')->default(1200)->min(200)->max(9000)->step(100);
      }),
      'keys' => [...$open, $bs, $bs, $bs, $bs, '1', $enter],
      'rows' => 10,
      'callouts' => [
        ['col' => 23, 'row' => 4, 'label' => 'buffer'],
        ['col' => 24, 'row' => 4, 'label' => 'caret'],
        ['col' => 23, 'row' => 5, 'label' => 'error'],
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
  $lines = explode("\r\n", $frame);

  // The row budget sizes the terminal the frame was drawn in, not the frame:
  // a shorter frame leaves blank rows and a taller one would be cropped from
  // the top, so the cast is sized from the lines actually captured.
  while ($lines !== [] && trim(end($lines)) === '') {
    array_pop($lines);
  }

  $frame = implode("\r\n", $lines);
  $width = 0;

  foreach ($lines as $line) {
    $width = max($width, Ansi::width($line));
  }

  $cast = json_encode(['version' => 2, 'width' => $width, 'height' => count($lines)]) . "\n" . json_encode([0.0, 'o', $clear . $frame]) . "\n";
  $cast_file = $tmp_dir . '/anatomy-' . $name . '.cast';
  file_put_contents($cast_file, $cast);

  $dark_file = $assets_dir . '/anatomy-' . $name . '-dark-static.svg';
  renderCast($cast_file, $dark_file, $util_dir);
  $light_file = deriveLightTwin($dark_file);

  $plain = array_map(static fn(string $line): string => Ansi::strip($line), $lines);

  // Violet reads as annotation rather than as output: the palettes already
  // spend teal on the frame, green on values and red on errors, so a callout
  // in any of those would look like something the widget had drawn.
  annotate($dark_file, $spec['callouts'], $plain, $width, 'rgb(167,139,250)', 'rgb(186,164,255)');
  annotate($light_file, $spec['callouts'], $plain, $width, 'rgb(124,58,237)', 'rgb(109,40,217)');
}

/**
 * Whether a stretch of a row holds nothing a leader may not cross.
 *
 * Box-drawing characters are crossable: a leader meeting the frame reads as a
 * callout, while one meeting a letter reads as a strikethrough.
 *
 * @param string $line
 *   The row, without escape sequences.
 * @param int $from
 *   The first column to test.
 * @param int $to
 *   The last column to test.
 *
 * @return bool
 *   TRUE when every column in the range is blank or part of the frame.
 */
function crossable(string $line, int $from, int $to): bool {
  $chars = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [];

  for ($column = max(0, $from); $column <= $to; $column++) {
    $char = $chars[$column] ?? ' ';

    if ($char !== ' ' && !str_contains('─│├┤╭╮╰╯┬┴┼', $char)) {
      return FALSE;
    }
  }

  return TRUE;
}

/**
 * Draw the callouts onto a rendered frame.
 *
 * The canvas gains a margin on both sides and each leader leaves through
 * whichever side of its own row is clear, so the labels sit around the frame
 * and no leader is ever drawn over a letter.
 *
 * @param string $file
 *   The SVG to annotate, rewritten in place.
 * @param array<int,array<string,mixed>> $callouts
 *   The callouts, each naming a cell by column and row.
 * @param array<int,string> $lines
 *   The frame's rows, without escape sequences.
 * @param int $columns
 *   The number of columns the frame holds.
 * @param string $leader_color
 *   The stroke colour for the leaders.
 * @param string $label_color
 *   The fill colour for the labels.
 */
function annotate(string $file, array $callouts, array $lines, int $columns, string $leader_color, string $label_color): void {
  $svg = file_get_contents($file);

  if ($svg === FALSE || !preg_match('/<svg[^>]*width="([\d.]+)"[^>]*height="([\d.]+)"/', $svg, $m)) {
    throw new \RuntimeException('Could not read the canvas size of ' . $file);
  }

  $frame_width = (float) $m[1];
  $frame_height = (float) $m[2];

  // Derived from the render rather than assumed, so a change of font size or
  // line height moves the callouts with the frame instead of stranding them.
  $cell_width = $frame_width / $columns;
  $cell_height = $frame_height / count($lines);
  $offset = $cell_width * MARGIN_COLUMNS;
  $canvas_width = $frame_width + $offset * 2;

  // Labels follow their cells down the frame, so a leader never has to climb
  // back over one already drawn - that ordering is what keeps them from
  // crossing. A label only slides further down when the one above crowds it.
  usort($callouts, static fn(array $a, array $b): int => [$a['row'], $a['col']] <=> [$b['row'], $b['col']]);

  $marks = '';
  $next = ['left' => -$cell_height, 'right' => -$cell_height];

  foreach ($callouts as $callout) {
    $row = (int) $callout['row'];
    $column = (int) $callout['col'];
    $line = $lines[$row] ?? '';
    $cell_x = $offset + ((float) $column + 0.5) * $cell_width;
    $cell_y = ((float) $row + 0.5) * $cell_height;

    // The near side wins when it is clear, so a part on the left of the frame
    // is named on the left. When a row is boxed in on both sides the leader
    // joins it past the end of its own text, which is always clear.
    $side = crossable($line, 0, $column - 1) ? 'left' : 'right';
    $start = $side === 'left' ? $cell_x - $cell_width : $cell_x + $cell_width;

    if ($side === 'right' && !crossable($line, $column + 1, $columns - 1)) {
      $start = $offset + ((float) Ansi::width(rtrim(mb_substr($line, 0, max(0, mb_strlen($line) - 1)))) + 1.5) * $cell_width;
    }

    $label_y = max($cell_y, $next[$side] + $cell_height * 0.9);
    $next[$side] = $label_y;

    $edge_x = $side === 'left' ? $offset - $cell_width * 0.8 : $offset + $frame_width + $cell_width * 0.8;
    $label_x = $side === 'left' ? $offset - $cell_width * 2.2 : $offset + $frame_width + $cell_width * 2.2;
    $anchor = $side === 'left' ? 'end' : 'start';

    $marks .= sprintf('<circle cx="%.2f" cy="%.2f" r="2.8" fill="none" stroke="%s" stroke-width="1.2"/>', $cell_x, $cell_y, $leader_color);
    $marks .= sprintf('<path d="M %.2f %.2f L %.2f %.2f L %.2f %.2f" fill="none" stroke="%s" stroke-width="0.9" stroke-linejoin="round" stroke-opacity="0.6" stroke-dasharray="1.5 3"/>', $start, $cell_y, $edge_x, $cell_y, $label_x + ($side === 'left' ? 5.0 : -5.0), $label_y, $leader_color);
    $marks .= sprintf('<text x="%.2f" y="%.2f" text-anchor="%s" fill="%s" font-family="Consolas, &quot;Courier New&quot;, Courier, monospace" font-size="13">%s</text>', $label_x, $label_y + 4.5, $anchor, $label_color, htmlspecialchars((string) $callout['label'], ENT_XML1));
  }

  $height = max($frame_height, max($next['left'], $next['right']) + $cell_height);

  // The frame renders inside a nested svg of its own, so wrapping rather than
  // editing it keeps every generated coordinate inside untouched.
  $wrapped = sprintf('<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="%.2f" height="%.2f"><g transform="translate(%.2f,0)">%s</g><g>%s</g></svg>', $canvas_width, $height, $offset, $svg, $marks);

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

  $cmd = sprintf('node %s %s %s --line-height %s --at 0 2>&1', escapeshellarg($util_dir . '/svg-term-render.js'), escapeshellarg($cast_file), escapeshellarg($svg_file), LINE_HEIGHT);
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
