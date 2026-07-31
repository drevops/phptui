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
 * Usage:
 *   php playground/12-translations.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\CollectException;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Translation\Translator;
use DrevOps\Tui\Tui;

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

// The summary renders through the same catalog; the collected values are
// untouched - only the presentation is localized.
echo $answers->toSummary() . PHP_EOL;
