<?php

/**
 * @file
 * Renders the annotated widget anatomy diagrams.
 *
 * Each diagram is a real widget frame, driven through the library's own
 * keystroke harness, with vector callouts drawn around it. The callouts are
 * geometry rather than characters, so nothing is injected into the frame and
 * no font metric can shift a border.
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
 * The parts a field draws in view mode, in reading order.
 *
 * A part's number is its position here. The two modes are numbered separately,
 * because a reader looking at one is not looking at the other, and a single run
 * of numbers across both would make every reference in the smaller list start
 * at some arbitrary offset.
 */
const VIEW_PARTS = [
  'breadcrumb',
  'breadcrumb separator',
  'field selector',
  'label',
  'help marker',
  'value',
  'value separator',
  'description',
  'help',
  'overflow marker',
  'legend',
  'legend key',
  'legend description',
  'legend separator',
];

/**
 * The parts a field draws in edit mode, in reading order.
 */
const EDIT_PARTS = [
  'entry',
  'entry selector',
  'entry marker',
  'entry note',
  'entry description',
  'constraint',
  'error',
  'caret',
  'draft',
  'state',
  'caption',
];

/**
 * The number a part carries within its mode.
 *
 * @param string $label
 *   The part name.
 * @param string $mode
 *   The mode the diagram belongs to, 'view' or 'edit'.
 *
 * @return int
 *   The part's number.
 */
function numberOf(string $label, string $mode): int {
  $parts = $mode === 'view' ? VIEW_PARTS : EDIT_PARTS;
  $index = array_search($label, $parts, TRUE);

  if ($index === FALSE) {
    throw new \RuntimeException(sprintf('"%s" is not a named part of %s mode.', $label, $mode));
  }

  return $index + 1;
}

/**
 * The rows reserved above and below the frame for the callout labels.
 */
const MARGIN_ROWS = 2.7;

/**
 * The terminal width the frames are drawn at.
 *
 * Narrower than the other assets. The whole canvas is scaled to the width of
 * the documentation column, and the callout margins take a fixed share of it,
 * so every column the frame does not need is one the reader gets back as
 * legible type.
 */
const FRAME_COLUMNS = 68;

/**
 * The label type size, in SVG user units.
 *
 * Generous against the frame: the whole canvas is scaled down to the width of
 * the documentation column, so type sized to look right here lands near body
 * size on the page.
 */
const FONT_SIZE = 15.0;

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
 * A callout names the cell it points at. The cell is chosen so that one side
 * of it is clear of text, since that is the side the leader arrives from.
 *
 * @param string $tree
 *   The sample project directory the file picker browses.
 *
 * @return array<string,array<string,mixed>>
 *   Each spec carries the form, the keystrokes, the row budget and the
 *   callouts.
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
      'form' => Form::create('Orchard')->panel('main', 'Delivery', function (PanelBuilder $p): void {
        $p->select('basket', 'Basket contents')->description('Pick the produce for this delivery.')->hint('Every crate is weighed and labelled at the packing bench before it leaves the orchard.')->multiple()->default(['apple', 'carrot'])->options(['apple' => 'Apple', 'carrot' => 'Carrot']);
        $p->number('weight', 'Basket weight')->description('Weighed at the packing bench.')->default(1200)->min(200)->max(9000);
        $p->calendar('harvest', 'Harvest date')->default('2026-07-15');
        $p->text('courier', 'Courier')->default('Valley Runs');
        $p->confirm('organic', 'Organic only?')->default(TRUE);
        $p->text('notes', 'Notes')->default('Leave at the gate');
      }),
      'mode' => 'view',
      'keys' => [$enter],
      'rows' => 21,
      // The panel's own legend is the longest line any diagram carries, and a
      // truncated legend is the one thing this frame must not show.
      'cols' => 78,
      'callouts' => [
        ['col' => 2, 'row' => 1, 'label' => 'breadcrumb', 'side' => 'left'],
        ['col' => 10, 'row' => 1, 'label' => 'breadcrumb separator'],
        ['col' => 2, 'row' => 4, 'label' => 'field selector'],
        ['col' => 6, 'row' => 5, 'label' => 'description'],
        ['label' => 'label', 'side' => 'left', 'cells' => [[7, 4], [10, 4], [12, 4]]],
        ['col' => 20, 'row' => 4, 'label' => 'help marker'],
        ['col' => 28, 'row' => 4, 'label' => 'value separator'],
        ['label' => 'value', 'side' => 'right', 'cells' => [[4, 35], [7, 22], [10, 27], [12, 23]]],
        ['col' => 4, 'row' => 15, 'label' => 'overflow marker'],
        ['col' => 2, 'row' => 18, 'label' => 'legend', 'side' => 'left'],
        ['col' => 2, 'row' => 18, 'label' => 'legend key', 'side' => 'down'],
        // Each points at the far end of its part rather than the near one, so a
        // riser clears the label of the band above instead of crossing it.
        ['col' => 12, 'row' => 18, 'label' => 'legend description', 'side' => 'down'],
        ['col' => 28, 'row' => 18, 'label' => 'legend separator', 'side' => 'down'],
      ],
    ],
    'editor' => [
      'form' => Form::create('Orchard')->panel('main', 'Basket', function (PanelBuilder $p): void {
        $p->select('basket', 'Basket')->description('Pick the produce for this delivery.')->multiple()->minSelections(2)->maxSelections(3)
          ->option('apple', 'Apple', description: 'Crisp and sweet, the everyday choice.')
          ->option('carrot', 'Carrot', description: 'Stays crisp for weeks when kept cold.')
          ->option('tomato', 'Tomato', disabled: TRUE, disabled_reason: 'out of season');
      }),
      'mode' => 'edit',
      'keys' => [...$open, $space, $down, $space],
      'rows' => 16,
      'callouts' => [
        ['col' => 20, 'row' => 4, 'label' => 'entry'],
        ['col' => 12, 'row' => 5, 'label' => 'entry selector'],
        ['col' => 14, 'row' => 6, 'label' => 'entry marker'],
        ['col' => 37, 'row' => 6, 'label' => 'entry note'],
        ['col' => 16, 'row' => 7, 'label' => 'entry description'],
        ['col' => 12, 'row' => 8, 'label' => 'constraint'],
      ],
    ],
    'filepicker' => [
      'form' => Form::create('Orchard')->panel('main', 'Price list', function (PanelBuilder $p) use ($tree): void {
        $p->filePicker('price_list', 'Price list')->description('The CSV the orchard sends each week.')->startIn($tree)->filesOnly()->extensions(['csv'])->maxSize(2097152);
      }),
      'mode' => 'edit',
      'keys' => [...$open, $down],
      'rows' => 16,
      'callouts' => [
        ['col' => 29, 'row' => 4, 'label' => 'caption'],
        ['col' => 18, 'row' => 5, 'label' => 'entry'],
        ['col' => 16, 'row' => 6, 'label' => 'entry selector'],
        ['col' => 16, 'row' => 8, 'label' => 'constraint'],
      ],
    ],
    'text' => [
      'form' => Form::create('Orchard')->panel('main', 'Crate', function (PanelBuilder $p): void {
        $p->template('crate', 'Crate label')->description('Identifies the crate on the loading dock.')->pattern('{{orchard}}-{{fruit}}-{{grade}}')->default('valley-pear-a')->slot('orchard', 'Orchard')->slot('fruit', 'Fruit')->slot('grade', 'Grade');
      }),
      'mode' => 'edit',
      'keys' => [...$open, $tab],
      'rows' => 10,
      'callouts' => [
        ['col' => 28, 'row' => 4, 'label' => 'caret'],
        ['col' => 30, 'row' => 4, 'label' => 'draft'],
        ['col' => 32, 'row' => 5, 'label' => 'state', 'side' => 'right'],
      ],
    ],
    'constraint' => [
      'form' => Form::create('Orchard')->panel('main', 'Price list', function (PanelBuilder $p) use ($tree): void {
        $p->filePicker('price_list', 'Price list')->startIn($tree)->filesOnly()->maxSize(64);
      }),
      'mode' => 'edit',
      'keys' => [...$open],
      'rows' => 20,
      'callouts' => [
        ['col' => 16, 'row' => 11, 'label' => 'constraint'],
      ],
    ],
    // The same picker, after a pick that breaks the size limit the line above
    // announced: one line, the other of its two states.
    'error' => [
      'form' => Form::create('Orchard')->panel('main', 'Price list', function (PanelBuilder $p) use ($tree): void {
        $p->filePicker('price_list', 'Price list')->startIn($tree)->filesOnly()->maxSize(64);
      }),
      'mode' => 'edit',
      'keys' => [...$open, $down, $down, $down, $enter],
      'rows' => 20,
      'callouts' => [
        ['col' => 16, 'row' => 11, 'label' => 'error'],
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
  $tester = (new TuiTester($spec['form']))->options(['color' => TRUE, 'unicode' => TRUE, 'mode' => Mode::Dark])->rows($spec['rows'])->cols($spec['cols'] ?? FRAME_COLUMNS);
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

  // A callout must read as annotation rather than as output, so it takes a hue
  // the palettes do not spend on the frame, on values or on errors - and one
  // that stays legible against its own surface, which is why the two modes do
  // not share it.
  annotate($dark_file, $spec['callouts'], $plain, $width, $spec['mode'], 'rgb(163,230,53)');
  annotate($light_file, $spec['callouts'], $plain, $width, $spec['mode'], 'rgb(124,45,190)');
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
 * Whether a column is clear of text across a range of rows.
 *
 * @param array<int,string> $lines
 *   The frame's rows, without escape sequences.
 * @param int $column
 *   The column to test.
 * @param int $from
 *   The first row to test.
 * @param int $to
 *   The last row to test.
 *
 * @return bool
 *   TRUE when every row in the range is blank or frame at that column.
 */
function columnClear(array $lines, int $column, int $from, int $to): bool {
  for ($row = max(0, $from); $row <= $to; $row++) {
    if (!crossable($lines[$row] ?? '', $column, $column)) {
      return FALSE;
    }
  }

  return TRUE;
}

/**
 * The cells a callout points at.
 *
 * @param array<string,mixed> $callout
 *   The callout.
 *
 * @return array<int,array<int,int>>
 *   The cells, each a row and a column.
 */
function cellsOf(array $callout): array {
  return $callout['cells'] ?? [[(int) $callout['row'], (int) $callout['col']]];
}

/**
 * The column a multi-cell callout gathers its arrows on.
 *
 * The bracket has to stand clear of every row it spans, not just the rows it
 * points at, and each arrow has to reach its cell without crossing anything.
 * The nearest column satisfying both wins, so the bracket hugs the cells.
 *
 * @param array<int,string> $lines
 *   The frame's rows, without escape sequences.
 * @param array<int,array<int,int>> $cells
 *   The cells, each a row and a column.
 * @param string $side
 *   The side the bracket stands on, 'left' or 'right'.
 * @param int $columns
 *   The number of columns the frame holds.
 *
 * @return int|null
 *   The column, or NULL when no column on that side is clear.
 */
function bracketColumn(array $lines, array $cells, string $side, int $columns): ?int {
  $rows = array_column($cells, 0);
  $cols = array_column($cells, 1);
  $first = min($rows);
  $last = max($rows);
  // The frame's own border occupies the outermost column, and a bracket laid
  // against it reads as part of the frame rather than as an annotation of it.
  $range = $side === 'left' ? range(min($cols) - 1, 2) : range(max($cols) + 1, $columns - 3);
  $clear = [];

  foreach ($range as $offset => $column) {
    if (!columnClear($lines, $column, $first, $last)) {
      continue;
    }

    foreach ($cells as [$row, $cell]) {
      $from = $side === 'left' ? $column + 1 : $cell + 1;
      $to = $side === 'left' ? $cell - 1 : $column - 1;

      if (!crossable($lines[$row] ?? '', $from, $to)) {
        continue 2;
      }
    }

    // Standing the bracket off by a few columns where the room exists keeps
    // the arrowheads clear of the glyphs they point at; hard against the text
    // they read as part of it.
    $clear[] = $column;

    if ($offset >= 3) {
      break;
    }
  }

  return $clear === [] ? NULL : end($clear);
}

/**
 * The row a bracket's label reaches it on.
 *
 * @param array<int,string> $lines
 *   The frame's rows, without escape sequences.
 * @param int $bracket
 *   The bracket's column.
 * @param string $side
 *   The side the bracket stands on, 'left' or 'right'.
 * @param int $first
 *   The first row the bracket spans.
 * @param int $last
 *   The last row the bracket spans.
 * @param int $columns
 *   The number of columns the frame holds.
 *
 * @return int
 *   The row, closest to the bracket's middle that the label can reach along.
 */
function connectorRow(array $lines, int $bracket, string $side, int $first, int $last, int $columns): int {
  $middle = (int) round(($first + $last) / 2);
  $rows = range($first, $last);

  usort($rows, static fn(int $a, int $b): int => abs($a - $middle) <=> abs($b - $middle));

  foreach ($rows as $row) {
    $from = $side === 'left' ? 0 : $bracket + 1;
    $to = $side === 'left' ? $bracket - 1 : $columns - 1;

    if (crossable($lines[$row] ?? '', $from, $to)) {
      return $row;
    }
  }

  return $middle;
}

/**
 * The side a callout's leader arrives from.
 *
 * Vertical exits are preferred near the top and bottom of the frame so the
 * labels spread around it rather than piling into the two margins.
 *
 * @param array<int,string> $lines
 *   The frame's rows, without escape sequences.
 * @param int $row
 *   The callout's row.
 * @param int $column
 *   The callout's column.
 * @param int $columns
 *   The number of columns the frame holds.
 *
 * @return string
 *   One of 'up', 'down', 'left' or 'right'.
 */
function leaderSide(array $lines, int $row, int $column, int $columns): string {
  $rows = count($lines);
  $up = columnClear($lines, $column, 0, $row - 1);
  $down = columnClear($lines, $column, $row + 1, $rows - 1);
  $left = crossable($lines[$row] ?? '', 0, $column - 1);
  $right = crossable($lines[$row] ?? '', $column + 1, $columns - 1);

  if ($up && $row <= 2) {
    return 'up';
  }

  if ($down && $row >= $rows - 3) {
    return 'down';
  }

  return match (TRUE) {
    $left => 'left',
    $right => 'right',
    $up => 'up',
    $down => 'down',
    default => 'right',
  };
}

/**
 * Draw the callouts around a rendered frame.
 *
 * The canvas gains a margin on all four sides and each leader arrives from
 * whichever side of its cell is clear, so the labels sit around the frame and
 * no leader is ever drawn over a letter.
 *
 * @param string $file
 *   The SVG to annotate, rewritten in place.
 * @param array<int,array<string,mixed>> $callouts
 *   The callouts, each naming a cell by column and row.
 * @param array<int,string> $lines
 *   The frame's rows, without escape sequences.
 * @param int $columns
 *   The number of columns the frame holds.
 * @param string $mode
 *   The mode the diagram belongs to, which its numbering runs within.
 * @param string $color
 *   The colour for the leaders and labels.
 */
function annotate(string $file, array $callouts, array $lines, int $columns, string $mode, string $color): void {
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

  usort($callouts, static function (array $a, array $b): int {
    $first = cellsOf($a)[0];
    $second = cellsOf($b)[0];

    return $first <=> $second;
  });

  // Each side is settled first, then the margins are sized from the labels
  // that will actually land in them: a fixed margin either clips the longest
  // part name or pads every diagram out to fit it.
  $plans = [];
  $levels = [];
  $needs = ['left' => 0.0, 'right' => 0.0];

  foreach ($callouts as $index => $callout) {
    $text = numberOf($callout['label'], $mode) . '  ' . $callout['label'];
    $width = mb_strlen($text) * FONT_SIZE * 0.6;
    $cells = cellsOf($callout);

    // A part that occurs several times gets one label and one arrow per
    // occurrence, gathered on a bracket - three numbers for one part would
    // read as three parts.
    if (count($cells) > 1) {
      $side = $callout['side'] ?? 'right';
      $bracket = bracketColumn($lines, $cells, $side, $columns);

      if ($bracket === NULL) {
        throw new \RuntimeException(sprintf('No clear %s bracket for "%s".', $side, $callout['label']));
      }

      $rows = array_column($cells, 0);
      $plans[$index] = ['bracket', $side, $text, $cells, $bracket, connectorRow($lines, $bracket, $side, min($rows), max($rows), $columns)];
      $needs[$side] = max($needs[$side], $width + $cell_width * 2.4);

      continue;
    }

    [$row, $column] = $cells[0];
    // A declared side wins: the clear sides of a cell are often several, and
    // which of them reads best is a judgement the frame cannot make.
    $side = $callout['side'] ?? leaderSide($lines, $row, $column, $columns);
    $plans[$index] = ['single', $side, $text, $cells];

    if ($side === 'left' || $side === 'right') {
      $needs[$side] = max($needs[$side], $width + $cell_width * 2.4);

      continue;
    }

    // A label above or below is centred on its cell, so one near either end of
    // the frame hangs over that edge and the margin has to take the overhang.
    // Two that overlap horizontally stack into bands, and the band count is
    // what the room above has to be measured from - a fixed allowance clips the
    // outermost one the moment a second band is needed.
    $centre = ((float) $column + 0.5) * $cell_width;
    $needs['left'] = max($needs['left'], $width / 2 - $centre);
    $needs['right'] = max($needs['right'], $width / 2 - ($frame_width - $centre));

    $level = 0;

    while (isset($levels[$side][$level]) && overlaps($levels[$side][$level], $centre - $width / 2, $centre + $width / 2)) {
      $level++;
    }

    $levels[$side][$level][] = [$centre - $width / 2, $centre + $width / 2];
    $plans[$index][4] = $level;
  }

  $offset_x = $needs['left'];

  // The bands above are only worth their room when something lands in them; a
  // diagram labelled entirely from the sides would otherwise open with a strip
  // of empty canvas.
  $offset_y = isset($levels['up'])
    ? FONT_SIZE * (1.35 * count($levels['up']) + 0.4)
    : $cell_height * 0.4;
  $left_edge = $offset_x;
  $right_edge = $offset_x + $frame_width;
  $bottom_edge = $offset_y + $frame_height;

  $marks = '';
  $stack = ['left' => -$cell_height, 'right' => -$cell_height];
  $lowest = $bottom_edge;

  foreach ($plans as $plan) {
    [$kind, $side, $text, $cells] = $plan;

    if ($kind === 'bracket') {
      [, , , , $bracket, $connector] = $plan;
      $bracket_x = $offset_x + ((float) $bracket + 0.5) * $cell_width;
      $connector_y = $offset_y + ((float) $connector + 0.5) * $cell_height;
      $rows = array_column($cells, 0);
      $top = $offset_y + ((float) min($rows) + 0.5) * $cell_height;
      $foot = $offset_y + ((float) max($rows) + 0.5) * $cell_height;
      $edge = $side === 'left' ? $left_edge - $cell_width * 0.6 : $right_edge + $cell_width * 0.6;
      $label_x = $side === 'left' ? $left_edge - $cell_width * 1.4 : $right_edge + $cell_width * 1.4;

      $marks .= sprintf('<path d="M %.2f %.2f L %.2f %.2f" fill="none" stroke="%s" stroke-width="1.4"/>', $bracket_x, $top, $bracket_x, $foot, $color);
      $marks .= sprintf('<path d="M %.2f %.2f L %.2f %.2f" fill="none" stroke="%s" stroke-width="1.4"/>', $side === 'left' ? $label_x + FONT_SIZE * 0.4 : $label_x - FONT_SIZE * 0.4, $connector_y, $bracket_x, $connector_y, $color);

      foreach ($cells as [$cell_row, $cell_col]) {
        $to_x = $offset_x + ((float) $cell_col + 0.5) * $cell_width;
        $tip = $side === 'left' ? $to_x - $cell_width * 0.7 : $to_x + $cell_width * 0.7;
        $marks .= sprintf('<path d="M %.2f %.2f L %.2f %.2f" fill="none" stroke="%s" stroke-width="1.4" marker-end="url(#anatomy-arrow)"/>', $bracket_x, $offset_y + ((float) $cell_row + 0.5) * $cell_height, $tip, $offset_y + ((float) $cell_row + 0.5) * $cell_height, $color);
      }

      $marks .= sprintf('<text x="%.2f" y="%.2f" text-anchor="%s" fill="%s" font-family="Consolas, &quot;Courier New&quot;, Courier, monospace" font-size="%.1f">%s</text>', $label_x, $connector_y + FONT_SIZE * 0.35, $side === 'left' ? 'end' : 'start', $color, FONT_SIZE, label($text));
      $stack[$side] = max($stack[$side], $foot);

      continue;
    }

    [$row, $column] = $cells[0];
    $cell_x = $offset_x + ((float) $column + 0.5) * $cell_width;
    $cell_y = $offset_y + ((float) $row + 0.5) * $cell_height;

    if ($side === 'up' || $side === 'down') {
      // The band was settled while the margins were being measured, so the room
      // above the frame already accounts for however many bands are in use.
      $step = (FONT_SIZE * 1.35) * $plan[4];
      $label_y = $side === 'up' ? $offset_y - FONT_SIZE * 0.5 - $step : $bottom_edge + FONT_SIZE * 1.1 + $step;
      $tip_y = $side === 'up' ? $cell_y - $cell_height * 0.55 : $cell_y + $cell_height * 0.55;
      $from_y = $side === 'up' ? $label_y + FONT_SIZE * 0.35 : $label_y - FONT_SIZE * 0.95;

      $marks .= sprintf('<path d="M %.2f %.2f L %.2f %.2f" fill="none" stroke="%s" stroke-width="1.4" marker-end="url(#anatomy-arrow)"/>', $cell_x, $from_y, $cell_x, $tip_y, $color);
      $marks .= sprintf('<text x="%.2f" y="%.2f" text-anchor="middle" fill="%s" font-family="Consolas, &quot;Courier New&quot;, Courier, monospace" font-size="%.1f">%s</text>', $cell_x, $label_y, $color, FONT_SIZE, label($text));
      $lowest = max($lowest, $label_y);

      continue;
    }

    // One row of separation is enough, and it is what keeps a leader straight:
    // a label that fits its own row sits level with the cell it names, so the
    // leader is a horizontal line and can cross nothing on the way.
    $label_y = max($cell_y, $stack[$side] + max($cell_height, FONT_SIZE * 1.15));
    $stack[$side] = $label_y;
    $tip_x = $side === 'left' ? $cell_x - $cell_width * 0.7 : $cell_x + $cell_width * 0.7;
    $bend_x = $side === 'left' ? $left_edge - $cell_width * 0.6 : $right_edge + $cell_width * 0.6;
    $label_x = $side === 'left' ? $left_edge - $cell_width * 1.4 : $right_edge + $cell_width * 1.4;
    $anchor = $side === 'left' ? 'end' : 'start';
    $from_x = $side === 'left' ? $label_x + FONT_SIZE * 0.4 : $label_x - FONT_SIZE * 0.4;

    $marks .= sprintf('<path d="M %.2f %.2f L %.2f %.2f L %.2f %.2f" fill="none" stroke="%s" stroke-width="1.4" stroke-linejoin="round" marker-end="url(#anatomy-arrow)"/>', $from_x, $label_y, $bend_x, $cell_y, $tip_x, $cell_y, $color);
    $marks .= sprintf('<text x="%.2f" y="%.2f" text-anchor="%s" fill="%s" font-family="Consolas, &quot;Courier New&quot;, Courier, monospace" font-size="%.1f">%s</text>', $label_x, $label_y + FONT_SIZE * 0.35, $anchor, $color, FONT_SIZE, label($text));
  }

  $canvas_width = $needs['left'] + $frame_width + $needs['right'];
  $canvas_height = max($lowest, $stack['left'], $stack['right']) + FONT_SIZE;
  $arrow = sprintf('<defs><marker id="anatomy-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse"><path d="M 0 1 L 10 5 L 0 9 z" fill="%s"/></marker></defs>', $color);

  // The frame renders inside a nested svg of its own, so wrapping rather than
  // editing it keeps every generated coordinate inside untouched.
  $wrapped = sprintf('<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="%.2f" height="%.2f">%s<g transform="translate(%.2f,%.2f)">%s</g><g>%s</g></svg>', $canvas_width, $canvas_height, $arrow, $offset_x, $offset_y, $svg, $marks);

  file_put_contents($file, $wrapped);
}

/**
 * Whether a span overlaps any span already placed in a band.
 *
 * @param array<int,array<int,float>> $placed
 *   The spans already in the band, each a start and an end.
 * @param float $from
 *   The span's start.
 * @param float $to
 *   The span's end.
 *
 * @return bool
 *   TRUE when the span would collide.
 */
function overlaps(array $placed, float $from, float $to): bool {
  foreach ($placed as $span) {
    if ($from < $span[1] + 6.0 && $to + 6.0 > $span[0]) {
      return TRUE;
    }
  }

  return FALSE;
}

/**
 * A label with its number set in bold.
 *
 * @param string $text
 *   The label, its number first.
 *
 * @return string
 *   The label's markup.
 */
function label(string $text): string {
  [$number, $name] = explode('  ', $text, 2);

  return sprintf('<tspan font-weight="bold">%s</tspan>  %s', $number, htmlspecialchars($name, ENT_XML1));
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
