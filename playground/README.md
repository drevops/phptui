# TUI playground

Runnable examples of the `drevops/tui` engine, one file per example, grouped by a numbered `NN-topic-` prefix that follows the [documentation](https://phptui.dev) order. Every script is self-contained: it requires the Composer autoloader directly, declares its whole form inline and handles its own output, so any single file can be copied out as a starting point. Most take no CLI options - each demonstrates exactly one thing, and variants are separate scripts - with one exception: [`03-panels-fullscreen.php`](03-panels-fullscreen.php) picks its alignment with `--halign`/`--valign` (plus `--max-width`) rather than spreading nine near-identical files.

Reusable helper classes the scripts load sit in [`themes/`](themes) and [`handlers/`](handlers); the fixtures the examples read from are in [`sample-project/`](sample-project) - one example project the file-picker and discovery demos share - and [`translations/`](translations).

```bash
composer install
php playground/01-quickstart.php
```

Every interactive script also runs unattended: pipe stdin (or run it from CI) and the answers resolve from defaults and `TUI_<ID>` environment variables instead of prompting.

## Examples

| Group | Feature | Scripts |
|---|---|---|
| `01-quickstart` | The documentation's quick-start form: the fluent builder, one panel, five fields, `run()` picking interactive or unattended. | [`01-quickstart.php`](01-quickstart.php) |
| `02-widgets-*` | Every widget as a one-field form, plus the whole gallery on one panel. | one script per widget (`02-widgets-<name>.php`), plus [`02-widgets-all-widgets.php`](02-widgets-all-widgets.php) |
| `03-panels-*` | The full-screen panel browser: drill-in hubs, modal dialogs, the border frame, side-by-side panel grids, the fullscreen stretch with its alignment flags. | [`03-panels-nested.php`](03-panels-nested.php), [`03-panels-modal.php`](03-panels-modal.php), [`03-panels-bordered.php`](03-panels-bordered.php), [`03-panels-borderless.php`](03-panels-borderless.php), [`03-panels-layout.php`](03-panels-layout.php), [`03-panels-fullscreen.php`](03-panels-fullscreen.php) |
| `04-inline-editing` | Editors opening in place on the panel row; `->standalone()` opting a field out to full-screen. | [`04-inline-editing.php`](04-inline-editing.php) |
| `05-form-logic-*` | Answers that react to other answers, settling to a fixpoint. | [`05-form-logic-derived-values.php`](05-form-logic-derived-values.php), [`05-form-logic-conditional-fields.php`](05-form-logic-conditional-fields.php), [`05-form-logic-conditional-indent.php`](05-form-logic-conditional-indent.php), [`05-form-logic-fixup-rules.php`](05-form-logic-fixup-rules.php) |
| `06-field-behaviour-*` | Required fields with a derived or declared message, dynamic defaults, validation and transforms - as field closures and as reusable handler classes. | [`06-field-behaviour-closures.php`](06-field-behaviour-closures.php), [`06-field-behaviour-handlers.php`](06-field-behaviour-handlers.php) (loads [`handlers/OrderCode.php`](handlers/OrderCode.php)) |
| `07-discovery` | Update-mode discovery against the bundled `sample-project/` directory: dotenv, JSON dot-path, path-exists and directory-scan rules, plus a custom env prefix. | [`07-discovery.php`](07-discovery.php) |
| `08-headless-*` | Unattended collection from a JSON payload and environment variables; the JSON schema, answer validation, generated agent help and folding it into a consumer's help. | [`08-headless-collect.php`](08-headless-collect.php), [`08-headless-schema.php`](08-headless-schema.php), [`08-headless-agent-help.php`](08-headless-agent-help.php), [`08-headless-agent-cli.php`](08-headless-agent-cli.php) |
| `09-themes-*` | The six built-in themes, a custom theme class, theme options and the field input styles. | one script per built-in theme (`09-themes-<name>.php`), plus [`09-themes-custom.php`](09-themes-custom.php), [`09-themes-options.php`](09-themes-options.php), [`09-themes-field-boxed.php`](09-themes-field-boxed.php), [`09-themes-field-underline.php`](09-themes-field-underline.php) (load [`themes/OceanTheme.php`](themes/OceanTheme.php), [`themes/AccentTheme.php`](themes/AccentTheme.php)) |
| `10-key-bindings-*` | The `vim` preset and per-binding overrides on top of a preset. | [`10-key-bindings-vim.php`](10-key-bindings-vim.php), [`10-key-bindings-custom.php`](10-key-bindings-custom.php) |
| `11-display-modes-*` | Dark/light detection and forcing, ASCII glyphs, colour off, a static Unicode-vs-ASCII gallery, and rich text (markdown and clickable links) degrading with the display switches. | [`11-display-modes-mode-auto.php`](11-display-modes-mode-auto.php), [`11-display-modes-mode-forced.php`](11-display-modes-mode-forced.php), [`11-display-modes-ascii.php`](11-display-modes-ascii.php), [`11-display-modes-no-color.php`](11-display-modes-no-color.php), [`11-display-modes-glyph-gallery.php`](11-display-modes-glyph-gallery.php), [`11-display-modes-markdown.php`](11-display-modes-markdown.php) |
| `12-translations` | Chrome and questions localized through a consumer catalog, English fallback. | [`12-translations.php`](12-translations.php), `translations/es.php`, `translations/uk.php` |
| `13-testing` | The scripted-keystroke harness: drive the real TUI without a terminal, read back answers and rendered frames. | [`13-testing.php`](13-testing.php) |
| `14-produce-box` | The capstone: panels, widgets, derivation, conditions and behaviour composed into one real form. | [`14-produce-box.php`](14-produce-box.php) |
| `15-progress-*` | The progress primitive - a spinner when the length is unknown, a determinate bar when it is - theme-drawn, animating on a TTY and degrading to a plain line when piped or headless. | [`15-progress-spinner.php`](15-progress-spinner.php), [`15-progress-bar.php`](15-progress-bar.php) |
| `16-loading-data` | Loading a panel's data on demand: a field's `->options()` and a panel's `->preload()` taking a callback, resolved the first time the panel opens with a themed `Loading…` on the field. | [`16-loading-data.php`](16-loading-data.php) |
| `17-query-options` | Options that follow the query: `->optionsFrom()` called again on every query change with a themed `Loading…` while it runs, a per-query cache, and `->minQuery()` holding the call back until the query is long enough. | [`17-query-options.php`](17-query-options.php) |
| `17-output-*` | The output primitives - a titled box and card, an aligned table, the five status lines, a definition list, wrapped prose, rules and a banner - theme-drawn chrome for around a form run, dropping their colour when piped or redirected. | [`17-output-box.php`](17-output-box.php), [`17-output-status.php`](17-output-status.php), [`17-output-definitions.php`](17-output-definitions.php), [`17-output-table.php`](17-output-table.php), [`17-output-text.php`](17-output-text.php) |

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
NO_COLOR=1 php playground/02-widgets-select.php       # colour off
LC_ALL=C php playground/02-widgets-select.php         # ASCII glyphs
COLORFGBG='0;15' php playground/02-widgets-select.php # hint a light background
```

## How the TUI picks a theme

Set it on the `Tui` facade with `->theme(...)`, lowest friction first:

1. **Name the class** - `->theme('\Your\ThemeClass')`. The class is instantiated directly; no registration needed. This is what [`09-themes-custom.php`](09-themes-custom.php) does.
2. **Register a short name** - `ThemeManager::register('accent', AccentTheme::class)`, then `->theme('accent')`. Useful to give a class a stable alias. This is what [`09-themes-options.php`](09-themes-options.php) does.
3. **Built-in name** - `->theme('midnight')` (or `frost`, `ember`, `mono`, `default` or `dos`). Dark or light is a separate `mode` display option, not a theme, so a built-in adapts to both. One script per theme, `09-themes-<name>.php`.
4. **Auto-detect** - leave it unset (or `->theme('auto')`) and the `default` theme is used, with the interactive TUI picking the dark or light `mode` from the terminal background (an OSC 11 query, then `COLORFGBG`, then a dark default). Setting `mode` explicitly opts out. This is what [`11-display-modes-mode-auto.php`](11-display-modes-mode-auto.php) demonstrates.

## How the TUI sets key bindings

Set them on the `Tui` facade with `->keys(...)`, mirroring `->theme(...)`:

1. **A preset name** - `->keys('vim')` for the built-in vim navigation, or a name registered with `KeyMapManager::register('name', MyKeyMap::class)`.
2. **A preset class** - `->keys('\Your\KeyMapClass')`, instantiated directly with no registration.
3. **Overrides** - `->keys('default', [new Binding(Scope::field(FieldType::Select), Action::Accept, KeyName::Tab)])` retunes individual bindings on top of a preset. A binding names a scope (the base, navigation, or a widget type), an action and its keys.
4. **Defaults** - leave it unset for the built-in bindings. This is what most examples do.

Conflicting or un-typeable bindings throw when the facade is configured, so a bad key map is caught at declaration time, not mid-session. Both override styles live in [`10-key-bindings-vim.php`](10-key-bindings-vim.php) and [`10-key-bindings-custom.php`](10-key-bindings-custom.php).
