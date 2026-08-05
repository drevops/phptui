<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Resolver;

use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\FieldType;
use DrevOps\Tui\Resolver\InputResolver;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the non-interactive input resolver.
 */
#[CoversClass(InputResolver::class)]
#[Group('resolver')]
final class InputResolverTest extends TestCase {

  #[DataProvider('dataProviderEnvValueCoercion')]
  public function testEnvValueCoercion(string $variable, string $raw, string $field, mixed $expected): void {
    $inputs = (new InputResolver('APP_'))->resolve($this->fields(), '', [$variable => $raw]);

    $this->assertSame($expected, $inputs[$field]);
  }

  /**
   * Data provider for testEnvValueCoercion().
   *
   * @return \Iterator<string, array{string, string, string, mixed}>
   *   The variable, the raw value it carries, the field it answers and the
   *   value that field settles on.
   */
  public static function dataProviderEnvValueCoercion(): \Iterator {
    yield 'text passes through' => ['APP_NAME', 'Acme', 'name', 'Acme'];
    yield 'truthy confirm' => ['APP_AGREE', 'yes', 'agree', TRUE];
    yield 'falsey confirm' => ['APP_AGREE', 'no', 'agree', FALSE];
    yield 'pause reads like a confirm' => ['APP_ACK', 'yes', 'ack', TRUE];
    yield 'toggle passes through' => ['APP_VIS', 'private', 'vis', 'private'];
    yield 'date passes through' => ['APP_DUE', '2026-07-15', 'due', '2026-07-15'];
    yield 'number trims and casts' => ['APP_PORT', ' 8080 ', 'port', 8080];
    yield 'rating trims and casts' => ['APP_TASTE', ' 4 ', 'taste', 4];
    // Left as typed so the collection rejects it instead of it becoming a 0.
    yield 'non-integral rating stays a string' => ['APP_TASTE', 'great', 'taste', 'great'];
    yield 'multiple select splits a comma list' => ['APP_MODS', 'a, b ,c', 'mods', ['a', 'b', 'c']];
    yield 'empty multiple select' => ['APP_MODS', '', 'mods', []];
    yield 'multiple search splits a comma list' => ['APP_TAGS', 'a, b', 'tags', ['a', 'b']];
    yield 'reorder splits a comma list' => ['APP_RANK', 'c, a, b', 'rank', ['c', 'a', 'b']];
    yield 'multiple picker splits a comma list' => ['APP_PATHS', 'a/b, c/d', 'paths', ['a/b', 'c/d']];
    yield 'single picker stays a string' => ['APP_CFG', '/etc/app.yml', 'cfg', '/etc/app.yml'];
  }

  #[DataProvider('dataProviderEnvNameResolution')]
  public function testEnvNameResolution(Field $field, array $env, array $expected): void {
    $this->assertSame($expected, (new InputResolver('APP_'))->resolve([$field], '', $env));
  }

  /**
   * Data provider for testEnvNameResolution().
   *
   * @return \Iterator<string, array{\DrevOps\Tui\Block\Field, array<string,string>, array<string,mixed>}>
   *   The field, the environment it is resolved against and the inputs it
   *   contributes.
   */
  public static function dataProviderEnvNameResolution(): \Iterator {
    yield 'mechanical name is the prefixed field id' => [
      new Field('machine_name', 'Machine', FieldType::Text),
      ['APP_MACHINE_NAME' => 'x'],
      ['machine_name' => 'x'],
    ];

    yield 'declared name replaces the mechanical one' => [
      (new Field('crate_size', 'Crate size', FieldType::Text))->env('LEGACY_CRATE'),
      ['LEGACY_CRATE' => 'large', 'APP_CRATE_SIZE' => 'small'],
      ['crate_size' => 'large'],
    ];

    yield 'mechanical name is not read once replaced' => [
      (new Field('crate_size', 'Crate size', FieldType::Text))->env('LEGACY_CRATE'),
      ['APP_CRATE_SIZE' => 'small'],
      [],
    ];

    yield 'alias answers when the canonical name is unset' => [
      (new Field('crate_size', 'Crate size', FieldType::Text))->envAliases(['OLD_CRATE']),
      ['OLD_CRATE' => 'large'],
      ['crate_size' => 'large'],
    ];

    yield 'canonical name wins over an alias' => [
      (new Field('crate_size', 'Crate size', FieldType::Text))->envAliases(['OLD_CRATE']),
      ['OLD_CRATE' => 'large', 'APP_CRATE_SIZE' => 'small'],
      ['crate_size' => 'small'],
    ];

    yield 'earlier alias wins over a later one' => [
      (new Field('crate_size', 'Crate size', FieldType::Text))->envAliases(['OLD_CRATE', 'OLDER_CRATE']),
      ['OLDER_CRATE' => 'small', 'OLD_CRATE' => 'large'],
      ['crate_size' => 'large'],
    ];

    yield 'alias value is coerced like the canonical one' => [
      (new Field('organic', 'Organic', FieldType::Confirm))->envAliases(['OLD_ORGANIC']),
      ['OLD_ORGANIC' => 'yes'],
      ['organic' => TRUE],
    ];
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

  /**
   * Build one field of each coercible type for resolution.
   *
   * @return list<\DrevOps\Tui\Block\Field>
   *   The fields.
   */
  protected function fields(): array {
    return [
      new Field('name', 'Name', FieldType::Text),
      new Field('agree', 'Agree', FieldType::Confirm),
      (new Field('mods', 'Mods', FieldType::Select))->multiple(),
      new Field('port', 'Port', FieldType::Number),
      new Field('taste', 'Taste', FieldType::Rating),
      new Field('ack', 'Ack', FieldType::Pause),
      (new Field('tags', 'Tags', FieldType::Search))->multiple(),
      new Field('rank', 'Rank', FieldType::Reorder),
      new Field('vis', 'Visibility', FieldType::Toggle),
      (new Field('paths', 'Paths', FieldType::FilePicker))->multiple(),
      new Field('cfg', 'Config', FieldType::FilePicker),
      new Field('due', 'Due', FieldType::Calendar),
    ];
  }

}
