<?php

/**
 * @file
 * Glyph gallery: every field rendered in Unicode and ASCII, side by side.
 *
 * Fields pull their glyphs from the theme, so the same field renders with
 * Unicode glyphs under a Unicode theme and ASCII glyphs under an ASCII one -
 * exactly how the TUI adapts to the terminal locale. This is a static
 * render, not an interactive form: each field's view() is captured under
 * both themes so the difference is visible in one screen.
 *
 * Usage:
 *   php playground/11-display-modes-glyph-gallery.php
 */

declare(strict_types=1);

use DrevOps\Tui\Field\Calendar;
use DrevOps\Tui\Field\Confirm;
use DrevOps\Tui\Field\FieldInterface;
use DrevOps\Tui\Field\Number;
use DrevOps\Tui\Field\Password;
use DrevOps\Tui\Field\Pause;
use DrevOps\Tui\Field\Reorder;
use DrevOps\Tui\Field\Search;
use DrevOps\Tui\Field\Select;
use DrevOps\Tui\Field\Suggest;
use DrevOps\Tui\Field\Text;
use DrevOps\Tui\Field\Textarea;
use DrevOps\Tui\Field\Toggle;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Utils\Strings;

require __DIR__ . '/../vendor/autoload.php';

// Colour is disabled so the output is plain; only the glyph mode differs.
$unicode = new DefaultTheme(76, ['color' => FALSE]);
$ascii = new DefaultTheme(76, ['color' => FALSE, 'unicode' => FALSE]);

/**
 * The fields to showcase, each built freshly (fields are stateful).
 *
 * @var array<string,callable():\DrevOps\Tui\Field\FieldInterface>
 */
$fields = [
  'Text' => static fn(): FieldInterface => new Text('Pear'),
  'Number' => static fn(): FieldInterface => new Number('1200'),
  'Calendar' => static fn(): FieldInterface => new Calendar('2026-07-15'),
  'Textarea' => static fn(): FieldInterface => new Textarea('Crisp and sweet' . chr(10) . 'Hint of citrus'),
  'Password' => static fn(): FieldInterface => new Password('melon7'),
  'Select' => static fn(): FieldInterface => new Select([
    'apple' => 'Apple',
    'banana' => 'Banana',
    'cherry' => 'Cherry',
  ], 'apple'),
  'MultiSelect' => static fn(): FieldInterface => new Select([
    'apple' => 'Apple',
    'carrot' => 'Carrot',
    'tomato' => 'Tomato',
  ], ['apple', 'carrot'], TRUE),
  'Reorder' => static fn(): FieldInterface => new Reorder([
    'apple' => 'Apple',
    'carrot' => 'Carrot',
    'tomato' => 'Tomato',
  ]),
  'Suggest' => static fn(): FieldInterface => new Suggest([
    'Apple',
    'Apricot',
    'Banana',
    'Cherry',
    'Mango',
  ], 'Ap'),
  'Search' => static fn(): FieldInterface => new Search([
    'carrot' => 'Carrot',
    'potato' => 'Potato',
    'onion' => 'Onion',
    'pepper' => 'Pepper',
  ], 'carrot'),
  'MultiSearch' => static fn(): FieldInterface => new Search([
    'apple' => 'Apple',
    'banana' => 'Banana',
    'carrot' => 'Carrot',
    'tomato' => 'Tomato',
  ], ['apple'], TRUE),
  'Confirm' => static fn(): FieldInterface => new Confirm(TRUE),
  'Toggle' => static fn(): FieldInterface => new Toggle([
    'ripe' => 'Ripe',
    'unripe' => 'Unripe',
  ], 'ripe'),
  'Pause' => static fn(): FieldInterface => new Pause(),
];

// Lay two rendered views out as columns, the left one padded to align.
$columns = static function (string $left, string $right, int $gap = 8): string {
  $left_lines = explode("\n", $left);
  $right_lines = explode("\n", $right);
  $width = 0;
  foreach ($left_lines as $line) {
    $width = max($width, Strings::length($line));
  }

  $rows = [];
  for ($i = 0, $count = max(count($left_lines), count($right_lines)); $i < $count; $i++) {
    $line = $left_lines[$i] ?? '';
    $pad = str_repeat(' ', max(0, $width - Strings::length($line) + $gap));
    $rows[] = '    ' . $line . $pad . ($right_lines[$i] ?? '');
  }

  return implode("\n", $rows);
};

echo PHP_EOL;
echo $columns('UNICODE', 'TEXTUAL (ASCII)') . PHP_EOL;
echo str_repeat('-', 60) . PHP_EOL . PHP_EOL;

foreach ($fields as $name => $make) {
  echo $name . PHP_EOL;
  echo $columns($make()->view($unicode), $make()->view($ascii)) . PHP_EOL . PHP_EOL;
}
