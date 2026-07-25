<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Model;

use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\FormException;
use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Model\OptionKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the option model, option kinds and the field option helpers.
 */
#[CoversClass(Option::class)]
#[CoversClass(OptionKind::class)]
#[CoversClass(FieldType::class)]
#[CoversClass(Field::class)]
#[Group('model')]
final class OptionTest extends TestCase {

  public function testListFromMap(): void {
    $options = Option::list(['a' => 'Apple', 'b' => 'Banana']);

    $this->assertCount(2, $options);
    $this->assertSame('a', $options[0]->value);
    $this->assertSame('Apple', $options[0]->label);
    $this->assertSame(OptionKind::Option, $options[0]->kind);
    $this->assertTrue($options[0]->selectable());
  }

  public function testListLabelDefaultsToValue(): void {
    $options = Option::list(['a' => '']);

    $this->assertSame('a', $options[0]->label);
  }

  public function testListFromOptionsPassesThrough(): void {
    $sep = new Option('', '', '', OptionKind::Separator);
    $options = Option::list([new Option('a', 'Apple'), $sep]);

    $this->assertSame('Apple', $options[0]->label);
    $this->assertSame($sep, $options[1]);
  }

  public function testListMixed(): void {
    $options = Option::list(['a' => 'Apple', new Option('b', 'Banana', '', OptionKind::Option, TRUE, 'nope')]);

    $this->assertSame('a', $options[0]->value);
    $this->assertTrue($options[1]->disabled);
    $this->assertSame('nope', $options[1]->disabledReason);
  }

  #[DataProvider('dataProviderSelectable')]
  public function testSelectable(Option $option, bool $expected): void {
    $this->assertSame($expected, $option->selectable());
  }

  public static function dataProviderSelectable(): \Iterator {
    yield 'plain option' => [new Option('a', 'A'), TRUE];
    yield 'disabled option' => [new Option('a', 'A', '', OptionKind::Option, TRUE), FALSE];
    yield 'separator' => [new Option('', '', '', OptionKind::Separator), FALSE];
    yield 'heading' => [new Option('', 'Group', '', OptionKind::Heading), FALSE];
  }

  #[DataProvider('dataProviderConstrainsToOptions')]
  public function testConstrainsToOptions(FieldType $type, bool $expected): void {
    $this->assertSame($expected, $type->constrainsToOptions());
  }

  public static function dataProviderConstrainsToOptions(): \Iterator {
    yield [FieldType::Select, TRUE];
    yield [FieldType::Search, TRUE];
    yield [FieldType::Reorder, TRUE];
    yield [FieldType::Suggest, FALSE];
    yield [FieldType::Text, FALSE];
    yield [FieldType::Confirm, FALSE];
  }

  #[DataProvider('dataProviderIsMultiChoice')]
  public function testIsMultiChoice(FieldType $type, bool $multiple, bool $expected): void {
    $field = new Field('f', 'F', '', $type, $multiple ? [] : '', multiple: $multiple);

    $this->assertSame($expected, $field->isMultiChoice());
  }

  public static function dataProviderIsMultiChoice(): \Iterator {
    yield 'multiple select' => [FieldType::Select, TRUE, TRUE];
    yield 'multiple search' => [FieldType::Search, TRUE, TRUE];
    yield 'reorder' => [FieldType::Reorder, FALSE, TRUE];
    yield 'multiple file picker' => [FieldType::FilePicker, TRUE, FALSE];
    yield 'single select' => [FieldType::Select, FALSE, FALSE];
    yield 'single search' => [FieldType::Search, FALSE, FALSE];
    yield 'text' => [FieldType::Text, FALSE, FALSE];
  }

  #[DataProvider('dataProviderCollectsList')]
  public function testCollectsList(FieldType $type, bool $multiple, bool $expected): void {
    $field = new Field('f', 'F', '', $type, $multiple ? [] : '', multiple: $multiple);

    $this->assertSame($expected, $field->collectsList());
  }

  public static function dataProviderCollectsList(): \Iterator {
    yield 'multiple select' => [FieldType::Select, TRUE, TRUE];
    yield 'multiple search' => [FieldType::Search, TRUE, TRUE];
    yield 'multiple file picker' => [FieldType::FilePicker, TRUE, TRUE];
    yield 'reorder' => [FieldType::Reorder, FALSE, TRUE];
    yield 'single select' => [FieldType::Select, FALSE, FALSE];
    yield 'single file picker' => [FieldType::FilePicker, FALSE, FALSE];
    yield 'text' => [FieldType::Text, FALSE, FALSE];
  }

  #[DataProvider('dataProviderAcceptsValue')]
  public function testAcceptsValue(FieldType $type, bool $multiple, mixed $value, bool $expected): void {
    $field = new Field('f', 'F', '', $type, $multiple ? [] : '', multiple: $multiple);

    $this->assertSame($expected, $field->acceptsValue($value));
  }

  public static function dataProviderAcceptsValue(): \Iterator {
    yield 'confirm accepts bool' => [FieldType::Confirm, FALSE, TRUE, TRUE];
    yield 'confirm rejects string' => [FieldType::Confirm, FALSE, 'yes', FALSE];
    yield 'pause accepts bool' => [FieldType::Pause, FALSE, FALSE, TRUE];
    yield 'multiple accepts list' => [FieldType::Select, TRUE, ['a'], TRUE];
    yield 'multiple rejects scalar' => [FieldType::Select, TRUE, 'a', FALSE];
    yield 'reorder accepts list' => [FieldType::Reorder, FALSE, ['a'], TRUE];
    yield 'number accepts int' => [FieldType::Number, FALSE, 42, TRUE];
    yield 'number rejects numeric string' => [FieldType::Number, FALSE, '42', FALSE];
    yield 'calendar accepts empty' => [FieldType::Calendar, FALSE, '', TRUE];
    yield 'calendar accepts iso date' => [FieldType::Calendar, FALSE, '2026-07-16', TRUE];
    yield 'calendar rejects non-date' => [FieldType::Calendar, FALSE, 'nope', FALSE];
    yield 'text accepts string' => [FieldType::Text, FALSE, 'x', TRUE];
    yield 'text rejects int' => [FieldType::Text, FALSE, 1, FALSE];
  }

  #[DataProvider('dataProviderValueKind')]
  public function testValueKind(FieldType $type, bool $multiple, string $expected): void {
    $field = new Field('f', 'F', '', $type, $multiple ? [] : '', multiple: $multiple);

    $this->assertSame($expected, $field->valueKind());
  }

  public static function dataProviderValueKind(): \Iterator {
    yield 'confirm' => [FieldType::Confirm, FALSE, 'a boolean'];
    yield 'pause' => [FieldType::Pause, FALSE, 'a boolean'];
    yield 'multiple' => [FieldType::Select, TRUE, 'a list'];
    yield 'reorder' => [FieldType::Reorder, FALSE, 'a list'];
    yield 'number' => [FieldType::Number, FALSE, 'a number'];
    yield 'calendar' => [FieldType::Calendar, FALSE, 'a date (YYYY-MM-DD)'];
    yield 'text' => [FieldType::Text, FALSE, 'a string'];
  }

  #[DataProvider('dataProviderSupportsMultiple')]
  public function testSupportsMultiple(FieldType $type, bool $expected): void {
    $this->assertSame($expected, $type->supportsMultiple());
  }

  public static function dataProviderSupportsMultiple(): \Iterator {
    yield 'select' => [FieldType::Select, TRUE];
    yield 'search' => [FieldType::Search, TRUE];
    yield 'file picker' => [FieldType::FilePicker, TRUE];
    yield 'reorder' => [FieldType::Reorder, FALSE];
    yield 'number' => [FieldType::Number, FALSE];
    yield 'text' => [FieldType::Text, FALSE];
  }

  #[DataProvider('dataProviderIsPresentational')]
  public function testIsPresentational(FieldType $type, bool $expected): void {
    $this->assertSame($expected, $type->isPresentational());
  }

  public static function dataProviderIsPresentational(): \Iterator {
    yield 'note' => [FieldType::Note, TRUE];
    // A pause renders but still carries a boolean answer, so it is not
    // presentational.
    yield 'pause' => [FieldType::Pause, FALSE];
    yield 'text' => [FieldType::Text, FALSE];
    yield 'confirm' => [FieldType::Confirm, FALSE];
  }

  public function testNoteLabel(): void {
    $this->assertSame('Note', FieldType::Note->label());
  }

  public function testConstructorRejectsMultipleOnUnsupportedType(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Field "n" of type "number" does not collect several values');

    new Field('n', 'N', '', FieldType::Number, 0, multiple: TRUE);
  }

  public function testFieldOptionScan(): void {
    $field = $this->selectField();

    $this->assertSame('Standard', $field->option('standard')?->label);
    // A disabled option is still found by value.
    $this->assertTrue($field->option('demo')?->disabled);
    // Missing values and structural rows are not returned.
    $this->assertNotInstanceOf(Option::class, $field->option('missing'));
    $this->assertNotInstanceOf(Option::class, $field->option(''));
  }

  public function testSelectableValues(): void {
    $this->assertSame(['standard', 'minimal'], $this->selectField()->selectableValues());
  }

  #[DataProvider('dataProviderOptionError')]
  public function testOptionError(FieldType $type, bool $multiple, array $options, mixed $value, ?string $expected): void {
    $field = new Field('f', 'F', '', $type, $multiple ? [] : '', $options, multiple: $multiple);

    $this->assertSame($expected, $field->optionError($value));
  }

  public static function dataProviderOptionError(): \Iterator {
    $options = [
      new Option('standard', 'Standard'),
      new Option('minimal', 'Minimal'),
      new Option('demo', 'Demo', '', OptionKind::Option, TRUE, 'unavailable'),
      new Option('legacy', 'Legacy', '', OptionKind::Option, TRUE),
      new Option('', '', '', OptionKind::Separator),
    ];
    yield 'selectable value' => [FieldType::Select, FALSE, $options, 'standard', NULL];
    yield 'disabled with reason' => [FieldType::Select, FALSE, $options, 'demo', 'option "demo" is disabled: unavailable'];
    yield 'disabled without reason' => [FieldType::Select, FALSE, $options, 'legacy', 'option "legacy" is disabled'];
    yield 'unknown value' => [FieldType::Select, FALSE, $options, 'bogus', 'value "bogus" is not one of: standard, minimal'];
    yield 'unconstrained type' => [FieldType::Suggest, FALSE, $options, 'bogus', NULL];
    yield 'no options' => [FieldType::Select, FALSE, [], 'bogus', NULL];
    yield 'multi valid' => [FieldType::Select, TRUE, $options, ['standard', 'minimal'], NULL];
    yield 'multi disabled item' => [FieldType::Select, TRUE, $options, ['standard', 'demo'], 'option "demo" is disabled: unavailable'];
    yield 'multi non-array' => [FieldType::Select, TRUE, $options, 'standard', 'value must be a list'];
    yield 'reorder full permutation' => [FieldType::Reorder, FALSE, $options, ['minimal', 'standard'], NULL];
    yield 'reorder partial' => [FieldType::Reorder, FALSE, $options, ['standard'], 'must rank every option exactly once (standard, minimal)'];
    yield 'reorder duplicate' => [FieldType::Reorder, FALSE, $options, ['standard', 'standard'], 'must rank every option exactly once (standard, minimal)'];
    yield 'reorder unknown item' => [FieldType::Reorder, FALSE, $options, ['standard', 'bogus'], 'value "bogus" is not one of: standard, minimal'];
    yield 'reorder non-array' => [FieldType::Reorder, FALSE, $options, 'standard', 'value must be a list'];
  }

  /**
   * Tests completing and de-duplicating a desired ordering.
   *
   * @param list<string> $allowed
   *   The full set of values, in declared order.
   * @param list<string> $desired
   *   The requested ordering.
   * @param list<string> $expected
   *   The resolved permutation.
   */
  #[DataProvider('dataProviderCanonicalOrder')]
  public function testCanonicalOrder(array $allowed, array $desired, array $expected): void {
    $this->assertSame($expected, Field::canonicalOrder($allowed, $desired));
  }

  /**
   * Data provider for testCanonicalOrder().
   *
   * @return \Iterator<string, array{list<string>, list<string>, list<string>}>
   *   The allowed values, desired order and resolved permutation.
   */
  public static function dataProviderCanonicalOrder(): \Iterator {
    yield 'empty desired keeps declared order' => [['a', 'b', 'c'], [], ['a', 'b', 'c']];
    yield 'full desired preserved' => [['a', 'b', 'c'], ['c', 'b', 'a'], ['c', 'b', 'a']];
    yield 'partial desired completed' => [['a', 'b', 'c'], ['c'], ['c', 'a', 'b']];
    yield 'unknown desired dropped' => [['a', 'b', 'c'], ['x', 'b'], ['b', 'a', 'c']];
    yield 'duplicate desired collapsed' => [['a', 'b', 'c'], ['b', 'b', 'a'], ['b', 'a', 'c']];
    yield 'no allowed values' => [[], ['a'], []];
  }

  /**
   * A select field mixing selectable, disabled and structural rows.
   */
  protected function selectField(): Field {
    return new Field('profile', 'Profile', '', FieldType::Select, '', [
      new Option('standard', 'Standard'),
      new Option('', 'Group', '', OptionKind::Heading),
      new Option('minimal', 'Minimal'),
      new Option('', '', '', OptionKind::Separator),
      new Option('demo', 'Demo', '', OptionKind::Option, TRUE, 'unavailable'),
    ]);
  }

}
