<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="logo-light.svg">
    <img width="300" src="logo-dark.svg" alt="TUI logo">
  </picture>
</p>

<h1 align="center">Terminal user interfaces for PHP</h1>

<div align="center">

[![GitHub Issues](https://img.shields.io/github/issues/drevops/tui.svg)](https://github.com/drevops/tui/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/drevops/tui.svg)](https://github.com/drevops/tui/pulls)
[![Test PHP](https://github.com/drevops/tui/actions/workflows/test-php.yml/badge.svg)](https://github.com/drevops/tui/actions/workflows/test-php.yml)
[![codecov](https://codecov.io/gh/drevops/tui/graph/badge.svg?token=7WEB1IXBYT)](https://codecov.io/gh/drevops/tui)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/drevops/tui)
![LICENSE](https://img.shields.io/github/license/drevops/tui)
![Renovate](https://img.shields.io/badge/renovate-enabled-green?logo=renovatebot)

</div>

---

<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/assets/bordered-panels-dark-animated.svg">
    <img src="docs/assets/bordered-panels-light-animated.svg" width="100%" alt="Terminal form with a rounded border">
  </picture>
</p>

`drevops/tui` is a PHP engine for panel-based terminal forms: keyboard-driven questionnaires that collect a set of answers and hand them back to your code as typed values.

- **Declarative form model.** A form is declared with a fluent builder (`Form` / `PanelBuilder` / `FieldBuilder`): panels of typed fields, each field a widget with its own options, conditions, derivation rules and behavior.
- **Two collection modes, one declaration.** The same form runs as a full-screen interactive TUI on a terminal, or resolves non-interactively from a JSON payload, per-field environment variables, discovery rules and defaults.
- **Application-agnostic.** The engine doesn't know (or care) what application it serves; questions and handlers live in your code, and applying the collected answers is your job. It collects; you apply.
- **Dependency-light.** The runtime dependency surface is a single string-transform package.

The padded rounded border above is the default look. The same form explicitly opted out of the frame (`border` `none`, `normal` spacing):

<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/assets/borderless-panels-dark-animated.svg">
    <img src="docs/assets/borderless-panels-light-animated.svg" width="100%" alt="Terminal form without a border">
  </picture>
</p>

## 📖 Documentation

Full documentation lives at **[phptui.dev](https://phptui.dev)**. The in-development build, rebuilt from `main` ahead of each release, is previewed at **[tui-docs.netlify.app](https://tui-docs.netlify.app/)**.

## Features

Every feature has a reference page and a runnable, self-contained example in [`playground/`](playground):

| Feature | Summary | Docs | Example |
|---|---|---|---|
| 🧭 Full-screen TUI | Scrollable panel browser: hubs drill into sub-panels to any depth, contextual key-hint footer, <kbd>?</kbd> help overlay | [panels](https://phptui.dev/panels) | [`03-panels-*`](playground) |
| 🪟 Modal panels | A panel marked `->modal()` opens as a centered dialog over its dimmed parent, with its own submit/cancel buttons | [panels](https://phptui.dev/panels#modal-panels) | [`03-panels-*`](playground) |
| 🧱 Panel layouts | `->layout(1, 2)` arranges panels as a grid of side-by-side preview columns - rows of any width, recursively per level, with spatial arrow navigation | [panels](https://phptui.dev/panels#panel-layouts) | [`03-panels-*`](playground) |
| 🖥️ Fullscreen mode | `->fullscreen()` stretches the frame to the whole terminal; `halign`/`valign` anchor the content and min/max size options guard small or very wide terminals | [panels](https://phptui.dev/panels#fullscreen) | [`03-panels-*`](playground) |
| ⚡ Inline editing | A field's editor opens in place on the panel row; `->standalone()` opts a field out to full-screen | [panels](https://phptui.dev/panels#inline-editing) | [`04-inline-editing`](playground/04-inline-editing.php) |
| 🧩 Widgets | 14 field types: text, template, number, calendar, textarea, password, select, reorder, suggest, search, file picker, confirm, toggle, pause | [widgets](https://phptui.dev/widgets) | [`02-widgets-*`](playground) |
| 🏗️ Builder-driven | The form is declared in PHP with a fluent builder; the common cases need no code | [configuration](https://phptui.dev/configuration) | [`01-quickstart`](playground/01-quickstart.php) |
| 🎛️ Interactive or unattended | `run()` picks the mode: keyboard on a terminal, otherwise JSON payload + `TUI_<ID>` environment variables | [headless collection](https://phptui.dev/headless-collection) | [`08-headless-*`](playground) |
| 🔗 Derived values | Fields computed from other answers via `{{field}}` templates and str2name transforms, settling to a fixpoint | [configuration](https://phptui.dev/configuration#derived-values) | [`05-form-logic-*`](playground) |
| 🔀 Conditional fields | `->when()` conditions (eq/ne/in/contains, composable with all/any/not) drive visibility; form-level fix-ups reconcile answers, and an opt-in indent steps each field in from the answer that reveals it | [configuration](https://phptui.dev/configuration#conditional-fields) | [`05-form-logic-*`](playground) |
| ⚙️ Declared behavior | `->required()` rejects an empty value with a label-derived or declared message; dynamic defaults, validation and transforms as field closures, or as per-field handler classes resolved by naming convention | [field behavior](https://phptui.dev/field-behaviour) | [`06-field-behaviour-*`](playground) |
| 🔍 Discovery | Update mode detects defaults from an existing directory: dotenv keys, JSON dot-paths, path checks, directory scans | [discovery](https://phptui.dev/field-behaviour#discovery) | [`07-discovery`](playground/07-discovery.php) |
| ⏳ Progress | A `progress()` primitive wraps slow work: a spinner when the length is unknown, a determinate bar when known - theme-drawn, animating on a TTY, degrading to a plain line when piped or headless | [progress](https://phptui.dev/progress) | [`15-progress-*`](playground) |
| 🌐 Remote-backed options | `->optionsFrom()` resolves a search or suggest field's candidates from the live query - a themed `Loading…` while it runs, a typing burst settling into one call, a per-query cache, and `->minQuery()` holding it back until the query is worth sending | [options from a query](https://phptui.dev/progress#options-from-a-query) | [`17-query-options`](playground/17-query-options.php) |
| 🧾 Output | An `output()` primitive draws the chrome around a form: a titled box, five status lines and an aligned definition list - theme-drawn, dropping their color when piped or redirected | [output](https://phptui.dev/output) | [`17-output-*`](playground) |
| 📦 Self-describing answers | Answers carry provenance; `toSummary()` renders a badged, panel-grouped report and `toJson()` the machine result; `schema()`, `validate()` and `agentHelp()` describe the form itself | [self-describing answers](https://phptui.dev/headless-collection#self-describing-answers) | [`08-headless-*`](playground) |
| 🎨 Themes | Six built-ins selected by name; a custom theme is a `DefaultTheme` subclass overriding palette atoms and render methods | [themes](https://phptui.dev/themes) | [`09-themes-*`](playground) |
| ⌨️ Key bindings | Presets (`default`, `vim`, or a class) plus per-binding overrides scoped to navigation or a widget type; conflicts throw at setup | [key bindings](https://phptui.dev/key-bindings) | [`10-key-bindings-*`](playground) |
| ✨ Display modes | Dark/light follows the terminal background, glyphs follow the locale, color honors `NO_COLOR`; all three can be forced | [display modes](https://phptui.dev/display-modes) | [`11-display-modes-*`](playground) |
| 🧪 Test harness | `TuiTester` drives the real panel loop from scripted keystrokes, no TTY; assert on answers, output and rendered frames | [testing](https://phptui.dev/testing) | [`13-testing`](playground/13-testing.php) |
| 🌍 Translations | Bundled chrome catalogs load automatically; a directory, a single catalog file or an inline map layers your own strings and chrome overrides on top, falling back to English | [translations](https://phptui.dev/translations) | [`12-translations`](playground/12-translations.php) |

## Installation

```bash
composer require drevops/tui
```

## Quick start

Declare a form with the `Form` builder, then drive it through the `Tui` facade - the one class that wires up the engine, resolver, schema tools and TUI for you:

```php
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Tui;

$form = Form::create('Quick start')
  ->panel('order', 'New order', function (PanelBuilder $p): void {
    // A required single-line text field.
    $p->text('name', 'Order name')->required();

    // A single choice, starting on "Banana".
    $p->select('fruit', 'Fruit')->default('banana')->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
    ]);

    // A multi-select, with one option pre-checked.
    $p->select('veg', 'Vegetables')->multiple()->default(['carrot'])->options([
      'carrot' => 'Carrot',
      'tomato' => 'Tomato',
      'spinach' => 'Spinach',
    ]);

    // An integer bounded to a sensible quantity.
    $p->number('quantity', 'Quantity')->min(1)->max(99)->default(6);

    // A yes/no gate.
    $p->confirm('organic', 'Organic only?')->default(FALSE);
  });

$tui = new Tui($form, handler_namespaces: ['App\\Handler']);

$answers = $tui->run();
```

The facade's surface:

| Call | Purpose |
|---|---|
| `run($prompts, $version, $directory, $interactive)` | Collect answers; interactive on a TTY, headless otherwise (or forced via `$interactive`) |
| `collect($prompts, $directory, $update, $version)` | Headless collection from JSON + environment; `$update` enables discovery |
| `interact()` | The interactive panel TUI, explicitly |
| `progress($total, $caption, $work)` | Show slow work running around the form: a spinner with no total, a determinate bar with one - a theme-drawn primitive |
| `output()` | Draw the chrome around the form: a titled box, the five status lines and an aligned definition list - theme-drawn primitives |
| `schema()` / `validate($answers)` / `agentHelp()` | Describe the questions as structured metadata, validate an answer payload, emit the agent-facing answer schema |
| `theme($theme, $options)` / `keys($preset, $overrides)` | Select the theme and key bindings |
| `color($bool)` / `unicode($bool)` / `fullscreen($bool)` / `footer($bool)` / `clearOnExit($bool)` / `translator($t)` | Display and runtime switches |
| `form()` / `engine()` / `registry()` | The internals, for finer control |

Read the [full guide at phptui.dev](https://phptui.dev), and browse [`playground/`](playground) for complete, runnable examples - the numbered scripts for each feature in the table above.

## Widgets

There's a widget for most things you'd want to ask: text entry, numbers and dates, single and multiple choice, fuzzy search, filesystem browsing, and simple gates. Each one links to its full reference on [phptui.dev](https://phptui.dev/widgets), and every card below plays back the real interaction in whichever color scheme - light or dark - your reader is using.

<table>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-calendar-dark-animated.svg"><img src="docs/assets/widget-calendar-light-animated.svg" width="100%" alt="Calendar widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/calendar">Calendar</a></strong><br>A month calendar returning a normalized ISO <code>YYYY-MM-DD</code>; arrows move by day and week.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-confirm-dark-animated.svg"><img src="docs/assets/widget-confirm-light-animated.svg" width="100%" alt="Confirm widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/confirm">Confirm</a></strong><br>Yes/No toggle; arrows or <kbd>Space</kbd> switch, <kbd>y</kbd>/<kbd>n</kbd> set the choice directly, <kbd>Enter</kbd> accepts.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-filepicker-dark-animated.svg"><img src="docs/assets/widget-filepicker-light-animated.svg" width="100%" alt="File picker widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/filepicker">File picker</a></strong><br>Browse the filesystem for a path; arrows move, <kbd>→</kbd> enters a directory and <kbd>←</kbd> returns to its parent. Add <code>-&gt;multiple()</code> for several paths.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-number-dark-animated.svg"><img src="docs/assets/widget-number-light-animated.svg" width="100%" alt="Number widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/number">Number</a></strong><br>Integer entry (digits with an optional leading minus) accepted as an <code>int</code>, with optional min, max and step.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-password-dark-animated.svg"><img src="docs/assets/widget-password-light-animated.svg" width="100%" alt="Password widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/password">Password</a></strong><br>Text rendered as a mask in the editor, the field row and the summary; the accepted value stays plain for your code, and can be made revealable.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-pause-dark-animated.svg"><img src="docs/assets/widget-pause-light-animated.svg" width="100%" alt="Pause widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/pause">Pause</a></strong><br>An acknowledgment gate; <kbd>Enter</kbd> or <kbd>Space</kbd> accepts. Unattended runs auto-acknowledge it, so it never blocks automation.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-progress-dark-animated.svg"><img src="docs/assets/widget-progress-light-animated.svg" width="100%" alt="Progress widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/progress">Progress</a></strong><br>A panel row that runs its work when activated, filling a bar or ticking a spinner in the row itself; it collects no value.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-reorder-dark-animated.svg"><img src="docs/assets/widget-reorder-light-animated.svg" width="100%" alt="Reorder widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/reorder">Reorder</a></strong><br>Rank a list by moving items into the order you want; <kbd>Space</kbd> picks an item up, arrows carry it through the list, <kbd>Enter</kbd> accepts.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-search-dark-animated.svg"><img src="docs/assets/widget-search-light-animated.svg" width="100%" alt="Search widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/search">Search</a></strong><br>Single choice with a visible filter line; typing fuzzy-matches and ranks the labels, exact and prefix matches leading.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-select-dark-animated.svg"><img src="docs/assets/widget-select-light-animated.svg" width="100%" alt="Select widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/select">Select</a></strong><br>Single choice from a list; arrows move, <kbd>Enter</kbd> accepts the highlighted option, long lists page around the cursor.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-suggest-dark-animated.svg"><img src="docs/assets/widget-suggest-light-animated.svg" width="100%" alt="Suggest widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/suggest">Suggest</a></strong><br>Free text with autocomplete over a fixed option set: as you type, suggestions are fuzzy-matched and ranked by relevance.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-template-dark-animated.svg"><img src="docs/assets/widget-template-light-animated.svg" width="100%" alt="Template widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/template">Template</a></strong><br>Fill the named slots of a fixed shape; the fixed text is context, <kbd>Tab</kbd> steps between slots and each one validates on its own.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-text-dark-animated.svg"><img src="docs/assets/widget-text-light-animated.svg" width="100%" alt="Text widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/text">Text</a></strong><br>Single-line input with a movable caret; type to insert, arrows move, <kbd>Backspace</kbd> deletes, <kbd>Enter</kbd> accepts.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-textarea-dark-animated.svg"><img src="docs/assets/widget-textarea-light-animated.svg" width="100%" alt="Textarea widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/textarea">Textarea</a></strong><br>Multi-line input; <kbd>Enter</kbd> inserts a newline, arrows move between lines, <kbd>Tab</kbd> accepts, with an external-editor handoff.</td>
</tr>
<tr>
<td width="50%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/assets/widget-toggle-dark-animated.svg"><img src="docs/assets/widget-toggle-light-animated.svg" width="100%" alt="Toggle widget"></picture></td>
<td><strong><a href="https://phptui.dev/widgets/toggle">Toggle</a></strong><br>An inline switch between two labeled values; arrows or <kbd>Space</kbd> flip, the first letter of each label sets it directly.</td>
</tr>
</table>

## Themes

Six themes ship built-in, selected by name on the `Tui` facade. Dark or light is a separate `mode` display option auto-detected from the terminal background, so every adaptive theme serves both:

```php
$tui = (new Tui($form))->theme('midnight');
```

| Name | Palette |
|------|---------|
| `default` | Cyan accents on an auto-detected dark or light base - the out-of-the-box look. |
| `midnight` | Violet accents, green values, pink highlights. |
| `frost` | Arctic frost-blue accents, sage values, sand highlights. |
| `ember` | Burnt-orange accents, olive values, gold highlights. |
| `mono` | Hue-free - bold weight, gray levels and reverse video for maximum compatibility. |
| `dos` | Retro MS-DOS: the bright white/cyan/yellow CGA palette in a double-line window, made for a blue terminal background. |

Each renders across every widget and degrades to plain text without ANSI. Here the dark palette (left) and the light palette (right); the [themes docs](https://phptui.dev/themes) also show every theme inside the rounded border frame:

**`midnight`**

<p>
  <img src="docs/assets/theme-midnight-dark-static.svg" width="48%" alt="midnight theme, dark mode">
  <img src="docs/assets/theme-midnight-light-static.svg" width="48%" alt="midnight theme, light mode">
</p>

**`frost`**

<p>
  <img src="docs/assets/theme-frost-dark-static.svg" width="48%" alt="frost theme, dark mode">
  <img src="docs/assets/theme-frost-light-static.svg" width="48%" alt="frost theme, light mode">
</p>

**`ember`**

<p>
  <img src="docs/assets/theme-ember-dark-static.svg" width="48%" alt="ember theme, dark mode">
  <img src="docs/assets/theme-ember-light-static.svg" width="48%" alt="ember theme, light mode">
</p>

**`mono`**

<p>
  <img src="docs/assets/theme-mono-dark-static.svg" width="48%" alt="mono theme, dark mode">
  <img src="docs/assets/theme-mono-light-static.svg" width="48%" alt="mono theme, light mode">
</p>

**`dos`** - the CGA blue screen, painted regardless of the terminal background

<p>
  <img src="docs/assets/theme-dos-dark-static.svg" width="48%" alt="dos theme, dark terminal">
  <img src="docs/assets/theme-dos-light-static.svg" width="48%" alt="dos theme, light terminal">
</p>

Write your own by subclassing `DefaultTheme` and overriding just its palette - see the [theming guide](https://phptui.dev/themes) and the playground's [`OceanTheme`](playground/themes/OceanTheme.php).

## Contributing

See the [Contributing guide](https://tui-docs.netlify.app/contributing) for the development workflow, quality gates and how the documentation and SVG assets are built.

---
_This repository was created using the [Scaffold](https://getscaffold.dev/) project template_
