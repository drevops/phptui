<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Engine;

use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Engine\Engine;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Handler\HandlerRegistry;
use DrevOps\Tui\Schema\SchemaValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the engine's answers model and its schema-validity.
 */
#[CoversClass(Engine::class)]
#[Group('engine')]
final class EngineAnswersTest extends TestCase {

  public function testAnswersModelReflectsRun(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('name')->default('');
        $p->text('machine')->default('')->derive(new Derive('{{name}}', 'machine'));
        $p->text('gone')->default('x')->when(new Condition('name', eq: 'never'));
      })
      ->build();
    $engine = new Engine($form, new HandlerRegistry());

    $answers = $engine->collect(['name' => 'Acme Site'], new Context());

    $this->assertSame('Acme Site', $answers->value('name'));
    $this->assertSame('acme_site', $answers->value('machine'));
    $this->assertSame(Provenance::Edited, $answers->provenanceOf('name'));
    $this->assertSame(Provenance::Derived, $answers->provenanceOf('machine'));
    $this->assertFalse($answers->has('gone'));
  }

  public function testEmittedSetValidatesAgainstSchema(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('name')->required()->default('Acme');
        $p->select('profile')->default('standard')->option('standard')->option('minimal');
      });
    $engine = new Engine($form->build(), new HandlerRegistry());

    $answers = $engine->collect([], new Context());

    $errors = (new SchemaValidator($form->root()))->validate($answers->values);
    $this->assertSame([], $errors);
  }

}
