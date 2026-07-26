#!/usr/bin/env php
<?php

/**
 * @file
 * Generate every terminal SVG asset - the single entry point.
 *
 * Records terminal sessions for the playground panel demos (the panel TUI
 * runners) and the widget montage, then converts the recordings to animated
 * SVGs; it also renders the option-group, password-reveal and discovery static
 * frames. Every per-widget card - both its animations and its static
 * display-mode screenshots - is rendered deterministically by
 * render-widget-svgs.php, the built-in theme previews by render-theme-svgs.php,
 * and the progress primitive by render-progress-svgs.php; a no-argument run
 * spawns all three alongside the recording workers, so one command regenerates
 * the whole set. Each dark SVG
 * derives its light twin the moment it lands (svg-light-twin.php), so the
 * pairs can never drift apart.
 * Static frames are anchored to the moment the demo's gate text first appears in
 * the recording; every animated SVG is slowed to ANIMATION_SLOWDOWN, and every
 * generated SVG is verified to contain the expected content before the job
 * succeeds. A recording that captures an expect failure aborts its job rather
 * than shipping the error text inside the animation.
 *
 * Output filenames follow the explicit convention shared with those sibling
 * scripts: <subject>-<dark|light>-<animated|static>[-ascii][-no-ansi].svg.
 *
 * Supports parallel execution: when run without arguments, launches all
 * recordings as parallel worker processes for faster generation.
 *
 * Dependencies: asciinema, expect, node, npm
 *
 * Environment variables:
 * - SCRIPT_QUIET: Set to '1' to suppress verbose messages.
 *
 * Usage:
 * @code
 * php docs/util/update-assets.php
 * php docs/util/update-assets.php --record widget-select
 * @endcode
 */

declare(strict_types=1);

// Default terminal dimensions for recordings.
define('TERMINAL_COLS', 80);
define('TERMINAL_ROWS', 24);

// Maximum idle time in recordings (seconds).
define('MAX_IDLE_TIME', 3);

// Pause at the end of each recording before the animation loops (seconds).
define('END_PAUSE', 10);

// Offset of a static frame after its anchor text first appears (ms). The
// interactions pause for 1000ms after their gate matches, so half of that
// lands safely inside the stable initial frame.
define('FRAME_SETTLE_MS', 500);

// The playback-speed factor (ANIMATION_SLOWDOWN) and the slowAnimation() scaler
// are shared with render-widget-svgs.php, as is the light-twin derivation.
require_once __DIR__ . '/svg-slowdown.php';
require_once __DIR__ . '/svg-light-twin.php';

/**
 * The expect body walking the all-widgets montage field by field.
 *
 * The montage form (playground/02-widgets-all-widgets.php) is one panel with
 * every widget type. Fields edit inline and accepting keeps the cursor on
 * the field, so each step is: open with Enter, drive the widget with its own
 * keys, accept, then arrow down to the next field. The calendar is the one
 * standalone field - its month grid takes the whole screen and returns to
 * the panel on accept.
 *
 * @return string
 *   The expect script body.
 */
function allWidgetsInteraction(): string {
  return <<<'EXPECT'
# Wait for the hub, then drill into the montage panel.
expect "Widgets" {
    pause 2000
    safe_send "\r"
}

# Text: clear the default, type "Apple", accept.
pause 1500
safe_send "\r"
pause 1000
press_backspace
press_backspace
press_backspace
press_backspace
type_text "Apple"
wait_and_enter
arrow_down

# Number: clear the default, type a new one, accept.
pause 800
safe_send "\r"
pause 1000
press_backspace
press_backspace
press_backspace
press_backspace
type_text "4200"
wait_and_enter
arrow_down

# Calendar (standalone): the full-screen grid, down a week, accept.
pause 800
safe_send "\r"
pause 1500
arrow_down
wait_and_enter
arrow_down

# Textarea: Enter adds a line, type, Tab accepts.
pause 800
safe_send "\r"
pause 1000
safe_send "\r"
type_text "Slightly tart"
pause 1000
press_tab
arrow_down

# Password: type extra characters (masked), accept.
pause 800
safe_send "\r"
pause 1000
type_text "grape5"
wait_and_enter
arrow_down

# Select: down to the next option, accept.
pause 800
safe_send "\r"
pause 1000
arrow_down
wait_and_enter
arrow_down

# MultiSelect: toggle the second option, accept.
pause 800
safe_send "\r"
pause 1000
arrow_down
toggle_space
wait_and_enter
arrow_down

# Reorder: grab the top item, move it down, drop, accept.
pause 800
safe_send "\r"
pause 1000
toggle_space
arrow_down
toggle_space
wait_and_enter
arrow_down

# Suggest: type to filter, highlight a suggestion, accept.
pause 800
safe_send "\r"
pause 1000
type_text "Ch"
arrow_down
wait_and_enter
arrow_down

# Search: type to filter down to one option, accept.
pause 800
safe_send "\r"
pause 1000
type_text "on"
wait_and_enter
arrow_down

# MultiSearch: filter, toggle the match, accept.
pause 800
safe_send "\r"
pause 1000
type_text "to"
toggle_space
wait_and_enter
arrow_down

# Confirm: switch to No, accept.
pause 800
safe_send "\r"
pause 1000
type_text "n"
wait_and_enter
arrow_down

# Toggle: select the second value by its first letter, accept.
pause 800
safe_send "\r"
pause 1000
type_text "u"
wait_and_enter
arrow_down

# Pause: acknowledge.
pause 800
safe_send "\r"

# Back to the hub and submit.
press_escape
pause 1000
arrow_down
pause 600
safe_send "\r"
EXPECT;
}

/**
 * The expect body driving the produce-box capstone panel TUI.
 *
 * @return string
 *   The expect script body.
 */
function produceBoxInteraction(): string {
  return <<<'EXPECT'
# Wait for the hub, then drill into the Basics panel.
expect "Contents & options" {
    pause 2000
    safe_send "\r"
}

# Walk the Basics fields, showing the derived values.
pause 1500
arrow_down
arrow_down
arrow_down
arrow_down

# Back to the hub, drill into Contents & options.
press_escape
pause 1000
arrow_down
pause 500
safe_send "\r"

# Edit the box size: pick the next option.
pause 1500
safe_send "\r"
pause 1000
arrow_down
pause 600
safe_send "\r"

# Edit the contents multiselect: pick fruit, vegetables and herbs.
pause 1000
arrow_down
pause 400
safe_send "\r"
pause 1000
toggle_space
arrow_down
toggle_space
arrow_down
toggle_space
pause 600
safe_send "\r"

# Suggest a delivery day: trim the default, type another.
pause 1000
arrow_down
arrow_down
arrow_down
pause 400
safe_send "\r"
pause 1000
press_backspace
press_backspace
press_backspace
press_backspace
press_backspace
press_backspace
type_text "Monday"
pause 800
safe_send "\r"

# Back out and submit.
press_escape
pause 1000
arrow_down
arrow_down
pause 600
safe_send "\r"
EXPECT;
}

/**
 * The expect body driving the quick-start panel TUI.
 *
 * @return string
 *   The expect script body.
 */
function quickstartInteraction(): string {
  return <<<'EXPECT'
# Wait for the hub, drill into the single panel to reveal its fields, hold the
# frame, then quit.
expect "New order" {
    pause 2000
    safe_send "\r"
}
pause 2000
safe_send "q"
EXPECT;
}

/**
 * The expect body driving the inline-editing panel TUI.
 *
 * Each field opens in place on its row - the confirm's Yes/No, the number's
 * input, the select's option list - and the standalone calendar contrasts by
 * taking the whole screen.
 *
 * @return string
 *   The expect script body.
 */
function inlineEditingInteraction(): string {
  return <<<'EXPECT'
# Wait for the hub, then drill into the options panel.
expect "Order options" {
    pause 2000
    safe_send "\r"
}

# Confirm: the Yes/No editor opens in place on the row; flip it to Yes.
pause 1500
safe_send "\r"
pause 1000
type_text "y"
wait_and_enter
arrow_down

# Number: the input opens in the row; replace the default.
pause 800
safe_send "\r"
pause 1000
press_backspace
press_backspace
type_text "12"
wait_and_enter
arrow_down

# Select: the option list drops under the label, inside the panel.
pause 800
safe_send "\r"
pause 1000
arrow_down
wait_and_enter
arrow_down

# Calendar: the one ->standalone() field - its month grid takes the whole
# screen and returns to the panel on accept.
pause 800
safe_send "\r"
pause 1500
arrow_down
wait_and_enter

# Back to the hub and submit.
press_escape
pause 1000
arrow_down
pause 600
safe_send "\r"
EXPECT;
}

/**
 * The expect body driving the derived-values panel TUI.
 *
 * @return string
 *   The expect script body.
 */
function derivedValuesInteraction(): string {
  return <<<'EXPECT'
# Wait for the hub, then drill into the naming panel.
expect "Naming" {
    pause 2000
    safe_send "\r"
}

# Hold the initial chain: Red Apple -> red_apple -> RED_APPLE.
pause 2500

# Rename the produce: trim "Apple", type "Plum".
safe_send "\r"
pause 1000
press_backspace
press_backspace
press_backspace
press_backspace
press_backspace
type_text "Plum"
pause 800
safe_send "\r"

# The chain re-settles: slug, code and lot all follow the new name.
pause 3000

# Back to the hub and submit.
press_escape
pause 1000
arrow_down
pause 600
safe_send "\r"
EXPECT;
}

/**
 * The expect body driving the conditional-fields panel TUI.
 *
 * @return string
 *   The expect script body.
 */
function conditionalFieldsInteraction(): string {
  return <<<'EXPECT'
# Wait for the hub, then drill into the packing panel.
expect "Packing" {
    pause 2000
    safe_send "\r"
}

# Contents: add herbs - the herb-bundle field appears below.
pause 1500
safe_send "\r"
pause 1000
arrow_down
arrow_down
toggle_space
pause 600
safe_send "\r"
pause 2000

# Box size: pick large - the weekly confirm appears, stackable goes.
arrow_down
pause 400
safe_send "\r"
pause 1000
arrow_down
pause 600
safe_send "\r"
pause 2500

# Back to the hub and submit.
press_escape
pause 1000
arrow_down
pause 600
safe_send "\r"
EXPECT;
}

/**
 * The expect body driving the vim key-bindings panel TUI.
 *
 * @return string
 *   The expect script body.
 */
function keyBindingsVimInteraction(): string {
  return <<<'EXPECT'
# Wait for the hub, then drill into the order panel.
expect "Order" {
    pause 2000
    safe_send "\r"
}

# Navigate with the vim keys: j down the fields, k back up.
pause 1500
safe_send "j"
pause 600
safe_send "j"
pause 600
safe_send "k"
pause 600

# The ? overlay lists whatever is bound; any key dismisses it.
safe_send "?"
pause 3000
press_escape

# Open the select under the cursor and pick the next option with j.
pause 800
safe_send "\r"
pause 1000
safe_send "j"
pause 600
safe_send "\r"

# Back to the hub; j reaches the buttons, Enter submits.
press_escape
pause 1000
safe_send "j"
pause 600
safe_send "\r"
EXPECT;
}

/**
 * The expect body driving the translations panel TUI.
 *
 * The gate is the form title, the one string the demo leaves untranslated,
 * so the match stays ASCII while everything else on screen is Ukrainian.
 * Unchecking three fruits moves the basket's condensed count from the
 * few-form to the one-form, showing the plural rules at work.
 *
 * @return string
 *   The expect script body.
 */
function translationsInteraction(): string {
  return <<<'EXPECT'
# Wait for the hub, then drill into the order panel.
expect "Produce order" {
    pause 2000
    safe_send "\r"
}

# Down to the basket sub-panel and drill in.
pause 1500
arrow_down
pause 400
safe_send "\r"

# Uncheck three fruits: the pluralized count will drop from four to one.
pause 1500
safe_send "\r"
pause 1000
toggle_space
arrow_down
toggle_space
arrow_down
toggle_space
pause 600
safe_send "\r"

# Back on the order panel: the basket row condenses to the one-form count.
pause 1000
press_escape
pause 2000

# Back to the hub and submit.
press_escape
pause 1000
arrow_down
pause 600
safe_send "\r"
EXPECT;
}

/**
 * The expect body driving the nested-panels panel TUI.
 *
 * @return string
 *   The expect script body.
 */
function nestedPanelsInteraction(): string {
  return <<<'EXPECT'
# Wait for the hub, then drill into Order.
expect "Order" {
    pause 2000
    safe_send "\r"
}

# Look at Order, then back out to the hub.
pause 1500
arrow_down
pause 1000
press_escape

# Drill into Delivery.
pause 1000
arrow_down
pause 500
safe_send "\r"

# Delivery: pick Doorstep (third option).
pause 1500
safe_send "\r"
pause 1000
arrow_down
arrow_down
pause 600
safe_send "\r"

# Drill into the nested Extras panel.
pause 1000
arrow_down
arrow_down
pause 500
safe_send "\r"

# Enable Herbs and Nuts.
pause 1500
safe_send "\r"
pause 1000
toggle_space
arrow_down
toggle_space
pause 600
safe_send "\r"

# Drill into the deeper Packaging panel.
pause 1000
arrow_down
arrow_down
pause 500
safe_send "\r"

# Bag weight: clear the default, pick from the list.
pause 1500
safe_send "\r"
pause 1000
press_backspace
press_backspace
press_backspace
press_backspace
arrow_down
arrow_down
arrow_down
pause 600
safe_send "\r"

# Back out to the hub and save.
press_escape
press_escape
press_escape
pause 1000
arrow_down
arrow_down
pause 600
safe_send "\r"
EXPECT;
}

/**
 * The expect body driving the bordered-panels panel TUI.
 *
 * @return string
 *   The expect script body.
 */
function borderedPanelsInteraction(): string {
  return <<<'EXPECT'
# Wait for the hub, then drill into Basics.
expect "Basics" {
    pause 2000
    safe_send "\r"
}

# Walk the Basics fields inside the bordered frame.
pause 1500
arrow_down
arrow_down

# Back to the hub, drill into Deployment.
press_escape
pause 1000
arrow_down
pause 500
safe_send "\r"

# Drill into the nested Resources panel - the border follows.
pause 1500
arrow_down
arrow_down
pause 500
safe_send "\r"

# Look at Resources, then back out to the hub.
pause 1500
press_escape
press_escape

# Submit via the Create button.
pause 1000
arrow_down
arrow_down
pause 600
safe_send "\r"
EXPECT;
}

/**
 * The expect body driving the fullscreen panel TUI.
 *
 * @return string
 *   The expect script body.
 */
function fullscreenInteraction(): string {
  return <<<'EXPECT'
# Wait for the centered grid hub, then walk it spatially.
expect "Summary" {
    pause 2000
    arrow_down
}

# Across the second row and back, then drill into Produce.
pause 800
arrow_right
pause 800
arrow_left
pause 600
safe_send "\r"

# Produce lays its own children out side by side; visit Vegetables.
pause 1500
arrow_right
pause 800
press_escape

# Down to the buttons, then submit via Place order.
pause 800
arrow_down
arrow_down
pause 600
safe_send "\r"
EXPECT;
}

/**
 * The expect body driving the panel-layout grid TUI.
 *
 * @return string
 *   The expect script body.
 */
function layoutInteraction(): string {
  return <<<'EXPECT'
# Wait for the grid hub: Summary on top, Produce and Delivery below.
expect "Summary" {
    pause 2000
    arrow_down
}

# Walk the second row, then open Produce's own side-by-side layout.
pause 800
arrow_right
pause 800
arrow_left
pause 600
safe_send "\r"

# Back out and submit via Place order.
pause 1500
press_escape
pause 800
arrow_down
arrow_down
pause 600
safe_send "\r"
EXPECT;
}

/**
 * The expect body driving the modal-panels panel TUI.
 *
 * @return string
 *   The expect script body.
 */
function modalPanelsInteraction(): string {
  return <<<'EXPECT'
# Wait for the hub, then drill into the basket.
expect "Basket" {
    pause 2000
    safe_send "\r"
}

# Move past the fields to Gift options and open the modal dialog.
pause 1500
arrow_down
arrow_down
arrow_down
pause 800
safe_send "\r"

# The dialog floats centered over the dimmed basket. Hold it, then Save.
pause 2500
arrow_down
arrow_down
pause 800
safe_send "\r"

# Back in the basket. Open the text-only warning modal and hold it.
pause 1200
arrow_down
pause 800
safe_send "\r"
pause 2500

# Keep the basket, back out to the hub, and place the order.
arrow_down
pause 800
safe_send "\r"
pause 1000
press_escape
pause 1000
arrow_down
pause 800
safe_send "\r"
EXPECT;
}

/**
 * The expect body driving the custom-theme (ocean) panel TUI.
 *
 * @return string
 *   The expect script body.
 */
function themeOceanInteraction(): string {
  return <<<'EXPECT'
# Acknowledge the banner.
expect "continue" {
    pause 2000
    safe_send " "
}

# Drill into the stall panel.
expect "Seaside stall" {
    pause 1500
    safe_send "\r"
}

# Rename the stall: clear "Harbour", type the new name.
pause 1500
safe_send "\r"
pause 1000
press_backspace
press_backspace
press_backspace
press_backspace
press_backspace
press_backspace
press_backspace
type_text "Seaview"
pause 600
safe_send "\r"

# Stock: pick Vegetables.
pause 1000
arrow_down
pause 400
safe_send "\r"
pause 1000
arrow_down
pause 600
safe_send "\r"

# Crates: take the apples and pears.
pause 1000
arrow_down
pause 400
safe_send "\r"
pause 1000
toggle_space
arrow_down
toggle_space
pause 600
safe_send "\r"

# Back to the hub and submit.
press_escape
pause 1000
arrow_down
pause 600
safe_send "\r"
EXPECT;
}

/**
 * Get all job definitions.
 *
 * @param string $project_dir
 *   Path to the project root.
 *
 * @return array<string, array<string, mixed>>
 *   Keyed by job name, each containing command, interact, rows, cols and
 *   optionally: at_needle (text anchoring a static frame - the frame is
 *   captured FRAME_SETTLE_MS after the text first appears), at (a fixed
 *   static-frame timestamp in ms), light (render on a light surface), dos
 *   (render on the CGA blue surface), and verify (text every animated SVG
 *   must contain).
 */
function getJobs(string $project_dir): array {
  $jobs = [];

  // Display-mode variants are forced through the environment - the playground
  // scripts take no flags: glyphs follow the locale (LC_ALL=C is ASCII) and
  // colour follows the NO_COLOR convention.
  $env_variants = ['' => '', '-ascii' => 'LC_ALL=C ', '-no-ansi' => 'NO_COLOR=1 ', '-ascii-no-ansi' => 'LC_ALL=C NO_COLOR=1 '];

  // The all-widgets montage: every widget on one panel, walked field by
  // field, in all display modes. "Pause" is the last field walked, so its
  // label proves the whole sequence was recorded. The screen is sized for
  // the content: all fifteen fields fit without scrolling, and the rows
  // stay wide enough for the right-aligned provenance badges - on a
  // narrower terminal a badged row overflows, wraps, and every frame below
  // it renders torn.
  foreach ($env_variants as $suffix => $env) {
    $jobs['widgets' . $suffix] = [
      'command' => 'env LINES=24 COLUMNS=80 ' . $env . 'php ' . $project_dir . '/playground/02-widgets-all-widgets.php',
      'interact' => allWidgetsInteraction(),
      'rows' => 24,
      'cols' => 80,
      'verify' => 'Pause',
    ];
  }

  // The produce box: the capstone panel TUI demo, in all display modes.
  foreach ($env_variants as $suffix => $env) {
    $jobs['produce-box' . $suffix] = [
      'command' => 'env LINES=' . TERMINAL_ROWS . ' COLUMNS=' . TERMINAL_COLS . ' ' . $env . 'php ' . $project_dir . '/playground/14-produce-box.php',
      'interact' => produceBoxInteraction(),
      'rows' => TERMINAL_ROWS,
      'cols' => TERMINAL_COLS,
      'verify' => 'Contents & options',
    ];
  }

  // The quick-start form: a static frame of the single panel's fields. The
  // default padded box spreads the five fields, so the frame is tall enough
  // to hold them all un-scrolled.
  $jobs['quickstart'] = [
    'command' => 'env LINES=18 COLUMNS=72 php ' . $project_dir . '/playground/01-quickstart.php',
    'interact' => quickstartInteraction(),
    'rows' => 18,
    'cols' => 72,
    'at_needle' => 'Vegetables',
  ];

  // Inline editing: each editor opens in place on its panel row, with the
  // standalone calendar as the full-screen contrast.
  $jobs['inline-editing'] = [
    'command' => 'env LINES=20 COLUMNS=64 php ' . $project_dir . '/playground/04-inline-editing.php',
    'interact' => inlineEditingInteraction(),
    'rows' => 20,
    'cols' => 64,
    'verify' => 'Harvest date',
  ];

  // Derived values: renaming the produce re-settles the slug/code/lot chain,
  // so the re-derived slug proves the edit and the settle were captured.
  $jobs['derived-values'] = [
    'command' => 'env LINES=22 COLUMNS=72 php ' . $project_dir . '/playground/05-form-logic-derived-values.php',
    'interact' => derivedValuesInteraction(),
    'rows' => 22,
    'cols' => 72,
    'verify' => 'red_plum',
  ];

  // Conditional fields: picking herbs and the large box makes fields appear
  // and disappear; the herb bundle only renders once herbs are selected.
  $jobs['conditional-fields'] = [
    'command' => 'env LINES=22 COLUMNS=72 php ' . $project_dir . '/playground/05-form-logic-conditional-fields.php',
    'interact' => conditionalFieldsInteraction(),
    'rows' => 22,
    'cols' => 72,
    'verify' => 'Herb bundle',
  ];

  // The vim key-bindings preset: j/k navigation and the ? help overlay. The
  // taller screen leaves the overlay room to list the bound keys.
  $jobs['key-bindings-vim'] = [
    'command' => 'env LINES=22 COLUMNS=72 php ' . $project_dir . '/playground/10-key-bindings-vim.php',
    'interact' => keyBindingsVimInteraction(),
    'rows' => 22,
    'cols' => 72,
    'verify' => 'Fruit',
  ];

  // Translations: the Ukrainian catalog localizes the chrome and the labels;
  // the translated Fruits label proves the localized render was captured.
  $jobs['translations'] = [
    'command' => 'env LINES=20 COLUMNS=64 php ' . $project_dir . '/playground/12-translations.php',
    'interact' => translationsInteraction(),
    'rows' => 20,
    'cols' => 64,
    'verify' => 'Фрукти',
  ];

  // Nested panels with drill-in sub-panels and custom buttons.
  $jobs['nested-panels'] = [
    'command' => 'env LINES=' . TERMINAL_ROWS . ' COLUMNS=' . TERMINAL_COLS . ' php ' . $project_dir . '/playground/03-panels-nested.php',
    'interact' => nestedPanelsInteraction(),
    'rows' => TERMINAL_ROWS,
    'cols' => TERMINAL_COLS,
    'verify' => 'Order',
  ];

  // The panel browser wrapped in a rounded border frame.
  $jobs['bordered-panels'] = [
    'command' => 'env LINES=' . TERMINAL_ROWS . ' COLUMNS=' . TERMINAL_COLS . ' php ' . $project_dir . '/playground/03-panels-bordered.php',
    'interact' => borderedPanelsInteraction(),
    'rows' => TERMINAL_ROWS,
    'cols' => TERMINAL_COLS,
    'verify' => 'Basics',
  ];

  // The same form explicitly opted out of the default frame: border 'none'
  // at normal spacing.
  $jobs['borderless-panels'] = [
    'command' => 'env LINES=' . TERMINAL_ROWS . ' COLUMNS=' . TERMINAL_COLS . ' php ' . $project_dir . '/playground/03-panels-borderless.php',
    'interact' => borderedPanelsInteraction(),
    'rows' => TERMINAL_ROWS,
    'cols' => TERMINAL_COLS,
    'verify' => 'Basics',
  ];

  // Fullscreen: the frame stretched to the whole terminal, the panel grid
  // anchored to the centered halign/valign layout inside the border.
  $jobs['fullscreen-panels'] = [
    'command' => 'env LINES=' . TERMINAL_ROWS . ' COLUMNS=' . TERMINAL_COLS . ' php ' . $project_dir . '/playground/03-panels-fullscreen.php',
    'interact' => fullscreenInteraction(),
    'rows' => TERMINAL_ROWS,
    'cols' => TERMINAL_COLS,
    'verify' => 'Order name',
  ];

  // Panel layouts: the layout(1, 2) grid hub with the nested layout(2) grid,
  // walked spatially with the arrows.
  $jobs['panel-layout'] = [
    'command' => 'env LINES=' . TERMINAL_ROWS . ' COLUMNS=' . TERMINAL_COLS . ' php ' . $project_dir . '/playground/03-panels-layout.php',
    'interact' => layoutInteraction(),
    'rows' => TERMINAL_ROWS,
    'cols' => TERMINAL_COLS,
    'verify' => 'Vegetables',
  ];

  // A modal panel: a dialog centered over the dimmed parent, dismissed by its
  // own buttons - one dialog collecting fields, one a text-only warning.
  $jobs['modal-panels'] = [
    'command' => 'env LINES=' . TERMINAL_ROWS . ' COLUMNS=' . TERMINAL_COLS . ' php ' . $project_dir . '/playground/03-panels-modal.php',
    'interact' => modalPanelsInteraction(),
    'rows' => TERMINAL_ROWS,
    'cols' => TERMINAL_COLS,
    'verify' => 'Gift options',
  ];

  // The custom ocean theme with a banner. It demonstrates a custom palette,
  // not the default light/dark pair, so it has no meaningful light twin.
  $jobs['theme-ocean'] = [
    'command' => 'env LINES=24 COLUMNS=' . TERMINAL_COLS . ' php ' . $project_dir . '/playground/09-themes-custom.php',
    'interact' => themeOceanInteraction(),
    'rows' => 24,
    'cols' => TERMINAL_COLS,
    'verify' => 'Seaside stall',
    'twin' => FALSE,
  ];

  // The built-in theme previews (dark/light, bordered/borderless) are
  // rendered deterministically by render-theme-svgs.php through the scripted
  // keystroke harness, so no theme recordings run here.
  // Update-mode discovery: headless, shows the provenance-badged summary.
  $jobs['discovery'] = [
    'command' => 'php ' . $project_dir . '/playground/07-discovery.php',
    'interact' => '# Headless run: wait for the summary output.',
    'rows' => 8,
    'cols' => TERMINAL_COLS,
    'at_needle' => 'Box name',
  ];

  // Headless collection: no terminal, one output flush - the JSON result and
  // the provenance-badged summary. Everything prints at once, so any needle
  // anchors the same (complete) frame.
  $jobs['headless-collect'] = [
    'command' => 'php ' . $project_dir . '/playground/08-headless-collect.php',
    'interact' => '# Headless run: wait for the JSON and summary output.',
    'rows' => 9,
    'cols' => 72,
    'at_needle' => 'Weekly Box',
  ];

  // The test harness: TuiTester drives the panel loop without a TTY and the
  // script prints the collected answers and the final rendered frame. The
  // frame marker is the last text printed, so it anchors the full output.
  // The harness prints its final frame as the default 76-column padded box,
  // so the pty is wide and tall enough that no output line wraps or scrolls
  // the marker away.
  $jobs['testing'] = [
    'command' => 'php ' . $project_dir . '/playground/13-testing.php',
    'interact' => '# Headless run: wait for the harness output.',
    'rows' => 26,
    'cols' => 80,
    'at_needle' => 'final frame',
  ];

  // The password reveal toggle: a static frame of the revealed plaintext with
  // the reveal hint, anchored to the moment Tab flips the display to plaintext.
  // The masked value hides "melon7", so the plaintext only appears once
  // revealed - anchoring on it captures the revealed frame, not the initial one.
  $jobs['widget-password-reveal'] = [
    'command' => 'php ' . $project_dir . '/playground/02-widgets-password-reveal.php',
    'interact' => <<<'EXPECT'
# Drill into the field, reveal the value with Tab, hold the plaintext frame, then accept.
expect "Password widget" {
    pause 1000
    safe_send "\r"
    pause 800
    safe_send "\r"
    pause 800
    press_tab
    pause 1000
    wait_and_enter
}
EXPECT,
    'rows' => 12,
    'cols' => 44,
    'at_needle' => 'melon7',
  ];

  // Every per-widget card - the animated unicode-colour hero README.md embeds
  // and all four static display-mode screenshots the documentation pages show -
  // is rendered deterministically by render-widget-svgs.php, so no per-widget
  // recordings run here.

  // Option-kind demos: a select and a multiselect showing group headings,
  // separators and disabled options, each in all four display modes. The
  // static frame is anchored to an option only visible once the list is open
  // (the hub shows the form title too, so gating on it would capture the hub).
  $group_demos = [
    'select-groups' => ['gate' => 'Select with groups', 'needle' => 'Rhubarb', 'rows' => 18],
    'select-multiple-groups' => ['gate' => 'MultiSelect with groups', 'needle' => 'Leek', 'rows' => 21],
  ];

  foreach ($group_demos as $demo => $meta) {
    $interact = sprintf("# Grouped options: open the field, hold the grouped list, accept, submit.\nexpect \"%s\" {\n    pause 1000\n    safe_send \"\\r\"\n    pause 800\n    safe_send \"\\r\"\n    pause 1500\n    wait_and_enter\n    press_escape\n    pause 600\n    arrow_down\n    pause 400\n    safe_send \"\\r\"\n}", $meta['gate']);

    foreach ($env_variants as $suffix => $env) {
      // spawn does not parse VAR=value prefixes, so route them through env.
      $jobs['widget-' . $demo . $suffix] = [
        'command' => 'env ' . $env . 'php ' . $project_dir . '/playground/02-widgets-' . $demo . '.php',
        'interact' => $interact,
        'rows' => $meta['rows'],
        'cols' => 44,
        'at_needle' => $meta['needle'],
      ];
    }
  }

  return $jobs;
}

/**
 * Main functionality - orchestrator mode.
 *
 * Launches all recordings as parallel worker processes.
 */
function main(): void {
  $script_dir = __DIR__;
  $project_dir = dirname($script_dir, 2);
  $assets_dir = dirname($script_dir) . '/assets';

  info('TUI - Asset Generator');
  info('=====================');
  info('');

  checkDependencies();
  installNodeDependencies($script_dir);

  if (!is_dir($assets_dir)) {
    mkdir($assets_dir, 0755, TRUE);
  }

  $jobs = getJobs($project_dir);
  $tmp_dir = $project_dir . '/.artifacts/tmp/asciinema';
  if (!is_dir($tmp_dir)) {
    mkdir($tmp_dir, 0755, TRUE);
  }

  // Launch all workers in parallel: one per recorded job, plus the two
  // deterministic sibling generators, so this one command regenerates the
  // whole asset set.
  $script_path = __FILE__;
  $processes = [];
  $pipes_list = [];

  $workers = [];
  foreach (array_keys($jobs) as $name) {
    $workers[$name] = sprintf('php %s --record %s', escapeshellarg($script_path), escapeshellarg($name));
  }

  $workers['widget-svgs'] = sprintf('php %s', escapeshellarg($script_dir . '/render-widget-svgs.php'));
  $workers['theme-svgs'] = sprintf('php %s', escapeshellarg($script_dir . '/render-theme-svgs.php'));
  $workers['progress-svgs'] = sprintf('php %s', escapeshellarg($script_dir . '/render-progress-svgs.php'));

  info('Launching ' . count($workers) . ' workers in parallel...');
  info('');

  foreach ($workers as $name => $cmd) {
    $descriptors = [
      0 => ['pipe', 'r'],
      1 => ['pipe', 'w'],
      2 => ['pipe', 'w'],
    ];

    $pipes = [];
    $process = proc_open($cmd, $descriptors, $pipes, $project_dir);

    if (!is_resource($process)) {
      throw new \RuntimeException('Failed to launch worker for: ' . $name);
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], FALSE);
    stream_set_blocking($pipes[2], FALSE);

    $processes[$name] = $process;
    $pipes_list[$name] = $pipes;

    info('  Started: ' . $name);
  }

  info('');

  // Wait for all workers to complete and reset terminal state.
  // Workers run asciinema/expect which can leave the terminal in raw mode.
  $failed = [];
  foreach ($processes as $name => $process) {
    $stdout = stream_get_contents($pipes_list[$name][1]);
    $stderr = stream_get_contents($pipes_list[$name][2]);
    fclose($pipes_list[$name][1]);
    fclose($pipes_list[$name][2]);

    $exit_code = proc_close($process);

    if ($exit_code !== 0) {
      $failed[$name] = trim(($stdout ?: '') . ($stderr ?: ''));
      info('  FAILED: ' . $name);
    }
    else {
      info('  Done: ' . $name);
    }
  }

  // Reset terminal - workers may leave it in raw mode.
  shell_exec('stty sane 2>/dev/null');

  // Cleanup.
  info('');
  info('Cleaning up: ' . $tmp_dir);
  removeDir($tmp_dir);

  if (!empty($failed)) {
    info('');
    info('Errors:');
    foreach ($failed as $name => $output) {
      info('  ' . $name . ': ' . $output);
    }
    throw new \RuntimeException('Failed to generate ' . count($failed) . ' asset(s).');
  }

  // The social card composes a just-regenerated SVG, so it runs after the
  // workers rather than among them.
  info('');
  info('Rendering the social card...');
  passthru(sprintf('php %s', escapeshellarg($script_dir . '/render-social-card.php')), $card_exit);

  if ($card_exit !== 0) {
    throw new \RuntimeException('Failed to render the social card.');
  }

  // The audit gates the whole set, not just the jobs that ran: any leaked
  // setup text, wrapped row, structural defect or missing twin in ANY asset
  // fails the run before the results are trusted.
  info('');
  info('Auditing the full asset set...');
  passthru(sprintf('php %s %s', escapeshellarg($script_dir . '/audit-svgs.php'), escapeshellarg($assets_dir)), $audit_exit);

  if ($audit_exit !== 0) {
    throw new \RuntimeException('The generated assets failed the audit.');
  }

  info('');
  info('Done. ' . count($workers) . ' workers updated the SVG assets in ' . $assets_dir);
}

/**
 * Worker mode - process a single recording.
 *
 * @param string $name
 *   The job name to process.
 */
function processOne(string $name): void {
  $script_dir = __DIR__;
  $project_dir = dirname($script_dir, 2);
  $assets_dir = dirname($script_dir) . '/assets';
  $tmp_dir = $project_dir . '/.artifacts/tmp/asciinema';

  $jobs = getJobs($project_dir);
  if (!isset($jobs[$name])) {
    throw new \RuntimeException('Unknown job: ' . $name);
  }

  if (!is_dir($tmp_dir)) {
    mkdir($tmp_dir, 0755, TRUE);
  }

  if (!is_dir($assets_dir)) {
    mkdir($assets_dir, 0755, TRUE);
  }

  $job = $jobs[$name];
  $static = isset($job['at_needle']) || isset($job['at']);
  $cast_file = $tmp_dir . '/' . $name . '.cast';
  $expect_script = $tmp_dir . '/' . $name . '.exp';
  $svg_file = $assets_dir . '/' . assetName($name, $static);
  $rows = $job['rows'] ?? TERMINAL_ROWS;
  $cols = $job['cols'] ?? TERMINAL_COLS;

  createExpectScript($expect_script, $job['command'], $job['interact']);
  recordSession($cast_file, $expect_script, $rows, $cols);
  postProcessCast($cast_file);
  assertCleanCast($cast_file, $name);

  // A static frame is anchored to the moment its expected text first appears
  // in the recording; a fixed timestamp would race the process startup and
  // capture an empty screen (or the spawn echo) on a loaded machine.
  $needle = $job['at_needle'] ?? NULL;
  $at = $job['at'] ?? NULL;

  if (is_string($needle) && $needle !== '') {
    $appeared = castTimeOf($cast_file, $needle);

    if ($appeared === NULL) {
      throw new \RuntimeException(sprintf('Anchor text "%s" never appeared in the recording for "%s".', $needle, $name));
    }

    $at = $appeared + FRAME_SETTLE_MS;
  }

  convertToSvg($cast_file, $svg_file, $script_dir, is_int($at) ? $at : NULL, (bool) ($job['light'] ?? FALSE), (bool) ($job['dos'] ?? FALSE));
  verifySvg($svg_file, $name, is_string($needle) ? $needle : ($job['verify'] ?? NULL), is_string($needle));

  // A dark render derives its light twin in the same pass, so the pairs the
  // documentation serves can never drift. Jobs that render a light or
  // custom-palette surface themselves opt out with 'twin' => FALSE.
  if (($job['twin'] ?? TRUE) && str_contains(basename($svg_file), '-dark-')) {
    deriveLightTwin($svg_file);
  }
}

/**
 * Fail a job whose recording captured an expect failure.
 *
 * A crashed interaction (an undefined helper, a Tcl error) prints its trace
 * into the pty, where it would ship inside the animation and can still slip
 * past the content check when the verify needle appeared before the crash.
 *
 * @param string $cast_file
 *   Path to the cast file.
 * @param string $name
 *   The job name, for the error message.
 */
function assertCleanCast(string $cast_file, string $name): void {
  $cast = file_get_contents($cast_file);

  if ($cast === FALSE) {
    throw new \RuntimeException('Failed to read cast: ' . $cast_file);
  }

  foreach (['invalid command name', 'while executing', 'usage: ', 'Traceback'] as $marker) {
    if (str_contains($cast, $marker)) {
      throw new \RuntimeException(sprintf('The recording for "%s" captured an interaction failure ("%s" found in the cast).', $name, $marker));
    }
  }
}

/**
 * The time at which given text first appears in a cast's output.
 *
 * Handles both asciicast v2 (absolute timestamps) and v3 (deltas). The cast
 * must already be post-processed, so the returned time matches the timeline
 * the SVG renderer sees.
 *
 * @param string $cast_file
 *   Path to the cast file.
 * @param string $needle
 *   The text to look for in output events.
 *
 * @return int|null
 *   The time in milliseconds, or NULL when the text never appears.
 */
function castTimeOf(string $cast_file, string $needle): ?int {
  $lines = file($cast_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

  if ($lines === FALSE || count($lines) < 2) {
    return NULL;
  }

  $header = json_decode($lines[0], TRUE);
  $version = is_array($header) && isset($header['version']) ? (int) $header['version'] : 2;
  $time = 0.0;

  for ($index = 1; $index < count($lines); $index++) {
    $event = json_decode($lines[$index], TRUE);
    if (!is_array($event)) {
        continue;
    }
    if (count($event) < 3) {
        continue;
    }

    $time = $version >= 3 ? $time + (float) $event[0] : (float) $event[0];

    if ($event[1] === 'o' && str_contains((string) $event[2], $needle)) {
      return (int) round($time * 1000);
    }
  }

  return NULL;
}

/**
 * Verify a generated SVG actually shows terminal content.
 *
 * Guards against a frame captured before the demo painted anything: the SVG
 * must contain text nodes and, when expected text is given, every word of it.
 * A static render must also hold exactly one frame window - a second window
 * sits outside the viewBox and hides the real content behind an empty frame.
 *
 * @param string $svg_file
 *   Path to the generated SVG.
 * @param string $name
 *   The job name, for error messages.
 * @param string|null $expected
 *   Text the SVG must contain, or NULL to only require any text at all.
 * @param bool $single_frame
 *   Whether the SVG must contain exactly one frame window (static renders).
 */
function verifySvg(string $svg_file, string $name, ?string $expected, bool $single_frame = FALSE): void {
  $content = (string) file_get_contents($svg_file);

  if (!str_contains($content, '<text')) {
    throw new \RuntimeException(sprintf('Generated SVG for "%s" has no text content - the captured frame is empty.', $name));
  }

  if ($single_frame && substr_count($content, '<use xlink:href="#a"') !== 1) {
    throw new \RuntimeException(sprintf('Generated SVG for "%s" holds more than one frame window - the visible frame may be empty.', $name));
  }

  $text = html_entity_decode(strip_tags($content));

  // Recording-setup text must never ship inside an asset: the spawn
  // announcement, the pty geometry, the sanitized home path or any script
  // path in a frame means pre-demo noise was captured. The demo content is
  // produce-themed, so none of these fingerprints can occur legitimately.
  foreach (['spawn', 'LINES=', 'COLUMNS=', '/home/user', 'playground/', '.php'] as $fingerprint) {
    if (str_contains($text, $fingerprint)) {
      throw new \RuntimeException(sprintf('Generated SVG for "%s" leaked recording-setup text ("%s" found in a frame).', $name, $fingerprint));
    }
  }

  if ($expected === NULL || $expected === '') {
    return;
  }

  $words = preg_split('/\s+/', $expected);

  foreach (is_array($words) ? $words : [] as $word) {
    if ($word !== '' && !str_contains($text, $word)) {
      throw new \RuntimeException(sprintf('Generated SVG for "%s" is missing expected text "%s".', $name, $word));
    }
  }
}

/**
 * Check that all required dependencies are installed.
 */
function checkDependencies(): void {
  $deps = ['asciinema', 'expect', 'node', 'npm'];
  $missing = [];

  foreach ($deps as $dep) {
    if (empty(shell_exec('which ' . escapeshellarg($dep) . ' 2>/dev/null'))) {
      $missing[] = $dep;
    }
  }

  if (!empty($missing)) {
    throw new \RuntimeException('Missing required dependencies: ' . implode(', ', $missing));
  }

  info('All dependencies found.');
}

/**
 * Install Node.js dependencies for svg-term rendering.
 *
 * @param string $util_dir
 *   Path to the tooling directory containing svg-term-render.js.
 */
function installNodeDependencies(string $util_dir): void {
  info('Installing svg-term Node.js dependency...');

  $node_modules = $util_dir . '/node_modules';
  if (is_dir($node_modules . '/svg-term')) {
    info('svg-term already installed.');

    return;
  }

  $cmd = sprintf('npm install --prefix %s svg-term@1.3.1 2>&1', escapeshellarg($util_dir));
  $output = shell_exec($cmd);
  if (!is_dir($node_modules . '/svg-term')) {
    throw new \RuntimeException('Failed to install svg-term: ' . ($output ?? 'unknown error'));
  }

  info('svg-term installed.');
}

/**
 * Create an expect script that spawns a command and drives it.
 *
 * The shared prologue defines the interaction procs; the per-job body uses
 * them to drive the demo. Every script ends waiting for the process to exit.
 *
 * The pause proc waits while continuously draining the child's output: the
 * full-screen TUI redraws a whole frame per key press, so a plain sleep would
 * let the pty buffer fill and block the child on write, desynchronizing the
 * interaction and batching frames at the wrong timestamps.
 *
 * @param string $script_path
 *   Path to write the expect script.
 * @param string $command
 *   The command to spawn.
 * @param string $body
 *   The expect body driving the interaction.
 */
function createExpectScript(string $script_path, string $command, string $body): void {
  $template = <<<'EXPECT'
#!/usr/bin/env expect

set timeout 60
match_max 100000
log_user 1

proc safe_send {s} {
    if {[exp_pid] > 0} {
        send -- $s
    }
}

# Wait for the given time while draining the child's output, so frames reach
# the recorder as they are drawn.
proc pause {ms} {
    set end [expr {[clock milliseconds] + $ms}]
    while {[clock milliseconds] < $end} {
        expect -timeout 0 -re "___never_matches___"
        after 25
    }
}

proc wait_and_enter {} {
    pause 1000
    safe_send "\r"
}

proc type_text {text} {
    foreach char [split $text ""] {
        pause 120
        safe_send $char
    }
}

proc arrow_up {} {
    pause 300
    safe_send "\033\[A"
}

proc arrow_down {} {
    pause 300
    safe_send "\033\[B"
}

proc arrow_right {} {
    pause 300
    safe_send "\033\[C"
}

proc arrow_left {} {
    pause 300
    safe_send "\033\[D"
}

proc toggle_space {} {
    pause 300
    safe_send " "
}

proc press_backspace {} {
    pause 150
    safe_send "\x7f"
}

proc press_tab {} {
    pause 300
    safe_send "\t"
}

proc press_escape {} {
    pause 500
    safe_send "\033"
}

# The spawn announcement obeys log_user, and anything expect prints lands
# in the recording as visible frames - so the announcement is suppressed at
# the source and output copying resumes before the demo paints.
log_user 0
spawn @command@
log_user 1

@body@

# Let the final frames drain before waiting for the process to exit.
pause 1500

expect eof
EXPECT;

  $content = str_replace(['@command@', '@body@'], [$command, $body], $template);

  file_put_contents($script_path, $content);
  chmod($script_path, 0755);
}

/**
 * Record a session using asciinema with an expect script.
 *
 * @param string $cast_file
 *   Path to write the cast file.
 * @param string $expect_script
 *   Path to the expect script for automation.
 * @param int $rows
 *   Number of terminal rows.
 * @param int $cols
 *   Number of terminal columns.
 */
function recordSession(string $cast_file, string $expect_script, int $rows = TERMINAL_ROWS, int $cols = TERMINAL_COLS): void {
  $cmd = sprintf(
    'asciinema rec --command=%s --window-size=%dx%d --idle-time-limit=%d --overwrite %s 2>&1',
    escapeshellarg($expect_script),
    $cols,
    $rows,
    MAX_IDLE_TIME,
    escapeshellarg($cast_file)
  );

  $output = shell_exec($cmd);

  if (!file_exists($cast_file)) {
    throw new \RuntimeException('Failed to record session: ' . $cast_file . "\n" . ($output ?? ''));
  }
}

/**
 * Post-process a cast file.
 *
 * Removes the spawn command line and sanitizes paths.
 *
 * @param string $cast_file
 *   Path to the cast file.
 */
function postProcessCast(string $cast_file): void {
  $content = file_get_contents($cast_file);
  if ($content === FALSE) {
    return;
  }

  // Remove the spawn command line from the recording.
  $lines = explode("\n", $content);
  $filtered = [$lines[0]];
  for ($i = 1; $i < count($lines); $i++) {
    if (str_contains($lines[$i], 'spawn ')) {
      continue;
    }
    $filtered[] = $lines[$i];
  }

  // Add a pause at the end of the recording before the animation loops.
  // In asciicast v3, timestamps are relative (delta from previous event),
  // so we add an empty output event with the pause duration.
  $filtered[] = json_encode([END_PAUSE, 'o', ' ']);

  $content = implode("\n", $filtered);

  // Sanitize home directory paths.
  $home = getenv('HOME');
  if ($home !== FALSE && $home !== '') {
    $content = str_replace($home, '/home/user', $content);
  }

  file_put_contents($cast_file, $content);
}

/**
 * Convert a cast file to an SVG.
 *
 * When $at is provided, renders a single static frame at that timestamp.
 * Otherwise, renders an animated SVG of the full recording.
 *
 * @param string $cast_file
 *   Path to the input cast file.
 * @param string $svg_file
 *   Path to the output SVG file.
 * @param string $util_dir
 *   Path to the tooling directory containing svg-term-render.js.
 * @param int|null $at
 *   Optional timestamp in ms to capture a static frame.
 * @param bool $light
 *   Whether to render on a light surface (for light-mode palettes).
 * @param bool $dos
 *   Whether to render on the CGA blue surface (for the dos theme).
 */
function convertToSvg(string $cast_file, string $svg_file, string $util_dir, ?int $at = NULL, bool $light = FALSE, bool $dos = FALSE): void {
  $renderer = $util_dir . '/svg-term-render.js';

  // Clear any prior output first, so a failed render leaves no stale file that
  // the success check would accept and then re-slow.
  if (is_file($svg_file)) {
    unlink($svg_file);
  }

  $at_flag = $at !== NULL ? sprintf(' --at %d', $at) : '';
  $surface_flag = $dos ? ' --dos' : ($light ? ' --light' : '');
  $cmd = sprintf(
    'node %s %s %s --line-height 1.1%s%s 2>&1',
    escapeshellarg($renderer),
    escapeshellarg($cast_file),
    escapeshellarg($svg_file),
    $at_flag,
    $surface_flag
  );

  $output = shell_exec($cmd);

  if (!file_exists($svg_file) || filesize($svg_file) === 0) {
    throw new \RuntimeException('Failed to convert cast to SVG: ' . $cast_file . "\n" . ($output ?? ''));
  }

  // A static frame has no animation to slow; an animated render is scaled to
  // the project-wide playback speed.
  if ($at === NULL) {
    file_put_contents($svg_file, slowAnimation((string) file_get_contents($svg_file), ANIMATION_SLOWDOWN));
  }
}

/**
 * The asset filename for a job under the explicit naming convention.
 *
 * The job key already carries the display-mode suffixes; this adds the theme
 * (always dark here - each job derives its own light twin in-run) and the
 * motion, yielding <subject>-dark-<motion>[-ascii][-no-ansi].svg.
 *
 * @param string $job
 *   The job key.
 * @param bool $static
 *   Whether the job renders a single static frame.
 *
 * @return string
 *   The asset filename.
 */
function assetName(string $job, bool $static): string {
  $ascii = str_contains($job, '-ascii');
  $noansi = str_contains($job, '-no-ansi');
  $subject = str_replace(['-ascii', '-no-ansi'], '', $job);

  return $subject . '-dark-' . ($static ? 'static' : 'animated') . ($ascii ? '-ascii' : '') . ($noansi ? '-no-ansi' : '') . '.svg';
}

/**
 * Remove a directory recursively.
 *
 * @param string $directory
 *   Path to the directory to remove.
 */
function removeDir(string $directory): void {
  if (!is_dir($directory)) {
    return;
  }

  $cmd = sprintf('rm -rf %s 2>&1', escapeshellarg($directory));
  shell_exec($cmd);
}

/**
 * Print an informational message.
 *
 * @param string $message
 *   The message to print.
 */
function info(string $message): void {
  if (getenv('SCRIPT_QUIET') === '1') {
    return;
  }
  print $message . PHP_EOL;
}

// Entrypoint.
ini_set('display_errors', '1');

if (PHP_SAPI !== 'cli' || !empty($_SERVER['REMOTE_ADDR'])) {
  die('This script can be only ran from the command line.');
}

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
  if ((error_reporting() & $severity) === 0) {
    return FALSE;
  }
  throw new \ErrorException($message, 0, $severity, $file, $line);
});

try {
  // Worker mode: process a single recording.
  $record_index = array_search('--record', $argv, true);
  if ($record_index !== FALSE && isset($argv[$record_index + 1])) {
    processOne($argv[$record_index + 1]);
  }
  else {
    // Orchestrator mode: launch all in parallel.
    main();
  }
}
catch (\Exception $exception) {
  info('');
  info('ERROR: ' . $exception->getMessage());
  exit(1);
}
