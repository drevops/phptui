<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Translation;

use DrevOps\Tui\Block\Buttons;
use DrevOps\Tui\Translation\Translator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Guards the bundled catalogs against drift from the source and each other.
 *
 * Every literal chrome string the library emits - a `Translator::t('...')`
 * call, both forms of a `Translator::formatPlural(..., '...', '...')` call, or
 * a `new Hint('...')` label - must have a matching template key, and the
 * template must carry no orphan key, so the canonical list of translatable
 * chrome stays complete and honest.
 *
 * Every locale catalog shipped beside the template must then carry exactly the
 * template's keys: a key it lacks renders in English in the middle of a
 * translated screen, and a key the template has dropped is dead weight nobody
 * will ever remove.
 */
#[CoversNothing]
#[Group('translation')]
final class ChromeCatalogTest extends TestCase {

  public function testTemplateMatchesTheStringsTheSourceEmits(): void {
    $root = dirname(__DIR__, 4);

    $catalog = require $root . '/translations/en.php';
    $this->assertIsArray($catalog);
    $template = array_keys($catalog);
    sort($template);

    $emitted = array_values(array_unique([...$this->emitted($root . '/src')['keys'], ...$this->computed()]));
    sort($emitted);

    $this->assertSame($template, $emitted, 'translations/en.php is out of sync with the chrome strings src/ emits. Regenerate it.');

    // The template is a self-describing English catalog: value equals key.
    foreach ($catalog as $key => $value) {
      $this->assertSame($key, $value);
    }
  }

  public function testLocaleCatalogsCarryExactlyTheTemplatesKeys(): void {
    $root = dirname(__DIR__, 4);

    $template = require $root . '/translations/en.php';
    $this->assertIsArray($template);
    $expected = array_keys($template);
    sort($expected);

    $locales = $this->localeCatalogs($root . '/translations');
    $this->assertNotSame([], $locales, 'No locale catalog ships beside the template, so nothing proves the template is translatable.');

    $plurals = $this->emitted($root . '/src')['plurals'];

    foreach ($locales as $language => $catalog) {
      $rule = $catalog[Translator::PLURAL_RULE] ?? NULL;
      unset($catalog[Translator::PLURAL_RULE]);

      $keys = array_keys($catalog);
      sort($keys);

      $this->assertSame($expected, $keys, sprintf('translations/%s.php does not carry exactly the keys of translations/en.php.', $language));

      foreach ($catalog as $key => $value) {
        $this->assertTranslation($language, (string) $key, $value, $rule instanceof \Closure ? $rule : NULL);
      }

      foreach ($plurals as $source) {
        $this->assertIsArray($catalog[$source] ?? NULL, sprintf('translations/%s.php does not give the count phrase "%s" a list of forms, so every count would fall back to English.', $language, $source));
      }
    }
  }

  /**
   * Assert one catalog entry is a usable translation of its source key.
   *
   * @param string $language
   *   The catalog's language, for the failure message.
   * @param string $key
   *   The English source string the entry translates.
   * @param mixed $value
   *   The entry: a translation, or the grammatical forms of a count phrase.
   * @param \Closure|null $rule
   *   The catalog's plural rule, when it supplies one.
   */
  protected function assertTranslation(string $language, string $key, mixed $value, ?\Closure $rule): void {
    $forms = is_array($value) ? $value : [$value];

    foreach ($forms as $form) {
      $this->assertIsString($form, sprintf('translations/%s.php translates "%s" as something other than a string.', $language, $key));
      $this->assertNotSame('', trim($form), sprintf('translations/%s.php leaves "%s" untranslated.', $language, $key));

      // A dropped placeholder renders the name of a value instead of the value
      // itself, which no reader can recover from.
      foreach ($this->placeholders($key) as $placeholder) {
        $this->assertStringContainsString($placeholder, $form, sprintf('translations/%s.php drops the "%s" placeholder from "%s".', $language, $placeholder, $key));
      }
    }

    if (!is_array($value) || !$rule instanceof \Closure) {
      return;
    }

    // Forms are read by position, so a list keyed any other way is skipped
    // whole and the phrase renders in English at every count.
    $this->assertTrue(array_is_list($value), sprintf('translations/%s.php keys the forms of "%s" itself, so the library cannot read them.', $language, $key));

    $furthest = 0;

    // Past a hundred the rule repeats the boundaries it has already crossed, so
    // this reaches every form it can ask for.
    for ($count = 0; $count <= 200; $count++) {
      $furthest = max($furthest, (int) $rule($count));
    }

    // A form the rule asks for but the list does not hold falls back to the
    // English plural, so the language reaches through its own translation.
    $this->assertArrayHasKey($furthest, $value, sprintf('translations/%s.php gives "%s" fewer forms than its own plural rule asks for.', $language, $key));
  }

  /**
   * The locale catalogs shipped beside the template, keyed by language.
   *
   * @param string $directory
   *   The bundled catalog directory.
   *
   * @return array<string,array<mixed>>
   *   The loaded catalogs, the English template excluded.
   */
  protected function localeCatalogs(string $directory): array {
    $catalogs = [];

    foreach ((array) glob($directory . '/*.php') as $file) {
      $language = pathinfo((string) $file, PATHINFO_FILENAME);

      if ($language === 'en') {
        continue;
      }

      $catalog = require $file;
      $this->assertIsArray($catalog);
      $catalogs[$language] = $catalog;
    }

    return $catalogs;
  }

  /**
   * The @name placeholders a message carries.
   *
   * @param string $message
   *   The message.
   *
   * @return list<string>
   *   The placeholders, e.g. ["@min", "@max"].
   */
  protected function placeholders(string $message): array {
    preg_match_all('/@\w+/', $message, $matches);

    return array_values(array_unique($matches[0]));
  }

  /**
   * The chrome keys that reach the translator as a value, not as a literal.
   *
   * A scanner reads what the source spells out, so a string computed on the
   * way to the translator is invisible to it. Each one is derived here from
   * the same place the library derives it, so a change to either side is
   * caught rather than hard-coded twice.
   *
   * @return list<string>
   *   The month names a calendar heading formats, and the button labels a form
   *   ends on unless it renames them.
   */
  protected function computed(): array {
    $months = [];

    for ($month = 1; $month <= 12; $month++) {
      $months[] = (new \DateTimeImmutable(sprintf('2000-%02d-01', $month)))->format('F');
    }

    $buttons = new Buttons();

    return [...$months, $buttons->submitLabel, $buttons->cancelLabel];
  }

  /**
   * What the source under a directory emits, by the shape it emits it in.
   *
   * @param string $directory
   *   The directory to scan.
   *
   * @return array{keys:list<string>,plurals:list<string>}
   *   Every distinct literal chrome key, and the count-phrase sources among
   *   them - the keys a translation hangs its grammatical forms from.
   */
  protected function emitted(string $directory): array {
    $keys = [];
    $plurals = [];

    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
    foreach ($iterator as $entry) {
      if (!$entry instanceof \SplFileInfo) {
        continue;
      }
      if ($entry->getExtension() !== 'php') {
        continue;
      }
      $tokens = token_get_all((string) file_get_contents($entry->getPathname()));
      $count = count($tokens);
      for ($i = 0; $i < $count; $i++) {
        foreach ($this->literalKeys($tokens, $i) as $key) {
          $keys[$key] = TRUE;
        }

        foreach ($this->pluralSource($tokens, $i) as $key) {
          $plurals[$key] = TRUE;
        }
      }
    }

    return ['keys' => array_keys($keys), 'plurals' => array_keys($plurals)];
  }

  /**
   * The literal chrome keys a token sequence introduces at an index.
   *
   * @param array<int,array{int,string,int}|string> $tokens
   *   The token stream.
   * @param int $index
   *   The index to test.
   *
   * @return list<string>
   *   The literal argument(s) of a Translator::t() call (one), a
   *   Translator::formatPlural() call (its singular and plural), or a Hint
   *   construction (one) at this index; empty when it is none of these.
   */
  protected function literalKeys(array $tokens, int $index): array {
    $token = $tokens[$index];
    if (!is_array($token) || $token[0] !== T_STRING) {
      return [];
    }

    if ($token[1] === 'Translator' && $this->isStaticCall($tokens, $index, 't')
      && is_array($tokens[$index + 4] ?? NULL) && $tokens[$index + 4][0] === T_CONSTANT_ENCAPSED_STRING) {
      return [$this->literalValue($tokens[$index + 4][1])];
    }

    if ($token[1] === 'Translator' && $this->isStaticCall($tokens, $index, 'formatPlural')) {
      return $this->argumentStrings($tokens, $index + 3, 2);
    }

    if ($token[1] === 'Hint'
      && ($tokens[$index + 1] ?? NULL) === '('
      && is_array($tokens[$index + 2] ?? NULL) && $tokens[$index + 2][0] === T_CONSTANT_ENCAPSED_STRING
      && $this->precededByNew($tokens, $index)) {
      return [$this->literalValue($tokens[$index + 2][1])];
    }

    return [];
  }

  /**
   * The count-phrase source a token sequence introduces at an index.
   *
   * @param array<int,array{int,string,int}|string> $tokens
   *   The token stream.
   * @param int $index
   *   The index to test.
   *
   * @return list<string>
   *   The plural argument of a Translator::formatPlural() call at this index -
   *   the key its forms hang from; empty when this is not such a call.
   */
  protected function pluralSource(array $tokens, int $index): array {
    $token = $tokens[$index];

    if (!is_array($token) || $token[0] !== T_STRING || $token[1] !== 'Translator') {
      return [];
    }

    if (!$this->isStaticCall($tokens, $index, 'formatPlural')) {
      return [];
    }

    return array_slice($this->argumentStrings($tokens, $index + 3, 2), 1, 1);
  }

  /**
   * Whether a `Class::method(` static call opens at an index.
   *
   * @param array<int,array{int,string,int}|string> $tokens
   *   The token stream.
   * @param int $index
   *   The index of the class-name token.
   * @param string $method
   *   The method name to match.
   *
   * @return bool
   *   TRUE when the tokens read `<class> :: <method> (`.
   */
  protected function isStaticCall(array $tokens, int $index, string $method): bool {
    return is_array($tokens[$index + 1] ?? NULL) && $tokens[$index + 1][0] === T_DOUBLE_COLON
      && is_array($tokens[$index + 2] ?? NULL) && $tokens[$index + 2][1] === $method
      && ($tokens[$index + 3] ?? NULL) === '(';
  }

  /**
   * The first N string-literal arguments passed at a call's opening paren.
   *
   * Tracks parenthesis depth from the opening paren so a nested call in an
   * earlier argument (such as `count($value)`) is skipped and only the call's
   * own direct string arguments are collected.
   *
   * @param array<int,array{int,string,int}|string> $tokens
   *   The token stream.
   * @param int $open
   *   The index of the call's opening parenthesis.
   * @param int $max
   *   The most arguments to collect.
   *
   * @return list<string>
   *   The collected literal strings, in call order.
   */
  protected function argumentStrings(array $tokens, int $open, int $max): array {
    $depth = 0;
    $strings = [];
    $count = count($tokens);

    for ($i = $open; $i < $count; $i++) {
      $token = $tokens[$i];

      if ($token === '(') {
        $depth++;

        continue;
      }

      if ($token === ')') {
        $depth--;

        if ($depth === 0) {
          break;
        }

        continue;
      }

      if ($depth === 1 && is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
        $strings[] = $this->literalValue($token[1]);

        if (count($strings) >= $max) {
          break;
        }
      }
    }

    return $strings;
  }

  /**
   * Whether the token before an index is the `new` keyword.
   *
   * @param array<int,array{int,string,int}|string> $tokens
   *   The token stream.
   * @param int $index
   *   The index whose predecessor is tested.
   *
   * @return bool
   *   TRUE when the nearest prior significant token is T_NEW.
   */
  protected function precededByNew(array $tokens, int $index): bool {
    for ($i = $index - 1; $i >= 0; $i--) {
      if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], TRUE)) {
        continue;
      }

      return is_array($tokens[$i]) && $tokens[$i][0] === T_NEW;
    }

    return FALSE;
  }

  /**
   * The runtime value of a single-quoted PHP string literal token.
   *
   * @param string $token
   *   The token text, including its surrounding quotes.
   *
   * @return string
   *   The unquoted, unescaped string.
   */
  protected function literalValue(string $token): string {
    return stripcslashes(substr($token, 1, -1));
  }

}
