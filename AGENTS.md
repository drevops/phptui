# AGENTS.md

This file provides guidance to AI agents when working with
code in this repository.


## Project Overview

This project is a PHP library for building panel-based terminal forms:
keyboard-driven questionnaires that collect answers interactively through a
TUI or headlessly from a JSON payload and environment variables.


## PHP Application Architecture


### Library API

This package is a library consumed programmatically - it has no CLI entry point
of its own. The public surface is:

- **`DrevOps\Tui\Tui`** - the facade: collect a form's answers, headlessly or
  through the interactive panel TUI.
- **`DrevOps\Tui\Builder\Form`** - the fluent builder for declaring a form's
  panels and fields.

A consumer declares a form with `Form::create(...)->panel(...)` and drives it
through the `Tui` facade.

The facade also exposes **primitives** - standalone, theme-drawn elements that
collect no answer and never run inside the panel:

- `Tui::progress()` (`DrevOps\Tui\Primitive\Progress`) wraps a slow callback
  with a spinner (unknown length) or a determinate bar (known total) for work
  that runs around the form.
- `Tui::output()` (`DrevOps\Tui\Primitive\Output`) draws the static chrome
  around a form: boxes and cards, aligned tables, the five status lines of
  `DrevOps\Tui\Primitive\Status`, definition lists, wrapped text, rules and a
  banner.

Every piece a primitive draws routes through a `render*()` method on the theme
that takes only plain strings and arrays - never a `Field`, `Panel` or
`Answers`. `renderCard()` is the single renderer behind both the standalone
card and the one a markup block draws in a panel, grid included, so a theme
override restyles the two together. Keep it that way: a renderer that reaches
for form state cannot be used standalone.


### Namespace Structure

- Source code: `DrevOps\Tui\`
- Tests: `DrevOps\Tui\Tests\`
- Autoloading: PSR-4 via Composer

## Commands

### Code Quality

```bash
# Run all linters (PHPCS, PHPStan, Rector)
composer lint

# Auto-fix code style issues
composer lint-fix

# Individual tools
./vendor/bin/phpcs # Check coding standards
./vendor/bin/phpcbf # Fix coding standards
./vendor/bin/phpstan # Static analysis (level 9)
./vendor/bin/rector --dry-run # Check Rector suggestions
```

### Testing

```bash
# Run all PHPUnit tests (fast, no coverage)
composer test

# Run with coverage reports
composer test-coverage
# Coverage reports: .logs/.coverage-html/index.html, .logs/cobertura.xml

# Run specific test file
./vendor/bin/phpunit tests/phpunit/Unit/TuiTest.php

# Run specific test method
./vendor/bin/phpunit --filter testMethodName
```


### Dependencies


```bash
# Clean and reinstall dependencies
composer reset # removes vendor/ and composer.lock
composer install
```

## Code Quality Standards

### Three-Layer Quality Stack

1. **PHP_CodeSniffer** - Drupal coding standards + strict types requirement
  - Config: `phpcs.xml`
  - Rules: Drupal standard, Generic.PHP.RequireStrictTypes
  - Relaxed rules in test files (long arrays, missing function docs)

2. **PHPStan** - Level 9 static analysis
  - Config: `phpstan.neon`
  - Ignores: Untyped iterables in tests/data providers

3. **Rector** - PHP 8.3 modernization + code quality
  - Config: `rector.php`
  - Sets: PHP_83, CODE_QUALITY, CODING_STYLE, DEAD_CODE,
    TYPE_DECLARATION

### Coding Conventions

- All PHP files must declare `strict_types=1`
- Use single quotes for strings (double quotes if containing single quote)
- All files must end with a newline character
- Local variables/method arguments: `snake_case`
- Method names/class properties: `camelCase`
- **Never model a closed set of values as string literals.** Any value that is
  one-of-a-fixed-set (a kind, a state, a mode, a source) is a backed or pure
  enum, and every property, parameter and return that carries it is typed with
  the enum - existing examples: `FieldType`, `Provenance`, `Source`,
  `KeyName`. String literals for such values are forbidden in source and in
  tests alike; use the enum case (and its `->value` only at a rendering or
  serialization boundary).

### Demo content

- **Examples use a fruit-and-vegetable theme and contain no software, product or
  technology references.** All demo content across documentation code blocks,
  playground scripts and generated SVG screenshots follows the canonical set in
  `.claude/demo_content_reference.md`. Draw sample data from that reference so the
  docs, the scripts and the screenshots stay consistent, and never introduce a
  programming language, framework, service, tool or brand into an example.

### Playground scripts

- **Each playground script is self-contained.** A demo requires the Composer
  autoloader directly (`require __DIR__ . '/../vendor/autoload.php';`) and
  handles its own concerns inline - argument parsing, its own `try`/`catch`
  around collection (including the `InterruptException` a Ctrl-C abort raises -
  which also covers the Cancel button's `CancelException` subclass - caught to
  `exit(130)`), and its own output. There is no shared bootstrap or helper
  include: a reader can copy a single script and run it standalone.

## Testing Patterns

### PHPUnit Structure

- `tests/phpunit/Unit/` - unit tests; filesystem seams use vfsStream
- `tests/phpunit/Fixtures/` - shared fixtures and test doubles
- `tests/phpunit/Traits/` - shared test utilities

### Writing Tests

Tests should use PHPUnit 12 features:

- Coverage attributes: `#[CoversClass(ClassName::class)]`
- Test attributes: `#[Test]` (optional, using `test` prefix is also fine)
- Data providers: `#[DataProvider('providerMethodName')]`


## CI/CD

GitHub Actions workflows test across:

- PHP versions: 8.3, 8.4, 8.5 (normal and lowest dependency sets) on Ubuntu,
  plus Windows portability jobs on PHP 8.3 and 8.4 (normal dependencies)
- One matrix job runs lint, tests and the coverage upload (Codecov)

Key workflows:

- `.github/workflows/test-php.yml` - PHP testing


## Documentation

Architecture diagrams and a narrative walkthrough live in `docs/architecture/`.
After a structural change, update them with the `render-tui-diagrams` skill.

### Terminal SVG assets

Every field, every other block a panel draws (markup, the progress row) and
every primitive carries a full set of terminal SVGs under `docs/assets/` -
light and dark, in all four display modes (Unicode/ASCII, colour on/off) -
embedded in the README and the docs pages.
Anything that moves also carries an animated variant beside its static one; a
subject with no motion to record (the output primitives, which write finished
lines and return) is static-only by design. They render deterministically (no
pty) from the scripts in `docs/util/`:
`render-field-svgs.php` for fields and the blocks beside them,
`render-progress-svgs.php` for the progress primitive,
`render-output-svgs.php` for the output primitives (static only - they write
finished lines, so there is no motion to record), and
`render-theme-svgs.php` for theme previews, all run by `update-assets.php`. The
naming convention lives in `docs/assets/README.md`.

Whenever you add a field or a primitive, do all of the following before
opening a PR:

- Add a spec to the matching renderer and generate its variants with it: a
  field's spec (form, keystrokes, rows) goes in `fieldSpecs()` in
  `render-field-svgs.php` for 16 variants; a primitive's goes in the renderer
  that suits how it draws - `progressSpecs()` in `render-progress-svgs.php`
  when it animates, `outputSpecs()` in `render-output-svgs.php` when it does
  not. Run `php docs/util/render-<kind>-svgs.php <name>` to generate them. A
  spec whose subject is not a field names it with `subject`, so its files
  carry the name of what they show rather than a `field-` prefix that would
  misname it - which is what the markup and progress-row specs do.
- For a field only, regenerate the all-fields montage so the gallery includes
  it: `php docs/util/update-assets.php --record fields`. A primitive is not in
  the montage, so it skips this step.
- Add its documentation page and a `docs/sidebars.js` entry: a field goes in
  `docs/content/fields/<name>.mdx` (mirror `pause.mdx`), a primitive at the
  top level in `docs/content/<name>.mdx` (mirror `progress.mdx`).
- Run `php docs/util/audit-svgs.php` - it must stay green, and every dark asset
  needs its light twin.
- Visually confirm at least the `-dark-static` render (open the SVG in a
  browser and screenshot it) - a render that completes is not proof it fits.

## Updating from the template

This project was generated from a template and can pull the template's latest
infrastructure (CI workflows, linting and test configuration, Docker, and docs)
at any time. When you are asked to "update scaffold", fetch the updater skill
from the template repository into the local `.claude/skills/` directory, then
invoke it:

1. Create the target directory if it does not exist:

   ```bash
   mkdir -p .claude/skills/update-consumer-scaffold
   ```

2. Download the skill:

   ```bash
   curl -sSL https://raw.githubusercontent.com/AlexSkrypnyk/scaffold/main/.scaffold/skills/update-consumer-scaffold/SKILL.md -o .claude/skills/update-consumer-scaffold/SKILL.md
   ```

3. Invoke the `update-consumer-scaffold` skill and follow its steps.

The skill directory is fetched on demand and is git-ignored - it is not
committed to the project.
