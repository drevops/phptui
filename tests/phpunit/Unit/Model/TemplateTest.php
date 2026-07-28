<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Model;

use DrevOps\Tui\Model\FormException;
use DrevOps\Tui\Model\Template;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the template value object.
 */
#[CoversClass(Template::class)]
#[Group('model')]
final class TemplateTest extends TestCase {

  public function testKeepsThePattern(): void {
    $template = new Template('{{a}}-{{b}}');

    $this->assertSame('{{a}}-{{b}}', $template->pattern());
  }

  #[DataProvider('dataProviderPlaceholders')]
  public function testPlaceholders(string $pattern, array $expected): void {
    $this->assertSame($expected, (new Template($pattern))->placeholders());
  }

  public static function dataProviderPlaceholders(): \Iterator {
    yield 'single' => ['{{only}}', ['only']];
    yield 'two' => ['{{a}}-{{b}}', ['a', 'b']];
    yield 'three' => ['{{a}}/{{b}}:{{c}}', ['a', 'b', 'c']];
    yield 'inner whitespace' => ['{{ a }}-{{  b  }}', ['a', 'b']];
    yield 'surrounded by fixed text' => ['crate {{id}} ready', ['id']];
    yield 'digits and underscores' => ['{{part_1}}-{{part_2}}', ['part_1', 'part_2']];
  }

  #[DataProvider('dataProviderLiteralAt')]
  public function testLiteralAt(string $pattern, int $index, string $expected): void {
    $this->assertSame($expected, (new Template($pattern))->literalAt($index));
  }

  public static function dataProviderLiteralAt(): \Iterator {
    yield 'leading' => ['crate {{id}} ready', 0, 'crate '];
    yield 'trailing' => ['crate {{id}} ready', 1, ' ready'];
    yield 'empty leading' => ['{{a}}-{{b}}', 0, ''];
    yield 'between' => ['{{a}}-{{b}}', 1, '-'];
    yield 'empty trailing' => ['{{a}}-{{b}}', 2, ''];
    yield 'beyond the end' => ['{{a}}', 9, ''];
  }

  #[DataProvider('dataProviderAssemble')]
  public function testAssemble(string $pattern, array $parts, string $expected): void {
    $this->assertSame($expected, (new Template($pattern))->assemble($parts));
  }

  public static function dataProviderAssemble(): \Iterator {
    yield 'every slot filled' => ['{{a}}-{{b}}', ['a' => 'one', 'b' => 'two'], 'one-two'];
    yield 'a missing slot fills empty' => ['{{a}}-{{b}}', ['a' => 'one'], 'one-'];
    yield 'no slot filled' => ['{{a}}-{{b}}', [], '-'];
    yield 'unknown parts ignored' => ['{{a}}-{{b}}', ['a' => 'x', 'b' => 'y', 'c' => 'z'], 'x-y'];
    yield 'fixed text kept' => ['crate {{id}} ready', ['id' => '42'], 'crate 42 ready'];
    yield 'multibyte' => ['{{a}}-{{b}}', ['a' => 'ééé', 'b' => 'ü'], 'ééé-ü'];
  }

  #[DataProvider('dataProviderExtract')]
  public function testExtract(string $pattern, string $value, array $expected): void {
    $this->assertSame($expected, (new Template($pattern))->extract($value));
  }

  public static function dataProviderExtract(): \Iterator {
    yield 'round trip' => ['{{a}}-{{b}}', 'one-two', ['a' => 'one', 'b' => 'two']];
    yield 'empty parts' => ['{{a}}-{{b}}', '-', ['a' => '', 'b' => '']];
    yield 'fixed text stripped' => ['crate {{id}} ready', 'crate 42 ready', ['id' => '42']];
    yield 'multibyte' => ['{{a}}-{{b}}', 'ééé-ü', ['a' => 'ééé', 'b' => 'ü']];
    // A slot ends at the first following fixed chunk, so a repeated separator
    // lands in the last slot rather than the first.
    yield 'repeated separator' => ['{{a}}-{{b}}', 'one-two-three', ['a' => 'one', 'b' => 'two-three']];
    yield 'ending in a slot' => ['crate-{{id}}', 'crate-42', ['id' => '42']];
    yield 'starting with a slot' => ['{{id}}-crate', '42-crate', ['id' => '42']];
    yield 'regex characters are literal' => ['{{a}}.{{b}}', 'oneXtwo', []];
    yield 'shape mismatch' => ['{{a}}-{{b}}', 'nope', []];
    yield 'missing trailing text' => ['crate {{id}} ready', 'crate 42', []];
    yield 'empty value' => ['{{a}}-{{b}}', '', []];
  }

  #[DataProvider('dataProviderMatches')]
  public function testMatches(string $pattern, string $value, bool $expected): void {
    $this->assertSame($expected, (new Template($pattern))->matches($value));
  }

  public static function dataProviderMatches(): \Iterator {
    yield 'matching' => ['{{a}}-{{b}}', 'one-two', TRUE];
    yield 'empty parts still match' => ['{{a}}-{{b}}', '-', TRUE];
    yield 'not matching' => ['{{a}}-{{b}}', 'nope', FALSE];
    // A newline is part of a slot's value, not a reason to stop matching.
    yield 'newline inside a slot' => ['{{a}}-{{b}}', "one\nx-two", TRUE];
    yield 'trailing newline' => ['{{a}}-{{b}}', "one-two\n", TRUE];
  }

  #[DataProvider('dataProviderRoundTrip')]
  public function testAssembleAndExtractAreInverses(string $pattern, string $value): void {
    $template = new Template($pattern);

    // Whatever a matching string decomposes into must assemble back to it, so
    // the answer and the parts read off it can never disagree.
    $this->assertSame($value, $template->assemble($template->extract($value)));
  }

  public static function dataProviderRoundTrip(): \Iterator {
    yield 'plain' => ['{{a}}-{{b}}', 'one-two'];
    yield 'empty parts' => ['{{a}}-{{b}}', '-'];
    yield 'repeated separator' => ['{{a}}-{{b}}', 'one-two-three'];
    yield 'fixed text' => ['crate {{id}} ready', 'crate 42 ready'];
    yield 'multibyte' => ['{{a}}-{{b}}', 'ééé-ü'];
    // The end anchor is absolute, so a trailing newline stays in the last slot
    // instead of being silently dropped.
    yield 'trailing newline' => ['{{a}}-{{b}}', "one-two\n"];
    yield 'newline inside a slot' => ['{{a}}-{{b}}', "one\nx-two"];
  }

  public function testLabelFallsBackToTheSlotName(): void {
    $template = new Template('{{a}}-{{b}}', ['a' => 'Alpha']);

    $this->assertSame('Alpha', $template->labelOf('a'));
    $this->assertSame('b', $template->labelOf('b'));
    $this->assertSame('absent', $template->labelOf('absent'));
  }

  public function testValidatorIsReturnedPerSlot(): void {
    $validator = static fn(string $value): ?string => NULL;
    $template = new Template('{{a}}-{{b}}', validators: ['a' => $validator]);

    $this->assertSame($validator, $template->validatorOf('a'));
    $this->assertNotInstanceOf(\Closure::class, $template->validatorOf('b'));
  }

  #[DataProvider('dataProviderPartError')]
  public function testPartError(string $value, ?string $expected): void {
    $template = new Template('{{a}}-{{b}}', ['a' => 'Alpha'], [
      'a' => static fn(string $part): ?string => $part === 'good' ? NULL : 'must be good',
      // A validator returning an empty message accepts the part, mirroring the
      // field-level contract.
      'b' => static fn(string $part): string => '',
    ]);

    $this->assertSame($expected, $template->partError('a', $value));
    $this->assertNull($template->partError('b', $value));
  }

  public static function dataProviderPartError(): \Iterator {
    yield 'valid' => ['good', NULL];
    yield 'invalid, framed with the label' => ['bad', 'Alpha: must be good'];
    yield 'empty' => ['', 'Alpha: must be good'];
  }

  public function testPartErrorIsNullWithoutValidator(): void {
    $this->assertNull((new Template('{{a}}-{{b}}'))->partError('a', 'anything'));
  }

  #[DataProvider('dataProviderError')]
  public function testError(string $value, ?string $expected): void {
    $template = new Template('{{a}}-{{b}}', ['b' => 'Beta'], [
      'b' => static fn(string $part): ?string => $part === 'ok' ? NULL : 'must be ok',
    ]);

    $this->assertSame($expected, $template->error($value));
  }

  public static function dataProviderError(): \Iterator {
    yield 'valid' => ['one-ok', NULL];
    yield 'invalid slot' => ['one-bad', 'Beta: must be ok'];
    yield 'shape mismatch' => ['nope', '"nope" does not match the template "{{a}}-{{b}}".'];
  }

  public function testErrorReportsTheFirstOffendingSlot(): void {
    $template = new Template('{{a}}-{{b}}', validators: [
      'a' => static fn(string $part): string => 'first',
      'b' => static fn(string $part): string => 'second',
    ]);

    $this->assertSame('a: first', $template->error('x-y'));
  }

  public function testRegexAnchorsAndEscapesTheFixedText(): void {
    $template = new Template('a.b{{slot}}');

    $this->assertSame('/^a\.b(.*?)$/usD', $template->regex());
    $this->assertFalse($template->matches('aXbvalue'));
    $this->assertTrue($template->matches('a.bvalue'));
  }

  #[DataProvider('dataProviderSchemaPattern')]
  public function testSchemaPattern(string $pattern, string $expected): void {
    $this->assertSame($expected, (new Template($pattern))->schemaPattern());
  }

  public static function dataProviderSchemaPattern(): \Iterator {
    // Only the characters an ECMAScript expression treats as special are
    // escaped; a hyphen or a slash carries no meaning there and stays bare.
    yield 'plain separators' => ['{{a}}-{{b}}', '^(.*?)-(.*?)$'];
    yield 'slash' => ['{{a}}/{{b}}', '^(.*?)/(.*?)$'];
    yield 'special characters' => ['{{a}}.({{b}}', '^(.*?)\.\((.*?)$'];
    yield 'backslash' => ['{{a}}\\{{b}}', '^(.*?)\\\\(.*?)$'];
  }

  #[DataProvider('dataProviderRejectsPattern')]
  public function testRejectsPattern(string $pattern, array $labels, array $validators, string $expected): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage($expected);

    new Template($pattern, $labels, $validators);
  }

  public static function dataProviderRejectsPattern(): \Iterator {
    yield 'no placeholder' => ['crate-42', [], [], 'declares no {{placeholder}} to fill in'];
    yield 'empty pattern' => ['', [], [], 'declares no {{placeholder}} to fill in'];
    yield 'repeated name' => ['{{a}}-{{a}}', [], [], 'repeats the placeholder "a"'];
    yield 'adjacent slots' => ['{{a}}{{b}}', [], [], 'places "b" directly after "a"'];
    yield 'adjacent later in the pattern' => ['{{a}}-{{b}}{{c}}', [], [], 'places "c" directly after "b"'];
    yield 'label for an absent slot' => ['{{a}}-{{b}}', ['c' => 'Gamma'], [], 'has a label for "c"'];
    yield 'validator for an absent slot' => ['{{a}}-{{b}}', [], ['c' => static fn(string $value): ?string => NULL], 'has a validator for "c"'];
  }

}
