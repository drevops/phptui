<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Resolver;

use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\FieldType;
use DrevOps\Tui\Resolver\EnvNameResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the environment variable naming rule.
 */
#[CoversClass(EnvNameResolver::class)]
#[Group('resolver')]
final class EnvNameResolverTest extends TestCase {

  #[DataProvider('dataProviderCanonical')]
  public function testCanonical(string $prefix, string $env_name, string $expected): void {
    $this->assertSame($expected, (new EnvNameResolver($prefix))->canonical(self::field($env_name)));
  }

  public static function dataProviderCanonical(): \Iterator {
    yield 'mechanical name uppercases the id under the prefix' => ['APP_', '', 'APP_CRATE_SIZE'];
    yield 'mechanical name without a prefix is the bare id' => ['', '', 'CRATE_SIZE'];
    // The override is absolute, so the prefix is not applied to it and its
    // casing is preserved - it reproduces a name published elsewhere.
    yield 'override replaces the mechanical name' => ['APP_', 'LEGACY_CRATE', 'LEGACY_CRATE'];
    yield 'override ignores the prefix' => ['ORCHARD_', 'LEGACY_CRATE', 'LEGACY_CRATE'];
    yield 'override keeps its own casing' => ['APP_', 'legacy_crate', 'legacy_crate'];
  }

  public function testAliasesAreReportedInDeclarationOrder(): void {
    $this->assertSame(['OLD_CRATE', 'OLDER_CRATE'], (new EnvNameResolver('APP_'))->aliases(self::field('', ['OLD_CRATE', 'OLDER_CRATE'])));
  }

  public function testAliasesAreEmptyWhenNoneDeclared(): void {
    $this->assertSame([], (new EnvNameResolver('APP_'))->aliases(self::field()));
  }

  /**
   * Tests that every answering name is reported in precedence order.
   *
   * @param string $env_name
   *   The declared name, or empty to keep the mechanical one.
   * @param list<string> $aliases
   *   The declared aliases.
   * @param list<string> $expected
   *   The expected names, in precedence order.
   */
  #[DataProvider('dataProviderAll')]
  public function testAll(string $env_name, array $aliases, array $expected): void {
    $this->assertSame($expected, (new EnvNameResolver('APP_'))->all(self::field($env_name, $aliases)));
  }

  public static function dataProviderAll(): \Iterator {
    yield 'mechanical name only' => ['', [], ['APP_CRATE_SIZE']];
    yield 'canonical name comes before every alias' => ['', ['OLD_CRATE'], ['APP_CRATE_SIZE', 'OLD_CRATE']];
    yield 'override leads its own aliases' => ['NEW_CRATE', ['OLD_CRATE', 'OLDER_CRATE'], ['NEW_CRATE', 'OLD_CRATE', 'OLDER_CRATE']];
  }

  #[DataProvider('dataProviderIsAdvertisable')]
  public function testIsAdvertisable(string $prefix, string $env_name, bool $expected): void {
    $this->assertSame($expected, (new EnvNameResolver($prefix))->isAdvertisable(self::field($env_name)));
  }

  public static function dataProviderIsAdvertisable(): \Iterator {
    yield 'a prefix namespaces the mechanical name' => ['APP_', '', TRUE];
    yield 'an override names itself' => ['', 'LEGACY_CRATE', TRUE];
    yield 'a prefixed override names itself' => ['APP_', 'LEGACY_CRATE', TRUE];
    // A bare, unnamespaced variable collides with anything else in the
    // environment, so it is not offered as an answer route.
    yield 'a bare mechanical name is not advertised' => ['', '', FALSE];
  }

  /**
   * A field declaring the environment names under test.
   *
   * @param string $env_name
   *   The declared name, or empty to keep the mechanical one.
   * @param list<string> $aliases
   *   The declared aliases.
   *
   * @return \DrevOps\Tui\Block\Field
   *   The field.
   */
  protected static function field(string $env_name = '', array $aliases = []): Field {
    $field = new Field('crate_size', 'Crate size', FieldType::Text);

    if ($env_name !== '') {
      $field->env($env_name);
    }

    if ($aliases !== []) {
      $field->envAliases($aliases);
    }

    return $field;
  }

}
