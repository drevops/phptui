<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Answers;

use DrevOps\Tui\Answers\Answer;
use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Model\FieldType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the answers model.
 */
#[CoversClass(Answers::class)]
#[CoversClass(Answer::class)]
#[Group('answers')]
final class AnswersTest extends TestCase {

  public function testAccessors(): void {
    $answers = new Answers(['name' => 'Acme', 'agree' => TRUE], ['name' => Provenance::Edited, 'agree' => Provenance::Default]);

    $this->assertTrue($answers->has('name'));
    $this->assertFalse($answers->has('nope'));
    $this->assertSame('Acme', $answers->value('name'));
    $this->assertNull($answers->value('nope'));
    $this->assertSame(Provenance::Edited, $answers->provenanceOf('name'));
    $this->assertSame(Provenance::Default, $answers->provenanceOf('missing'));
    $this->assertSame(['name' => 'Acme', 'agree' => TRUE], $answers->values);
  }

  public function testToJson(): void {
    $answers = new Answers(['name' => 'Acme', 'mods' => ['a', 'b']]);

    $this->assertSame('{"name":"Acme","mods":["a","b"]}', $answers->toJson());
  }

  public function testEmpty(): void {
    $answers = new Answers();

    $this->assertSame([], $answers->values);
    $this->assertSame(Provenance::Default, $answers->provenanceOf('x'));
    $this->assertNotInstanceOf(Answer::class, $answers->item('x'));
    $this->assertSame('', $answers->toSummary());
  }

  public function testForTreeSnapshotsQuestions(): void {
    $form = Form::create('T')
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name', 'Site name');
        $p->text('inactive', 'Inactive');
        $p->panel('adv', 'Advanced', function (PanelBuilder $sp): void {
          $sp->confirm('debug', 'Debug');
        });
      })
      ->root();

    $answers = Answers::forTree($form, ['name' => 'Acme', 'debug' => TRUE], ['name' => Provenance::Edited]);

    // Snapshots exist only for active questions, in form order.
    $this->assertSame(['name', 'debug'], array_keys($answers->items));

    $name = $answers->item('name');
    $this->assertInstanceOf(Answer::class, $name);
    $this->assertSame('Acme', $name->value);
    $this->assertSame(Provenance::Edited, $name->provenance);
    $this->assertSame('Site name', $name->label);
    $this->assertSame(FieldType::Text, $name->type);
    $this->assertSame(['General'], $name->panels);

    $debug = $answers->item('debug');
    $this->assertInstanceOf(Answer::class, $debug);
    $this->assertSame(Provenance::Default, $debug->provenance);
    $this->assertSame(FieldType::Confirm, $debug->type);
    $this->assertSame(['General', 'Advanced'], $debug->panels);
  }

  public function testForTreeSplitsTemplateAnswerIntoItsParts(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $p): void {
        $p->template('crate', 'Crate label')->pattern('{{orchard}}-{{grade}}');
        $p->text('name', 'Name');
      })
      ->root();

    $answers = Answers::forTree($form, ['crate' => 'valley-a', 'name' => 'Acme'], []);

    // The value stays whole and the parts ride alongside it.
    $this->assertSame('valley-a', $answers->value('crate'));
    $this->assertSame(['orchard' => 'valley', 'grade' => 'a'], $answers->parts('crate'));
    $this->assertSame(['orchard' => 'valley', 'grade' => 'a'], $answers->item('crate')?->parts);

    // Only a template question carries parts.
    $this->assertSame([], $answers->parts('name'));
    $this->assertSame([], $answers->parts('absent'));
  }

  public function testPartsAreEmptyWhenTheAnswerDoesNotMatchTheShape(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $p): void {
        $p->template('crate', 'Crate label')->pattern('{{orchard}}-{{grade}}');
      })
      ->root();

    $answers = Answers::forTree($form, ['crate' => 'nope'], []);

    $this->assertSame([], $answers->parts('crate'));
  }

  public function testToSummaryDelegates(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
      })
      ->root();

    $summary = Answers::forTree($form, ['name' => 'Acme'], ['name' => Provenance::Edited])->toSummary();

    $this->assertStringContainsString('P', $summary);
    $this->assertStringContainsString('Name: Acme (edited)', $summary);
  }

  public function testToSummaryFollowsColorCapability(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $p): void {
        $p->text('name', 'Order at [Basket](https://example.com/basket)');
      })
      ->root();
    $answers = Answers::forTree($form, ['name' => 'Weekly'], ['name' => Provenance::Edited]);

    $no_color = getenv('NO_COLOR');
    $term = getenv('TERM');

    try {
      // NO_COLOR degrades the linked label to plain text.
      putenv('NO_COLOR=1');
      $this->assertStringContainsString('Order at Basket (https://example.com/basket)', $answers->toSummary());

      // A colour-capable terminal emits the hyperlink escape.
      putenv('NO_COLOR');
      putenv('TERM=xterm-256color');
      $this->assertStringContainsString("\033]8;;https://example.com/basket\007", $answers->toSummary());
    }
    finally {
      putenv($no_color === FALSE ? 'NO_COLOR' : 'NO_COLOR=' . $no_color);
      putenv($term === FALSE ? 'TERM' : 'TERM=' . $term);
    }
  }

}
