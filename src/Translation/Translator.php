<?php

declare(strict_types=1);

namespace DrevOps\Tui\Translation;

/**
 * Resolves user-facing strings to a target language, English as the fallback.
 *
 * The English source string is the catalog key: a lookup that misses returns
 * the source unchanged, so an untranslated string always renders in English.
 * A catalog is a per-language `source => translation` map; the array-file
 * format loads under a PHAR without a parser.
 *
 * The package's bundled catalogs always load first, so the shipped chrome
 * translations apply with no configuration. Consumer sources merge on top in
 * order, a later one overriding an earlier one per key - so a single source
 * can carry a form's own strings alone, add overrides for some chrome, or
 * replace the whole chrome. A source is a directory of per-locale catalog
 * files (e.g. "es.php"), a single per-locale catalog file, or an inline
 * locale-keyed map of catalogs.
 *
 * The active language is either empty (translation off), the "auto" sentinel
 * (detected from the environment), or a locale such as "es" or "es_ES". A
 * region locale falls back to its primary subtag, so "es_ES" uses "es.php" when
 * no "es_ES.php" exists.
 *
 * A process-wide instance set with {@see Translator::setShared()} backs the
 * static {@see Translator::t()} entry point, so chrome can translate from
 * anywhere without threading a translator through every constructor.
 *
 * @package DrevOps\Tui\Translation
 */
final class Translator {

  /**
   * The reserved catalog key a catalog supplies its plural rule under.
   *
   * A catalog may return, under this key, a `fn(int $count): int` closure
   * mapping a count to the zero-based index of the plural form to use, letting
   * a language define its own plural boundaries. Absent, a count of one takes
   * the first form and any other count the second.
   */
  public const string PLURAL_RULE = '__plural_rule__';

  /**
   * The process-wide translator backing the t() function.
   */
  protected static ?Translator $shared = NULL;

  /**
   * The merged catalog for the active language, loaded on first use.
   *
   * @var array<string,string>|null
   */
  protected ?array $catalog = NULL;

  /**
   * The plural-form lists for the active language, keyed by plural source.
   *
   * @var array<string,list<string>>
   */
  protected array $plurals = [];

  /**
   * The active language's plural rule, when its catalog supplies one.
   */
  protected ?\Closure $pluralRule = NULL;

  /**
   * Construct a translator.
   *
   * @param string $language
   *   The active language: empty to disable translation (English source),
   *   "auto" to detect from the environment, or a locale such as "es" or
   *   "es_ES".
   * @param list<string|array<string,array<string,string|list<string>|\Closure>>> $sources
   *   Catalog sources merged, in order, on top of the bundled defaults; a
   *   later entry overrides an earlier one per key. Each is a directory of
   *   per-locale catalog files, a single per-locale catalog file, or an
   *   inline locale-keyed map of catalogs.
   */
  public function __construct(
    protected string $language = '',
    protected array $sources = [],
  ) {
  }

  /**
   * Set the process-wide translator backing the t() function.
   *
   * @param \DrevOps\Tui\Translation\Translator|null $translator
   *   The translator, or NULL to disable translation (English source).
   */
  public static function setShared(?Translator $translator): void {
    self::$shared = $translator;
  }

  /**
   * The process-wide translator backing the t() function.
   *
   * @return \DrevOps\Tui\Translation\Translator|null
   *   The translator, or NULL when none is set.
   */
  public static function shared(): ?Translator {
    return self::$shared;
  }

  /**
   * Translate a string through the process-wide translator, English fallback.
   *
   * The entry point for every user-facing string: with a shared translator set
   * it localizes, otherwise it returns the source with its placeholders filled,
   * so a call is always safe and defaults to English.
   *
   * @param string $message
   *   The English source string, used as the catalog key.
   * @param array<string,string|int|float|\Stringable> $args
   *   Replacements for the @name placeholders in the message.
   *
   * @return string
   *   The translated string, or the interpolated source when untranslated.
   */
  public static function t(string $message, array $args = []): string {
    return self::$shared instanceof self ? self::$shared->translate($message, $args) : self::interpolate($message, $args);
  }

  /**
   * Select and localize a singular or plural message by count.
   *
   * The count picks the grammatical form, and @count is bound to it so a
   * message need only carry the placeholder. With a shared translator the
   * active language's catalog supplies both the forms and the rule that maps
   * the count to one of them; without one, a count of one yields the singular
   * source and any other count the plural. Mirrors Drupal's formatPlural().
   *
   * @param int $count
   *   The item count the grammatical form is chosen for.
   * @param string $singular
   *   The English singular source, a catalog key and the one-form fallback.
   * @param string $plural
   *   The English plural source: the key a translation's forms are listed
   *   under, and the fallback form for any count that is not one.
   * @param array<string,string|int|float|\Stringable> $args
   *   Replacements for the @name placeholders; @count is added automatically.
   *
   * @return string
   *   The resolved, interpolated message for the count.
   */
  public static function formatPlural(int $count, string $singular, string $plural, array $args = []): string {
    if (self::$shared instanceof self) {
      return self::$shared->translatePlural($count, $singular, $plural, $args);
    }

    $args['@count'] = $count;

    return self::interpolate($count === 1 ? $singular : $plural, $args);
  }

  /**
   * Translate a source string, substituting its placeholders.
   *
   * @param string $source
   *   The English source string, used as the catalog key.
   * @param array<string,string|int|float|\Stringable> $args
   *   Replacements for the @name placeholders in the resolved string.
   *
   * @return string
   *   The translation, or the interpolated source when it is untranslated.
   */
  public function translate(string $source, array $args = []): string {
    $catalog = $this->catalog();

    return self::interpolate($catalog[$source] ?? $source, $args);
  }

  /**
   * Resolve a count to its localized plural form.
   *
   * A translation lists the forms for the plural source, and the catalog's own
   * rule - or the default one-versus-other when it supplies none - selects
   * among them. Without a translation the two English source forms and the
   * default rule are used, so a language's rule never applies to the English
   * wording and the English singular is used only for a count of one. An index
   * the chosen forms do not cover falls back to the plural source, so a
   * rendering is always defined.
   *
   * @param int $count
   *   The item count the form is chosen for.
   * @param string $singular
   *   The English singular source, the one-form fallback.
   * @param string $plural
   *   The English plural source: the key the catalog's forms are listed under,
   *   and the fallback form.
   * @param array<string,string|int|float|\Stringable> $args
   *   Replacements for the @name placeholders; @count is added automatically.
   *
   * @return string
   *   The localized, interpolated form for the count.
   */
  public function translatePlural(int $count, string $singular, string $plural, array $args = []): string {
    $this->catalog();

    $args['@count'] = $count;

    if (isset($this->plurals[$plural])) {
      $forms = $this->plurals[$plural];
      $rule = $this->pluralRule ?? self::defaultPluralRule();
    }
    else {
      $forms = [$singular, $plural];
      $rule = self::defaultPluralRule();
    }

    return self::interpolate($forms[(int) $rule($count)] ?? $plural, $args);
  }

  /**
   * The fallback plural rule: the first form for one, the second otherwise.
   *
   * @return \Closure(int): int
   *   The rule mapping a count to a zero-based form index.
   */
  protected static function defaultPluralRule(): \Closure {
    return static fn(int $count): int => $count === 1 ? 0 : 1;
  }

  /**
   * Substitute @name placeholders in a message.
   *
   * One substitution routine serves the instance path and the t() fallback
   * path, so a translated and an untranslated string interpolate identically.
   *
   * A placeholder value may itself be an already-translated phrase; the
   * concatenation cannot honour every locale's word order or grammatical
   * agreement, so a language needing those supplies full-sentence catalog
   * keys rather than relying on composition.
   *
   * @param string $message
   *   The message, possibly carrying @name placeholders.
   * @param array<string,string|int|float|\Stringable> $args
   *   The placeholder replacements, keyed by placeholder (e.g. "@count").
   *
   * @return string
   *   The message with its placeholders replaced.
   */
  protected static function interpolate(string $message, array $args = []): string {
    if ($args === []) {
      return $message;
    }

    $map = [];

    foreach ($args as $placeholder => $value) {
      $map[$placeholder] = (string) $value;
    }

    return strtr($message, $map);
  }

  /**
   * Detect the language from the environment's POSIX locale variables.
   *
   * The first set and non-empty LC_ALL, LC_MESSAGES or LANG decides. The
   * encoding and modifier are stripped ("es_ES.UTF-8@euro" becomes "es_ES"),
   * and a "C" or "POSIX" locale means no translation.
   *
   * @return string
   *   The detected locale (e.g. "es_ES"), or an empty string when none applies.
   */
  public static function detectLanguage(): string {
    foreach (['LC_ALL', 'LC_MESSAGES', 'LANG'] as $var) {
      $value = getenv($var);

      if (!is_string($value)) {
        continue;
      }

      if ($value === '') {
        continue;
      }

      $locale = (string) preg_replace('/[.@].*$/', '', $value);

      return $locale === 'C' || $locale === 'POSIX' ? '' : $locale;
    }

    return '';
  }

  /**
   * The merged catalog for the active language, loaded once.
   *
   * @return array<string,string>
   *   The source => translation map (empty when translation is off).
   */
  protected function catalog(): array {
    if ($this->catalog !== NULL) {
      return $this->catalog;
    }

    $language = $this->language === 'auto' ? self::detectLanguage() : $this->language;

    $this->catalog = $language === '' ? [] : $this->load($language);

    return $this->catalog;
  }

  /**
   * Load and merge the catalog for a language across the sources.
   *
   * @param string $language
   *   The effective language (a locale such as "es" or "es_ES").
   *
   * @return array<string,string>
   *   The merged source => translation map.
   */
  protected function load(string $language): array {
    $candidates = self::candidates($language);
    $catalog = [];

    // The bundled defaults are the implicit first source, so any consumer
    // source overrides them. A missing bundled directory (a trimmed archive)
    // degrades to English rather than erroring on every construction.
    $bundled = self::bundledDirectory();
    $sources = is_dir($bundled) ? [$bundled, ...$this->sources] : $this->sources;

    foreach ($sources as $source) {
      $catalog = array_merge($catalog, $this->loadSource($source, $candidates));
    }

    return $catalog;
  }

  /**
   * The catalog entries one source contributes for the candidate locales.
   *
   * A directory is searched for a per-locale catalog file by candidate order;
   * the first candidate present wins, so a region catalog is not merged on
   * top of its primary-subtag catalog within one source. A file is a
   * per-locale catalog loaded only when its filename names a candidate, so a
   * file for another language contributes nothing. An array is a locale-keyed
   * map of catalogs, resolved by the same candidate order.
   *
   * @param string|array<string,array<string,string|list<string>|\Closure>> $source
   *   The source: a directory path, a catalog file path, or a locale-keyed
   *   map of catalogs.
   * @param list<string> $candidates
   *   The candidate locales, most specific first.
   *
   * @return array<string,string>
   *   The source => translation entries the source contributes.
   */
  protected function loadSource(string|array $source, array $candidates): array {
    if (is_array($source)) {
      foreach ($candidates as $candidate) {
        $section = $source[$candidate] ?? NULL;

        if (is_array($section)) {
          return $this->absorb($section);
        }
      }

      return [];
    }

    if (is_dir($source)) {
      foreach ($candidates as $candidate) {
        $file = rtrim($source, '/') . '/' . $candidate . '.php';

        if (is_file($file)) {
          return $this->readCatalog($file);
        }
      }

      return [];
    }

    if (is_file($source)) {
      return in_array(pathinfo($source, PATHINFO_FILENAME), $candidates, TRUE) ? $this->readCatalog($source) : [];
    }

    throw new \InvalidArgumentException(sprintf('The translation source "%s" is neither a directory nor a catalog file.', $source));
  }

  /**
   * The directory the package's default catalogs ship in.
   *
   * @return string
   *   The bundled catalog directory path.
   */
  protected static function bundledDirectory(): string {
    return dirname(__DIR__, 2) . '/translations';
  }

  /**
   * Read a catalog file and absorb its entries.
   *
   * @param string $file
   *   The catalog file path.
   *
   * @return array<string,string>
   *   The source => translation map.
   */
  protected function readCatalog(string $file): array {
    $data = require $file;

    return $this->absorb(is_array($data) ? $data : []);
  }

  /**
   * Absorb catalog data into the string map, plural forms, and plural rule.
   *
   * Returns the string => string pairs; the plural-form lists and a plural-rule
   * closure are read as side effects into $this->plurals and $this->pluralRule,
   * so the caller merges only the returned string map. One normalisation
   * routine serves file catalogs and inline maps, so both shapes honour the
   * same entry kinds.
   *
   * @param array<mixed> $data
   *   The catalog data: translations, plural-form lists, and the plural rule.
   *
   * @return array<string,string>
   *   The source => translation map.
   */
  protected function absorb(array $data): array {
    $catalog = [];

    foreach ($data as $key => $value) {
      if ($key === self::PLURAL_RULE) {
        if ($value instanceof \Closure) {
          $this->pluralRule = $value;
        }

        continue;
      }

      if (!is_string($key)) {
        continue;
      }

      if (is_string($value)) {
        $catalog[$key] = $value;

        continue;
      }

      if (is_array($value) && $this->isFormList($value)) {
        $this->plurals[$key] = $value;
      }
    }

    return $catalog;
  }

  /**
   * Whether a value is a non-empty list of strings - a set of plural forms.
   *
   * @param array<mixed> $value
   *   The value to test.
   *
   * @return bool
   *   TRUE when the keys are a zero-based list and every element is a string.
   *
   * @phpstan-assert-if-true list<string> $value
   */
  protected function isFormList(array $value): bool {
    if ($value === [] || !array_is_list($value)) {
      return FALSE;
    }

    foreach ($value as $form) {
      if (!is_string($form)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * The catalog filenames to try for a locale, most specific first.
   *
   * A candidate becomes part of a `require`d file path, so anything that is not
   * a plain locale identifier is dropped: this keeps a path separator or a
   * traversal segment out of the filename and leaves such a locale with no
   * catalog (English).
   *
   * @param string $language
   *   The locale (e.g. "es" or "es_ES"; a hyphen is normalized to underscore).
   *
   * @return list<string>
   *   The valid candidate language codes, e.g. ["es_ES", "es"] or ["es"].
   */
  protected static function candidates(string $language): array {
    $normalized = str_replace('-', '_', $language);
    $primary = explode('_', $normalized)[0];

    return array_values(array_filter(array_unique([$normalized, $primary]), static fn(string $candidate): bool => preg_match('/^\w+$/', $candidate) === 1));
  }

}
