<?php

/**
 * @file
 * The Ukrainian chrome catalog.
 *
 * Named for the ISO 639-1 language code "uk" (Ukrainian) - not the country
 * code "ua". The library resolves a locale to its primary subtag (uk_UA -> uk),
 * so this file loads for any Ukrainian locale.
 *
 * @see https://en.wikipedia.org/wiki/List_of_ISO_639_language_codes
 *
 * Ukrainian has three plural forms, so the catalog supplies its own rule under
 * the reserved key; "@count items selected" lists its one/few/many forms and so
 * needs no separate singular entry. The wording is a first pass - a native
 * review is welcome.
 */

declare(strict_types=1);

use DrevOps\Tui\Translation\Translator;

return [
  // The count-to-form rule: 0 one, 1 few, 2 many (Unicode CLDR for Ukrainian).
  Translator::PLURAL_RULE => static function (int $count): int {
    $mod10 = $count % 10;
    $mod100 = $count % 100;

    if ($mod10 === 1 && $mod100 !== 11) {
      return 0;
    }

    if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
      return 1;
    }

    return 2;
  },
  // The plural forms, in rule order: one, few, many.
  '@count items selected' => [
    '@count елемент вибрано',
    '@count елементи вибрано',
    '@count елементів вибрано',
  ],
  'at least @count items' => [
    'щонайменше @count елемент',
    'щонайменше @count елементи',
    'щонайменше @count елементів',
  ],
  'at most @count items' => [
    'щонайбільше @count елемент',
    'щонайбільше @count елементи',
    'щонайбільше @count елементів',
  ],
  'exactly @count items' => [
    'рівно @count елемент',
    'рівно @count елементи',
    'рівно @count елементів',
  ],
  // The default button labels: the Buttons model defaults render through t(),
  // so they localize here even though they are not in en.php's scanned key
  // list.
  'Submit' => 'Надіслати',
  'Cancel' => 'Скасувати',
  '"@min" must not exceed "@max".' => '"@min" не може перевищувати "@max".',
  '(empty)' => '(порожньо)',
  '@value is not a valid "@key". Allowed: @allowed.' => '@value не є припустимим "@key". Дозволено: @allowed.',
  '@value is not a valid "@key". Use a non-negative integer.' => '@value не є припустимим "@key". Використайте ціле число не менше нуля.',
  'Calendar' => 'Календар',
  'Confirm' => 'Підтвердити',
  'Enter a number @constraint.' => 'Введіть число @constraint.',
  'File picker' => 'Вибір файлу',
  'Fr' => 'Пт',
  'Invalid value for field "@id": @error' => 'Неприпустиме значення поля "@id": @error',
  'Keyboard help' => 'Довідка з клавіатури',
  'Missing required question "@id".' => 'Пропущено потрібне питання "@id".',
  'Mo' => 'Пн',
  'Navigation' => 'Навігація',
  'Need at least @width x @height - have @w x @h.' => 'Потрібно щонайменше @width x @height - є @w x @h.',
  'No' => 'Ні',
  'Number' => 'Число',
  'Page size must be a positive integer, @size given.' => 'Розмір сторінки має бути додатним цілим числом, задано @size.',
  'Password' => 'Пароль',
  'Passwords do not match.' => 'Паролі не збігаються.',
  'Pause' => 'Пауза',
  'Press @key to continue' => 'Натисніть @key, щоб продовжити',
  'Press any key to continue...' => 'Натисніть будь-яку клавішу, щоб продовжити...',
  'Question "@id" is required.' => 'Питання "@id" є потрібним.',
  'Question "@id" must be @constraint.' => 'Питання "@id" має бути @constraint.',
  'Question "@id": @error.' => 'Питання "@id": @error.',
  'Reorder' => 'Перевпорядкувати',
  'Sa' => 'Сб',
  'Search' => 'Пошук',
  'Select' => 'Вибрати',
  'Select @constraint.' => 'Виберіть @constraint.',
  'Su' => 'Нд',
  'Suggest' => 'Підказка',
  'Terminal too small.' => 'Термінал замалий.',
  'Text' => 'Текст',
  'Textarea' => 'Текстова область',
  'Th' => 'Чт',
  'The --prompts value is neither a JSON object nor a path to one.' => 'Значення --prompts не є документом JSON або шляхом до нього.',
  'Toggle' => 'Перемкнути',
  'Tu' => 'Вт',
  'Unknown question "@id".' => 'Невідоме питання "@id".',
  'Unknown theme option "@key". Known: @known.' => 'Невідомий параметр теми "@key". Відомі: @known.',
  'Version: @version' => 'Версія: @version',
  'We' => 'Ср',
  'Yes' => 'Так',
  'a boolean' => 'логічне значення',
  'a date (YYYY-MM-DD)' => 'дата (РРРР-ММ-ДД)',
  'a list' => 'список',
  'a number' => 'число',
  'a string' => 'рядок',
  'accept' => 'прийняти',
  'adjust' => 'налаштувати',
  'at least @min' => 'щонайменше @min',
  'at most @max' => 'щонайбільше @max',
  'back' => 'назад',
  'between @min and @max' => 'від @min до @max',
  'between @min and @max items' => 'від @min до @max елементів',
  'bksp' => 'bksp',
  'cancel' => 'скасувати',
  'close' => 'закрити',
  'continue' => 'продовжити',
  'ctrl-c' => 'ctrl-c',
  'day' => 'день',
  'default' => 'типове',
  'del' => 'del',
  'derived' => 'похідне',
  'detected' => 'виявлено',
  'drop' => 'покласти',
  'edited' => 'змінено',
  'editor' => 'редактор',
  'end' => 'end',
  'esc' => 'esc',
  'grab' => 'взяти',
  'help' => 'довідка',
  'hidden' => 'приховано',
  'home' => 'home',
  'move' => 'перемістити',
  'must be @constraint.' => 'має бути @constraint.',
  'must rank every option exactly once (@options)' => 'потрібно впорядкувати кожен пункт лише раз (@options)',
  'newline' => 'новий рядок',
  'no' => 'ні',
  'none/all' => 'нічого/усе',
  'on or after @min' => '@min або пізніше',
  'on or before @max' => '@max або раніше',
  'open' => 'відкрити',
  'option "@value" is disabled' => 'пункт "@value" вимкнено',
  'option "@value" is disabled: @reason' => 'пункт "@value" вимкнено: @reason',
  'override' => 'перевизначено',
  'pgdn' => 'pgdn',
  'pgup' => 'pgup',
  'quit' => 'вийти',
  're-enter to confirm' => 'введіть ще раз для підтвердження',
  'reorder' => 'перевпорядкувати',
  'reveal' => 'показати',
  'select' => 'вибрати',
  'space' => 'пробіл',
  'tab' => 'tab',
  'toggle' => 'перемкнути',
  'up' => 'вгору',
  'value "@value" is not one of: @options' => 'значення "@value" не є одним з: @options',
  'value must be a list' => 'значення має бути списком',
  'week' => 'тиждень',
  'yes' => 'так',
  'yes/no' => 'так/ні',
];
