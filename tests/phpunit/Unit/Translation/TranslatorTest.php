<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Translation;

use DrevOps\Tui\Tests\Traits\IsolatesEnvTrait;
use DrevOps\Tui\Tests\Traits\ResetsTranslatorTrait;
use DrevOps\Tui\Translation\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the translator and its static t() bridge.
 */
#[CoversClass(Translator::class)]
#[Group('translation')]
final class TranslatorTest extends TestCase {

  use IsolatesEnvTrait;
  use ResetsTranslatorTrait {
    tearDown as translatorTearDown;
  }

  protected function tearDown(): void {
    $this->restoreEnv();
    $this->translatorTearDown();
  }

  /**
   * The absolute path to a translation fixture directory.
   */
  protected function fixtures(string $name): string {
    return dirname(__DIR__, 2) . '/Fixtures/' . $name;
  }

  #[DataProvider('dataProviderTranslate')]
  public function testTranslate(string $language, array $sources, string $source, array $args, string $expected): void {
    $resolved = array_map(fn(string|array $entry): string|array => is_string($entry) ? $this->fixtures($entry) : $entry, $sources);
    $translator = new Translator($language, array_values($resolved));

    $this->assertSame($expected, $translator->translate($source, $args));
  }

  public static function dataProviderTranslate(): \Iterator {
    yield 'basic lookup' => ['es', ['translations'], 'Submit', [], 'Enviar'];
    yield 'unknown key falls back to source' => ['es', ['translations'], 'Unknown', [], 'Unknown'];
    yield 'placeholder interpolated' => ['es', ['translations'], 'Enter a number @constraint.', ['@constraint' => 'x'], 'Introduzca un numero x.'];
    yield 'question label translated' => ['es', ['translations'], 'Colour theme', [], 'Tema de color'];
    yield 'translation off returns source' => ['', ['translations'], 'Submit', [], 'Submit'];
    yield 'translation off still interpolates' => ['', ['translations'], 'Value @n.', ['@n' => 5], 'Value 5.'];
    yield 'region catalog wins over primary' => ['es_ES', ['translations'], 'Submit', [], 'Enviar (ES)'];
    yield 'region falls back to primary' => ['es_ES', ['translations-override'], 'Submit', [], 'Enviar (override)'];
    yield 'hyphen locale normalized' => ['es-ES', ['translations'], 'Submit', [], 'Enviar (ES)'];
    yield 'later directory overrides earlier' => ['es', ['translations', 'translations-override'], 'Submit', [], 'Enviar (override)'];
    yield 'malformed catalog ignored' => ['es', ['translations-malformed'], 'Submit', [], 'Submit'];
    yield 'mixed catalog keeps string entry' => ['es', ['translations-mixed'], 'Valid', [], 'Valido'];
    yield 'mixed catalog drops non-string value' => ['es', ['translations-mixed'], 'Integer value', [], 'Integer value'];
    yield 'path traversal locale is rejected' => ['../../etc/passwd', ['translations'], 'Submit', [], 'Submit'];
    yield 'file source loads matching locale' => ['es', ['translations/es.php'], 'Submit', [], 'Enviar'];
    yield 'file source loads for region locale' => ['es_ES', ['translations-override/es.php'], 'Submit', [], 'Enviar (override)'];
    yield 'file source for another locale is a no-op' => ['de', ['translations/es.php'], 'Submit', [], 'Submit'];
    yield 'inline map loads matching locale' => ['es', [['es' => ['Submit' => 'Enviar (inline)']]], 'Submit', [], 'Enviar (inline)'];
    yield 'inline map region falls back to primary' => ['es_ES', [['es' => ['Submit' => 'Enviar (inline)']]], 'Submit', [], 'Enviar (inline)'];
    yield 'inline map most specific key wins' => ['es_ES', [['es_ES' => ['Submit' => 'Enviar (region)'], 'es' => ['Submit' => 'Enviar (inline)']]], 'Submit', [], 'Enviar (region)'];
    yield 'inline map for another locale is a no-op' => ['es', [['de' => ['Submit' => 'Senden']]], 'Submit', [], 'Submit'];
    yield 'inline map non-array section ignored' => ['es', [['es' => 'oops']], 'Submit', [], 'Submit'];
    yield 'inline map overrides earlier directory' => ['es', ['translations', ['es' => ['Submit' => 'Enviar (inline)']]], 'Submit', [], 'Enviar (inline)'];
    yield 'bundled chrome loads with no sources' => ['uk', [], 'Files only', [], 'Лише файли'];
    yield 'bundled english template is identity' => ['en', [], 'Submit', [], 'Submit'];
    yield 'inline map overrides bundled chrome' => ['uk', [['uk' => ['Submit' => 'Готово']]], 'Submit', [], 'Готово'];
    yield 'bundled chrome persists beside overrides' => ['uk', [['uk' => ['Submit' => 'Готово']]], 'Files only', [], 'Лише файли'];
  }

  public function testMissingSourceThrows(): void {
    $translator = new Translator('es', [$this->fixtures('translations-absent')]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('neither a directory nor a catalog file');

    $translator->translate('Submit');
  }

  public function testCatalogLoadsOnce(): void {
    $translator = new Translator('es', [$this->fixtures('translations')]);

    $this->assertSame('Enviar', $translator->translate('Submit'));
    $this->assertSame('Enviar', $translator->translate('Submit'));
  }

  #[DataProvider('dataProviderAuto')]
  public function testAuto(?string $lc_all, string $expected): void {
    $this->putEnv('LC_ALL', $lc_all);

    $translator = new Translator('auto', [$this->fixtures('translations')]);
    $this->assertSame($expected, $translator->translate('Submit'));
  }

  public static function dataProviderAuto(): \Iterator {
    yield 'detected region loads catalog' => ['es_ES.UTF-8', 'Enviar (ES)'];
    yield 'posix locale is english' => ['C', 'Submit'];
  }

  #[DataProvider('dataProviderDetectLanguage')]
  public function testDetectLanguage(?string $lc_all, ?string $lc_messages, ?string $lang, string $expected): void {
    $this->putEnv('LC_ALL', $lc_all);
    $this->putEnv('LC_MESSAGES', $lc_messages);
    $this->putEnv('LANG', $lang);

    $this->assertSame($expected, Translator::detectLanguage());
  }

  public static function dataProviderDetectLanguage(): \Iterator {
    yield 'lc_all decides first' => ['es_ES.UTF-8', 'fr_FR', 'de_DE', 'es_ES'];
    yield 'lc_messages before lang' => [NULL, 'fr_FR.UTF-8', 'de_DE', 'fr_FR'];
    yield 'lang is the last resort' => [NULL, NULL, 'de_DE.UTF-8', 'de_DE'];
    yield 'modifier stripped' => [NULL, NULL, 'ca_ES.UTF-8@valencia', 'ca_ES'];
    yield 'empty value skipped' => ['', NULL, 'it_IT', 'it_IT'];
    yield 'c locale is english' => ['C', NULL, NULL, ''];
    yield 'posix locale is english' => ['POSIX', NULL, NULL, ''];
    yield 'none set is english' => [NULL, NULL, NULL, ''];
  }

  public function testShared(): void {
    $this->assertNotInstanceOf(Translator::class, Translator::shared());

    $translator = new Translator('es', [$this->fixtures('translations')]);
    Translator::setShared($translator);
    $this->assertSame($translator, Translator::shared());

    Translator::setShared(NULL);
    $this->assertNull(Translator::shared());
  }

  public function testStaticBridgeUsesSharedTranslator(): void {
    $this->assertSame('Submit', Translator::t('Submit'));
    $this->assertSame('Value 3.', Translator::t('Value @n.', ['@n' => 3]));

    Translator::setShared(new Translator('es', [$this->fixtures('translations')]));
    $this->assertSame('Enviar', Translator::t('Submit'));
  }

  #[DataProvider('dataProviderFormatPlural')]
  public function testFormatPlural(int $count, string $expected): void {
    $this->assertSame($expected, Translator::formatPlural($count, '1 item selected', '@count items selected'));
  }

  public static function dataProviderFormatPlural(): \Iterator {
    // With no translator, English's one-versus-other rule chooses the source.
    yield 'one is singular' => [1, '1 item selected'];
    yield 'zero is plural' => [0, '0 items selected'];
    yield 'two is plural' => [2, '2 items selected'];
    yield 'many is plural' => [7, '7 items selected'];
  }

  #[DataProvider('dataProviderFormatPluralLocalizesForms')]
  public function testFormatPluralLocalizesForms(int $count, string $expected): void {
    Translator::setShared(new Translator('uk', [$this->fixtures('translations-plural')]));

    $this->assertSame($expected, Translator::formatPlural($count, '1 item selected', '@count items selected'));
  }

  public static function dataProviderFormatPluralLocalizesForms(): \Iterator {
    // Ukrainian's one/few/many, across its 11-14 and 21+ boundaries.
    yield 'one: 1' => [1, '1 елемент вибрано'];
    yield 'few: 4' => [4, '4 елементи вибрано'];
    yield 'many: 5' => [5, '5 елементів вибрано'];
    yield 'many: 11' => [11, '11 елементів вибрано'];
    yield 'many: 14' => [14, '14 елементів вибрано'];
    yield 'one: 21' => [21, '21 елемент вибрано'];
    yield 'few: 22' => [22, '22 елементи вибрано'];
    yield 'many: 25' => [25, '25 елементів вибрано'];
  }

  public function testFormatPluralUsesDefaultRuleWithoutCatalogRule(): void {
    Translator::setShared(new Translator('es', [$this->fixtures('translations-plural')]));

    // A catalog may list forms without a rule; the default rule applies.
    $this->assertSame('1 elemento seleccionado', Translator::formatPlural(1, '1 item selected', '@count items selected'));
    $this->assertSame('9 elementos seleccionados', Translator::formatPlural(9, '1 item selected', '@count items selected'));
  }

  public function testFormatPluralFallsBackWhenFormMissing(): void {
    Translator::setShared(new Translator('uk', [$this->fixtures('translations-plural')]));

    // An untranslated message keeps the English forms and the default rule.
    // A count Ukrainian would treat as 'one' but that is not literally one
    // (21) still reads as the English plural, not the singular "1 file".
    $this->assertSame('1 file', Translator::formatPlural(1, '1 file', '@count files'));
    $this->assertSame('3 files', Translator::formatPlural(3, '1 file', '@count files'));
    $this->assertSame('5 files', Translator::formatPlural(5, '1 file', '@count files'));
    $this->assertSame('21 files', Translator::formatPlural(21, '1 file', '@count files'));
  }

  public function testFormatPluralFallsBackWhenRuleOutrunsForms(): void {
    Translator::setShared(new Translator('uk', [$this->fixtures('translations-plural')]));

    // The catalog translates this message with only two forms, yet its rule can
    // return 'many' (index 2); the missing form falls back to the plural.
    $this->assertSame('1 коробка', Translator::formatPlural(1, '1 box', '@count boxes'));
    $this->assertSame('3 коробки', Translator::formatPlural(3, '1 box', '@count boxes'));
    $this->assertSame('5 boxes', Translator::formatPlural(5, '1 box', '@count boxes'));
  }

  public function testFormatPluralIgnoresMalformedCatalog(): void {
    Translator::setShared(new Translator('de', [$this->fixtures('translations-plural')]));

    // A non-closure rule and non-string form list are ignored: the default rule
    // applies, invalid forms fall back to English, and plain strings resolve.
    $this->assertSame('Senden', Translator::t('Submit'));
    $this->assertSame('1 item selected', Translator::formatPlural(1, '1 item selected', '@count items selected'));
    $this->assertSame('6 items selected', Translator::formatPlural(6, '1 item selected', '@count items selected'));
  }

  public function testBundledCatalogLoadsWithNoSources(): void {
    Translator::setShared(new Translator('uk'));

    // Chrome and the default button labels resolve from the bundled catalog.
    $this->assertSame('Лише файли', Translator::t('Files only'));
    $this->assertSame('Надіслати', Translator::t('Submit'));

    // The plural message renders its one/few/many forms by the bundled rule.
    $this->assertSame('1 елемент вибрано', Translator::formatPlural(1, '1 item selected', '@count items selected'));
    $this->assertSame('4 елементи вибрано', Translator::formatPlural(4, '1 item selected', '@count items selected'));
    $this->assertSame('5 елементів вибрано', Translator::formatPlural(5, '1 item selected', '@count items selected'));
  }

  public function testInlineMapSuppliesPluralFormsAndRule(): void {
    // The inline rule (always the last form) replaces the bundled Ukrainian
    // rule, proving a consumer source overrides the defaults wholesale.
    Translator::setShared(new Translator('uk', [
      [
        'uk' => [
          Translator::PLURAL_RULE => static fn(int $count): int => 2,
          '@count items selected' => ['@count елемент вибрано', '@count елементи вибрано', '@count позицій вибрано'],
        ],
      ],
    ]));

    $this->assertSame('1 позицій вибрано', Translator::formatPlural(1, '1 item selected', '@count items selected'));
    $this->assertSame('7 позицій вибрано', Translator::formatPlural(7, '1 item selected', '@count items selected'));
  }

}
