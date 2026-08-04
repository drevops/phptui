import React, {useCallback, useEffect, useRef, useState} from 'react';
import clsx from 'clsx';
import Layout from '@theme/Layout';
import Head from '@docusaurus/Head';
import {useBaseUrlUtils} from '@docusaurus/useBaseUrl';
import useDocusaurusContext from '@docusaurus/useDocusaurusContext';
import {Highlight, Prism} from 'prism-react-renderer';
import prismIncludeLanguages from '@theme/prism-include-languages';
import styles from './index.module.css';

// Load the site's configured additionalLanguages (php, bash) into the Prism
// instance, exactly as the docs code blocks do.
prismIncludeLanguages(Prism);

/* ────────────────────────────────────────────────────────────────────────
 *  CONFIG - edit these freely.
 *
 *  TITLE_PHRASES are the rotating hero headlines. They are typed out one
 *  after another (type -> hold -> delete -> next -> loop). Add, remove or
 *  reorder them; the FIRST one is what search engines and no-JS visitors
 *  see, and it stays the accessible label for the heading.
 * ──────────────────────────────────────────────────────────────────────── */
const TITLE_PHRASES = [
  'Terminal user interfaces for PHP',
  'Dark and light terminal UIs, auto-detected',
  'Terminal UI fields for every use case',
  'Build multi-lingual terminal UIs with ease',
  'Testable terminal UIs for your PHP projects',
];

const INSTALL_CMD = 'composer require drevops/tui';

const SUBHEAD =
  'A dependency-light PHP engine for interactive, keyboard-driven terminal ' +
  'forms. Declare the questions with a fluent builder; the engine renders a ' +
  'scrollable, themeable TUI - or collects the answers headlessly from JSON ' +
  'and environment variables.';

const REPO_BLOB = 'https://github.com/drevops/tui/blob/main/';

/* ────────────────────────────────────────────────────────────────────────
 *  PHP snippets. Plain strings highlighted by highlightPhp() below; every
 *  snippet is an excerpt of the playground script its feature links to, so
 *  the code, the recording and the full script always tell the same story.
 * ──────────────────────────────────────────────────────────────────────── */
const QUICKSTART_CODE = `use DrevOps\\Tui\\Builder\\Form;
use DrevOps\\Tui\\Builder\\PanelBuilder;
use DrevOps\\Tui\\Tui;

$form = Form::create('Quick start')
  ->panel('order', 'New order', function (PanelBuilder $p): void {
    $p->text('name', 'Order name')->required();
    $p->select('fruit', 'Fruit')->default('banana')->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
    ]);
    $p->select('veg', 'Vegetables')->multiple()->default(['carrot'])->options([
      'carrot' => 'Carrot',
      'tomato' => 'Tomato',
      'spinach' => 'Spinach',
    ]);
    $p->number('quantity', 'Quantity')->min(1)->max(99)->default(6);
    $p->confirm('organic', 'Organic only?')->default(FALSE);
  });

// Interactive on a terminal, non-interactive otherwise.
$answers = (new Tui($form))->run();`;

/* ────────────────────────────────────────────────────────────────────────
 *  Feature card icons. Path data from Lucide (lucide.dev, ISC License),
 *  inlined so the page loads no icon library or external asset.
 * ──────────────────────────────────────────────────────────────────────── */
const ICONS = {
  'maximize': <><path d="M8 3H5a2 2 0 0 0-2 2v3" /><path d="M21 8V5a2 2 0 0 0-2-2h-3" /><path d="M3 16v3a2 2 0 0 0 2 2h3" /><path d="M16 21h3a2 2 0 0 0 2-2v-3" /></>,
  'text-cursor-input': <><path d="M12 20h-1a2 2 0 0 1-2-2 2 2 0 0 1-2 2H6" /><path d="M13 8h7a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-7" /><path d="M5 16H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h1" /><path d="M6 4h1a2 2 0 0 1 2 2 2 2 0 0 1 2-2h1" /><path d="M9 6v12" /></>,
  'layout-grid': <><rect width="7" height="7" x="3" y="3" rx="1" /><rect width="7" height="7" x="14" y="3" rx="1" /><rect width="7" height="7" x="14" y="14" rx="1" /><rect width="7" height="7" x="3" y="14" rx="1" /></>,
  'braces': <><path d="M8 3H7a2 2 0 0 0-2 2v5a2 2 0 0 1-2 2 2 2 0 0 1 2 2v5c0 1.1.9 2 2 2h1" /><path d="M16 21h1a2 2 0 0 0 2-2v-5c0-1.1.9-2 2-2a2 2 0 0 1-2-2V5a2 2 0 0 0-2-2h-1" /></>,
  'keyboard': <><path d="M10 8h.01" /><path d="M12 12h.01" /><path d="M14 8h.01" /><path d="M16 12h.01" /><path d="M18 8h.01" /><path d="M6 8h.01" /><path d="M7 16h10" /><path d="M8 12h.01" /><rect width="20" height="16" x="2" y="4" rx="2" /></>,
  'workflow': <><rect width="8" height="8" x="3" y="3" rx="2" /><path d="M7 11v4a2 2 0 0 0 2 2h4" /><rect width="8" height="8" x="13" y="13" rx="2" /></>,
  'split': <><path d="M16 3h5v5" /><path d="M8 3H3v5" /><path d="M12 22v-8.3a4 4 0 0 0-1.172-2.872L3 3" /><path d="m15 9 6-6" /></>,
  'palette': <><path d="M12 22a1 1 0 0 1 0-20 10 9 0 0 1 10 9 5 5 0 0 1-5 5h-2.25a1.75 1.75 0 0 0-1.4 2.8l.3.4a1.75 1.75 0 0 1-1.4 2.8z" /><circle cx="13.5" cy="6.5" r=".5" fill="currentColor" /><circle cx="17.5" cy="10.5" r=".5" fill="currentColor" /><circle cx="6.5" cy="12.5" r=".5" fill="currentColor" /><circle cx="8.5" cy="7.5" r=".5" fill="currentColor" /></>,
  'command': <path d="M15 6v12a3 3 0 1 0 3-3H6a3 3 0 1 0 3 3V6a3 3 0 1 0-3 3h12a3 3 0 1 0-3-3" />,
  'languages': <><path d="m5 8 6 6" /><path d="m4 14 6-6 2-3" /><path d="M2 5h12" /><path d="M7 2h1" /><path d="m22 22-5-10-5 10" /><path d="M14 18h6" /></>,
  'sun-moon': <><path d="M12 2v2" /><path d="M14.837 16.385a6 6 0 1 1-7.223-7.222c.624-.147.97.66.715 1.248a4 4 0 0 0 5.26 5.259c.589-.255 1.396.09 1.248.715" /><path d="M16 12a4 4 0 0 0-4-4" /><path d="m19 5-1.256 1.256" /><path d="M20 12h2" /></>,
  'flask-conical': <><path d="M14 2v6a2 2 0 0 0 .245.96l5.51 10.08A2 2 0 0 1 18 22H6a2 2 0 0 1-1.755-2.96l5.51-10.08A2 2 0 0 0 10 8V2" /><path d="M6.453 15h11.094" /><path d="M8.5 2h7" /></>,
};

function FeatIcon({name}) {
  return <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false">{ICONS[name]}</svg>;
}

const FEATURES = [
  {
    idx: '01',
    icon: 'maximize',
    name: 'Full-screen TUI',
    desc: <>A scrollable, keyboard-driven form; fields group into sections that drill in to any depth, with a contextual key-hint footer and a <code className={styles.tok}>?</code> help overlay.</>,
    demo: {
      svg: 'fullscreen-panels-dark-animated.svg',
      alt: 'Animated recording of the fullscreen panel grid walked with the keyboard',
      caption: 'The layout(1, 2) grid, stretched over the whole terminal',
      script: 'playground/03-panels-fullscreen.php',
      doc: '/panels',
      code: `$form = Form::create('Market stall')
  ->layout(1, 2)
  ->buttons(TRUE, 'Place order', 'Cancel')
  ->panel('summary', 'Summary', function (PanelBuilder $p): void {
    $p->text('name', 'Order name')->default('Weekly Box')->required();
  })
  ->panel('produce', 'Produce', function (PanelBuilder $p): void { /* ... */ })
  ->panel('delivery', 'Delivery', function (PanelBuilder $p): void { /* ... */ });

// Stretch the frame over the whole terminal; anchor the content.
$answers = (new Tui($form))
  ->theme('default', ['border' => 'rounded', 'halign' => 'center', 'valign' => 'middle'])
  ->fullscreen()
  ->run();`,
    },
  },
  {
    idx: '02',
    icon: 'text-cursor-input',
    name: 'Inline editing',
    desc: <>A field's editor opens in place on the panel row, with its own view and keys; opt a field out to a full screen with <code className={styles.tok}>{'->standalone()'}</code>.</>,
    demo: {
      svg: 'inline-editing-dark-animated.svg',
      alt: 'Animated recording of fields edited in place on their panel rows',
      caption: 'Editors open on the row; the calendar opts out to a full screen',
      script: 'playground/04-inline-editing.php',
      doc: '/panels',
      code: `$form = Form::create('Produce order')
  ->panel('options', 'Order options', function (PanelBuilder $p): void {
    // Inline by default: the editor opens in place on the row.
    $p->confirm('organic', 'Organic only?')->default(FALSE);
    $p->number('quantity', 'Quantity')->min(1)->max(99)->default(6);
    $p->select('ripeness', 'Ripeness')->default('ripe')->options([
      'ripe' => 'Ripe',
      'unripe' => 'Unripe',
      'mixed' => 'Mixed',
    ]);

    // The month grid wants the whole screen: opt out of inline.
    $p->calendar('harvest', 'Harvest date')->standalone()->default('2026-07-15');
  });`,
    },
  },
  {
    idx: '03',
    icon: 'layout-grid',
    name: 'Field library',
    desc: <>Text, numbers, dates, single and multiple choice, fuzzy search, file browsing, reordering and gates.</>,
    demo: {
      svg: 'fields-dark-animated.svg',
      alt: 'Animated recording of every field type walked through on one panel',
      caption: 'Every field on one panel, walked one by one',
      script: 'playground/02-fields-all-fields.php',
      doc: '/fields',
      code: `$form = Form::create('Fields')
  ->panel('fields', 'Fields', function (PanelBuilder $p): void {
    $p->text('text', 'Text')->default('Pear');
    $p->number('number', 'Number')->default(1200);
    $p->calendar('calendar', 'Calendar')->default('2026-07-15')->standalone();
    $p->textarea('textarea', 'Textarea');
    $p->password('password', 'Password')->default('melon7');
    $p->select('select', 'Select')->default('apple')->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
    ]);
    $p->reorder('reorder', 'Reorder')->options([/* ... */]);
    $p->suggest('suggest', 'Suggest')->options([/* ... */]);
    $p->search('search', 'Search')->default('carrot')->options([/* ... */]);
    $p->confirm('confirm', 'Confirm')->default(TRUE);
    $p->toggle('toggle', 'Toggle')->default('ripe')->options([/* ... */]);
    $p->pause('pause', 'Pause');
  });`,
    },
  },
  {
    idx: '04',
    icon: 'braces',
    name: 'Builder-driven',
    desc: <>The form is declared in PHP with a fluent builder; the common cases need no extra code.</>,
    demo: {
      svg: 'quickstart-dark-static.svg',
      alt: 'The quick-start order panel rendered in the terminal',
      caption: 'The declared panel, rendered',
      script: 'playground/01-quickstart.php',
      doc: '/configuration',
      code: QUICKSTART_CODE,
    },
  },
  {
    idx: '05',
    icon: 'keyboard',
    name: 'Interactive or unattended',
    desc: <>Answer by keyboard, or supply the answers up front as a JSON payload and environment variables so it runs without prompting. Emits a JSON schema for agents.</>,
    demo: {
      svg: 'headless-collect-dark-static.svg',
      alt: 'Terminal output of a headless collection run: the JSON result and the provenance-badged summary',
      caption: 'No terminal: answers resolve from JSON, the environment and defaults',
      script: 'playground/08-headless-collect.php',
      doc: '/headless-collection',
      code: `// The same form, no terminal: collect() resolves every field from
// the prompts JSON, TUI_<ID> environment variables, discovered and
// derived values, then the declared defaults - in that order.
putenv('TUI_ORGANIC=1');

$answers = (new Tui($form))->collect('{"name": "Weekly Box", "fruit": "cherry"}');

// {"name":"Weekly Box","fruit":"cherry","quantity":6,"organic":true}
echo $answers->toJson();`,
    },
  },
  {
    idx: '06',
    icon: 'workflow',
    name: 'Derived values',
    desc: <>Compute one field from others with <code className={styles.tok}>str2name</code> transforms; chains settle to a fixpoint.</>,
    demo: {
      svg: 'derived-values-dark-animated.svg',
      alt: 'Animated recording of derived fields re-settling as the name is edited',
      caption: 'Rename the produce; slug, code and lot follow',
      script: 'playground/05-form-logic-derived-values.php',
      doc: '/configuration#derived-values',
      code: `$form = Form::create('Derived values')
  ->panel('naming', 'Naming', function (PanelBuilder $p): void {
    $p->text('name', 'Produce name')->default('Red Apple')->required();

    // "Red Apple" -> "red_apple": the 'machine' transform of the name.
    $p->text('slug', 'Slug')->derive(new Derive('{{name}}', 'machine'));

    // A chain: the derived slug feeds this rule. "red_apple" -> "RED_APPLE".
    $p->text('code', 'Code')->derive(new Derive('{{slug}}', 'constant'));

    // A template mixing two answers, then lowercased.
    $p->text('grower', 'Grower')->default('Sunny');
    $p->text('lot', 'Lot')->derive(new Derive('{{grower}}/{{slug}}', 'lower'));
  });`,
    },
  },
  {
    idx: '07',
    icon: 'split',
    name: 'Conditional fields',
    desc: <>Show or hide fields with <code className={styles.tok}>when</code> rules; a fix-up pass reconciles dependent answers.</>,
    demo: {
      svg: 'conditional-fields-dark-animated.svg',
      alt: 'Animated recording of fields appearing and disappearing as answers change',
      caption: 'Pick herbs and a large box; fields appear and go',
      script: 'playground/05-form-logic-conditional-fields.php',
      doc: '/configuration#conditional-fields',
      code: `$p->select('contents', 'Contents')->multiple()->default(['fruit'])->options([
  'fruit' => 'Fruit',
  'veg' => 'Vegetables',
  'herbs' => 'Herbs',
]);
$p->select('size', 'Box size')->default('medium')->options([
  'small' => 'Small',
  'medium' => 'Medium',
  'large' => 'Large',
]);

// Shown only while "herbs" is among the selected contents.
$p->text('herb_bundle', 'Herb bundle')->when(new Condition('contents', contains: 'herbs'));

// Composites: all(), any() and not() nest to any depth.
$p->confirm('weekly', 'Weekly herb delivery?')->when(Condition::all(
  new Condition('contents', contains: 'herbs'),
  new Condition('size', eq: 'large'),
));

// An operator over a set: shown for the small or medium box.
$p->confirm('stackable', 'Stack the boxes?')->when(new Condition('size', in: ['small', 'medium']));`,
    },
  },
  {
    idx: '08',
    icon: 'palette',
    name: 'Themes',
    desc: <>The whole visual representation - colours, glyphs, layout - is a theme class; six themes ship built-in, from <code className={styles.tok}>dos</code> to <code className={styles.tok}>midnight</code>, or subclass your own.</>,
    demo: {
      svg: 'theme-ocean-dark-animated.svg',
      alt: 'Animated recording of a form rendered by a custom ocean theme with a banner',
      caption: 'A custom theme class, named on the facade',
      script: 'playground/09-themes-custom.php',
      doc: '/themes',
      code: `// A theme class subclasses DefaultTheme and overrides appearance
// atoms and render methods; name it on the facade, done.
$answers = (new Tui($form))
  ->theme(OceanTheme::class, ['border' => 'rounded'])
  ->run('', '1.0.0');

// Or pick a shipped theme by name: 'default', 'dos', 'ember',
// 'frost', 'midnight' or 'mono'. Dark or light is not part of the
// theme - it is the auto-detected 'mode' display option.
$answers = (new Tui($form))->theme('midnight')->run();`,
    },
  },
  {
    idx: '09',
    icon: 'command',
    name: 'Key bindings',
    desc: <>Remap navigation, edit, accept and cancel keys per field type; ships a vim-style preset.</>,
    demo: {
      svg: 'key-bindings-vim-dark-animated.svg',
      alt: 'Animated recording of a form navigated with the vim keys and its help overlay',
      caption: 'The vim preset: j/k navigation and a truthful ? overlay',
      script: 'playground/10-key-bindings-vim.php',
      doc: '/key-bindings',
      code: `// The built-in vim preset adds h/j/k/l alongside the arrows -
// letters bind only where they are not typed input.
$answers = (new Tui($form))->keys('vim')->run();

// Or retune single bindings on top of any preset; a conflicting
// binding throws at configure time, so a bad map cannot ship.
$answers = (new Tui($form))
  ->keys('default', [
    new Binding(Scope::navigation(), Action::Quit, 'x'),
    new Binding(Scope::field(FieldType::Select), Action::Accept, KeyName::Tab, KeyName::Enter),
  ])
  ->run();`,
    },
  },
  {
    idx: '10',
    icon: 'languages',
    name: 'Translations',
    desc: <>A <code className={styles.tok}>Translator</code> localizes everything user-facing - hints, buttons, badges and the form's own labels - plural rules included, English as the fallback.</>,
    demo: {
      svg: 'translations-dark-animated.svg',
      alt: 'Animated recording of the form presented in Ukrainian, including pluralized counts',
      caption: 'The shipped Ukrainian catalog, plural forms included',
      script: 'playground/12-translations.php',
      doc: '/translations',
      code: `// The bundled chrome catalogs load automatically - this alone
// renders the chrome in Ukrainian ('auto' follows the locale).
$answers = (new Tui($form))->translator(new Translator('uk'))->run();

// Layer your own labels and chrome overrides on top from a
// directory, a single catalog file, or an inline map.
$translator = new Translator('uk', [
  __DIR__ . '/translations',
  ['uk' => ['Submit' => 'Готово']],
]);
$answers = (new Tui($form))->translator($translator)->run();`,
    },
  },
  {
    idx: '11',
    icon: 'sun-moon',
    name: 'Display modes',
    desc: <>A dark or light palette read from the terminal background, Unicode or ASCII glyphs, colour dropped under <code className={styles.tok}>NO_COLOR</code> - auto-detected, or pinned.</>,
    demo: {
      svg: 'fields-dark-animated-ascii-no-ansi.svg',
      alt: 'Animated recording of the field montage degraded to ASCII glyphs without colour',
      caption: 'The montage under LC_ALL=C and NO_COLOR',
      script: 'playground/11-display-modes-ascii.php',
      doc: '/display-modes',
      code: `// Nothing to configure: colour support, Unicode glyphs and the
// dark or light palette are read from the terminal and locale.
$answers = (new Tui($form))->run();

// Or pin any of them: ASCII glyphs, colour off (NO_COLOR works
// too), and the palette mode as an explicit theme option.
$answers = (new Tui($form))
  ->unicode(FALSE)
  ->color(FALSE)
  ->theme('default', ['mode' => Mode::Light])
  ->run();`,
    },
  },
  {
    idx: '12',
    icon: 'flask-conical',
    name: 'Testing',
    desc: <><code className={styles.tok}>TuiTester</code> drives the real panel loop from scripted keystrokes - no TTY - returning the answers, the output and the final frame.</>,
    demo: {
      svg: 'testing-dark-static.svg',
      alt: 'Terminal output of the test harness: asserted answers and the final rendered frame',
      caption: 'Scripted keystrokes, asserted answers, the final frame',
      script: 'playground/13-testing.php',
      doc: '/testing',
      code: `$tester = new TuiTester($form);
$answers = $tester->run(
  Key::named(KeyName::Enter),   // drill into the panel
  Key::named(KeyName::Enter),   // open the name field
  ' ', 'B', 'o', 'x',           // append to the default
  Key::named(KeyName::Enter),   // accept the field
  Key::named(KeyName::Escape),  // back out to the hub
  Key::named(KeyName::Down),    // onto the Submit button
  Key::named(KeyName::Enter),   // press it
);

// In PHPUnit these become assertions:
// $this->assertSame('Weekly Box', $answers->value('name'));
echo $answers->value('name');`,
    },
  },
];

const FIELDS = [
  {name: 'Calendar', file: 'field-calendar-dark-animated.svg', desc: <>A month calendar returning a normalized ISO <code className={styles.tok}>YYYY-MM-DD</code>; arrows move by day and week.</>},
  {name: 'Confirm', file: 'field-confirm-dark-animated.svg', desc: <>Yes/No toggle; arrows or Space switch, <code className={styles.tok}>y</code>/<code className={styles.tok}>n</code> set the choice directly, Enter accepts.</>},
  {name: 'File picker', file: 'field-filepicker-dark-animated.svg', desc: <>Browse the filesystem for a path, or several with <code className={styles.tok}>{'->multiple()'}</code>; <code className={styles.tok}>{'->'}</code> enters a directory, <code className={styles.tok}>{'<-'}</code> returns to its parent.</>},
  {name: 'Number', file: 'field-number-dark-animated.svg', desc: <>Integer entry accepted as an <code className={styles.tok}>int</code>, with optional min, max and step.</>},
  {name: 'Password', file: 'field-password-dark-animated.svg', desc: <>Text rendered as a mask everywhere; the accepted value stays plain for the consumer, and can be made revealable.</>},
  {name: 'Pause', file: 'field-pause-dark-animated.svg', desc: <>An acknowledgement gate; Enter or Space accepts. Unattended runs auto-acknowledge it.</>},
  {name: 'Rating', file: 'field-rating-dark-animated.svg', desc: <>A graded answer picked off a scale of points, collected as an <code className={styles.tok}>int</code>; arrows walk the scale and points may carry captions.</>},
  {name: 'Reorder', file: 'field-reorder-dark-animated.svg', desc: <>Rank a list by moving items; Space picks an item up, arrows carry it, Enter accepts.</>},
  {name: 'Search', file: 'field-search-dark-animated.svg', desc: <>Single choice with a visible filter line; typing fuzzy-matches and ranks the labels.</>},
  {name: 'Select', file: 'field-select-dark-animated.svg', desc: <>Single choice from a list; arrows move, Enter accepts, long lists page around the cursor.</>},
  {name: 'Suggest', file: 'field-suggest-dark-animated.svg', desc: <>Free text with autocomplete over a fixed option set; suggestions fuzzy-matched and ranked.</>},
  {name: 'Template', file: 'field-template-dark-animated.svg', desc: <>A fixed shape with named slots; Tab moves between them and the assembled string is collected alongside its parts.</>},
  {name: 'Text', file: 'field-text-dark-animated.svg', desc: <>Single-line input with a movable caret; type to insert, arrows move, Backspace deletes.</>},
  {name: 'Textarea', file: 'field-textarea-dark-animated.svg', desc: <>Multi-line input; Enter inserts a newline, Tab accepts, with an external-editor handoff.</>},
  {name: 'Toggle', file: 'field-toggle-dark-animated.svg', desc: <>An inline switch between two labelled values; arrows or Space flip, first letter sets it directly.</>},
];

const MODES = [
  {k: 'INTERACTIVE', t: 'On a terminal', d: <>A scrollable, keyboard-driven TUI; fields group into sections that drill in to any depth, with a contextual key-hint footer and a <code className={styles.tok}>?</code> help overlay.</>},
  {k: 'UNATTENDED', t: 'Everywhere else', d: <>Supply the answers up front as a JSON payload and environment variables so it runs without prompting. Emits a JSON schema for agents.</>},
];

/* ────────────────────────────────────────────────────────────────────────
 *  The snippets above render through the same Prism highlighter and theme
 *  as the docs code blocks, so homepage and docs code always match.
 *  Memoized: the hero typewriter re-renders the page continuously, and an
 *  unchanged snippet must not re-tokenize on every tick.
 * ──────────────────────────────────────────────────────────────────────── */
const PhpCode = React.memo(function PhpCode({code}) {
  const {siteConfig} = useDocusaurusContext();

  // The homepage is a fixed dark design, so it always highlights with the
  // site's dark Prism theme, whatever the colour-mode toggle says.
  return (
    <Highlight theme={siteConfig.themeConfig.prism.darkTheme} code={code} language="php">
      {({style, tokens, getLineProps, getTokenProps}) => (
        <pre className={styles.code} style={{backgroundColor: style.backgroundColor, color: style.color}}>
          <code>
            {tokens.map((line, i) => (
              <div key={i} {...getLineProps({line})}>
                {line.map((token, j) => <span key={j} {...getTokenProps({token})} />)}
              </div>
            ))}
          </code>
        </pre>
      )}
    </Highlight>
  );
});

const GITHUB_PATH = 'M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0016 8c0-4.42-3.58-8-8-8z';

function useTypewriter(phrases) {
  const [text, setText] = useState(phrases[0]);

  useEffect(() => {
    const reduce = typeof window !== 'undefined' && window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduce) {
      setText(phrases[0]);
      return undefined;
    }

    let pi = 0;
    let ci = 0;
    let deleting = false;
    let timer;

    setText('');

    const tick = () => {
      const word = phrases[pi];

      if (!deleting) {
        ci += 1;
        setText(word.slice(0, ci));

        if (ci === word.length) {
          deleting = true;
          timer = setTimeout(tick, 2000);
          return;
        }

        timer = setTimeout(tick, 46 + Math.random() * 44);
        return;
      }

      ci -= 1;
      setText(word.slice(0, ci));

      if (ci === 0) {
        deleting = false;
        pi = (pi + 1) % phrases.length;
        timer = setTimeout(tick, 380);
        return;
      }

      timer = setTimeout(tick, 26 + Math.random() * 22);
    };

    timer = setTimeout(tick, 460);

    return () => clearTimeout(timer);
  }, [phrases]);

  return text;
}

function CopyButton({text}) {
  const [done, setDone] = useState(false);

  const onCopy = () => {
    const flash = () => {
      setDone(true);
      setTimeout(() => setDone(false), 1600);
    };

    if (typeof navigator !== 'undefined' && navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(flash, flash);
      return;
    }

    flash();
  };

  return (
    <button type="button" className={clsx(styles.copy, done && styles.copyDone)} onClick={onCopy} aria-label="Copy the install command to the clipboard">
      <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
        <rect x="9" y="9" width="11" height="11" rx="2" />
        <path d="M5 15V5a2 2 0 0 1 2-2h10" />
      </svg>
      <span>{done ? 'copied' : 'copy'}</span>
    </button>
  );
}

const ZOOM_MIN = 0.5;
const ZOOM_MAX = 2;
const ZOOM_STEP = 0.25;

function PlayIcon() {
  return (
    <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor" aria-hidden="true"><path d="M7 4.5 19 12 7 19.5z" /></svg>
  );
}

function PauseIcon() {
  return (
    <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor" aria-hidden="true"><rect x="6.5" y="5" width="4" height="14" rx="1" /><rect x="13.5" y="5" width="4" height="14" rx="1" /></svg>
  );
}

function RestartIcon() {
  return (
    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><polyline points="1 4 1 10 7 10" /><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10" /></svg>
  );
}

/* The recordings are SVGs animated by CSS keyframes (a filmstrip translate).
 * An <img> embed animates but exposes no playback control, and inlining the
 * markup breaks playback - Chromium leaves the injected filmstrip animation
 * permanently play-pending. The player embeds the recording as an <object>
 * instead: the SVG runs in its own same-origin document, where it provably
 * animates, and the child document's Web Animations API drives pause, play
 * and restart. */
function SvgPlayer({svg, alt, name}) {
  const {withBaseUrl} = useBaseUrlUtils();
  const src = withBaseUrl('/' + svg);
  const animated = svg.includes('-animated');
  const objectRef = useRef(null);
  const [natural, setNatural] = useState(null);
  const [zoom, setZoom] = useState(1);
  const [playing, setPlaying] = useState(true);

  const childAnimations = () => {
    const doc = objectRef.current ? objectRef.current.contentDocument : null;

    return doc && doc.getAnimations ? doc.getAnimations() : [];
  };

  const onLoad = () => {
    const doc = objectRef.current ? objectRef.current.contentDocument : null;
    const root = doc ? doc.documentElement : null;

    if (!root || typeof root.getAttribute !== 'function') {
      return;
    }

    // The load event can fire more than once for the same document; only
    // the state with the recording's numeric pixel size is processed, so a
    // repeat pass never re-reads the percentage sizes set below.
    const rawWidth = root.getAttribute('width') || '';
    const rawHeight = root.getAttribute('height') || '';

    if (rawWidth.includes('%') || rawHeight.includes('%')) {
      return;
    }

    const w = parseFloat(rawWidth);
    const h = parseFloat(rawHeight);

    if (!(w > 0) || !(h > 0)) {
      return;
    }

    // The recordings carry fixed pixel sizes and no root viewBox, so one is
    // synthesized before switching the root to 100% - without it the child
    // document would clip at its native size instead of scaling, and the
    // zoom buttons could not resize real pixels through the object's box.
    if (!root.getAttribute('viewBox')) {
      root.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
    }

    root.setAttribute('width', '100%');
    root.setAttribute('height', '100%');
    setNatural({w, h});
  };

  const step = (delta) => setZoom((z) => Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, Math.round((z + delta) * 100) / 100)));

  const setPlay = (next) => {
    childAnimations().forEach((animation) => (next ? animation.play() : animation.pause()));
    setPlaying(next);
  };

  const restart = () => {
    childAnimations().forEach((animation) => {
      animation.currentTime = 0;
      animation.play();
    });
    setPlaying(true);
  };

  return (
    <div className={styles.player}>
      <div className={styles.playerBar}>
        <span className={styles.dots} aria-hidden="true"><span /><span /><span /></span>
        <span className={styles.playerName}>{name}</span>
        <span className={styles.playerControls}>
          {animated ? (
            <button type="button" className={styles.playerBtn} onClick={() => setPlay(!playing)} aria-label={playing ? 'Pause the recording' : 'Play the recording'}>{playing ? <PauseIcon /> : <PlayIcon />}</button>
          ) : null}
          {animated ? (
            <button type="button" className={styles.playerBtn} onClick={restart} aria-label="Restart the recording"><RestartIcon /></button>
          ) : null}
          <button type="button" className={styles.playerBtn} onClick={() => step(-ZOOM_STEP)} disabled={zoom <= ZOOM_MIN} aria-label="Zoom the recording out">&minus;</button>
          <button type="button" className={clsx(styles.playerBtn, styles.playerPct)} onClick={() => setZoom(1)} aria-label="Reset the recording to its actual size">{Math.round(zoom * 100)}%</button>
          <button type="button" className={styles.playerBtn} onClick={() => step(ZOOM_STEP)} disabled={zoom >= ZOOM_MAX} aria-label="Zoom the recording in">+</button>
        </span>
      </div>
      <div className={styles.playerBody}>
        <div className={styles.playerScreen}>
          <object ref={objectRef} type="image/svg+xml" data={src} onLoad={onLoad} role="img" aria-label={alt} tabIndex={-1} className={styles.playerObject} style={natural ? {width: natural.w * zoom + 'px', height: natural.h * zoom + 'px'} : undefined}>
            <img src={src} alt={alt} decoding="async" />
          </object>
        </div>
        {animated && !playing ? (
          <button type="button" className={styles.playerOverlay} onClick={() => setPlay(true)} aria-label="Play the recording">
            <span className={styles.playerOverlayIcon} aria-hidden="true"><svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M7 4.5 19 12 7 19.5z" /></svg></span>
          </button>
        ) : null}
      </div>
    </div>
  );
}

function FeatureModal({feature, onClose}) {
  const {withBaseUrl} = useBaseUrlUtils();
  const dialogRef = useRef(null);
  const closeRef = useRef(null);

  useEffect(() => {
    const onKey = (event) => {
      if (event.key === 'Escape') {
        onClose();
      }
    };

    const prevOverflow = document.body.style.overflow;
    document.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';

    if (closeRef.current) {
      closeRef.current.focus();
    }

    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = prevOverflow;
    };
  }, [onClose]);

  // A minimal focus trap: Tab cycles within the dialog while it is open.
  const onTrapKeyDown = (event) => {
    if (event.key !== 'Tab' || !dialogRef.current) {
      return;
    }

    const focusables = dialogRef.current.querySelectorAll('a[href], button:not([disabled])');

    if (!focusables.length) {
      return;
    }

    const first = focusables[0];
    const last = focusables[focusables.length - 1];

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  };

  const {demo} = feature;

  return (
    <div className={styles.overlay} role="presentation" onClick={(event) => { if (event.target === event.currentTarget) { onClose(); } }}>
      <div className={styles.dialog} role="dialog" aria-modal="true" aria-labelledby="feature-dialog-title" ref={dialogRef} onKeyDown={onTrapKeyDown}>
        <div className={styles.dialogBar}>
          <span className={styles.dots} aria-hidden="true"><span /><span /><span /></span>
          <span className={styles.dialogTitle} id="feature-dialog-title">{feature.idx} &middot; {feature.name}</span>
          <button type="button" className={styles.dialogClose} onClick={onClose} aria-label="Close the feature demo" ref={closeRef}>&#10005;</button>
        </div>
        <div className={styles.dialogBody}>
          <div className={styles.dialogCol}>
            <span className={styles.dialogLabel}>{demo.script}</span>
            <div className={styles.dialogCode}>
              <PhpCode code={demo.code} />
            </div>
          </div>
          <div className={styles.dialogCol}>
            <span className={styles.dialogLabel}>{demo.caption}</span>
            <SvgPlayer svg={demo.svg} alt={demo.alt} name={demo.svg} />
          </div>
        </div>
        <div className={styles.dialogFoot}>
          <p className={styles.dialogDesc}>{feature.desc}</p>
          <div className={styles.dialogLinks}>
            <a className={clsx(styles.btn, styles.btnGhost)} href={REPO_BLOB + demo.script}>Full script</a>
            <a className={clsx(styles.btn, styles.btnPrimary)} href={withBaseUrl(demo.doc)}>Read the docs &rarr;</a>
          </div>
        </div>
      </div>
    </div>
  );
}

export default function Home() {
  const rootRef = useRef(null);
  const typed = useTypewriter(TITLE_PHRASES);
  const {withBaseUrl} = useBaseUrlUtils();
  const [active, setActive] = useState(null);
  const triggerRef = useRef(null);

  // Memoized so the modal's effect keeps a stable onClose: the typewriter
  // re-renders Home every tick, and a fresh callback identity would re-run
  // the effect and yank focus back to the close button while the dialog is
  // open.
  const openFeature = useCallback((feature, event) => {
    triggerRef.current = event.currentTarget;
    setActive(feature);
  }, []);

  const closeFeature = useCallback(() => {
    setActive(null);

    if (triggerRef.current) {
      triggerRef.current.focus();
      triggerRef.current = null;
    }
  }, []);

  useEffect(() => {
    const root = rootRef.current;

    if (!root) {
      return undefined;
    }

    const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const items = root.querySelectorAll('.' + styles.revealUp);

    if (reduce || typeof IntersectionObserver === 'undefined') {
      items.forEach((el) => el.classList.add(styles.shown));
      return undefined;
    }

    root.classList.add(styles.revealReady);

    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add(styles.shown);
          io.unobserve(entry.target);
        }
      });
    }, {rootMargin: '0px 0px -8% 0px', threshold: 0.08});

    items.forEach((el) => io.observe(el));

    return () => io.disconnect();
  }, []);

  // Layout gets no title: the homepage <title> is then the site title itself
  // - the slogan - rather than 'phrase | slogan'.
  return (
    <Layout description={SUBHEAD}>
      <Head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:ital,wght@0,400;0,500;0,700;0,800;1,400&family=Martian+Mono:wght@600;700;800&display=swap" rel="stylesheet" />
      </Head>

      <div className={styles.home} ref={rootRef}>
        <div className={styles.grain} aria-hidden="true" />
        <div className={styles.scan} aria-hidden="true" />

        <div className={styles.content}>
          {/* hero */}
          <section className={clsx(styles.section, styles.hero)} aria-labelledby="hero-title">
            <div className={styles.wrap}>
              <span className={clsx(styles.eyebrow, styles.loadReveal)} style={{'--d': '.16s'}}>
                <span className={styles.pulse} aria-hidden="true" />A PHP library &middot; MIT
              </span>

              <h1 id="hero-title" className={clsx(styles.headline, styles.loadReveal)} style={{'--d': '.24s'}} aria-label={TITLE_PHRASES[0]}>
                <span aria-hidden="true">{typed}</span>
                <span className={styles.cursor} aria-hidden="true" />
              </h1>

              <p className={clsx(styles.subhead, styles.loadReveal)} style={{'--d': '.38s'}}>{SUBHEAD}</p>

              <div className={clsx(styles.install, styles.loadReveal)} style={{'--d': '.5s'}}>
                <code className={styles.installCmd}><span className={styles.prompt} aria-hidden="true">$</span> {INSTALL_CMD}</code>
                <CopyButton text={INSTALL_CMD} />
              </div>

              <div className={clsx(styles.ctaRow, styles.loadReveal)} style={{'--d': '.6s'}}>
                <a className={clsx(styles.btn, styles.btnPrimary)} href={withBaseUrl('/installation')}>Get started</a>
                <a className={clsx(styles.btn, styles.btnGhost)} href="https://github.com/drevops/tui">
                  <svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" fill="currentColor"><path d={GITHUB_PATH} /></svg>
                  View on GitHub
                </a>
                <a className={clsx(styles.btn, styles.btnLink)} href={withBaseUrl('/introduction')}>Read the docs &rarr;</a>
              </div>

              <div className={clsx(styles.term, styles.loadReveal)} style={{'--d': '.72s'}} role="img" aria-label="A form built with TUI, recorded in the terminal">
                <div className={styles.termBar}>
                  <span className={styles.dots} aria-hidden="true"><span /><span /><span /></span>
                  <span className={styles.termTitle}>tui - zsh</span>
                  <span className={styles.termMeta} aria-hidden="true">UTF-8</span>
                </div>
                <div className={styles.termScreen}>
                  <img src={withBaseUrl('/bordered-panels-dark-animated.svg')} alt="Animated terminal recording of a keyboard-driven form built with TUI, shown inside a rounded border." width="800" height="441" loading="eager" decoding="async" />
                </div>
              </div>
            </div>
          </section>

          {/* quick start */}
          <section id="quickstart" className={styles.section} aria-labelledby="qs-title">
            <div className={styles.wrap}>
              <div className={styles.qsHead}>
                <span className={clsx(styles.kicker, styles.revealUp)}>quick-start</span>
                <h2 id="qs-title" className={clsx(styles.h2, styles.revealUp)} style={{'--i': 1}}>Declare a form in PHP</h2>
                <p className={clsx(styles.lead, styles.revealUp)} style={{'--i': 2}}>Describe the questions with a fluent builder - text, choices, toggles and more - and call <code className={styles.tok}>run()</code>. Interactive on a terminal, non-interactive otherwise.</p>
              </div>

              <div className={clsx(styles.codewin, styles.revealUp)} style={{'--i': 1}}>
                <div className={styles.codewinBar}>
                  <span className={styles.dots} aria-hidden="true"><span /><span /><span /></span>
                  <span className={styles.codewinFile}>form.php</span>
                </div>
                <div className={styles.codewinScroll}>
                  <PhpCode code={QUICKSTART_CODE} />
                </div>
              </div>

              <div className={styles.modes}>
                {MODES.map((mode, i) => (
                  <div key={mode.k} className={clsx(styles.mode, styles.revealUp)} style={{'--i': i}}>
                    <span className={styles.modeK}><span className={styles.prompt} aria-hidden="true">$</span>{mode.k}</span>
                    <p className={styles.modeT}>{mode.t}</p>
                    <p className={styles.modeD}>{mode.d}</p>
                  </div>
                ))}
              </div>
            </div>
          </section>

          {/* features */}
          <section id="features" className={styles.section} aria-labelledby="features-title">
            <div className={styles.wrap}>
              <div className={styles.featuresHead}>
                <span className={clsx(styles.kicker, styles.revealUp)}>features</span>
                <h2 id="features-title" className={clsx(styles.h2, styles.revealUp)} style={{'--i': 1}}>Built for keyboard-driven forms</h2>
                <p className={clsx(styles.lead, styles.revealUp)} style={{'--i': 2}}>The engine stays generic. Your questions and handlers live in the consumer - it collects, you apply. Open any card for the code and a recording.</p>
              </div>
              <div className={styles.featGrid}>
                {FEATURES.map((feature, i) => (
                  <button key={feature.name} type="button" className={clsx(styles.feat, styles.revealUp)} style={{'--i': i}} onClick={(event) => openFeature(feature, event)} aria-haspopup="dialog">
                    <span className={styles.featTop}>
                      <span className={styles.featIcon}><FeatIcon name={feature.icon} /></span>
                      <span className={styles.featIdx}>{feature.idx}</span>
                    </span>
                    <strong className={styles.featName}>{feature.name}</strong>
                    <span className={styles.featDesc}>{feature.desc}</span>
                    <span className={styles.featHint} aria-hidden="true">&#9656; code + demo</span>
                  </button>
                ))}
              </div>
            </div>
          </section>

          {/* fields */}
          <section id="fields" className={styles.section} aria-labelledby="fields-title">
            <div className={styles.wrap}>
              <div className={styles.fieldsHead}>
                <span className={clsx(styles.kicker, styles.revealUp)}>fields</span>
                <h2 id="fields-title" className={clsx(styles.h2, styles.revealUp)} style={{'--i': 1}}>One layer, every field type</h2>
                <p className={clsx(styles.lead, styles.revealUp)} style={{'--i': 2}}>Text, numbers, dates, single and multiple choice, fuzzy search, file browsing, reordering and gates.</p>
              </div>
              <div className={styles.wgrid}>
                {FIELDS.map((field) => (
                  <article key={field.name} className={clsx(styles.wcard, styles.revealUp)}>
                    <div className={styles.wcardScreen}>
                      <img src={withBaseUrl('/' + field.file)} alt={`Animated demo of the ${field.name} field`} loading="lazy" decoding="async" />
                    </div>
                    <div className={styles.wcardMeta}>
                      <h3 className={styles.wcardName}>{field.name}</h3>
                      <p className={styles.wcardDesc}>{field.desc}</p>
                    </div>
                  </article>
                ))}
              </div>
            </div>
          </section>
        </div>

        {active ? <FeatureModal feature={active} onClose={closeFeature} /> : null}
      </div>
    </Layout>
  );
}
