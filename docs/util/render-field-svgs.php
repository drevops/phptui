#!/usr/bin/env php
<?php

/**
 * @file
 * Render the per-field and per-markup animated SVGs deterministically.
 *
 * The full-demo montages and panel walkthroughs are recorded from a live pty
 * (see update-assets.php), but these assets are one-block forms that the
 * library's own scripted-keystroke harness drives with no terminal at all. For
 * each of them, in all four glyph and colour display modes, this renders both
 * an animation (every rendered frame captured, laid out into an asciicast and
 * handed to the shared svg-term renderer; the unicode-colour one is the hero
 * README.md and the docs pages embed) and a static screenshot of the opened
 * editor (the four the docs grid shows). The result is reproducible on any
 * machine (and in CI), unlike a pty recording.
 *
 * Dependencies: node, npm (for the svg-term renderer shared with update-assets).
 *
 * Usage:
 * @code
 * php docs/util/render-field-svgs.php            # every spec
 * php docs/util/render-field-svgs.php confirm    # one or more by name
 * php docs/util/render-field-svgs.php markup     # the markup block
 * @endcode
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Primitive\ProgressReporter;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Testing\TuiTester;
use DrevOps\Tui\Theme\Mode;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/svg-slowdown.php';
require_once __DIR__ . '/svg-light-twin.php';

// Seconds each captured frame is held: a longer beat on the opening frame, an
// even cadence through the interaction, and a rest on the last frame before the
// animation loops.
const HOLD_FIRST = 1.1;
const HOLD_STEP = 0.65;
const HOLD_LAST = 2.2;

// The four glyph and colour display modes, keyed by the filename suffix that
// distinguishes them. Both the animated cards and the static screenshots are
// rendered in every mode. Unicode and colour are the unmarked default.
const DISPLAY_MODES = [
  '' => ['color' => TRUE, 'unicode' => TRUE],
  '-ascii' => ['color' => TRUE, 'unicode' => FALSE],
  '-no-ansi' => ['color' => FALSE, 'unicode' => TRUE],
  '-ascii-no-ansi' => ['color' => FALSE, 'unicode' => FALSE],
];

/**
 * The per-subject forms and the keystrokes that drive them.
 *
 * Each single-block form mirrors its playground/02-* script - same ids,
 * labels, defaults and options - so the rendered cards match the code a
 * reader runs. The keystrokes follow the panel model: Enter drills the hub
 * into the panel, a second Enter opens the field editor, then the
 * field-specific keys exercise it and Enter (or Tab) accepts - the same path
 * a person walks.
 *
 * A spec hands back a factory rather than a form: a run that ends mid-edit
 * leaves its answers on the form it drove, so a form shared between the
 * display modes would open the next one on the last one's value.
 *
 * @param string $tree
 *   The fixture directory the file-picker fields browse.
 *
 * @return array<string, array{form: callable(): \DrevOps\Tui\Builder\Form, keys: list<string|\DrevOps\Tui\Input\Key>, rows: int, static_keys?: list<string|\DrevOps\Tui\Input\Key>, subject?: string}>
 *   The specs keyed by name. A spec may add "static_keys" when the opened
 *   editor needs a keystroke before its static frame is worth capturing, and
 *   "subject" when what it draws is not a field, so its assets carry the name
 *   of what they show rather than a field prefix that would misname it.
 */
function fieldSpecs(string $tree): array {
  $enter = Key::named(KeyName::Enter);
  $down = Key::named(KeyName::Down);
  $space = Key::named(KeyName::Space);
  $bs = Key::named(KeyName::Backspace);
  $tab = Key::named(KeyName::Tab);
  $left = Key::named(KeyName::Left);
  $right = Key::named(KeyName::Right);
  // Two Enters walk the hub into the panel and open the field editor; the
  // animation then ends inside the editor on the changed value, so the frame
  // stays as narrow as the field itself rather than the full-width panel row.
  $open = [$enter, $enter];
  // A short packing job whose every advance() repaints the row, so the capture
  // holds one frame per filled step as the bar grows.
  $pack = static function (ProgressReporter $reporter): void {
    for ($step = 0; $step < 6; $step++) {
      $reporter->advance();
    }
  };

  return [
    'text' => [
      'form' => static fn(): Form => Form::create('Text field')->panel('main', 'Text', function (PanelBuilder $p): void { $p->text('item', 'Item')->default('Pear')->complete(['Pear', 'Peach', 'Plum']); }),
      'keys' => [...$open, $bs, $bs, $bs, $bs, 'A', 'p', 'p', 'l', 'e'],
      'rows' => 6,
    ],
    'template' => [
      'form' => static fn(): Form => Form::create('Template field')->panel('main', 'Template', function (PanelBuilder $p): void { $p->template('crate', 'Crate label')->pattern('{{orchard}}-{{fruit}}-{{grade}}')->default('valley-pear-a')->slot('orchard', 'Orchard')->slot('fruit', 'Fruit')->slot('grade', 'Grade'); }),
      'keys' => [...$open, $bs, $bs, $bs, $bs, $bs, $bs, 'r', 'i', 'd', 'g', 'e', $tab, $tab, $bs, 'b'],
      'rows' => 7,
    ],
    'number' => [
      'form' => static fn(): Form => Form::create('Number field')->panel('main', 'Number', function (PanelBuilder $p): void { $p->number('weight', 'Basket weight (g)')->default(1200)->min(200)->max(9000)->step(100); }),
      'keys' => [...$open, $bs, $bs, $bs, $bs, '4', '2', '0', '0'],
      'rows' => 6,
    ],
    'rating' => [
      'form' => static fn(): Form => Form::create('Rating field')->panel('main', 'Rating', function (PanelBuilder $p): void { $p->rating('freshness', 'Freshness')->default(4)->captions([1 => 'Poor', 3 => 'Fair', 5 => 'Excellent']); }),
      // Walk down the scale and back, so the animation passes through a
      // captioned point; the static frame rests on that point, since a caption
      // is half of what the field shows.
      'keys' => [...$open, $left, $left, $right, $right],
      'static_keys' => [...$open, $left],
      'rows' => 6,
    ],
    'calendar' => [
      'form' => static fn(): Form => Form::create('Calendar field')->panel('main', 'Calendar', function (PanelBuilder $p): void { $p->calendar('harvest', 'Harvest date')->default('2026-07-15'); }),
      'keys' => [...$open, $down],
      'rows' => 14,
    ],
    'textarea' => [
      'form' => static fn(): Form => Form::create('Textarea field')->panel('main', 'Textarea', function (PanelBuilder $p): void { $p->textarea('notes', 'Tasting notes')->default('Crisp and sweet' . chr(10) . 'Hint of citrus'); }),
      'keys' => [...$open, $enter, 'S', 'l', 'i', 'g', 'h', 't', 'l', 'y', ' ', 't', 'a', 'r', 't'],
      'rows' => 8,
    ],
    'password' => [
      'form' => static fn(): Form => Form::create('Password field')->panel('main', 'Password', function (PanelBuilder $p): void { $p->password('code', 'Order code')->default('melon7'); }),
      'keys' => [...$open, $bs, $bs, $bs, $bs, $bs, $bs, 'g', 'r', 'a', 'p', 'e', '5'],
      'rows' => 6,
    ],
    'select' => [
      'form' => static fn(): Form => Form::create('Select field')->panel('main', 'Select', function (PanelBuilder $p): void { $p->select('fruit', 'Fruit')->default('apple')->options(['apple' => 'Apple', 'banana' => 'Banana', 'cherry' => 'Cherry']); }),
      'keys' => [...$open, $down],
      'rows' => 8,
    ],
    'select-descriptions' => [
      'form' => static fn(): Form => Form::create('Option descriptions')->panel('main', 'Select', function (PanelBuilder $p): void { $p->select('fruit', 'Fruit')->default('apple')->option('apple', 'Apple', description: 'Crisp and sweet, the everyday choice.')->option('banana', 'Banana', description: 'Rich in potassium; ripens off the tree.')->option('cherry', 'Cherry', description: 'Short season; best eaten fresh.'); }),
      'keys' => [...$open, $down],
      'rows' => 12,
    ],
    'select-multiple' => [
      'form' => static fn(): Form => Form::create('MultiSelect field')->panel('main', 'MultiSelect', function (PanelBuilder $p): void { $p->select('basket', 'Basket')->multiple()->default(['apple'])->options(['apple' => 'Apple', 'carrot' => 'Carrot', 'tomato' => 'Tomato']); }),
      'keys' => [...$open, $down, $space],
      'rows' => 8,
    ],
    'select-multiple-limited' => [
      'form' => static fn(): Form => Form::create('Bounded MultiSelect')->panel('main', 'MultiSelect', function (PanelBuilder $p): void { $p->select('basket', 'Basket')->multiple()->minSelections(2)->maxSelections(3)->options(['apple' => 'Apple', 'carrot' => 'Carrot', 'tomato' => 'Tomato']); }),
      'keys' => [...$open, $space, $down, $space],
      'rows' => 11,
    ],
    'reorder' => [
      'form' => static fn(): Form => Form::create('Reorder field')->panel('main', 'Reorder', function (PanelBuilder $p): void { $p->reorder('basket', 'Basket')->options(['apple' => 'Apple', 'carrot' => 'Carrot', 'tomato' => 'Tomato']); }),
      'keys' => [...$open, $space, $down, $space],
      'rows' => 12,
    ],
    'reorder-descriptions' => [
      'form' => static fn(): Form => Form::create('Option descriptions')->panel('main', 'Reorder', function (PanelBuilder $p): void { $p->reorder('basket', 'Basket')->option('apple', 'Apple', description: 'Crisp and sweet, the everyday choice.')->option('carrot', 'Carrot', description: 'Stays crisp for weeks when kept cold.')->option('tomato', 'Tomato', description: 'Best ripened on the vine, never chilled.'); }),
      'keys' => [...$open, $down],
      'rows' => 12,
    ],
    'suggest' => [
      'form' => static fn(): Form => Form::create('Suggest field')->panel('main', 'Suggest', function (PanelBuilder $p): void { $p->suggest('fruit', 'Fruit')->options(['Apple' => 'Apple', 'Apricot' => 'Apricot', 'Banana' => 'Banana', 'Cherry' => 'Cherry', 'Mango' => 'Mango']); }),
      'keys' => [...$open, 'C', 'h', $down],
      'rows' => 10,
    ],
    'suggest-ghost' => [
      'form' => static fn(): Form => Form::create('Ghost text')->panel('main', 'Suggest', function (PanelBuilder $p): void { $p->suggest('fruit', 'Fruit')->options(['Apple' => 'Apple', 'Apricot' => 'Apricot', 'Banana' => 'Banana', 'Cherry' => 'Cherry', 'Mango' => 'Mango'])->ghost(); }),
      // The preview only exists while the query is a prefix of a candidate and
      // nothing is highlighted, so the frames settle on typed text alone.
      'keys' => [...$open, 'A', 'p'],
      'static_keys' => [...$open, 'A', 'p'],
      'rows' => 10,
    ],
    'suggest-descriptions' => [
      'form' => static fn(): Form => Form::create('Option descriptions')->panel('main', 'Suggest', function (PanelBuilder $p): void { $p->suggest('fruit', 'Fruit')->option('Apple', 'Apple', description: 'Crisp and sweet, the everyday choice.')->option('Apricot', 'Apricot', description: 'Small and tart; best when soft.')->option('Banana', 'Banana', description: 'Rich in potassium; ripens off the tree.')->option('Cherry', 'Cherry', description: 'Short season; best eaten fresh.')->option('Mango', 'Mango', description: 'Fragrant and juicy when it yields to a squeeze.'); }),
      'keys' => [...$open, $down],
      'static_keys' => [...$open, $down],
      'rows' => 13,
    ],
    'search' => [
      'form' => static fn(): Form => Form::create('Search field')->panel('main', 'Search', function (PanelBuilder $p): void { $p->search('vegetable', 'Vegetable')->default('carrot')->options(['carrot' => 'Carrot', 'potato' => 'Potato', 'onion' => 'Onion', 'pepper' => 'Pepper']); }),
      'keys' => [...$open, 'o', 'n'],
      'rows' => 10,
    ],
    'search-descriptions' => [
      'form' => static fn(): Form => Form::create('Option descriptions')->panel('main', 'Search', function (PanelBuilder $p): void { $p->search('vegetable', 'Vegetable')->default('carrot')->option('carrot', 'Carrot', description: 'Stays crisp for weeks when kept cold.')->option('potato', 'Potato', description: 'Stores best somewhere cool and dark.')->option('onion', 'Onion', description: 'Sharp raw, sweet once cooked.')->option('pepper', 'Pepper', description: 'Crunchy and bright; sweetest when red.'); }),
      'keys' => [...$open, $down],
      'rows' => 12,
    ],
    'search-multiple' => [
      'form' => static fn(): Form => Form::create('MultiSearch field')->panel('main', 'MultiSearch', function (PanelBuilder $p): void { $p->search('basket', 'Basket')->multiple()->default(['apple'])->options(['apple' => 'Apple', 'banana' => 'Banana', 'carrot' => 'Carrot', 'tomato' => 'Tomato']); }),
      'keys' => [...$open, 't', 'o', $space],
      'rows' => 10,
    ],
    'search-multiple-limited' => [
      'form' => static fn(): Form => Form::create('Bounded MultiSearch')->panel('main', 'MultiSearch', function (PanelBuilder $p): void { $p->search('basket', 'Basket')->multiple()->minSelections(2)->maxSelections(3)->options(['apple' => 'Apple', 'banana' => 'Banana', 'carrot' => 'Carrot', 'tomato' => 'Tomato']); }),
      'keys' => [...$open, $space, $down, $space],
      'rows' => 13,
    ],
    'confirm' => [
      'form' => static fn(): Form => Form::create('Confirm field')->panel('main', 'Confirm', function (PanelBuilder $p): void { $p->confirm('organic', 'Organic only?')->default(TRUE); }),
      'keys' => [...$open, 'n'],
      'rows' => 6,
    ],
    'toggle' => [
      'form' => static fn(): Form => Form::create('Toggle field')->panel('main', 'Toggle', function (PanelBuilder $p): void { $p->toggle('ripeness', 'Ripeness')->default('ripe')->options(['ripe' => 'Ripe', 'unripe' => 'Unripe']); }),
      'keys' => [...$open, 'u'],
      'rows' => 6,
    ],
    'pause' => [
      'form' => static fn(): Form => Form::create('Pause field')->panel('main', 'Pause', function (PanelBuilder $p): void { $p->pause('review', 'Review your basket'); }),
      'keys' => [$enter],
      'rows' => 6,
    ],
    'markup' => [
      'form' => static fn(): Form => Form::create('Markup')->panel('main', 'Note', function (PanelBuilder $p): void {
        $p->note('intro', 'Fresh produce order')->body('A read-only card - the cursor skips it.');
        $p->note('packing', 'Ready to pack')->body('Framed with a border.')->bordered();
      }),
      'keys' => [$enter],
      'rows' => 14,
      'subject' => 'markup',
    ],
    'markup-markdown' => [
      'form' => static fn(): Form => Form::create('Markdown note')->panel('main', 'Note', function (PanelBuilder $p): void {
        $p->note('order', 'Fresh produce order')->body('Pick what is **ripe** today:' . chr(10) . '- crisp `apples`' . chr(10) . '- sweet *pears*' . chr(10) . 'See the [seasonal guide](https://example.com/guide).')->bordered();
      }),
      'keys' => [$enter],
      'rows' => 14,
      'options' => ['markdown' => TRUE],
      'subject' => 'markup-markdown',
    ],
    'markup-table' => [
      'form' => static fn(): Form => Form::create('Markup table')->panel('main', 'Stock', function (PanelBuilder $p): void {
        $p->note('stock', 'Basket contents')->body('Everything picked so far:')->table(['Fruit', 'Color', 'In stock'], [
          ['Apple', 'Red', '12'],
          ['Pear', 'Green', '5'],
          ['Plum', 'Purple', '120'],
        ]);
      }),
      'keys' => [$enter],
      'rows' => 16,
      'subject' => 'markup-table',
    ],
    'progress' => [
      'form' => static fn(): Form => Form::create('Progress field')->panel('main', 'Progress', function (PanelBuilder $p) use ($pack): void { $p->progress('pack', 'Packing the box')->steps(6)->work($pack); }),
      'keys' => [$enter, $enter],
      'rows' => 6,
    ],
    'filepicker' => [
      'form' => static fn(): Form => Form::create('File picker field')->panel('main', 'File picker', function (PanelBuilder $p) use ($tree): void { $p->filePicker('price_list', 'Price list')->startIn($tree)->filesOnly()->extensions(['csv'])->maxSize(2097152); }),
      'keys' => [...$open, $down],
      'rows' => 12,
    ],
    'filepicker-multiple' => [
      'form' => static fn(): Form => Form::create('File picker field')->panel('main', 'File picker', function (PanelBuilder $p) use ($tree): void { $p->filePicker('price_lists', 'Price lists')->multiple()->startIn($tree); }),
      'keys' => [...$open, $space, $down, $space],
      'rows' => 10,
    ],
    'filepicker-multiple-limited' => [
      'form' => static fn(): Form => Form::create('File picker field')->panel('main', 'File picker', function (PanelBuilder $p) use ($tree): void { $p->filePicker('price_lists', 'Price lists')->multiple()->minSelections(2)->maxSelections(3)->startIn($tree); }),
      'keys' => [...$open, $space, $down, $space],
      'rows' => 14,
    ],
  ];
}

/**
 * Drive one field and write its animated SVG.
 *
 * @param string $name
 *   The spec name.
 * @param array{form: callable(): \DrevOps\Tui\Builder\Form, keys: list<string|\DrevOps\Tui\Input\Key>, rows: int} $spec
 *   The spec.
 * @param string $assets_dir
 *   The output directory.
 * @param string $util_dir
 *   The tooling directory holding the svg-term renderer.
 * @param string $tmp_dir
 *   A scratch directory for the intermediate cast.
 */
function renderField(string $name, array $spec, string $assets_dir, string $util_dir, string $tmp_dir): void {
  $subject = assetSubject($name, $spec);

  foreach (DISPLAY_MODES as $suffix => $mode) {
    // The animated hero cards render at the default look - the padded
    // rounded border every panel demo shares. The border adds a row above
    // and below the content and the padding another one each side.
    $tester = (new TuiTester(($spec['form'])()))
      ->options(['color' => $mode['color'], 'unicode' => $mode['unicode'], 'mode' => Mode::Dark] + ($spec['options'] ?? []))
      ->rows($spec['rows'] + 4);
    $tester->run(...$spec['keys']);

    $frames = splitFrames($tester->output());

    if (count($frames) < 2) {
      throw new \RuntimeException(sprintf('Spec "%s" (%s) produced %d frame(s); an animation needs at least two.', $name, $suffix === '' ? 'default' : $suffix, count($frames)));
    }

    $cast_file = $tmp_dir . '/' . $subject . '-animated' . $suffix . '.cast';
    file_put_contents($cast_file, buildCast($frames, $spec['rows'] + 4));

    // The unmarked mode is the unicode, colour hero README.md embeds; the
    // light twin derives in the same pass.
    $svg_file = $assets_dir . '/' . $subject . '-dark-animated' . $suffix . '.svg';
    renderCast($cast_file, $svg_file, $util_dir);
    file_put_contents($svg_file, slowAnimation((string) file_get_contents($svg_file), ANIMATION_SLOWDOWN));
    deriveLightTwin($svg_file);
  }

  printf("  %s-dark-animated*.svg (4 display modes)\n", $subject);
}

/**
 * The subject a spec's assets are named for.
 *
 * @param string $name
 *   The spec name.
 * @param array{subject?: string} $spec
 *   The spec.
 *
 * @return string
 *   The filename stem the display-mode and motion segments follow.
 */
function assetSubject(string $name, array $spec): string {
  return $spec['subject'] ?? 'field-' . $name;
}

/**
 * Render a spec's static display-mode screenshots.
 *
 * The documentation page shows each field's editor, opened on its default, in
 * all four glyph and colour combinations. Every frame comes from the same
 * scripted open, so the grid stays consistent with itself and with the animated
 * hero above it.
 *
 * @param string $name
 *   The spec name.
 * @param array{form: callable(): \DrevOps\Tui\Builder\Form, keys: list<string|\DrevOps\Tui\Input\Key>, rows: int} $spec
 *   The spec.
 * @param string $assets_dir
 *   The output directory.
 * @param string $util_dir
 *   The tooling directory holding the svg-term renderer.
 * @param string $tmp_dir
 *   A scratch directory for the intermediate cast.
 */
function renderStaticVariants(string $name, array $spec, string $assets_dir, string $util_dir, string $tmp_dir): void {
  // A gate settles one Enter in and markup is non-interactive, so both open
  // with a single drill into the panel; every other field opens its editor
  // with the hub-into-panel-into-field drill.
  $enter = Key::named(KeyName::Enter);
  $subject = assetSubject($name, $spec);
  $open = in_array($name, ['pause', 'markup', 'markup-markdown', 'markup-table'], TRUE) ? [$enter] : [$enter, $enter];
  // A field whose opened editor shows nothing worth a screenshot until a key
  // is pressed (suggest highlights no row until you arrow into the list)
  // declares the keystrokes its static frame settles on.
  $keys = $spec['static_keys'] ?? $open;
  $clear = Ansi::ESC . '[2J' . Ansi::ESC . '[H';

  foreach (DISPLAY_MODES as $suffix => $mode) {
    // The static screenshots share the default padded rounded border, so
    // the four extra chrome rows join the content budget here too.
    $tester = (new TuiTester(($spec['form'])()))
      ->options(['color' => $mode['color'], 'unicode' => $mode['unicode'], 'mode' => Mode::Dark] + ($spec['options'] ?? []))
      ->rows($spec['rows'] + 4);
    $tester->run(...$keys);

    $frames = splitFrames($tester->output());
    if ($frames === []) {
      throw new \RuntimeException(sprintf('Spec "%s" static "%s" produced no frame.', $name, $suffix === '' ? 'default' : $suffix));
    }

    $frame = $frames[count($frames) - 1];
    $cast = json_encode(['version' => 2, 'width' => castWidth([$frame]), 'height' => $spec['rows'] + 4]) . "\n"
      . json_encode([0.0, 'o', $clear . $frame]) . "\n";
    $cast_file = $tmp_dir . '/' . $subject . '-static' . $suffix . '.cast';
    file_put_contents($cast_file, $cast);

    $svg_file = $assets_dir . '/' . $subject . '-dark-static' . $suffix . '.svg';
    renderCast($cast_file, $svg_file, $util_dir, 0);
    deriveLightTwin($svg_file);
  }
}

/**
 * Split captured output into whole rendered frames.
 *
 * Every frame the panel loop draws is prefixed by the clear-screen sequence, so
 * splitting on it yields one entry per repaint; the leading setup chunk and any
 * blank tail are dropped.
 *
 * @param string $output
 *   The captured terminal output.
 *
 * @return list<string>
 *   The frame byte strings, in order.
 */
function splitFrames(string $output): array {
  $clear = Ansi::ESC . '[2J' . Ansi::ESC . '[H';
  $parts = explode($clear, $output);
  $frames = array_values(array_filter($parts, static fn(string $s): bool => trim(Ansi::strip($s)) !== ''));

  // The in-memory capture stream applies no ONLCR translation, so the frames
  // carry bare line feeds; the emulator needs a carriage return to return to
  // column 0, or every row starts where the last one ended.
  return array_map(static fn(string $frame): string => str_replace("\n", "\r\n", str_replace("\r", '', $frame)), $frames);
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
    foreach (explode("\n", str_replace("\r", '', $frame)) as $line) {
      $width = max($width, Ansi::width($line));
    }
  }

  return $width;
}

/**
 * Assemble an asciicast v2 that plays the frames as an animation.
 *
 * @param list<string> $frames
 *   The captured frames.
 * @param int $rows
 *   The terminal height.
 *
 * @return string
 *   The cast file contents.
 */
function buildCast(array $frames, int $rows): string {
  $clear = Ansi::ESC . '[2J' . Ansi::ESC . '[H';
  $lines = [json_encode(['version' => 2, 'width' => castWidth($frames), 'height' => $rows])];

  $last = count($frames) - 1;
  $time = 0.0;
  foreach ($frames as $index => $frame) {
    $lines[] = json_encode([round($time, 3), 'o', $clear . $frame]);
    // The final frame's hold is HOLD_LAST alone, so it does not advance here.
    if ($index < $last) {
      $time += $index === 0 ? HOLD_FIRST : HOLD_STEP;
    }
  }
  // Hold the final frame before the animation loops back to the first.
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
 *   A timestamp in milliseconds to capture a single static frame, or NULL to
 *   render the whole cast as an animation.
 */
function renderCast(string $cast_file, string $svg_file, string $util_dir, ?int $at = NULL): void {
  // Clear any prior output first, so a failed render leaves no stale file for
  // the caller to mistake for (and re-slow) a fresh one.
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
$tmp_dir = dirname(__DIR__, 2) . '/.artifacts/tmp/field-svgs';
$tree = dirname(__DIR__, 2) . '/playground/sample-project';

if (!is_dir($tmp_dir)) {
  mkdir($tmp_dir, 0755, TRUE);
}

$specs = fieldSpecs($tree);
$only = array_slice($argv, 1);
$names = $only === [] ? array_keys($specs) : $only;

info('Rendering ' . count($names) . ' animation(s)...');

foreach ($names as $name) {
  if (!isset($specs[$name])) {
    throw new \RuntimeException('Unknown spec: ' . $name);
  }

  renderField($name, $specs[$name], $assets_dir, $util_dir, $tmp_dir);
  renderStaticVariants($name, $specs[$name], $assets_dir, $util_dir, $tmp_dir);
}

info('Done.');
