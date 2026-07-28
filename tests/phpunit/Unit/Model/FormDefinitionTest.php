<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Model;

use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Model\Panel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the root form-definition model.
 */
#[CoversClass(FormDefinition::class)]
#[Group('model')]
final class FormDefinitionTest extends TestCase {

  public function testFieldsFlattensTreeInOrder(): void {
    $form = new FormDefinition('T', 'S', [
      new Panel('a', 'A', '', [new Field('f1', 'F1', '', FieldType::Text, '')], [
        new Panel('b', 'B', '', [new Field('f2', 'F2', '', FieldType::Text, '')]),
      ]),
      new Panel('c', 'C', '', [new Field('f3', 'F3', '', FieldType::Text, '')]),
    ]);

    $ids = array_map(static fn(Field $field): string => $field->id, $form->fields());

    $this->assertSame(['f1', 'f2', 'f3'], $ids);
  }

  public function testFieldFindsByIdAcrossTree(): void {
    $form = new FormDefinition('T', 'S', [
      new Panel('a', 'A', '', [new Field('top', 'T', '', FieldType::Text, '')], [
        new Panel('b', 'B', '', [new Field('deep', 'D', '', FieldType::Text, '')]),
      ]),
    ]);

    $this->assertSame('top', $form->field('top')?->id);
    $this->assertSame('deep', $form->field('deep')?->id);
    $this->assertNotInstanceOf(Field::class, $form->field('missing'));
  }

  /**
   * Tests the conditional depth stamped onto each field.
   *
   * @param array<string,\DrevOps\Tui\Condition\ConditionInterface|null> $rules
   *   The `when` rule of each field, keyed by the field id to declare.
   * @param array<string,int> $expected
   *   The depth each field is expected to resolve to, keyed by field id.
   */
  #[DataProvider('dataProviderConditionalDepth')]
  public function testConditionalDepth(array $rules, array $expected): void {
    $fields = [];
    foreach ($rules as $id => $rule) {
      $fields[] = new Field($id, strtoupper($id), '', FieldType::Text, '', when: $rule);
    }

    $form = new FormDefinition('T', 'S', [new Panel('p', 'P', '', $fields)]);

    $actual = [];
    foreach ($form->fields() as $field) {
      $actual[$field->id] = $field->conditionalDepth;
    }

    $this->assertSame($expected, $actual);
  }

  public static function dataProviderConditionalDepth(): \Iterator {
    yield 'unconditional fields stay flat' => [
      ['a' => NULL, 'b' => NULL],
      ['a' => 0, 'b' => 0],
    ];
    yield 'a rule over an unconditional field is one deep' => [
      ['a' => NULL, 'b' => new Condition('a', eq: 'x')],
      ['a' => 0, 'b' => 1],
    ];
    yield 'a chain deepens by one per link' => [
      [
        'a' => NULL,
        'b' => new Condition('a', eq: 'x'),
        'c' => new Condition('b', eq: 'y'),
        'd' => new Condition('c', eq: 'z'),
      ],
      ['a' => 0, 'b' => 1, 'c' => 2, 'd' => 3],
    ];
    yield 'a composite takes its deepest reference' => [
      [
        'a' => NULL,
        'b' => new Condition('a', eq: 'x'),
        'c' => Condition::all(new Condition('a', eq: 'x'), new Condition('b', eq: 'y')),
      ],
      ['a' => 0, 'b' => 1, 'c' => 2],
    ];
    yield 'a rule referencing an unknown field is one deep' => [
      ['a' => new Condition('nowhere', eq: 'x')],
      ['a' => 1],
    ];
    yield 'a forward reference resolves like a backward one' => [
      ['a' => new Condition('b', eq: 'x'), 'b' => new Condition('c', eq: 'y'), 'c' => NULL],
      ['a' => 2, 'b' => 1, 'c' => 0],
    ];
    yield 'a field referencing itself does not deepen forever' => [
      ['a' => new Condition('a', eq: 'x')],
      ['a' => 1],
    ];
    yield 'a cycle between two fields terminates' => [
      ['a' => new Condition('b', eq: 'x'), 'b' => new Condition('a', eq: 'y')],
      ['a' => 2, 'b' => 1],
    ];
    yield 'a negated rule counts like any other' => [
      ['a' => NULL, 'b' => Condition::not(new Condition('a', eq: 'x'))],
      ['a' => 0, 'b' => 1],
    ];
  }

}
