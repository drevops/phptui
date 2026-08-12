<?php

/**
 * @file
 * Translations: chrome and questions presented in another language.
 *
 * A Translator carries the active language and catalog sources; set on the
 * facade, it localizes everything user-facing - key hints, buttons, badges,
 * validation messages, and the form's own labels - with English as the fallback
 * for anything untranslated. The language can also be the 'auto' sentinel to
 * follow the environment locale (LC_ALL, LC_MESSAGES, LANG), and 'uk_UA' falls
 * back to the 'uk' catalog when no region catalog exists.
 *
 * The Basket sub-panel's multi-select condenses to a pluralized count in its
 * hub summary, so selecting a different number of fruits shows Ukrainian's
 * one/few/many forms - see uk.php for the rule that chooses between them.
 *
 * The catalog is not a property of the screen, so it survives having none: an
 * unattended run resolves the answers from the defaults and prints the same
 * localized summary, which is the whole Ukrainian session in one line of
 * output and no terminal.
 *
 * Usage:
 *   php playground/12-translations.php
 *
 *   # The same session with no terminal at all: panels, labels and summary,
 *   # localized end to end.
 *   php playground/12-translations.php < /dev/null
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Translation\Translator;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// The form is declared in English; the catalog translates it at render time,
// so the answer ids and values stay language-neutral.
$form = Form::create('Produce order')
  ->panel('order', 'New order', function (PanelBuilder $p): void {
    $p->description('Your weekly produce order.');
    $p->text('name', 'Order name')->default('Weekly');

    // A nested sub-panel renders as a drillable row with a value summary; the
    // multi-select condenses there to a pluralized "@count items selected".
    $p->panel('basket', 'Basket', function (PanelBuilder $sp): void {
      $sp->description('Pick your fruits.');
      $sp->select('fruits', 'Fruits')->multiple()->default(['apple', 'banana', 'cherry', 'pear'])->options([
        'apple' => 'Apple',
        'banana' => 'Banana',
        'cherry' => 'Cherry',
        'pear' => 'Pear',
        'grape' => 'Grape',
      ]);
    });
  });

// Ukrainian: the library's bundled catalog (translations/uk.php) loads
// automatically with the chrome and its three plural forms; the local
// translations/ layers this form's own labels on top, and could override any
// chrome string the same way. An inline map works too - e.g.
// ['uk' => ['Fruits' => 'Фрукти']] - and Translator('auto', [...]) would
// follow the terminal locale instead.
$translator = new Translator('uk', [__DIR__ . '/translations']);

try {
  $answers = (new Tui($form))->translator($translator)->run();
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
catch (CollectException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

// The summary renders through the same catalog, interactively or not; the
// collected values are untouched - only the presentation is localized, so the
// ids and the answers stay the language-neutral ones the form declared.
echo $answers->toSummary() . PHP_EOL;
