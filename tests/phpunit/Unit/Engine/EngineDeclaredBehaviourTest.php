<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Engine;

use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Engine\Engine;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Handler\HandlerRegistry;
use DrevOps\Tui\Tests\Fixtures\Handler\Spy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests field-declared behaviour: closures on the field, handler fallback.
 */
#[CoversClass(Engine::class)]
#[Group('engine')]
final class EngineDeclaredBehaviourTest extends TestCase {

  protected function setUp(): void {
    parent::setUp();
    Spy::$calls = [];
  }

  public function testDeclaredDefaultFromContext(): void {
    $engine = $this->engine(function (PanelBuilder $p): void {
      $p->text('name')->default(fn (Context $c): string => 'from-' . basename($c->directory));
    });

    $answers = $engine->collect([], new Context('some/project'));

    $this->assertSame('from-project', $answers->value('name'));
    $this->assertSame(Provenance::Default, $answers->provenanceOf('name'));
  }

  public function testDeclaredDefaultOverriddenByInput(): void {
    $engine = $this->engine(function (PanelBuilder $p): void {
      $p->text('name')->default(fn (Context $c): string => 'dynamic');
    });

    $this->assertSame(['name' => 'given'], $engine->collect(['name' => 'given'], new Context())->values);
  }

  public function testDeclaredValidateRejects(): void {
    $engine = $this->engine(function (PanelBuilder $p): void {
      $p->text('name')->validate(fn (mixed $v): ?string => $v === 'ok' ? NULL : 'Must be "ok".');
    });

    $this->assertSame(['name' => 'ok'], $engine->collect(['name' => 'ok'], new Context())->values);

    $this->expectException(EngineException::class);
    $this->expectExceptionMessage('Invalid value for field "name": Must be "ok".');
    $engine->collect(['name' => 'nope'], new Context());
  }

  /**
   * A required field rejects every empty supplied input, whatever its shape.
   *
   * @param string $id
   *   The id of the field the value is supplied for.
   * @param mixed $value
   *   The supplied input.
   * @param string $expected
   *   The expected exception message.
   */
  #[DataProvider('dataProviderRequiredRejectsEmpty')]
  public function testRequiredRejectsEmpty(string $id, mixed $value, string $expected): void {
    $engine = $this->engine(function (PanelBuilder $p): void {
      $p->text('name', 'Produce name')->required();
      $p->select('crates', 'Crates')->multiple()->required()->option('a')->option('b');
    });

    $this->expectException(EngineException::class);
    $this->expectExceptionMessage($expected);
    $engine->collect([$id => $value], new Context());
  }

  /**
   * Data provider for testRequiredRejectsEmpty().
   *
   * @return \Iterator<string,array{string,mixed,string}>
   *   The field id, the empty value supplied for it and the expected message.
   */
  public static function dataProviderRequiredRejectsEmpty(): \Iterator {
    yield 'empty string' => ['name', '', 'Invalid value for field "name": Produce name is required.'];
    yield 'null' => ['name', NULL, 'Invalid value for field "name": Produce name is required.'];
    yield 'empty list' => ['crates', [], 'Invalid value for field "crates": Crates is required.'];
  }

  public function testRequiredAcceptsValue(): void {
    $engine = $this->engine(function (PanelBuilder $p): void {
      $p->text('name', 'Produce name')->required();
    });

    $this->assertSame(['name' => 'Pear'], $engine->collect(['name' => 'Pear'], new Context())->values);
  }

  public function testRequiredMessageOverridesTheDerivedOne(): void {
    $engine = $this->engine(function (PanelBuilder $p): void {
      $p->text('plot', 'Garden plot name')->required(message: 'The garden plot name is required.');
    });

    $this->expectException(EngineException::class);
    $this->expectExceptionMessage('Invalid value for field "plot": The garden plot name is required.');
    $engine->collect(['plot' => ''], new Context());
  }

  public function testRequiredRunsBeforeTheDeclaredValidator(): void {
    $engine = $this->engine(function (PanelBuilder $p): void {
      $p->text('name', 'Produce name')->required()->validate(fn (mixed $v): ?string => $v === 'Pear' ? NULL : 'Only pears keep.');
    });

    $this->expectException(EngineException::class);
    $this->expectExceptionMessage('Invalid value for field "name": Produce name is required.');
    $engine->collect(['name' => ''], new Context());
  }

  public function testRequiredLeavesAnUnsuppliedFieldAlone(): void {
    $engine = $this->engine(function (PanelBuilder $p): void {
      $p->text('name', 'Produce name')->required();
    });

    // The guard weighs supplied inputs only, so an empty default is
    // collected as it stands - reporting it is the schema validator's job.
    $this->assertSame(['name' => ''], $engine->collect([], new Context())->values);
  }

  public function testRequiredIgnoresAnInactiveField(): void {
    $engine = $this->engine(function (PanelBuilder $p): void {
      $p->text('mode', 'Mode');
      $p->text('plot', 'Garden plot name')->required()->when(new Condition('mode', eq: 'custom'));
    });

    $this->assertSame(['mode' => 'standard'], $engine->collect(['mode' => 'standard', 'plot' => ''], new Context())->values);
  }

  public function testNumberBoundsRejectOutOfRange(): void {
    $engine = $this->engine(function (PanelBuilder $p): void {
      $p->number('port')->min(1)->max(10);
    });

    $this->assertSame(['port' => 5], $engine->collect(['port' => 5], new Context())->values);

    $this->expectException(EngineException::class);
    $this->expectExceptionMessage('Invalid value for field "port": must be between 1 and 10.');
    $engine->collect(['port' => 50], new Context());
  }

  public function testNumberBoundsRejectOutOfRangeFloat(): void {
    $engine = $this->engine(function (PanelBuilder $p): void {
      $p->number('port')->min(1)->max(10);
    });

    // A float outside the range is rejected too - bounds are not integer-gated.
    $this->expectException(EngineException::class);
    $this->expectExceptionMessage('Invalid value for field "port": must be between 1 and 10.');
    $engine->collect(['port' => 50.5], new Context());
  }

  public function testDateBoundsRejectOutOfRange(): void {
    $engine = $this->engine(function (PanelBuilder $p): void {
      $p->calendar('due')->minDate('2026-01-01')->maxDate('2026-12-31');
    });

    $this->assertSame(['due' => '2026-06-15'], $engine->collect(['due' => '2026-06-15'], new Context())->values);

    $this->expectException(EngineException::class);
    $this->expectExceptionMessage('Invalid value for field "due": must be between 2026-01-01 and 2026-12-31.');
    $engine->collect(['due' => '2027-01-01'], new Context());
  }

  public function testDeclaredTransformApplies(): void {
    $engine = $this->engine(function (PanelBuilder $p): void {
      $p->text('name')->transform(fn (mixed $v): mixed => is_string($v) ? trim($v) : $v);
    });

    $this->assertSame(['name' => 'Acme'], $engine->collect(['name' => '  Acme  '], new Context())->values);
  }

  public function testTransformedInputDrivesConditions(): void {
    $engine = $this->engine(function (PanelBuilder $p): void {
      $p->text('mode')->transform(fn (mixed $v): mixed => is_string($v) ? trim($v) : $v);
      $p->text('extra')->default('on')->when(new Condition('mode', eq: 'custom'));
    });

    $answers = $engine->collect(['mode' => '  custom  '], new Context());

    // Inputs normalize before stabilization, so the condition matches the
    // trimmed value and activates the dependent field.
    $this->assertSame(['mode' => 'custom', 'extra' => 'on'], $answers->values);
  }

  public function testDeclaredDiscoverClosure(): void {
    $engine = $this->engine(function (PanelBuilder $p): void {
      $p->text('name')->discover(fn (Context $c): string => 'seen-' . basename($c->directory));
    });

    $answers = $engine->collect([], new Context('some/project', [], TRUE));

    $this->assertSame('seen-project', $answers->value('name'));
    $this->assertSame(Provenance::Detected, $answers->provenanceOf('name'));
  }

  public function testDeclarationWinsOverHandler(): void {
    // The "spy" field resolves to the Spy fixture class, but the declared
    // closures take precedence over its reusable statics.
    $engine = $this->engine(function (PanelBuilder $p): void {
      $p->text('spy')
        ->validate(fn (mixed $v): ?string => NULL)
        ->transform(fn (mixed $v): mixed => is_string($v) ? $v . '?' : $v);
    });

    $answers = $engine->collect(['spy' => 'declared'], new Context('project'));

    $this->assertSame('declared?', $answers->value('spy'));
    $this->assertSame([], Spy::$calls);
  }

  /**
   * Build an engine over a single panel wired to the fixture handlers.
   *
   * @param \Closure $build
   *   The callback receiving the panel builder to declare its fields.
   */
  protected function engine(\Closure $build): Engine {
    $form = Form::create('T')->panel('p', 'p', $build)->build();

    return new Engine($form, new HandlerRegistry(['DrevOps\\Tui\\Tests\\Fixtures\\Handler']));
  }

}
