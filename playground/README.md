# TUI playground

Runnable examples of the `drevops/tui` engine, one file per example, grouped by a numbered `NN-topic-` prefix that follows the [documentation](https://phptui.dev) order. Every script is self-contained: it requires the Composer autoloader directly, declares its whole form inline and handles its own output, so any single file can be copied out as a starting point. Most take no CLI options - each demonstrates exactly one thing, and variants are separate scripts - with one exception: [`03-panels-fullscreen.php`](03-panels-fullscreen.php) picks its alignment with `--halign`/`--valign` (plus `--max-width`) rather than spreading nine near-identical files.

Reusable helper classes the scripts load sit in [`themes/`](themes), [`layouts/`](layouts) and [`handlers/`](handlers); the fixtures the examples read from are in [`sample-project/`](sample-project) - one example project the file-picker and discovery demos share - and [`translations/`](translations).

```bash
composer install
php playground/01-quickstart.php
```

Every interactive script also runs unattended: pipe stdin (or run it from CI) and the answers resolve from defaults and `TUI_<ID>` environment variables instead of prompting.

## Examples

| Group | Feature | Scripts |
|---|---|---|
| `01-quickstart` | The documentation's quick-start form: the fluent builder, one panel, five fields, `run()` picking interactive or unattended. | [`01-quickstart.php`](01-quickstart.php) |
| `02-fields-*` | Every field as a one-field form, plus the whole gallery on one panel. | one script per field (`02-fields-<name>.php`), plus [`02-fields-all-fields.php`](02-fields-all-fields.php) |
| `02-markup-*` | Markup: formatted content in the form flow that collects nothing - as prose and a bordered card, and laid out as a grid. | [`02-markup.php`](02-markup.php), [`02-markup-table.php`](02-markup-table.php) |
| `02-progress-row` | The progress block: a panel row that runs work when activated, filling a bar in place. The primitive around a form is `15-progress-*`. | [`02-progress-row.php`](02-progress-row.php) |
| `03-panels-*` | The full-screen panel browser: drill-in hubs, modal dialogs, the border frame, the grid built from the counts that are its shape, with a row above its windows and another below, the fullscreen stretch with its alignment flags. | [`03-panels-nested.php`](03-panels-nested.php), [`03-panels-modal.php`](03-panels-modal.php), [`03-panels-bordered.php`](03-panels-bordered.php), [`03-panels-borderless.php`](03-panels-borderless.php), [`03-panels-layout.php`](03-panels-layout.php), [`03-panels-fullscreen.php`](03-panels-fullscreen.php) |
| `04-inline-editing` | Editors opening in place on the panel row; `->standalone()` opting a field out to full-screen. | [`04-inline-editing.php`](04-inline-editing.php) |
| `05-form-logic-*` | Answers that react to other answers, settling to a fixpoint - on a field, and on a whole section that comes and goes with everything it holds. | [`05-form-logic-derived-values.php`](05-form-logic-derived-values.php), [`05-form-logic-conditional-fields.php`](05-form-logic-conditional-fields.php), [`05-form-logic-conditional-section.php`](05-form-logic-conditional-section.php), [`05-form-logic-conditional-indent.php`](05-form-logic-conditional-indent.php), [`05-form-logic-fixup-rules.php`](05-form-logic-fixup-rules.php) |
| `06-field-behaviour-*` | Required fields with a derived or declared message, dynamic defaults, validation and transforms - as field closures and as reusable handler classes. | [`06-field-behaviour-closures.php`](06-field-behaviour-closures.php), [`06-field-behaviour-handlers.php`](06-field-behaviour-handlers.php) (loads [`handlers/OrderCode.php`](handlers/OrderCode.php)) |
| `07-discovery` | Update-mode discovery against the bundled `sample-project/` directory: dotenv, JSON dot-path, path-exists and directory-scan rules, plus a custom env prefix. | [`07-discovery.php`](07-discovery.php) |
| `08-headless-*` | Unattended collection from a JSON payload and environment variables; the JSON schema, answer validation, generated agent help and folding it into a consumer's help. | [`08-headless-collect.php`](08-headless-collect.php), [`08-headless-schema.php`](08-headless-schema.php), [`08-headless-agent-help.php`](08-headless-agent-help.php), [`08-headless-agent-cli.php`](08-headless-agent-cli.php) |
| `09-themes-*` | The six built-in themes, a custom theme class, a theme built on the floor that declares no capability at all, per-element overrides without a class, theme options and the field input styles. | one script per built-in theme bar `default` (`09-themes-<name>.php`), plus [`09-themes-custom.php`](09-themes-custom.php), [`09-themes-floor.php`](09-themes-floor.php), [`09-themes-elements.php`](09-themes-elements.php), [`09-themes-options.php`](09-themes-options.php), [`09-themes-field-boxed.php`](09-themes-field-boxed.php), [`09-themes-field-underline.php`](09-themes-field-underline.php) (load [`themes/OceanTheme.php`](themes/OceanTheme.php), [`themes/PlainTheme.php`](themes/PlainTheme.php), [`themes/AccentTheme.php`](themes/AccentTheme.php)) |
| `10-key-bindings-*` | The `vim` preset and per-binding overrides on top of a preset. | [`10-key-bindings-vim.php`](10-key-bindings-vim.php), [`10-key-bindings-custom.php`](10-key-bindings-custom.php) |
| `11-display-modes-*` | Dark/light detection and forcing, ASCII glyphs, colour off, a static Unicode-vs-ASCII gallery, and rich text (markdown and clickable links) degrading with the display switches. | [`11-display-modes-mode-auto.php`](11-display-modes-mode-auto.php), [`11-display-modes-mode-forced.php`](11-display-modes-mode-forced.php), [`11-display-modes-ascii.php`](11-display-modes-ascii.php), [`11-display-modes-no-color.php`](11-display-modes-no-color.php), [`11-display-modes-glyph-gallery.php`](11-display-modes-glyph-gallery.php), [`11-display-modes-markdown.php`](11-display-modes-markdown.php) |
| `12-translations` | Chrome and questions localized through a consumer catalog, English fallback - interactively, and end to end with no terminal at all. | [`12-translations.php`](12-translations.php), `translations/es.php`, `translations/uk.php` |
| `12-specification-screen` | The [specification](https://phptui.dev/specification) made runnable: the levels a screen is built from, keys travelling inward to the innermost binder, and the same tree collected with no screen at all. | [`12-specification-screen.php`](12-specification-screen.php) |
| `13-testing` | The scripted-keystroke harness: drive the real TUI without a terminal, read back answers and rendered frames. | [`13-testing.php`](13-testing.php) |
| `14-produce-box` | The capstone: panels, fields, derivation, conditions and behaviour composed into one real form. | [`14-produce-box.php`](14-produce-box.php) |
| `15-progress-*` | The progress primitive, for slow work around a form rather than in one - a spinner when the length is unknown, a determinate bar when it is - theme-drawn, animating on a TTY and degrading to a plain line when piped or headless. | [`15-progress-spinner.php`](15-progress-spinner.php), [`15-progress-bar.php`](15-progress-bar.php) |
| `16-loading-data` | Loading a panel's data on demand: a field's `->options()` and a panel's `->preload()` taking a callback, resolved the first time the panel opens with a themed `Loading…` on the field. | [`16-loading-data.php`](16-loading-data.php) |
| `17-query-options` | Options that follow the query: `->optionsFrom()` called again on every query change with a themed `Loading…` while it runs, a per-query cache, and `->minQuery()` holding the call back until the query is long enough. | [`17-query-options.php`](17-query-options.php) |
| `18-output-*` | The output primitives - a titled box and card, an aligned table, the five status lines, a definition list, wrapped prose, rules and a banner - theme-drawn chrome for around a form run, dropping their colour when piped or redirected. | [`18-output-box.php`](18-output-box.php), [`18-output-status.php`](18-output-status.php), [`18-output-definitions.php`](18-output-definitions.php), [`18-output-table.php`](18-output-table.php), [`18-output-text.php`](18-output-text.php) |
| `19-dynamic-options` | Options that follow the answers: an `->options()` callback taking the run context, called again whenever they change, narrowing one field's choices by another's answer and dropping a choice the narrowed list no longer holds - reporting rather than dropping one that was supplied headlessly. | [`19-dynamic-options.php`](19-dynamic-options.php) |
| `20-layouts-*` | Arrangement: a consumer's own `AbstractLayout` subclass registered by name and picked for a panel and for the screen, blocks of your own placed in the regions around the form with a region running them across rather than down and packed from either end of that run, and a grid moving every line of itself together where no region could. | [`20-layouts-custom.php`](20-layouts-custom.php), [`20-layouts-region-flow.php`](20-layouts-region-flow.php), [`20-layouts-scrolling.php`](20-layouts-scrolling.php) (load [`layouts/StallLayout.php`](layouts/StallLayout.php), [`layouts/MarketLayout.php`](layouts/MarketLayout.php)) |

## Running the examples

Each script prints how to invoke it in its `@file` docblock. The common patterns:

```bash
# Interactive TUI (any interactive example).
php playground/14-produce-box.php

# Unattended: defaults and environment answer instead of a keyboard.
TUI_NAME='Summer Box' php playground/14-produce-box.php < /dev/null

# Discovery uses its own env prefix, declared by the form.
BOX_SEASON=winter php playground/07-discovery.php
```

Display modes follow the terminal and the standard environment conventions, so no script needs flags for them:

```bash
NO_COLOR=1 php playground/02-fields-select.php       # colour off
LC_ALL=C php playground/02-fields-select.php         # ASCII glyphs
COLORFGBG='0;15' php playground/02-fields-select.php # hint a light background
```

## How the TUI picks a theme

Set it on the `Tui` facade with `->theme(...)`, lowest friction first:

1. **Name the class** - `->theme('\Your\ThemeClass')`. The class is instantiated directly; no registration needed. This is what [`09-themes-custom.php`](09-themes-custom.php) does. What the manager asks of a class is the floor and nothing above it, so a theme declaring no capability at all is built and run the same way - [`09-themes-floor.php`](09-themes-floor.php) shows one, and what the driver does without when a theme promises nothing.
2. **Register a short name** - `ThemeManager::register('accent', AccentTheme::class)`, then `->theme('accent')`. Useful to give a class a stable alias. This is what [`09-themes-options.php`](09-themes-options.php) does.
3. **Built-in name** - `->theme('midnight')` (or `frost`, `ember`, `mono`, `default` or `dos`). Dark or light is a separate `mode` display option, not a theme, so a built-in adapts to both. One script per theme apart from `default`, `09-themes-<name>.php`.
4. **Auto-detect** - leave it unset (or `->theme('auto')`) and the `default` theme is used, with the interactive TUI picking the dark or light `mode` from the terminal background (an OSC 11 query, then `COLORFGBG`, then a dark default). Setting `mode` explicitly opts out. This is what [`11-display-modes-mode-auto.php`](11-display-modes-mode-auto.php) demonstrates.

Whatever the theme, `->theme(fn(ThemeBuilder $t) => ...)` restates individual elements on top of it - a separator, a selector, an entry marker, a caret - grouped by the block that declares them, with each glyph given as the mark and its ASCII stand-in. Anything left unnamed keeps the theme's own answer, which is why this is a patch and a subclass is a replacement. This is what [`09-themes-elements.php`](09-themes-elements.php) does.

## How the TUI picks a layout

A layout arranges: it names its regions, sizes them, says which of them scroll, and can be a scrolling surface itself. Set it on the `Tui` facade with `->layout(...)` for the screen, or on a panel with the builder's `->layout(...)` for that panel's own blocks - the same names reach both, which is what makes a layout reusable.

1. **Shipped name** - `->layout('two-column')`, or `default` (a fixed header, a scrolling content region, a fixed footer). `panel` is the single-region arrangement a panel takes when it names none. No name reaches a grid: a name carries no shape, so `->layout(1, 2)` builds one from the shape written at the call site, which is what [`03-panels-layout.php`](03-panels-layout.php) contrasts against a panel that declares no arrangement at all.
2. **Register a short name** - `LayoutManager::register('stall', StallLayout::class)`, then `->layout('stall')`.
3. **Name the class** - `->layout('\Your\LayoutClass')`, instantiated directly with no registration.

Both routes are in [`20-layouts-custom.php`](20-layouts-custom.php). A name nothing answers to throws where `->layout()` is written, not mid-session. Which blocks land where is decided by whatever assembles the screen, so a layout that keeps no `header` simply shows no trail rather than being refused.

The regions around the form are the session's, so a block of your own goes in on the facade too: `->place('header', $block)` puts one in a named region, `->place(..., tail: TRUE)` packs it from the end of that region's run, and `->flow('header', Axis::Columns)` turns the region across. The standard furniture is placed first, so a placed block lands after the trail or the key hints its region already holds. Both are in [`20-layouts-region-flow.php`](20-layouts-region-flow.php).

## How the TUI sets key bindings

Set them on the `Tui` facade with `->keys(...)`, mirroring `->theme(...)`:

1. **A preset name** - `->keys('vim')` for the built-in vim navigation, or a name registered with `KeyMapManager::register('name', MyKeyMap::class)`.
2. **A preset class** - `->keys('\Your\KeyMapClass')`, instantiated directly with no registration.
3. **Overrides** - `->keys('default', [new Binding(Scope::field(FieldType::Select), Action::Accept, KeyName::Tab)])` retunes individual bindings on top of a preset. A binding names a scope (the base, navigation, or a field type), an action and its keys.
4. **Defaults** - leave it unset for the built-in bindings. This is what most examples do.

Conflicting or un-typeable bindings throw when the facade is configured, so a bad key map is caught at declaration time, not mid-session. Both override styles live in [`10-key-bindings-vim.php`](10-key-bindings-vim.php) and [`10-key-bindings-custom.php`](10-key-bindings-custom.php).
