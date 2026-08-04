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
 * the reserved key and a count phrase lists its forms as one/few/many, in that
 * order. Key caps a keyboard prints in Latin (bksp, esc, tab) stay as they are,
 * because that is what the reader sees on the key.
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
  '"@min" must not exceed "@max".' => '"@min" не може перевищувати "@max".',
  '"@value" does not match the template "@pattern".' => '"@value" не відповідає шаблону "@pattern".',
  '(empty)' => '(порожньо)',
  '1 item selected' => '1 елемент вибрано',
  '@count items selected' => [
    '@count елемент вибрано',
    '@count елементи вибрано',
    '@count елементів вибрано',
  ],
  '@label is required.' => "@label є обов'язковим полем.",
  '@label: @error' => '@label: @error',
  '@label: must not contain "@text".' => '@label: не може містити "@text".',
  '@value is not a valid "@key". Allowed: @allowed.' => '@value не є припустимим "@key". Дозволено: @allowed.',
  '@value is not a valid "@key". Use a non-negative integer.' => '@value не є припустимим "@key". Використайте ціле число не менше нуля.',
  'April' => 'Квітень',
  'August' => 'Серпень',
  'Cancel' => 'Скасувати',
  'Choose @constraint.' => 'Оберіть @constraint.',
  'Could not load options for field "@id": @error' => 'Не вдалося завантажити варіанти для поля "@id": @error',
  'Could not load options.' => 'Не вдалося завантажити варіанти.',
  'December' => 'Грудень',
  'Directories only' => 'Лише каталоги',
  'Enter a number @constraint.' => 'Введіть число @constraint.',
  'Extensions: @extensions' => 'Розширення: @extensions',
  'February' => 'Лютий',
  'Files only' => 'Лише файли',
  'Fr' => 'Пт',
  'Invalid value for field "@id": @error' => 'Неприпустиме значення поля "@id": @error',
  'January' => 'Січень',
  'July' => 'Липень',
  'June' => 'Червень',
  'March' => 'Березень',
  'Max @size' => 'Максимум @size',
  'May' => 'Травень',
  'Missing required question "@id".' => 'Пропущено потрібне питання "@id".',
  'Mo' => 'Пн',
  'Need at least @width x @height - have @w x @h.' => 'Потрібно щонайменше @width x @height - є @w x @h.',
  'No' => 'Ні',
  'November' => 'Листопад',
  'October' => 'Жовтень',
  'Page size must be a positive integer, @size given.' => 'Розмір сторінки має бути додатним цілим числом, задано @size.',
  'Passwords do not match.' => 'Паролі не збігаються.',
  'Press @key to continue' => 'Натисніть @key, щоб продовжити',
  'Press any key to continue...' => 'Натисніть будь-яку клавішу, щоб продовжити...',
  'Question "@id" must be @constraint.' => 'Питання "@id" має бути @constraint.',
  'Question "@id": @error' => 'Питання "@id": @error',
  'Question "@id": @error.' => 'Питання "@id": @error.',
  'Sa' => 'Сб',
  'Select @constraint.' => 'Виберіть @constraint.',
  'September' => 'Вересень',
  'Su' => 'Нд',
  'Submit' => 'Надіслати',
  'Terminal too small.' => 'Термінал замалий.',
  'Th' => 'Чт',
  'The --prompts value is neither a JSON object nor a path to one.' => 'Значення --prompts не є документом JSON або шляхом до нього.',
  'Tu' => 'Вт',
  'Type 1 character to search.' => 'Введіть 1 символ для пошуку.',
  'Type @count characters to search.' => [
    'Введіть @count символ для пошуку.',
    'Введіть @count символи для пошуку.',
    'Введіть @count символів для пошуку.',
  ],
  'Unknown question "@id".' => 'Невідоме питання "@id".',
  'Unknown theme option "@key". Known: @known.' => 'Невідомий параметр теми "@key". Відомі: @known.',
  'Version: @version' => 'Версія: @version',
  'We' => 'Ср',
  'Yes' => 'Так',
  'a boolean' => 'логічне значення',
  'a date (YYYY-MM-DD)' => 'дата (РРРР-ММ-ДД)',
  'a file no larger than @size' => 'файл не більший за @size',
  'a file with a permitted extension (@extensions)' => 'файл із дозволеним розширенням (@extensions)',
  'a list' => 'список',
  'a number' => 'число',
  'a string' => 'рядок',
  'a whole number' => 'ціле число',
  'accept' => 'прийняти',
  'adjust' => 'налаштувати',
  'an existing directory' => 'наявний каталог',
  'an existing file' => 'наявний файл',
  'an existing path' => 'наявний шлях',
  'answer yes or no' => 'так/ні',
  'at least 1 item' => 'щонайменше 1 елемент',
  'at least @count items' => [
    'щонайменше @count елемент',
    'щонайменше @count елементи',
    'щонайменше @count елементів',
  ],
  'at least @min' => 'щонайменше @min',
  'at most 1 item' => 'щонайбільше 1 елемент',
  'at most @count items' => [
    'щонайбільше @count елемент',
    'щонайбільше @count елементи',
    'щонайбільше @count елементів',
  ],
  'at most @max' => 'щонайбільше @max',
  'between @min and @max' => 'від @min до @max',
  'between @min and @max items' => 'від @min до @max елементів',
  'bksp' => 'bksp',
  'cancel' => 'скасувати',
  'continue' => 'продовжити',
  'ctrl-c' => 'ctrl-c',
  'default' => 'типове',
  'del' => 'del',
  'derived' => 'похідне',
  'detected' => 'виявлено',
  'drop' => 'покласти',
  'edited' => 'змінено',
  'end' => 'end',
  'esc' => 'esc',
  'exactly 1 item' => 'рівно 1 елемент',
  'exactly @count items' => [
    'рівно @count елемент',
    'рівно @count елементи',
    'рівно @count елементів',
  ],
  'filling in @label' => 'заповнюється @label',
  'go back' => 'назад',
  'go up' => 'вгору',
  'grab' => 'взяти',
  'home' => 'home',
  'insert a newline' => 'новий рядок',
  'move' => 'рух',
  'move between parts' => 'між частинами',
  'move by day' => 'на день',
  'move by week' => 'на тиждень',
  'must be @constraint.' => 'має бути @constraint.',
  'must rank every option exactly once (@options)' => 'потрібно впорядкувати кожен пункт лише раз (@options)',
  'no' => 'ні',
  'on or after @min' => '@min або пізніше',
  'on or before @max' => '@max або раніше',
  'open' => 'відкрити',
  'open the editor' => 'відкрити редактор',
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
  'select none or all' => 'нічого/усе',
  'show help' => 'довідка',
  'show hidden' => 'приховані',
  'space' => 'пробіл',
  'tab' => 'tab',
  // A legend fragment reads as "<key> <action>", and Ukrainian needs no
  // preposition to join the two, so the action stands on its own.
  'to @action' => '@action',
  'toggle' => 'перемкнути',
  'value "@value" is not one of: @options' => 'значення "@value" не є одним з: @options',
  'value "@value" was not found' => 'значення "@value" не знайдено',
  'value must be a list' => 'значення має бути списком',
  'yes' => 'так',
];
