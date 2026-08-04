<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Schema;

use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Tree;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Schema\DefaultResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the schema default resolver.
 */
#[CoversClass(DefaultResolver::class)]
#[Group('schema')]
final class DefaultResolverTest extends TestCase {

  public function testLiteralDefaultReturnedAsIs(): void {
    $field = self::field(function (PanelBuilder $p): void {
      $p->text('x')->default('hello');
    });

    $this->assertSame('hello', DefaultResolver::resolve($field, new Context()));
  }

  public function testLiteralNullDefault(): void {
    $field = self::field(function (PanelBuilder $p): void {
      $p->text('x')->default(NULL);
    });

    $this->assertNull(DefaultResolver::resolve($field, new Context()));
  }

  public function testClosureResolvedAgainstEmptyContext(): void {
    $field = self::field(function (PanelBuilder $p): void {
      $p->text('x')->default(fn (Context $context): string => 'computed');
    });

    $this->assertSame('computed', DefaultResolver::resolve($field, new Context()));
  }

  public function testClosureReadsTheProvidedContext(): void {
    $field = self::field(function (PanelBuilder $p): void {
      $p->text('x')->default(fn (Context $context): string => $context->version);
    });

    $this->assertSame('1.2.3', DefaultResolver::resolve($field, new Context(version: '1.2.3')));
  }

  public function testDeclaredSchemaDefaultWinsOverClosure(): void {
    $field = self::field(function (PanelBuilder $p): void {
      $p->text('x')->default(fn (Context $context): string => 'live')->schemaDefault('static');
    });

    $this->assertSame('static', DefaultResolver::resolve($field, new Context()));
  }

  public function testDeclaredSchemaDefaultWinsEvenWhenClosureCouldResolve(): void {
    $field = self::field(function (PanelBuilder $p): void {
      $p->text('x')->default(fn (Context $context): string => $context->version)->schemaDefault('static');
    });

    // The declared stand-in is advertised even when the closure could resolve
    // cleanly against the given context: the declaration wins, deliberately.
    $this->assertSame('static', DefaultResolver::resolve($field, new Context(version: '1.0')));
  }

  public function testDeclaredSchemaDefaultMayBeNull(): void {
    $field = self::field(function (PanelBuilder $p): void {
      $p->text('x')->default(fn (Context $context): string => 'live')->schemaDefault(NULL);
    });

    $this->assertNull(DefaultResolver::resolve($field, new Context()));
  }

  public function testThrowingClosureIsUnresolvable(): void {
    $field = self::field(function (PanelBuilder $p): void {
      $p->text('x')->default(fn (Context $context): string => throw new \RuntimeException('needs answers'));
    });

    $this->assertNull(DefaultResolver::resolve($field, new Context()));
  }

  public function testDeclaredSchemaDefaultSkipsThrowingClosure(): void {
    $field = self::field(function (PanelBuilder $p): void {
      $p->text('x')->default(fn (Context $context): string => throw new \RuntimeException('boom'))->schemaDefault('safe');
    });

    $this->assertSame('safe', DefaultResolver::resolve($field, new Context()));
  }

  /**
   * Build a single-field form and return its field.
   *
   * @param \Closure $configure
   *   The callback declaring one field on the panel builder.
   *
   * @return \DrevOps\Tui\Block\Field
   *   The declared field.
   */
  protected static function field(\Closure $configure): Field {
    return Tree::fields(Form::create('T')->panel('p', 'p', $configure)->root())[0];
  }

}
