<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Resolver;

use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\NumberBounds;
use DrevOps\Tui\Resolver\InputResolver;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the non-interactive input resolver.
 */
#[CoversClass(InputResolver::class)]
#[Group('resolver')]
final class InputResolverTest extends TestCase {

  public function testEnvCoercion(): void {
    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), '', [
      'APP_NAME' => 'Acme',
      'APP_AGREE' => 'yes',
      'APP_MODS' => 'a, b ,c',
    ]);

    $this->assertSame('Acme', $inputs['name']);
    $this->assertTrue($inputs['agree']);
    $this->assertSame(['a', 'b', 'c'], $inputs['mods']);
  }

  public function testConfirmFalsey(): void {
    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), '', ['APP_AGREE' => 'no']);

    $this->assertFalse($inputs['agree']);
  }

  public function testToggleCoercion(): void {
    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), '', ['APP_VIS' => 'private']);

    $this->assertSame('private', $inputs['vis']);
  }

  public function testEmptyMultiselect(): void {
    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), '', ['APP_MODS' => '']);

    $this->assertSame([], $inputs['mods']);
  }

  public function testPromptsJsonWinsOverEnv(): void {
    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), '{"name": "FromPrompts", "agree": true}', [
      'APP_NAME' => 'FromEnv',
      'APP_AGREE' => 'no',
    ]);

    $this->assertSame('FromPrompts', $inputs['name']);
    $this->assertTrue($inputs['agree']);
  }

  public function testMissingEnvOmitsField(): void {
    $this->assertSame([], (new InputResolver('APP_'))->resolve($this->fields(), '', []));
  }

  public function testMalformedPromptsThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('--prompts');

    (new InputResolver('APP_'))->resolve($this->fields(), 'not json', ['APP_NAME' => 'Acme']);
  }

  public function testPromptsFromFile(): void {
    vfsStream::setup('p', NULL, ['prompts.json' => '{"name": "FromFile"}']);

    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), vfsStream::url('p/prompts.json'), []);

    $this->assertSame('FromFile', $inputs['name']);
  }

  public function testDateCoercionPassesThroughString(): void {
    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), '', ['APP_DUE' => '2026-07-15']);

    $this->assertSame('2026-07-15', $inputs['due']);
  }

  public function testEnvNameUppercasesTheFieldId(): void {
    $fields = [new Field('machine_name', 'Machine', '', FieldType::Text, '')];

    $inputs = (new InputResolver('APP_'))->resolve($fields, '', ['APP_MACHINE_NAME' => 'x']);

    $this->assertSame(['machine_name' => 'x'], $inputs);
  }

  public function testDeclaredEnvNameReplacesTheMechanicalOne(): void {
    $fields = [new Field('crate_size', 'Crate size', '', FieldType::Text, '', envName: 'LEGACY_CRATE')];

    $inputs = (new InputResolver('APP_'))->resolve($fields, '', [
      'LEGACY_CRATE' => 'large',
      'APP_CRATE_SIZE' => 'small',
    ]);

    $this->assertSame(['crate_size' => 'large'], $inputs);
  }

  public function testMechanicalNameIsNotReadOnceReplaced(): void {
    $fields = [new Field('crate_size', 'Crate size', '', FieldType::Text, '', envName: 'LEGACY_CRATE')];

    $this->assertSame([], (new InputResolver('APP_'))->resolve($fields, '', ['APP_CRATE_SIZE' => 'small']));
  }

  public function testAliasAnswersWhenTheCanonicalNameIsUnset(): void {
    $fields = [new Field('crate_size', 'Crate size', '', FieldType::Text, '', envAliases: ['OLD_CRATE'])];

    $inputs = (new InputResolver('APP_'))->resolve($fields, '', ['OLD_CRATE' => 'large']);

    $this->assertSame(['crate_size' => 'large'], $inputs);
  }

  public function testCanonicalNameWinsOverAnAlias(): void {
    $fields = [new Field('crate_size', 'Crate size', '', FieldType::Text, '', envAliases: ['OLD_CRATE'])];

    $inputs = (new InputResolver('APP_'))->resolve($fields, '', [
      'OLD_CRATE' => 'large',
      'APP_CRATE_SIZE' => 'small',
    ]);

    $this->assertSame(['crate_size' => 'small'], $inputs);
  }

  public function testEarlierAliasWinsOverLaterOne(): void {
    $fields = [new Field('crate_size', 'Crate size', '', FieldType::Text, '', envAliases: ['OLD_CRATE', 'OLDER_CRATE'])];

    $inputs = (new InputResolver('APP_'))->resolve($fields, '', [
      'OLDER_CRATE' => 'small',
      'OLD_CRATE' => 'large',
    ]);

    $this->assertSame(['crate_size' => 'large'], $inputs);
  }

  public function testAliasValueIsCoercedLikeTheCanonicalOne(): void {
    $fields = [new Field('organic', 'Organic', '', FieldType::Confirm, FALSE, envAliases: ['OLD_ORGANIC'])];

    $inputs = (new InputResolver('APP_'))->resolve($fields, '', ['OLD_ORGANIC' => 'yes']);

    $this->assertTrue($inputs['organic']);
  }

  public function testFilePickerCoercion(): void {
    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), '', [
      'APP_PATHS' => 'a/b, c/d',
      'APP_CFG' => '/etc/app.yml',
    ]);

    // A multiple picker splits a comma list; a single picker stays a string.
    $this->assertSame(['a/b', 'c/d'], $inputs['paths']);
    $this->assertSame('/etc/app.yml', $inputs['cfg']);
  }

  public function testNumberPauseAndMultisearchCoercion(): void {
    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), '', [
      'APP_PORT' => ' 8080 ',
      'APP_ACK' => 'yes',
      'APP_TAGS' => 'a, b',
    ]);

    $this->assertSame(8080, $inputs['port']);
    $this->assertTrue($inputs['ack']);
    $this->assertSame(['a', 'b'], $inputs['tags']);
  }

  public function testRatingCoercion(): void {
    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), '', [
      'APP_TASTE' => ' 4 ',
    ]);

    $this->assertSame(4, $inputs['taste']);
  }

  public function testRatingNonIntegralValueStaysString(): void {
    // Left as typed so the engine rejects it instead of it becoming a 0.
    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), '', ['APP_TASTE' => 'great']);

    $this->assertSame('great', $inputs['taste']);
  }

  public function testReorderCoercion(): void {
    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), '', ['APP_RANK' => 'c, a, b']);

    $this->assertSame(['c', 'a', 'b'], $inputs['rank']);
  }

  /**
   * Build one field of each coercible type for resolution.
   *
   * @return \DrevOps\Tui\Model\Field[]
   *   The fields.
   */
  protected function fields(): array {
    return [
      new Field('name', 'Name', '', FieldType::Text, ''),
      new Field('agree', 'Agree', '', FieldType::Confirm, FALSE),
      new Field('mods', 'Mods', '', FieldType::Select, [], multiple: TRUE),
      new Field('port', 'Port', '', FieldType::Number, 0),
      new Field('taste', 'Taste', '', FieldType::Rating, 1, bounds: new NumberBounds(1, 5)),
      new Field('ack', 'Ack', '', FieldType::Pause, TRUE),
      new Field('tags', 'Tags', '', FieldType::Search, [], multiple: TRUE),
      new Field('rank', 'Rank', '', FieldType::Reorder, []),
      new Field('vis', 'Visibility', '', FieldType::Toggle, 'public'),
      new Field('paths', 'Paths', '', FieldType::FilePicker, [], multiple: TRUE),
      new Field('cfg', 'Config', '', FieldType::FilePicker, ''),
      new Field('due', 'Due', '', FieldType::Calendar, ''),
    ];
  }

}
