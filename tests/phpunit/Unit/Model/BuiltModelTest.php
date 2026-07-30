<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Model;

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Model\FormException;
use DrevOps\Tui\Model\NumberBounds;
use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Model\Panel;
use DrevOps\Tui\Model\SelectionBounds;
use DrevOps\Tui\Model\Template;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the immutable models built by the fluent form builder.
 */
#[CoversClass(FormDefinition::class)]
#[CoversClass(Panel::class)]
#[CoversClass(Field::class)]
#[CoversClass(FieldType::class)]
#[CoversClass(Option::class)]
#[Group('model')]
final class BuiltModelTest extends TestCase {

  public function testBuildsNestedForm(): void {
    $form = Form::create('Demo', 'Acme')
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name')->default('Acme')->required();
        $p->text('email');
      })
      ->panel('drupal', 'Drupal', function (PanelBuilder $p): void {
        $p->select('profile')->option('standard', 'Standard');
        $p->panel('advanced', 'Advanced', function (PanelBuilder $sp): void {
          $sp->confirm('theme_debug');
        });
      })
      ->build();

    $this->assertSame('Demo', $form->title);
    $this->assertSame('Acme', $form->subject);
    $this->assertCount(2, $form->panels);

    $general = $form->panels[0];
    $this->assertSame('general', $general->id);
    $this->assertCount(2, $general->fields);

    $name = $general->fields[0];
    $this->assertSame(FieldType::Text, $name->type);
    $this->assertSame('Acme', $name->default);
    $this->assertTrue($name->required);

    $drupal = $form->panels[1];
    $profile = $drupal->fields[0];
    $this->assertSame(FieldType::Select, $profile->type);
    $standard = $profile->option('standard');
    $this->assertInstanceOf(Option::class, $standard);
    $this->assertSame('Standard', $standard->label);
    $this->assertNotInstanceOf(Option::class, $profile->option('missing'));

    $this->assertCount(1, $drupal->panels);
    $this->assertSame('advanced', $drupal->panels[0]->id);

    // field() resolves nested fields across sub-panels.
    $this->assertSame('theme_debug', $form->field('theme_debug')?->id);
    $this->assertNotInstanceOf(Field::class, $form->field('nope'));
    $this->assertCount(4, $form->fields());
  }

  /**
   * Only an empty value on a required field yields a violation message.
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
    $field = new Field('plot', 'Garden plot name', '', FieldType::Text, '', required: $required, requiredMessage: $message);

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
   * Tests that a name that cannot be honoured is rejected at construction.
   *
   * @param string $env_name
   *   The declared name, or empty to keep the mechanical one.
   * @param list<string> $aliases
   *   The declared aliases.
   * @param string $expected
   *   The expected exception message.
   */
  #[DataProvider('dataProviderEnvNameViolationThrows')]
  public function testEnvNameViolationThrows(string $env_name, array $aliases, string $expected): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage($expected);

    new Field('crate_size', 'Crate size', '', FieldType::Text, '', envName: $env_name, envAliases: $aliases);
  }

  public static function dataProviderEnvNameViolationThrows(): \Iterator {
    yield 'name starting with a digit' => ['1CRATE', [], 'Field "crate_size" declares the environment variable name "1CRATE", which is not a portable name'];
    yield 'name with a hyphen' => ['OLD-CRATE', [], 'Field "crate_size" declares the environment variable name "OLD-CRATE", which is not a portable name'];
    yield 'name with a space' => ['OLD CRATE', [], 'Field "crate_size" declares the environment variable name "OLD CRATE", which is not a portable name'];
    yield 'alias with a hyphen' => ['', ['OLD-CRATE'], 'Field "crate_size" declares the environment variable alias "OLD-CRATE", which is not a portable name'];
    yield 'empty alias' => ['', [''], 'Field "crate_size" declares the environment variable alias "", which is not a portable name'];
    yield 'alias repeating the name' => ['NEW_CRATE', ['NEW_CRATE'], 'Field "crate_size" declares "NEW_CRATE" as both its environment variable name and an alias of it'];
    yield 'alias declared twice' => ['', ['OLD_CRATE', 'OLD_CRATE'], 'Field "crate_size" declares the environment variable alias "OLD_CRATE" more than once'];
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
    $field = new Field('crate_size', 'Crate size', '', FieldType::Text, '', envName: $env_name, envAliases: $aliases);

    $this->assertSame($env_name, $field->envName);
    $this->assertSame($aliases, $field->envAliases);
  }

  public static function dataProviderEnvNameAccepted(): \Iterator {
    yield 'nothing declared' => ['', []];
    yield 'name only' => ['NEW_CRATE', []];
    yield 'aliases only' => ['', ['OLD_CRATE', 'OLDER_CRATE']];
    yield 'name and aliases' => ['NEW_CRATE', ['OLD_CRATE']];
    yield 'leading underscore' => ['_CRATE', []];
    yield 'digits after the first character' => ['CRATE_2', []];
    yield 'lowercase is left as declared' => ['old_crate', []];
  }

  public function testTemplateFieldWithoutTemplateThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Field "crate" is a template field but declares no pattern');

    new Field('crate', 'Crate', '', FieldType::Template, '');
  }

  #[DataProvider('dataProviderTemplateError')]
  public function testTemplateError(mixed $value, ?string $expected): void {
    $field = new Field('crate', 'Crate', '', FieldType::Template, '', template: new Template('{{a}}-{{b}}', ['b' => 'Beta'], [
      'b' => static fn(string $part): ?string => $part === 'ok' ? NULL : 'must be ok',
    ]));

    $this->assertSame($expected, $field->templateError($value));
  }

  public static function dataProviderTemplateError(): \Iterator {
    yield 'fits the shape' => ['one-ok', NULL];
    yield 'slot rejected' => ['one-bad', 'Beta: must be ok'];
    yield 'shape mismatch' => ['nope', '"nope" does not match the template "{{a}}-{{b}}".'];
    // An unfilled template is left to the required check, and a non-string is
    // left to the type check, so neither is reported here.
    yield 'empty' => ['', NULL];
    yield 'not a string' => [42, NULL];
  }

  public function testTemplateErrorIsNullWithoutTemplate(): void {
    $this->assertNull((new Field('name', 'Name', '', FieldType::Text, ''))->templateError('anything'));
  }

  #[DataProvider('dataProviderTemplateParts')]
  public function testTemplateParts(mixed $value, array $expected): void {
    $field = new Field('crate', 'Crate', '', FieldType::Template, '', template: new Template('{{a}}-{{b}}'));

    $this->assertSame($expected, $field->templateParts($value));
  }

  public static function dataProviderTemplateParts(): \Iterator {
    yield 'fits the shape' => ['one-two', ['a' => 'one', 'b' => 'two']];
    yield 'shape mismatch' => ['nope', []];
    yield 'not a string' => [42, []];
  }

  public function testTemplatePartsAreEmptyWithoutTemplate(): void {
    $this->assertSame([], (new Field('name', 'Name', '', FieldType::Text, ''))->templateParts('one-two'));
  }

  public function testPanelItemCount(): void {
    $panel = new Panel('p', 'P', '', [new Field('a', 'A', '', FieldType::Text, '')], [new Panel('s', 'S', '')]);

    $this->assertSame(2, $panel->itemCount());
    $this->assertSame(0, (new Panel('empty', 'E', ''))->itemCount());
  }

  public function testTypeDefaults(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->select('ms')->multiple();
        $p->confirm('cb');
        $p->text('tx');
      })
      ->build();

    $this->assertSame([], $form->field('ms')?->default);
    $this->assertFalse($form->field('cb')?->default);
    $this->assertSame('', $form->field('tx')?->default);
  }

  public function testSchemaDefault(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('with')->schemaDefault('static');
        $p->text('without');
      })
      ->build();

    $with = $form->field('with');
    $this->assertInstanceOf(Field::class, $with);
    $this->assertTrue($with->hasSchemaDefault);
    $this->assertSame('static', $with->schemaDefault);

    $without = $form->field('without');
    $this->assertInstanceOf(Field::class, $without);
    $this->assertFalse($without->hasSchemaDefault);
    $this->assertNull($without->schemaDefault);
  }

  public function testSelectionBoundsOnNonMultipleFieldThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Field "s" declares selection limits but does not collect several values.');

    new Field('s', 'S', '', FieldType::Text, '', selectionBounds: new SelectionBounds(2, 3));
  }

  public function testCaptionsOnFieldWithNoScaleThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Field "f" of type "text" draws no scale to caption; ->captions() applies to rating fields.');

    new Field('f', 'F', '', FieldType::Text, '', ratingCaptions: [1 => 'Poor']);
  }

  public function testCaptionOutsideTheScaleThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Field "f" captions the point 9, which is outside its scale of between 1 and 5.');

    new Field('f', 'F', '', FieldType::Rating, 1, bounds: new NumberBounds(1, 5), ratingCaptions: [9 => 'Nope']);
  }

  public function testCaptionsWithinTheScaleAreKept(): void {
    $field = new Field('f', 'F', '', FieldType::Rating, 1, bounds: new NumberBounds(1, 5), ratingCaptions: [1 => 'Poor', 5 => 'Excellent']);

    $this->assertSame([1 => 'Poor', 5 => 'Excellent'], $field->ratingCaptions);
  }

  public function testCaptionsOnAnUnboundedRatingAreKept(): void {
    // The builder always closes a rating's scale; a hand-built field without
    // one has no range to check a caption against, so every point passes.
    $field = new Field('f', 'F', '', FieldType::Rating, 1, ratingCaptions: [99 => 'Far out']);

    $this->assertSame([99 => 'Far out'], $field->ratingCaptions);
  }

  #[DataProvider('dataProviderPlaceholderIsRejectedWhenTypeHasNoInput')]
  public function testPlaceholderIsRejectedWhenTypeHasNoInput(FieldType $type, bool $accepted): void {
    if (!$accepted) {
      $this->expectException(FormException::class);
      $this->expectExceptionMessage(sprintf('Field "f" of type "%s" shows no placeholder', $type->value));
    }

    $field = new Field('f', 'F', '', $type, '', template: $type === FieldType::Template ? new Template('{{a}}-{{b}}') : NULL, placeholder: 'E.g. Golden Beetroot');

    $this->assertSame('E.g. Golden Beetroot', $field->placeholder);
  }

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

  #[DataProvider('dataProviderHelpIsAcceptedOnEveryType')]
  public function testHelpIsAcceptedOnEveryType(FieldType $type): void {
    $field = new Field('f', 'F', '', $type, '', template: $type === FieldType::Template ? new Template('{{a}}-{{b}}') : NULL, help: 'Use the arrows.');

    $this->assertSame('Use the arrows.', $field->help);
  }

  public static function dataProviderHelpIsAcceptedOnEveryType(): \Iterator {
    foreach (FieldType::cases() as $type) {
      yield $type->value => [$type];
    }
  }

  public function testFormDefaults(): void {
    $form = Form::create('T')->build();

    $this->assertSame('', $form->subject);
    $this->assertSame('', $form->envPrefix);
    $this->assertSame([], $form->fixups);
    // Form chrome defaults (the global TUI runtime lives on the Tui facade).
    $this->assertSame('', $form->banner);
    $this->assertTrue($form->buttons->show);
    $this->assertSame('Submit', $form->buttons->submitLabel);
    $this->assertSame('Cancel', $form->buttons->cancelLabel);
  }

}
