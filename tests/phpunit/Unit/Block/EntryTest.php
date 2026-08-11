<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Block;

use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\FieldType;
use DrevOps\Tui\Block\Option;
use DrevOps\Tui\Block\OptionType;
use DrevOps\Tui\Builder\FieldBuilder;
use DrevOps\Tui\FormException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the option model, option kinds and the entry helpers a field offers.
 */
#[CoversClass(Option::class)]
#[CoversClass(OptionType::class)]
#[CoversClass(FieldType::class)]
#[CoversClass(Field::class)]
#[CoversClass(FieldBuilder::class)]
#[Group('block')]
final class EntryTest extends TestCase {

  public function testListFromMap(): void {
    $options = Option::list(['a' => 'Apple', 'b' => 'Banana']);

    $this->assertCount(2, $options);
    $this->assertSame('a', $options[0]->value);
    $this->assertSame('Apple', $options[0]->label);
    $this->assertSame(OptionType::Option, $options[0]->kind);
    $this->assertTrue($options[0]->isSelectable());
  }

  public function testListLabelDefaultsToValue(): void {
    $options = Option::list(['a' => '']);

    $this->assertSame('a', $options[0]->label);
  }

  public function testListFromOptionsPassesThrough(): void {
    $sep = new Option('', '', '', OptionType::Separator);
    $options = Option::list([new Option('a', 'Apple'), $sep]);

    $this->assertSame('Apple', $options[0]->label);
    $this->assertSame($sep, $options[1]);
  }

  public function testListMixed(): void {
    $options = Option::list(['a' => 'Apple', new Option('b', 'Banana', '', OptionType::Option, TRUE, 'nope')]);

    $this->assertSame('a', $options[0]->value);
    $this->assertTrue($options[1]->disabled);
    $this->assertSame('nope', $options[1]->disabledReason);
  }

  #[DataProvider('dataProviderSelectable')]
  public function testSelectable(Option $option, bool $expected): void {
    $this->assertSame($expected, $option->isSelectable());
  }

  public static function dataProviderSelectable(): \Iterator {
    yield 'plain option' => [new Option('a', 'A'), TRUE];
    yield 'disabled option' => [new Option('a', 'A', '', OptionType::Option, TRUE), FALSE];
    yield 'separator' => [new Option('', '', '', OptionType::Separator), FALSE];
    yield 'heading' => [new Option('', 'Group', '', OptionType::Heading), FALSE];
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
    $this->assertSame($expected, (new Field('f', 'F', $type))->multiple($multiple)->isMultiChoice());
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
    $this->assertSame($expected, (new Field('f', 'F', $type))->multiple($multiple)->collectsList());
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
    $this->assertSame($expected, (new Field('f', 'F', $type))->multiple($multiple)->acceptsValue($value));
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
    $this->assertSame($expected, (new Field('f', 'F', $type))->multiple($multiple)->valueType());
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

  #[DataProvider('dataProviderSupportsPlaceholder')]
  public function testSupportsPlaceholder(FieldType $type, bool $expected): void {
    $this->assertSame($expected, $type->supportsPlaceholder());
  }

  public static function dataProviderSupportsPlaceholder(): \Iterator {
    yield 'text' => [FieldType::Text, TRUE];
    yield 'number' => [FieldType::Number, TRUE];
    yield 'textarea' => [FieldType::Textarea, TRUE];
    yield 'password' => [FieldType::Password, TRUE];
    yield 'suggest' => [FieldType::Suggest, TRUE];
    yield 'search' => [FieldType::Search, TRUE];
    // A template ghosts each empty slot with that slot's own label, so a
    // field-level placeholder would compete with it.
    yield 'template' => [FieldType::Template, FALSE];
    yield 'select' => [FieldType::Select, FALSE];
    yield 'confirm' => [FieldType::Confirm, FALSE];
  }

  public function testDeclaringMultipleOnUnsupportedTypeIsRefused(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Field "n" of type "number" does not collect several values');

    (new FieldBuilder('n', 'N', FieldType::Number))->multiple();
  }

  public function testSelectableValues(): void {
    $this->assertSame(['standard', 'minimal'], $this->selectField()->selectableValues());
  }

  #[DataProvider('dataProviderOptionError')]
  public function testOptionError(FieldType $type, bool $multiple, array $options, mixed $value, ?string $expected): void {
    $this->assertSame($expected, self::offering($type, $multiple, $options)->entryViolation($value));
  }

  public static function dataProviderOptionError(): \Iterator {
    $options = [
      new Option('standard', 'Standard'),
      new Option('minimal', 'Minimal'),
      new Option('demo', 'Demo', '', OptionType::Option, TRUE, 'unavailable'),
      new Option('legacy', 'Legacy', '', OptionType::Option, TRUE),
      new Option('', '', '', OptionType::Separator),
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
    return self::offering(FieldType::Select, FALSE, [
      new Option('standard', 'Standard'),
      new Option('', 'Group', '', OptionType::Heading),
      new Option('minimal', 'Minimal'),
      new Option('', '', '', OptionType::Separator),
      new Option('demo', 'Demo', '', OptionType::Option, TRUE, 'unavailable'),
    ]);
  }

  /**
   * A field offering the given rows, each declared as the kind of row it is.
   *
   * @param \DrevOps\Tui\Block\FieldType $type
   *   The kind of answer it collects.
   * @param bool $multiple
   *   Whether it collects several values.
   * @param array<array-key,\DrevOps\Tui\Block\Option> $options
   *   The rows it offers.
   *
   * @return \DrevOps\Tui\Block\Field
   *   The field.
   */
  protected static function offering(FieldType $type, bool $multiple, array $options): Field {
    $field = (new Field('f', 'F', $type))->multiple($multiple);

    foreach ($options as $option) {
      match ($option->kind) {
        OptionType::Heading => $field->heading($option->label),
        OptionType::Separator => $field->separator(),
        OptionType::Option => $field->entry($option->value, $option->label, $option->description, $option->disabled, $option->disabledReason),
      };
    }

    return $field;
  }

}
