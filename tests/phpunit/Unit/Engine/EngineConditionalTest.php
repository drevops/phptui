<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Engine;

use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Model\Fixup;
use DrevOps\Tui\Engine\Engine;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Handler\HandlerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests conditional gating and post-submit fix-ups in the engine.
 */
#[CoversClass(Engine::class)]
#[Group('engine')]
final class EngineConditionalTest extends TestCase {

  public function testInactiveFieldExcluded(): void {
    $engine = $this->engine(
      Form::create('T')
        ->panel('p', 'p', function (PanelBuilder $p): void {
          $p->text('theme')->default('olivero');
          $p->text('custom_theme')->default('mytheme')->when(new Condition('theme', eq: 'custom'));
        })
        ->build()
    );

    $answers = $engine->collect([], new Context());
    $this->assertTrue($answers->has('theme'));
    $this->assertFalse($answers->has('custom_theme'));

    $answers = $engine->collect(['theme' => 'custom'], new Context());
    $this->assertTrue($answers->has('custom_theme'));
    $this->assertSame('mytheme', $answers->value('custom_theme'));
  }

  public function testForceFixupAutoResolves(): void {
    $engine = $this->engine(
      Form::create('T')
        ->panel('p', 'p', function (PanelBuilder $p): void {
          $p->text('provision')->default('database');
          $p->text('database_source')->default('url');
        })
        ->fixup(new Fixup(set: 'database_source', to: 'none', when: new Condition('provision', eq: 'profile')))
        ->build()
    );

    $this->assertSame('url', $engine->collect([], new Context())->value('database_source'));
    // No input for database_source: the fix-up resolves it without prompting.
    $this->assertSame('none', $engine->collect(['provision' => 'profile'], new Context())->value('database_source'));
  }

  public function testFixupTargetingNoteDoesNotReintroduceIt(): void {
    $engine = $this->engine(
      Form::create('T')
        ->panel('p', 'p', function (PanelBuilder $p): void {
          $p->text('provision')->default('database');
          $p->note('intro', 'Intro')->description('Welcome.');
        })
        // A fix-up that mistakenly targets a note is ignored, so the note never
        // gains a value in the settled state.
        ->fixup(new Fixup(set: 'intro', to: 'forced'))
        ->build()
    );

    [$values] = $engine->resolveState([], new Context());

    $this->assertArrayNotHasKey('intro', $values);
    $this->assertArrayHasKey('provision', $values);
  }

  public function testFixupReadingFromNoteKeepsTargetSettled(): void {
    $engine = $this->engine(
      Form::create('T')
        ->panel('p', 'p', function (PanelBuilder $p): void {
          $p->text('target')->default('kept');
          $p->note('intro', 'Intro')->description('Welcome.');
        })
        // Copying from a note would read its absent value and write NULL over
        // the target; the rule is ignored, so the target keeps its value.
        ->fixup(new Fixup(set: 'target', from: 'intro'))
        ->build()
    );

    $this->assertSame('kept', $engine->collect([], new Context())->value('target'));
  }

  public function testMultiFieldConditional(): void {
    // A when can depend on any number of fields via all / any / not.
    $engine = $this->engine(
      Form::create('T')
        ->panel('p', 'p', function (PanelBuilder $p): void {
          $p->text('a')->default('x');
          $p->text('b')->default('y');
          $p->text('c')->default('z')->when(Condition::all(new Condition('a', eq: 'x'), new Condition('b', eq: 'y')));
        })
        ->build()
    );

    // Both conditions hold: c is active.
    $this->assertTrue($engine->collect([], new Context())->has('c'));

    // One condition fails: c is gated out.
    $this->assertFalse($engine->collect(['b' => 'other'], new Context())->has('c'));
  }

  public function testMergeCustomFixup(): void {
    $engine = $this->engine(
      Form::create('T')
        ->panel('p', 'p', function (PanelBuilder $p): void {
          $p->text('profile')->default('standard');
          $p->text('profile_custom')->default('');
        })
        ->fixup(new Fixup(set: 'profile', from: 'profile_custom', when: new Condition('profile', eq: 'custom')))
        ->build()
    );

    $answers = $engine->collect(['profile' => 'custom', 'profile_custom' => 'my_profile'], new Context());
    $this->assertSame('my_profile', $answers->value('profile'));
  }

  public function testCascadingDeactivation(): void {
    $engine = $this->engine(
      Form::create('T')
        ->panel('p', 'p', function (PanelBuilder $p): void {
          $p->text('a')->default('x');
          $p->text('b')->default('y')->when(new Condition('a', eq: 'x'));
          $p->text('c')->default('z')->when(new Condition('b', eq: 'y'));
        })
        ->build()
    );

    $this->assertSame(['a' => 'x', 'b' => 'y', 'c' => 'z'], $engine->collect([], new Context())->values);
    $this->assertSame(['a' => 'off'], $engine->collect(['a' => 'off'], new Context())->values);
  }

  public function testResolveStateKeepsInactiveFields(): void {
    $engine = $this->gatedEngine();

    [$values, $provenance, $active] = $engine->resolveState([], new Context());

    // The inactive field keeps its settled value and provenance; only the
    // active map records that it is gated out.
    $this->assertSame(['theme' => 'olivero', 'custom_theme' => 'mytheme'], $values);
    $this->assertSame(['theme' => TRUE, 'custom_theme' => FALSE], $active);
    $this->assertSame(Provenance::Default, $provenance['custom_theme']);
  }

  public function testSettleReappliesFormLogic(): void {
    $engine = $this->gatedEngine();

    [$active] = $engine->settle(['theme' => 'custom', 'custom_theme' => 'mytheme'], []);
    $this->assertSame(['theme' => TRUE, 'custom_theme' => TRUE], $active);

    [$active] = $engine->settle(['theme' => 'olivero', 'custom_theme' => 'mytheme'], []);
    $this->assertFalse($active['custom_theme']);
  }

  /**
   * Build an engine over a form whose second field is gated by the first.
   */
  protected function gatedEngine(): Engine {
    return $this->engine(
      Form::create('T')
        ->panel('p', 'p', function (PanelBuilder $p): void {
          $p->text('theme')->default('olivero');
          $p->text('custom_theme')->default('mytheme')->when(new Condition('theme', eq: 'custom'));
        })
        ->build()
    );
  }

  /**
   * Build an engine over the given form definition with no handlers.
   *
   * @param \DrevOps\Tui\Model\FormDefinition $form
   *   The form definition.
   */
  protected function engine(FormDefinition $form): Engine {
    return new Engine($form, new HandlerRegistry());
  }

}
