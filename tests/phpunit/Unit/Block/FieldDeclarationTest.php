<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Block;

use DrevOps\PhpTui\Block\Field;
use DrevOps\PhpTui\Block\FieldType;
use DrevOps\PhpTui\Block\Option;
use DrevOps\PhpTui\Block\Panel;
use DrevOps\PhpTui\Block\Template;
use DrevOps\PhpTui\Block\Tree;
use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\FormException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests what a field declaration refuses, and what it settles on.
 */
#[CoversClass(Field::class)]
#[CoversClass(Form::class)]
#[Group('block')]
final class FieldDeclarationTest extends TestCase {

  public function testTreeHoldsEveryPanelAndFieldItWasDeclaredWith(): void {
    $root = Form::create('Demo')
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name')->default('Acme')->required();
        $p->text('email');
      })
      ->panel('orchard', 'Orchard', function (PanelBuilder $p): void {
        $p->select('basket')->option('standard', 'Standard');
        $p->panel('advanced', 'Advanced', function (PanelBuilder $sp): void {
          $sp->confirm('trace');
        });
      })
      ->root();

    $this->assertSame('Demo', $root->title());
    $this->assertCount(2, $root->children());

    $general = $root->children()[0];
    $this->assertSame('general', $general->id());
    $this->assertCount(2, $general->fields());

    $name = $general->fields()[0];
    $this->assertSame(FieldType::Text, $name->type());
    $this->assertSame('Acme', $name->value());
    $this->assertTrue($name->isRequired());

    $orchard = $root->children()[1];
    $basket = $orchard->fields()[0];
    $this->assertSame(FieldType::Select, $basket->type());
    $this->assertSame('Standard', $basket->optionOf('standard')?->label);
    $this->assertNotInstanceOf(Option::class, $basket->optionOf('missing'));

    // The trail reaches every panel and every field beneath the root.
    $this->assertCount(1, $orchard->children());
    $this->assertSame('advanced', $orchard->children()[0]->id());
    $this->assertCount(4, Tree::fields($root));
  }

  /**
   * Tests when an empty value on a required field yields a message.
   *
   * @param bool $required
   *   Whether the field is required.
   * @param string $message
   *   The declared message, empty to derive one from the label.
   * @param mixed $value
   *   The candidate value.
   * @param string|null $expected
   *   The expected message, or NULL when the value is accepted.
   */
  #[DataProvider('dataProviderRequiredViolation')]
  public function testRequiredViolation(bool $required, string $message, mixed $value, ?string $expected): void {
    $field = (new Field('plot', 'Garden plot name'))->required($required, $message);

    $this->assertSame($expected, $field->requiredViolation($value));
  }

  /**
   * Data provider for testRequiredViolation().
   *
   * @return \Iterator<string,array{bool,string,mixed,string|null}>
   *   The required flag, the declared message, the value and the expectation.
   */
  public static function dataProviderRequiredViolation(): \Iterator {
    $derived = 'Garden plot name is required.';
    $declared = 'The garden plot name is required.';

    yield 'empty string' => [TRUE, '', '', $derived];
    yield 'empty list' => [TRUE, '', [], $derived];
    yield 'null' => [TRUE, '', NULL, $derived];
    yield 'declared message wins over the label' => [TRUE, $declared, '', $declared];
    yield 'non-empty string' => [TRUE, '', 'North bed', NULL];
    yield 'non-empty list' => [TRUE, '', ['a'], NULL];
    // Only the three empty shapes count: a falsy scalar is an answer, not an
    // omission, so a FALSE confirm and a 0 number both pass.
    yield 'false' => [TRUE, '', FALSE, NULL];
    yield 'zero' => [TRUE, '', 0, NULL];
    yield 'zero string' => [TRUE, '', '0', NULL];
    yield 'optional field ignores an empty value' => [FALSE, '', '', NULL];
    yield 'optional field ignores a declared message' => [FALSE, $declared, '', NULL];
  }

  /**
   * Tests that a variable name that cannot be honoured is refused.
   *
   * @param string $env_name
   *   The declared name, or empty to keep the mechanical one.
   * @param list<string> $aliases
   *   The declared aliases.
   * @param string $expected
   *   The expected message.
   */
  #[DataProvider('dataProviderEnvNameViolationThrows')]
  public function testEnvNameViolationThrows(string $env_name, array $aliases, string $expected): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($expected);

    $field = new Field('crate_size', 'Crate size');

    if ($env_name !== '') {
      $field->env($env_name);
    }

    $field->envAliases($aliases);
  }

  /**
   * Data provider for testEnvNameViolationThrows().
   *
   * @return \Iterator<string,array{string,list<string>,string}>
   *   The declared name, its aliases and the expected message.
   */
  public static function dataProviderEnvNameViolationThrows(): \Iterator {
    yield 'name starting with a digit' => ['1CRATE', [], 'declares the environment variable name "1CRATE", which is not portable'];
    yield 'name with a hyphen' => ['OLD-CRATE', [], 'declares the environment variable name "OLD-CRATE", which is not portable'];
    yield 'name with a space' => ['OLD CRATE', [], 'declares the environment variable name "OLD CRATE", which is not portable'];
    yield 'alias with a hyphen' => ['', ['OLD-CRATE'], 'declares the environment variable name "OLD-CRATE", which is not portable'];
    yield 'empty alias' => ['', [''], 'declares the environment variable name "", which is not portable'];
    yield 'alias repeating the name' => ['NEW_CRATE', ['NEW_CRATE'], 'declares the environment variable "NEW_CRATE" twice'];
    yield 'alias declared twice' => ['', ['OLD_CRATE', 'OLD_CRATE'], 'declares the environment variable "OLD_CRATE" twice'];
  }

  /**
   * Tests that a name that can be honoured is kept as declared.
   *
   * @param string $env_name
   *   The declared name, or empty to keep the mechanical one.
   * @param list<string> $aliases
   *   The declared aliases.
   */
  #[DataProvider('dataProviderEnvNameAccepted')]
  public function testEnvNameAccepted(string $env_name, array $aliases): void {
    $field = new Field('crate_size', 'Crate size');

    if ($env_name !== '') {
      $field->env($env_name);
    }

    $field->envAliases($aliases);

    $this->assertSame($env_name, $field->envName());
    $this->assertSame($aliases, $field->aliases());
  }

  /**
   * Data provider for testEnvNameAccepted().
   *
   * @return \Iterator<string,array{string,list<string>}>
   *   The declared name and its aliases.
   */
  public static function dataProviderEnvNameAccepted(): \Iterator {
    yield 'nothing declared' => ['', []];
    yield 'name only' => ['NEW_CRATE', []];
    yield 'aliases only' => ['', ['OLD_CRATE', 'OLDER_CRATE']];
    yield 'name and aliases' => ['NEW_CRATE', ['OLD_CRATE']];
    yield 'leading underscore' => ['_CRATE', []];
    yield 'digits after the first character' => ['CRATE_2', []];
    yield 'lowercase is left as declared' => ['old_crate', []];
  }

  public function testTemplateFieldWithoutShapeIsRefusedWhenTheFormIsDeclared(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Field "crate" is a template field but declares no pattern');

    Form::create('T')->panel('p', 'P', static function (PanelBuilder $p): void {
      $p->add(new Field('crate', 'Crate', FieldType::Template));
    })->root();
  }

  /**
   * Tests the reason an answer does not fit the shape it must have.
   *
   * @param mixed $value
   *   The candidate value.
   * @param string|null $expected
   *   The expected reason, or NULL when the value fits.
   */
  #[DataProvider('dataProviderTemplateError')]
  public function testTemplateError(mixed $value, ?string $expected): void {
    $field = (new Field('crate', 'Crate', FieldType::Template))->pattern(new Template('{{a}}-{{b}}', ['b' => 'Beta'], [
      'b' => static fn(string $part): ?string => $part === 'ok' ? NULL : 'must be ok',
    ]));

    $this->assertSame($expected, $field->templateViolation($value));
  }

  /**
   * Data provider for testTemplateError().
   *
   * @return \Iterator<string,array{mixed,string|null}>
   *   The value and the reason it is refused, or NULL when it fits.
   */
  public static function dataProviderTemplateError(): \Iterator {
    yield 'fits the shape' => ['one-ok', NULL];
    yield 'slot rejected' => ['one-bad', 'Beta: must be ok'];
    yield 'shape mismatch' => ['nope', '"nope" does not match the template "{{a}}-{{b}}".'];
    // An unfilled template is left to the required check, and a non-string is
    // left to the type check, so neither is reported here.
    yield 'empty' => ['', NULL];
    yield 'not a string' => [42, NULL];
  }

  public function testTemplateErrorIsNullWithoutShape(): void {
    $this->assertNull((new Field('name', 'Name'))->templateViolation('anything'));
  }

  /**
   * Tests the slot values recovered from an assembled answer.
   *
   * @param mixed $value
   *   The assembled answer.
   * @param array<string,string> $expected
   *   The value of each slot, keyed by slot name.
   */
  #[DataProvider('dataProviderTemplateParts')]
  public function testTemplateParts(mixed $value, array $expected): void {
    $field = (new Field('crate', 'Crate', FieldType::Template))->pattern(new Template('{{a}}-{{b}}'));

    $this->assertSame($expected, $field->templateParts($value));
  }

  /**
   * Data provider for testTemplateParts().
   *
   * @return \Iterator<string,array{mixed,array<string,string>}>
   *   The answer and the slots recovered from it.
   */
  public static function dataProviderTemplateParts(): \Iterator {
    yield 'fits the shape' => ['one-two', ['a' => 'one', 'b' => 'two']];
    yield 'shape mismatch' => ['nope', []];
    yield 'not a string' => [42, []];
  }

  public function testTemplatePartsAreEmptyWithoutShape(): void {
    $this->assertSame([], (new Field('name', 'Name'))->templateParts('one-two'));
  }

  public function testCaptionsOnFieldWithNoScaleThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Field "f" of type "text" draws no scale to caption; captions apply to rating fields.');

    Form::create('T')->panel('p', 'P', static function (PanelBuilder $p): void {
      $p->add((new Field('f', 'F'))->captions([1 => 'Poor']));
    })->root();
  }

  /**
   * Tests the rows a hand-built field of the wrong kind is refused.
   *
   * @param \Closure $declare
   *   The declaration, given the field it lands on.
   * @param string $message
   *   The message the finished tree is refused with.
   */
  #[DataProvider('dataProviderOptionsOnFieldWithNoListThrow')]
  public function testOptionsOnFieldWithNoListThrow(\Closure $declare, string $message): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage($message);

    Form::create('T')->panel('p', 'P', static function (PanelBuilder $p) use ($declare): void {
      $p->add($declare(new Field('f', 'F')));
    })->root();
  }

  /**
   * Data provider for testOptionsOnFieldWithNoListThrow().
   *
   * @return \Iterator<string, array{\Closure, string}>
   *   The declaration and the message refusing it.
   */
  public static function dataProviderOptionsOnFieldWithNoListThrow(): \Iterator {
    yield 'a declared row' => [
      static fn(Field $field): Field => $field->option('apple', 'Apple'),
      'Field "f" of type "text" shows no options; only select, search, suggest, toggle and reorder fields have a list.',
    ];
    yield 'a query source' => [
      static fn(Field $field): Field => $field->query(static fn(string $query, array $answers): array => []),
      'Field "f" of type "text" cannot source its options from a query; only search and suggest fields show one.',
    ];
  }

  public function testCaptionsOnAnUnboundedRatingAreKept(): void {
    // The builder always closes a rating's scale; a hand-built field without
    // one has no range to check a caption against, so every point passes.
    $field = (new Field('f', 'F', FieldType::Rating))->captions([99 => 'Far out']);

    $this->assertSame([99 => 'Far out'], $field->ratingCaptions());
  }

  /**
   * Tests that only a field drawing a buffer accepts ghost text.
   *
   * @param \DrevOps\PhpTui\Block\FieldType $type
   *   The kind of answer the field collects.
   * @param bool $accepted
   *   Whether it draws a buffer to ghost.
   */
  #[DataProvider('dataProviderPlaceholderIsRejectedWhenTypeHasNoInput')]
  public function testPlaceholderIsRejectedWhenTypeHasNoInput(FieldType $type, bool $accepted): void {
    if (!$accepted) {
      $this->expectException(FormException::class);
      $this->expectExceptionMessage(sprintf('Field "f" of type "%s" shows no placeholder', $type->value));
    }

    $field = (new Field('f', 'F', $type))->placeholder('E.g. Golden Beetroot');

    if ($type === FieldType::Template) {
      $field->pattern(new Template('{{a}}-{{b}}'));
    }

    Form::create('T')->panel('p', 'P', static fn(PanelBuilder $p): PanelBuilder => $p->add($field))->root();

    $this->assertSame('E.g. Golden Beetroot', $field->placeholderText());
  }

  /**
   * Data provider for testPlaceholderIsRejectedWhenTypeHasNoInput().
   *
   * @return \Iterator<string,array{\DrevOps\PhpTui\Block\FieldType,bool}>
   *   Each kind, and whether it draws a buffer to ghost.
   */
  public static function dataProviderPlaceholderIsRejectedWhenTypeHasNoInput(): \Iterator {
    // The accepting types are spelled out rather than read back from
    // supportsPlaceholder(), so a change to that set fails here instead of
    // moving the expectation along with it.
    $accepting = [
      FieldType::Text,
      FieldType::Number,
      FieldType::Textarea,
      FieldType::Password,
      FieldType::Suggest,
      FieldType::Search,
    ];

    foreach (FieldType::cases() as $type) {
      yield $type->value => [$type, in_array($type, $accepting, TRUE)];
    }
  }

  /**
   * Tests that every kind of field takes the long text behind its help key.
   *
   * @param \DrevOps\PhpTui\Block\FieldType $type
   *   The kind of answer the field collects.
   */
  #[DataProvider('dataProviderHelpIsAcceptedOnEveryType')]
  public function testHelpIsAcceptedOnEveryType(FieldType $type): void {
    $this->assertSame('Use the arrows.', (new Field('f', 'F', $type))->help('Use the arrows.')->helpText());
  }

  /**
   * Data provider for testHelpIsAcceptedOnEveryType().
   *
   * @return \Iterator<string,array{\DrevOps\PhpTui\Block\FieldType}>
   *   Each kind of answer a field collects.
   */
  public static function dataProviderHelpIsAcceptedOnEveryType(): \Iterator {
    foreach (FieldType::cases() as $type) {
      yield $type->value => [$type];
    }
  }

  public function testFormDefaults(): void {
    $builder = Form::create('T');
    $root = $builder->root();

    $this->assertSame('', $builder->currentEnvPrefix());
    $this->assertSame([], $builder->currentFixups());
    // Form chrome defaults (the global TUI runtime lives on the Tui facade).
    $this->assertSame('', $builder->currentBanner());
    $this->assertInstanceOf(Panel::class, $root);
    $this->assertTrue($root->currentButtons()->show);
    $this->assertSame('Submit', $root->currentButtons()->submitLabel);
    $this->assertSame('Cancel', $root->currentButtons()->cancelLabel);
  }

}
