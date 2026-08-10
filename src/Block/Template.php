<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\FormException;
use DrevOps\Tui\Terminal\Ansi;
use DrevOps\Tui\Translation\Translator;

/**
 * A fixed string shape with named `{{placeholder}}` slots to fill in.
 *
 * The shape is held as its fixed chunks and its slot names side by side:
 * `$literals` carries one more entry than `$placeholders`, and concatenating
 * them alternately - literal, placeholder, literal, ... - reassembles the
 * declared pattern. Filling the slots ({@see assemble()}) and recovering them
 * from a filled string ({@see extract()}) are therefore exact inverses.
 *
 * @package DrevOps\Tui\Block
 */
final class Template {

  /**
   * The fixed text around the slots, one chunk more than there are slots.
   *
   * @var list<string>
   */
  protected array $literals = [];

  /**
   * The slot names, in the order they appear in the pattern.
   *
   * @var list<string>
   */
  protected array $placeholders = [];

  /**
   * The declared pattern, carrying `{{name}}` slots.
   */
  protected string $pattern;

  /**
   * Construct a template.
   *
   * @param string $pattern
   *   The pattern carrying `{{name}}` slots, with optional inner whitespace -
   *   the token syntax the library uses everywhere else.
   * @param array<string,string> $labels
   *   The human label of each slot, keyed by slot name; an undeclared slot
   *   reads as its own name.
   * @param array<string,\Closure> $validators
   *   The validator `fn (string $value): ?string` of each slot, keyed by slot
   *   name, returning an error message or NULL when the part is valid.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the pattern declares no slot, repeats a name or places two slots
   *   side by side, or when a label or validator names an absent slot.
   */
  public function __construct(
    string $pattern,
    protected array $labels = [],
    protected array $validators = [],
  ) {
    // The pattern is filtered before it is split, so the fixed chunks it draws,
    // the expression it matches with and the string it assembles all describe
    // the same shape.
    $this->pattern = Ansi::sanitize($pattern);
    $this->labels = array_map(Ansi::sanitize(...), $this->labels);
    $this->split($this->pattern);
    $this->assertShape();
    $this->assertDeclared(array_keys($this->labels), 'label');
    $this->assertDeclared(array_keys($this->validators), 'validator');
  }

  /**
   * The declared pattern.
   *
   * @return string
   *   The pattern.
   */
  public function pattern(): string {
    return $this->pattern;
  }

  /**
   * The slot names, in the order they appear in the pattern.
   *
   * @return list<string>
   *   The names.
   */
  public function placeholders(): array {
    return $this->placeholders;
  }

  /**
   * The fixed chunk at a position, from before the first slot to past the last.
   *
   * @param int $index
   *   The position: 0 is the text before the first slot, and the slot count is
   *   the text after the last.
   *
   * @return string
   *   The fixed text; empty when the position holds none.
   */
  public function literalAt(int $index): string {
    return $this->literals[$index] ?? '';
  }

  /**
   * The human label of a slot.
   *
   * @param string $name
   *   The slot name.
   *
   * @return string
   *   The declared label, translated; the name itself when none was declared.
   */
  public function labelOf(string $name): string {
    return isset($this->labels[$name]) ? Translator::t($this->labels[$name]) : $name;
  }

  /**
   * The validator of a slot.
   *
   * @param string $name
   *   The slot name.
   *
   * @return \Closure|null
   *   The validator, or NULL when the slot is unvalidated.
   */
  public function validatorOf(string $name): ?\Closure {
    return $this->validators[$name] ?? NULL;
  }

  /**
   * Fill the slots, returning the assembled string.
   *
   * @param array<string,string> $parts
   *   The value of each slot, keyed by slot name; a missing slot fills empty.
   *
   * @return string
   *   The assembled string.
   */
  public function assemble(array $parts): string {
    $out = '';

    foreach ($this->placeholders as $index => $name) {
      $out .= $this->literals[$index] . ($parts[$name] ?? '');
    }

    return $out . $this->literals[count($this->placeholders)];
  }

  /**
   * Recover the slot values from an assembled string.
   *
   * Each slot takes the shortest run of characters that still lets the rest of
   * the pattern match, so a slot ends at the first following fixed chunk.
   *
   * @param string $value
   *   The assembled string.
   *
   * @return array<string,string>
   *   The value of each slot keyed by slot name, or an empty array when the
   *   string does not have the template's shape.
   */
  public function extract(string $value): array {
    if (preg_match($this->regex(), $value, $matches) !== 1) {
      return [];
    }

    $parts = [];

    foreach ($this->placeholders as $index => $name) {
      $parts[$name] = $matches[$index + 1];
    }

    return $parts;
  }

  /**
   * The first slot whose value would not survive assembly, if any.
   *
   * A slot ends at the first following fixed chunk, so a value carrying that
   * chunk inside it moves the boundary: the assembled string still has the
   * shape, but decomposes into different parts than it was built from. Only
   * assembly can hit this - a string that already matches always decomposes
   * back to itself.
   *
   * @param array<string,string> $parts
   *   The value of each slot, keyed by slot name.
   *
   * @return string|null
   *   The name of the first slot that would be misread, or NULL when the
   *   values assemble and decompose as the same set.
   */
  public function ambiguousSlot(array $parts): ?string {
    $roundtrip = $this->extract($this->assemble($parts));

    foreach ($this->placeholders as $placeholder) {
      if (($roundtrip[$placeholder] ?? '') !== ($parts[$placeholder] ?? '')) {
        return $placeholder;
      }
    }

    return NULL;
  }

  /**
   * Whether a string has the template's shape.
   *
   * @param string $value
   *   The string to test.
   *
   * @return bool
   *   TRUE when the string matches.
   */
  public function matches(string $value): bool {
    return preg_match($this->regex(), $value) === 1;
  }

  /**
   * The anchored expression an assembled string must match.
   *
   * The slots are captured positionally rather than by name: a slot name is any
   * word, and one starting with a digit is not a legal capture-group name. The
   * end anchor is absolute (`D`): left to its default it would also match
   * before a trailing newline, which would drop that newline from the last
   * slot and stop the assembled string round-tripping.
   *
   * @return string
   *   The expression.
   */
  public function regex(): string {
    return '/^' . $this->expression(static fn(string $literal): string => preg_quote($literal, '/')) . '$/usD';
  }

  /**
   * The same expression as a bare, delimiter-free pattern.
   *
   * The form a JSON Schema `pattern` keyword takes, so a consumer of the agent
   * schema can check an assembled answer before sending it.
   *
   * @return string
   *   The anchored expression, without delimiters or modifiers.
   */
  public function schemaPattern(): string {
    return '^' . $this->expression(static fn(string $literal): string => self::quoteEcma($literal)) . '$';
  }

  /**
   * The body of the expression an assembled string must match.
   *
   * @param \Closure $quote
   *   The `fn (string $literal): string` escaping the fixed text for the
   *   flavour of expression being built.
   *
   * @return string
   *   The expression body, unanchored.
   */
  protected function expression(\Closure $quote): string {
    $out = '';

    // The fixed chunks are separated by exactly the slots, so a capture group
    // goes before every chunk but the first.
    foreach ($this->literals as $index => $literal) {
      $out .= ($index === 0 ? '' : '(.*?)') . $quote($literal);
    }

    return $out;
  }

  /**
   * Escape the characters an ECMAScript expression treats as special.
   *
   * Narrower than {@see preg_quote()}, which also escapes characters that carry
   * no meaning in ECMAScript - and whose escapes a strict engine rejects.
   *
   * @param string $text
   *   The text to escape.
   *
   * @return string
   *   The escaped text.
   */
  protected static function quoteEcma(string $text): string {
    return (string) preg_replace('/[\\\\^$.|?*+()\[\]{}]/', '\\\\$0', $text);
  }

  /**
   * The first reason an assembled string is not a valid answer, if any.
   *
   * @param string $value
   *   The assembled string.
   *
   * @return string|null
   *   The error message, or NULL when the string has the template's shape and
   *   every slot passes its validator.
   */
  public function error(string $value): ?string {
    $parts = $this->extract($value);

    if ($parts === []) {
      return Translator::t('"@value" does not match the template "@pattern".', [
        '@value' => $value,
        '@pattern' => $this->pattern,
      ]);
    }

    foreach ($parts as $name => $part) {
      $error = $this->partError($name, $part);
      if ($error !== NULL) {
        return $error;
      }
    }

    return NULL;
  }

  /**
   * The reason one slot's value is invalid, if any.
   *
   * @param string $name
   *   The slot name.
   * @param string $value
   *   The slot's value.
   *
   * @return string|null
   *   The error message, framed with the slot's label, or NULL when the value
   *   is valid or the slot is unvalidated.
   */
  public function partError(string $name, string $value): ?string {
    $validator = $this->validatorOf($name);
    $error = $validator instanceof \Closure ? $validator($value) : NULL;

    if (!is_string($error) || $error === '') {
      return NULL;
    }

    return Translator::t('@label: @error', [
      '@label' => $this->labelOf($name),
      '@error' => $error,
    ]);
  }

  /**
   * Split a pattern into its fixed chunks and its slot names.
   *
   * @param string $pattern
   *   The pattern.
   */
  protected function split(string $pattern): void {
    $parts = preg_split('/\{\{\s*(\w+)\s*\}\}/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);

    foreach ($parts === FALSE ? [$pattern] : $parts as $index => $part) {
      if ($index % 2 === 0) {
        $this->literals[] = $part;
      }
      else {
        $this->placeholders[] = $part;
      }
    }
  }

  /**
   * Reject a pattern that cannot be filled in or read back unambiguously.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the pattern declares no slot, repeats a name, or places two slots
   *   side by side.
   */
  protected function assertShape(): void {
    if ($this->placeholders === []) {
      throw new FormException(sprintf('Template "%s" declares no {{placeholder}} to fill in.', $this->pattern));
    }

    $duplicates = array_diff_assoc($this->placeholders, array_unique($this->placeholders));
    if ($duplicates !== []) {
      throw new FormException(sprintf('Template "%s" repeats the placeholder "%s"; every placeholder is filled in separately, so each name must be unique.', $this->pattern, reset($duplicates)));
    }

    // A slot ends where the next fixed chunk begins, so two adjacent slots
    // could not be told apart when reading a filled string back.
    foreach ($this->placeholders as $index => $name) {
      if ($index > 0 && $this->literals[$index] === '') {
        throw new FormException(sprintf('Template "%s" places "%s" directly after "%s"; separate two placeholders with some fixed text.', $this->pattern, $name, $this->placeholders[$index - 1]));
      }
    }
  }

  /**
   * Reject configuration attached to a slot the pattern does not declare.
   *
   * @param list<string> $names
   *   The configured slot names.
   * @param string $kind
   *   The kind of configuration, named in the error message.
   *
   * @throws \DrevOps\Tui\FormException
   *   When a name is not one of the pattern's slots.
   */
  protected function assertDeclared(array $names, string $kind): void {
    foreach ($names as $name) {
      if (!in_array($name, $this->placeholders, TRUE)) {
        throw new FormException(sprintf('Template "%s" has a %s for "%s", which it does not declare; its placeholders are: %s.', $this->pattern, $kind, $name, implode(', ', $this->placeholders)));
      }
    }
  }

}
