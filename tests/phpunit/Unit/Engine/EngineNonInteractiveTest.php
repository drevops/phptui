<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Engine;

use DrevOps\Tui\Answers\Answer;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Discovery\Dotenv;
use DrevOps\Tui\Engine\Engine;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Handler\HandlerRegistry;
use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Resolver\InputResolver;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the full non-interactive precedence chain end to end.
 */
#[CoversClass(Engine::class)]
#[CoversClass(InputResolver::class)]
#[Group('engine')]
final class EngineNonInteractiveTest extends TestCase {

  public function testFullPrecedence(): void {
    vfsStream::setup('proj', NULL, ['.env' => "DETECTED=from_env\n"]);
    $dir = vfsStream::url('proj');

    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('src')->default('seed');
        $p->text('target')->default('static')->derive(new Derive('d-{{src}}'))->discover(new Dotenv('DETECTED'));
      })
      ->build();
    $resolver = new InputResolver('APP_');
    $engine = new Engine($form, new HandlerRegistry());

    // Static default is overtaken by the derived value (fresh install).
    $inputs = $resolver->resolve($form->fields(), '', []);
    $this->assertSame('d-seed', $engine->collect($inputs, new Context($dir, [], FALSE))->value('target'));

    // Detected (update mode) wins over derived.
    $inputs = $resolver->resolve($form->fields(), '', []);
    $this->assertSame('from_env', $engine->collect($inputs, new Context($dir, [], TRUE))->value('target'));

    // Env wins over detected.
    $inputs = $resolver->resolve($form->fields(), '', ['APP_TARGET' => 'from_env_var']);
    $this->assertSame('from_env_var', $engine->collect($inputs, new Context($dir, [], TRUE))->value('target'));

    // --prompts wins over env.
    $inputs = $resolver->resolve($form->fields(), '{"target": "from_prompts"}', ['APP_TARGET' => 'from_env_var']);
    $this->assertSame('from_prompts', $engine->collect($inputs, new Context($dir, [], TRUE))->value('target'));
  }

  public function testReorderHeadlessParity(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->reorder('ranking')->options(['a' => 'A', 'b' => 'B', 'c' => 'C'])->default(['b']);
      })
      ->build();
    $resolver = new InputResolver('APP_');
    $engine = new Engine($form, new HandlerRegistry());

    // No input: the declared default, completed to a full ranking.
    $inputs = $resolver->resolve($form->fields(), '', []);
    $this->assertSame(['b', 'a', 'c'], $engine->collect($inputs, new Context('', [], FALSE))->value('ranking'));

    // An env comma list is coerced to an ordered ranking.
    $inputs = $resolver->resolve($form->fields(), '', ['APP_RANKING' => 'c, a, b']);
    $this->assertSame(['c', 'a', 'b'], $engine->collect($inputs, new Context('', [], FALSE))->value('ranking'));

    // A --prompts JSON array is taken as the ranking directly.
    $inputs = $resolver->resolve($form->fields(), '{"ranking": ["c", "b", "a"]}', []);
    $this->assertSame(['c', 'b', 'a'], $engine->collect($inputs, new Context('', [], FALSE))->value('ranking'));
  }

  public function testNoteCollectsNoAnswer(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        // The validator returns an error and the transformer throws if a
        // note is ever guarded or transformed; neither runs for a note.
        $p->note('intro', 'Intro')->description('Welcome.')
          ->validate(static fn(mixed $value): string => 'notes are never validated')
          ->transform(static function (mixed $value): mixed {
            throw new \RuntimeException('notes are never transformed');
          });
        $p->text('name')->default('pear');
        // A note may be gated like any field, but still carries no answer.
        $p->note('gated', 'Gated')->when(new Condition('name', eq: 'pear'));
        // A table is presentational too, so a note carrying one collects nothing.
        $p->note('summary', 'Summary')->table(['Fruit', 'Qty'], [['Apple', '3']]);
      })
      ->build();
    $resolver = new InputResolver('APP_');
    $engine = new Engine($form, new HandlerRegistry());

    // Stray supplied values for the notes - even a malformed one - are ignored.
    $inputs = $resolver->resolve($form->fields(), '{"intro": ["not", "a", "string"]}', ['APP_GATED' => 'ignored']);
    $answers = $engine->collect($inputs, new Context('', [], FALSE));

    // Neither note contributes a value, provenance or self-describing item.
    $this->assertArrayNotHasKey('intro', $answers->values);
    $this->assertArrayNotHasKey('gated', $answers->values);
    $this->assertArrayNotHasKey('summary', $answers->values);
    $this->assertArrayNotHasKey('intro', $answers->provenance);
    $this->assertNotInstanceOf(Answer::class, $answers->item('intro'));
    $this->assertFalse($answers->has('gated'));
    $this->assertFalse($answers->has('summary'));

    // The real field between the notes still collects normally.
    $this->assertSame('pear', $answers->value('name'));
  }

  public function testReorderRejectsIncompletePermutation(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->reorder('ranking')->option('a')->option('b')->option('c');
      })
      ->build();
    $resolver = new InputResolver('APP_');
    $engine = new Engine($form, new HandlerRegistry());

    $inputs = $resolver->resolve($form->fields(), '', ['APP_RANKING' => 'a, b']);

    $this->expectException(EngineException::class);
    $this->expectExceptionMessage('Invalid value for field "ranking": must rank every option exactly once (a, b, c)');

    $engine->collect($inputs, new Context('', [], FALSE));
  }

  public function testMultipleSelectionWithinBoundsAccepted(): void {
    $form = $this->boundedTagsForm();
    $resolver = new InputResolver('APP_');
    $engine = new Engine($form, new HandlerRegistry());

    $inputs = $resolver->resolve($form->fields(), '{"tags": ["a", "b"]}', []);

    $this->assertSame(['a', 'b'], $engine->collect($inputs, new Context('', [], FALSE))->value('tags'));
  }

  public function testMultipleSelectionBelowMinRejected(): void {
    $form = $this->boundedTagsForm();
    $resolver = new InputResolver('APP_');
    $engine = new Engine($form, new HandlerRegistry());

    $inputs = $resolver->resolve($form->fields(), '{"tags": ["a"]}', []);

    $this->expectException(EngineException::class);
    $this->expectExceptionMessage('Invalid value for field "tags": must be between 2 and 3 items.');

    $engine->collect($inputs, new Context('', [], FALSE));
  }

  public function testMultipleSelectionAboveMaxRejected(): void {
    $form = $this->boundedTagsForm();
    $resolver = new InputResolver('APP_');
    $engine = new Engine($form, new HandlerRegistry());

    $inputs = $resolver->resolve($form->fields(), '{"tags": ["a", "b", "c", "d"]}', []);

    $this->expectException(EngineException::class);
    $this->expectExceptionMessage('Invalid value for field "tags": must be between 2 and 3 items.');

    $engine->collect($inputs, new Context('', [], FALSE));
  }

  /**
   * A form with a single count-bounded multiple select "tags".
   *
   * @return \DrevOps\Tui\Model\FormDefinition
   *   The form.
   */
  protected function boundedTagsForm(): FormDefinition {
    return Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->select('tags')->multiple()->minSelections(2)->maxSelections(3)->option('a')->option('b')->option('c')->option('d');
      })
      ->build();
  }

}
