<?php

/**
 * @file
 * Glyph gallery: every widget rendered in Unicode and ASCII, side by side.
 *
 * Widgets pull their glyphs from the theme, so the same widget renders with
 * Unicode glyphs under a Unicode theme and ASCII glyphs under an ASCII one -
 * exactly how the TUI adapts to the terminal locale. This is a static
 * render, not an interactive form: each widget's view() is captured under
 * both themes so the difference is visible in one screen.
 *
 * Usage:
 *   php playground/11-display-modes-glyph-gallery.php
 */

declare(strict_types=1);

use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Utils\Strings;
use DrevOps\Tui\Widget\CalendarWidget;
use DrevOps\Tui\Widget\ConfirmWidget;
use DrevOps\Tui\Widget\NumberWidget;
use DrevOps\Tui\Widget\PasswordWidget;
use DrevOps\Tui\Widget\PauseWidget;
use DrevOps\Tui\Widget\ReorderWidget;
use DrevOps\Tui\Widget\SearchWidget;
use DrevOps\Tui\Widget\SelectWidget;
use DrevOps\Tui\Widget\SuggestWidget;
use DrevOps\Tui\Widget\TextareaWidget;
use DrevOps\Tui\Widget\TextWidget;
use DrevOps\Tui\Widget\ToggleWidget;
use DrevOps\Tui\Widget\WidgetInterface;

require __DIR__ . '/../vendor/autoload.php';

// Colour is disabled so the output is plain; only the glyph mode differs.
$unicode = new DefaultTheme(76, ['color' => FALSE]);
$ascii = new DefaultTheme(76, ['color' => FALSE, 'unicode' => FALSE]);

/**
 * The widgets to showcase, each built freshly (widgets are stateful).
 *
 * @var array<string,callable():\DrevOps\Tui\Widget\WidgetInterface>
 */
$widgets = [
  'Text' => static fn(): WidgetInterface => new TextWidget('Pear'),
  'Number' => static fn(): WidgetInterface => new NumberWidget('1200'),
  'Calendar' => static fn(): WidgetInterface => new CalendarWidget('2026-07-15'),
  'Textarea' => static fn(): WidgetInterface => new TextareaWidget('Crisp and sweet' . chr(10) . 'Hint of citrus'),
  'Password' => static fn(): WidgetInterface => new PasswordWidget('melon7'),
  'Select' => static fn(): WidgetInterface => new SelectWidget([
    'apple' => 'Apple',
    'banana' => 'Banana',
    'cherry' => 'Cherry',
  ], 'apple'),
  'MultiSelect' => static fn(): WidgetInterface => new SelectWidget([
    'apple' => 'Apple',
    'carrot' => 'Carrot',
    'tomato' => 'Tomato',
  ], ['apple', 'carrot'], TRUE),
  'Reorder' => static fn(): WidgetInterface => new ReorderWidget([
    'apple' => 'Apple',
    'carrot' => 'Carrot',
    'tomato' => 'Tomato',
  ]),
  'Suggest' => static fn(): WidgetInterface => new SuggestWidget([
    'Apple',
    'Apricot',
    'Banana',
    'Cherry',
    'Mango',
  ], 'Ap'),
  'Search' => static fn(): WidgetInterface => new SearchWidget([
    'carrot' => 'Carrot',
    'potato' => 'Potato',
    'onion' => 'Onion',
    'pepper' => 'Pepper',
  ], 'carrot'),
  'MultiSearch' => static fn(): WidgetInterface => new SearchWidget([
    'apple' => 'Apple',
    'banana' => 'Banana',
    'carrot' => 'Carrot',
    'tomato' => 'Tomato',
  ], ['apple'], TRUE),
  'Confirm' => static fn(): WidgetInterface => new ConfirmWidget(TRUE),
  'Toggle' => static fn(): WidgetInterface => new ToggleWidget([
    'ripe' => 'Ripe',
    'unripe' => 'Unripe',
  ], 'ripe'),
  'Pause' => static fn(): WidgetInterface => new PauseWidget(),
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

foreach ($widgets as $name => $make) {
  echo $name . PHP_EOL;
  echo $columns($make()->view($unicode), $make()->view($ascii)) . PHP_EOL . PHP_EOL;
}
