<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Translation;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Guards translations/en.php against drift from the source.
 *
 * Every literal chrome string the library emits - a `Translator::t('...')`
 * call, both forms of a `Translator::formatPlural(..., '...', '...')` call, or
 * a `new Hint('...')` label - must have a matching template key, and the
 * template must carry no orphan key, so the canonical list of translatable
 * chrome stays complete and honest.
 */
#[CoversNothing]
#[Group('translation')]
final class ChromeCatalogTest extends TestCase {

  public function testTemplateMatchesSourceLiterals(): void {
    $root = dirname(__DIR__, 4);

    $catalog = require $root . '/translations/en.php';
    $this->assertIsArray($catalog);
    $template = array_keys($catalog);
    sort($template);

    $literals = $this->literals($root . '/src');
    sort($literals);

    $this->assertSame($template, $literals, 'translations/en.php is out of sync with the chrome literals in src/. Regenerate it.');

    // The template is a self-describing English catalog: value equals key.
    foreach ($catalog as $key => $value) {
      $this->assertSame($key, $value);
    }
  }

  /**
   * The distinct literal chrome keys emitted across a directory.
   *
   * @param string $directory
   *   The directory to scan.
   *
   * @return list<string>
   *   The unique literal keys from Translator::t() calls and Hint labels.
   */
  protected function literals(string $directory): array {
    $keys = [];

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
      }
    }

    return array_keys($keys);
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
