<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Screen;

use DrevOps\PhpTui\Answers\Answer;
use DrevOps\PhpTui\Answers\Answers;
use DrevOps\PhpTui\Answers\Provenance;
use DrevOps\PhpTui\Block\Field;
use DrevOps\PhpTui\Block\FieldType;
use DrevOps\PhpTui\Block\Markup;
use DrevOps\PhpTui\Block\Panel;
use DrevOps\PhpTui\Block\Tree;
use DrevOps\PhpTui\Builder\Fixup;
use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\Condition\Condition;
use DrevOps\PhpTui\Derive\Derive;
use DrevOps\PhpTui\Discovery\Dotenv;
use DrevOps\PhpTui\Discovery\JsonValue;
use DrevOps\PhpTui\Handler\Context;
use DrevOps\PhpTui\Handler\HandlerRegistry;
use DrevOps\PhpTui\Screen\Collector;
use DrevOps\PhpTui\Screen\Layout\PanelLayout;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests where a headless answer comes from, and what settles it.
 */
#[CoversClass(Collector::class)]
#[CoversClass(Tree::class)]
#[CoversClass(Field::class)]
#[CoversClass(Panel::class)]
#[CoversClass(Answers::class)]
#[Group('screen')]
final class CollectorResolutionTest extends TestCase {

  /**
   * The reusable behaviour of the fixture handlers.
   */
  protected const string HANDLERS = 'DrevOps\\PhpTui\\Tests\\Fixtures\\Handler';

  public function testSuppliedValueWinsOverEverythingElse(): void {
    $answers = $this->collect($this->sourcesForm(), ['name' => 'Supplied'], $this->project(TRUE));

    $this->assertSame('Supplied', $answers->value('name'));
    $this->assertSame(Provenance::Edited, $answers->provenanceOf('name'));
  }

  public function testDetectedValueWinsOverTheDeclaredDefault(): void {
    $answers = $this->collect($this->sourcesForm(), [], $this->project(TRUE));

    $this->assertSame('Detected Box', $answers->value('name'));
    $this->assertSame('summer', $answers->value('season'));
    $this->assertSame(Provenance::Detected, $answers->provenanceOf('name'));
  }

  public function testNothingIsDetectedOutsideUpdateMode(): void {
    $answers = $this->collect($this->sourcesForm(), [], $this->project(FALSE));

    $this->assertSame('Weekly Box', $answers->value('name'));
    $this->assertSame(Provenance::Default, $answers->provenanceOf('name'));
  }

  public function testDetectedValueTheFieldWouldRefuseFallsBackToTheDefault(): void {
    // The file holds a crate count outside the declared bounds, so the answer
    // is the default rather than a detected value nothing would accept.
    $answers = $this->collect($this->sourcesForm(), [], $this->project(TRUE));

    $this->assertSame(2, $answers->value('crates'));
    $this->assertSame(Provenance::Default, $answers->provenanceOf('crates'));
  }

  public function testDetectorReadsTheRunContextItWasGiven(): void {
    $seen = NULL;
    $form = Form::create('T')->panel('p', 'p', function (PanelBuilder $p) use (&$seen): void {
      $p->text('Version', 'version')->default('')->discover(function (Context $context) use (&$seen): string {
        $seen = $context;

        return $context->version;
      });
    });

    $answers = $this->collect($form, [], new Context('orchard', [], TRUE, '9.9'));

    $this->assertSame('9.9', $answers->value('version'));
    $this->assertInstanceOf(Context::class, $seen);
    $this->assertSame('orchard', $seen->directory);
  }

  public function testClosureDefaultReadsTheContextAndTheAnswersSoFar(): void {
    $form = Form::create('T')->panel('p', 'p', function (PanelBuilder $p): void {
      $p->text('Grower', 'grower')->default('sunny');
      $p->text('Lot', 'lot')->default(static function (Context $context): string {
        $grower = $context->answers['grower'] ?? '';

        return $context->version . ':' . (is_string($grower) ? $grower : '');
      });
    });

    $answers = $this->collect($form, [], new Context('', [], FALSE, '2.0'));

    $this->assertSame('2.0:sunny', $answers->value('lot'));
  }

  /**
   * Every source stamps the provenance the answer set reports.
   *
   * @param array<string,mixed> $supplied
   *   The values supplied to the collection.
   * @param bool $update
   *   Whether values already outside the form are detected.
   * @param array<string,\DrevOps\PhpTui\Answers\Provenance> $expected
   *   The expected provenance of each answer.
   */
  #[DataProvider('dataProviderProvenanceFollowsTheSource')]
  public function testProvenanceFollowsTheSource(array $supplied, bool $update, array $expected): void {
    $answers = $this->collect($this->sourcesForm(), $supplied, $this->project($update));

    foreach ($expected as $id => $provenance) {
      $this->assertSame($provenance, $answers->provenanceOf($id), $id);
    }
  }

  /**
   * Data provider for testProvenanceFollowsTheSource().
   *
   * @return \Iterator<string,array{array<string,mixed>,bool,array<string,\DrevOps\PhpTui\Answers\Provenance>}>
   *   The supplied values, the update flag and the expected provenance.
   */
  public static function dataProviderProvenanceFollowsTheSource(): \Iterator {
    yield 'declared defaults' => [[], FALSE, ['name' => Provenance::Default, 'slug' => Provenance::Derived]];
    yield 'supplied value' => [['name' => 'Pear'], FALSE, ['name' => Provenance::Edited, 'slug' => Provenance::Derived]];
    yield 'detected value' => [[], TRUE, ['name' => Provenance::Detected, 'slug' => Provenance::Detected]];
    yield 'supplied over a computed one' => [['slug' => 'kept'], FALSE, ['slug' => Provenance::Override]];
  }

  public function testComputedValueFollowsTheAnswerItReads(): void {
    $answers = $this->collect($this->sourcesForm(), ['name' => 'Golden Beetroot'], $this->project(FALSE));

    $this->assertSame('golden_beetroot', $answers->value('slug'));
  }

  public function testSuppliedValuePinsTheComputedOne(): void {
    $answers = $this->collect($this->sourcesForm(), ['name' => 'Golden Beetroot', 'slug' => 'kept'], $this->project(FALSE));

    $this->assertSame('kept', $answers->value('slug'));
  }

  public function testDetectedValuePinsTheComputedOne(): void {
    $answers = $this->collect($this->sourcesForm(), [], $this->project(TRUE));

    // The slug was detected, so the rule that would compute it stands down.
    $this->assertSame('detected-slug', $answers->value('slug'));
    $this->assertSame(Provenance::Detected, $answers->provenanceOf('slug'));
  }

  /**
   * A supplied value of the wrong shape is refused, naming the shape owed.
   *
   * @param \Closure $declare
   *   The panel declaration.
   * @param mixed $value
   *   The value supplied for the field "x".
   * @param string $expected
   *   The expected message.
   */
  #[DataProvider('dataProviderValueOfTheWrongShapeIsRefused')]
  public function testValueOfTheWrongShapeIsRefused(\Closure $declare, mixed $value, string $expected): void {
    $this->expectException(CollectException::class);
    $this->expectExceptionMessage($expected);

    $this->collect(Form::create('T')->panel('p', 'p', $declare), ['x' => $value]);
  }

  /**
   * Data provider for testValueOfTheWrongShapeIsRefused().
   *
   * @return \Iterator<string,array{\Closure,mixed,string}>
   *   The declaration, the supplied value and the expected message.
   */
  public static function dataProviderValueOfTheWrongShapeIsRefused(): \Iterator {
    yield 'text takes a string' => [
      static function (PanelBuilder $p): void {
        $p->text('X', 'x');
      },
      42,
      'Invalid value for field "x": must be a string.',
    ];

    yield 'number takes a number' => [
      static function (PanelBuilder $p): void {
        $p->number('X', 'x');
      },
      'many',
      'Invalid value for field "x": must be a number.',
    ];

    yield 'rating takes a whole number' => [
      static function (PanelBuilder $p): void {
        $p->rating('X', 'x');
      },
      2.5,
      'Invalid value for field "x": must be a whole number.',
    ];

    yield 'confirm takes a boolean' => [
      static function (PanelBuilder $p): void {
        $p->confirm('X', 'x');
      },
      'yes',
      'Invalid value for field "x": must be a boolean.',
    ];

    yield 'a multiple field takes a list' => [
      static function (PanelBuilder $p): void {
        $p->select('X', 'x')->multiple()->options(['a' => 'Alpha']);
      },
      'a',
      'Invalid value for field "x": must be a list.',
    ];

    yield 'calendar takes an ISO date' => [
      static function (PanelBuilder $p): void {
        $p->calendar('X', 'x');
      },
      '2026-7-5',
      'Invalid value for field "x": must be a date (YYYY-MM-DD).',
    ];
  }

  public function testEmptinessIsAnsweredBeforeTheShape(): void {
    $form = Form::create('T')->panel('p', 'p', function (PanelBuilder $p): void {
      $p->select('Picks', 'x')->multiple()->required()->options(['a' => 'Alpha']);
    });

    $this->expectException(CollectException::class);
    $this->expectExceptionMessage('Invalid value for field "x": Picks is required.');

    $this->collect($form, ['x' => []]);
  }

  public function testShapeIsAnsweredBeforeTheFieldsOwnValidator(): void {
    $form = Form::create('T')->panel('p', 'p', function (PanelBuilder $p): void {
      $p->number('X', 'x')->validate(static fn(): string => 'never reached');
    });

    $this->expectException(CollectException::class);
    $this->expectExceptionMessage('Invalid value for field "x": must be a number.');

    $this->collect($form, ['x' => 'many']);
  }

  public function testShapeOfTemplateAnswerIsMeasuredBeforeItsValidator(): void {
    $form = Form::create('T')->panel('p', 'p', function (PanelBuilder $p): void {
      $p->template('X', 'x')->pattern('{{head}}-{{tail}}');
    });

    $this->expectException(CollectException::class);
    $this->expectExceptionMessage('Invalid value for field "x"');

    $this->collect($form, ['x' => 'nodash']);
  }

  public function testReusableBehaviourStandsInWhereTheFieldDeclaresNone(): void {
    $form = Form::create('T')->panel('p', 'p', function (PanelBuilder $p): void {
      $p->text('Machine name', 'machine_name');
    });

    $answers = $this->collect($form, ['machine_name' => 'Golden Beetroot'], NULL, new HandlerRegistry([self::HANDLERS]));

    // The reusable transformer normalized the supplied value.
    $this->assertSame('golden beetroot', $answers->value('machine_name'));
  }

  public function testReusableBehaviourRefusesWhatItCannotAccept(): void {
    $form = Form::create('T')->panel('p', 'p', function (PanelBuilder $p): void {
      $p->text('Machine name', 'machine_name');
    });

    $this->expectException(CollectException::class);
    $this->expectExceptionMessage('Invalid value for field "machine_name": A machine name is required.');

    $this->collect($form, ['machine_name' => ''], NULL, new HandlerRegistry([self::HANDLERS]));
  }

  public function testTheFieldsOwnBehaviourWinsOverTheReusableOne(): void {
    $form = Form::create('T')->panel('p', 'p', function (PanelBuilder $p): void {
      $p->text('Machine name', 'machine_name')
        ->validate(static fn(mixed $value): ?string => $value === 'REFUSED' ? 'The field says so.' : NULL)
        ->transform(static fn(mixed $value): mixed => is_string($value) ? strtoupper($value) : $value);
    });

    $registry = new HandlerRegistry([self::HANDLERS]);

    $this->assertSame('GOLDEN', $this->collect($form, ['machine_name' => 'Golden'], NULL, $registry)->value('machine_name'));

    $this->expectException(CollectException::class);
    $this->expectExceptionMessage('Invalid value for field "machine_name": The field says so.');

    $this->collect($form, ['machine_name' => 'refused'], NULL, $registry);
  }

  public function testOnlySuppliedValuesAreNormalized(): void {
    $form = Form::create('T')->panel('p', 'p', function (PanelBuilder $p): void {
      $p->text('A', 'a')->default(' kept ')->transform(static fn(mixed $value): mixed => is_string($value) ? trim($value) : $value);
      $p->text('B', 'b')->default('')->transform(static fn(mixed $value): mixed => is_string($value) ? trim($value) : $value);
    });

    $answers = $this->collect($form, ['b' => ' trimmed ']);

    $this->assertSame(' kept ', $answers->value('a'));
    $this->assertSame('trimmed', $answers->value('b'));
  }

  public function testNormalizationHappensBeforeAnythingReadsTheValue(): void {
    $form = Form::create('T')->panel('p', 'p', function (PanelBuilder $p): void {
      $p->text('Name', 'name')->transform(static fn(mixed $value): mixed => is_string($value) ? trim($value) : $value);
      $p->text('Echo', 'echo')->derive(new Derive('{{name}}'));
    });

    $answers = $this->collect($form, ['name' => '  Pear  ']);

    $this->assertSame('Pear', $answers->value('echo'));
  }

  /**
   * A value the resolved rows no longer hold is restated against them.
   *
   * @param \Closure $declare
   *   The panel declaration.
   * @param mixed $expected
   *   The expected answer for the field "x".
   */
  #[DataProvider('dataProviderValueIsRestatedAgainstTheResolvedRows')]
  public function testValueIsRestatedAgainstTheResolvedRows(\Closure $declare, mixed $expected): void {
    $answers = $this->collect(Form::create('T')->panel('p', 'p', $declare), []);

    $this->assertSame($expected, $answers->value('x'));
  }

  /**
   * Data provider for testValueIsRestatedAgainstTheResolvedRows().
   *
   * @return \Iterator<string,array{\Closure,mixed}>
   *   The declaration and the expected answer.
   */
  public static function dataProviderValueIsRestatedAgainstTheResolvedRows(): \Iterator {
    $rows = static fn(Context $context): array => ['carrot' => 'Carrot', 'potato' => 'Potato'];

    yield 'a choice the set dropped falls away' => [
      static function (PanelBuilder $p) use ($rows): void {
        $p->select('X', 'x')->default('apple')->options($rows);
      },
      '',
    ];

    yield 'a list keeps only what the set still holds' => [
      static function (PanelBuilder $p) use ($rows): void {
        $p->select('X', 'x')->multiple()->default(['apple', 'carrot'])->options($rows);
      },
      ['carrot'],
    ];

    yield 'a ranking is completed to the resolved set' => [
      static function (PanelBuilder $p) use ($rows): void {
        $p->reorder('X', 'x')->options($rows);
      },
      ['carrot', 'potato'],
    ];

    yield 'a toggle falls back to the first resolved state' => [
      static function (PanelBuilder $p) use ($rows): void {
        $p->toggle('X', 'x')->options($rows);
      },
      'carrot',
    ];

    yield 'a hint set leaves the value alone' => [
      static function (PanelBuilder $p) use ($rows): void {
        $p->suggest('X', 'x')->default('Quince')->options($rows);
      },
      'Quince',
    ];
  }

  public function testSuppliedValueIsReportedRatherThanQuietlyDropped(): void {
    $form = Form::create('T')->panel('p', 'p', function (PanelBuilder $p): void {
      $p->select('X', 'x')->options(static fn(Context $context): array => ['carrot' => 'Carrot']);
    });

    $this->expectException(CollectException::class);
    $this->expectExceptionMessage('Invalid value for field "x": value "apple" is not one of: carrot');

    $this->collect($form, ['x' => 'apple']);
  }

  public function testRowsOwedOnceAreAskedForOnce(): void {
    $calls = 0;
    $form = Form::create('T')->panel('p', 'p', function (PanelBuilder $p) use (&$calls): void {
      $p->select('X', 'x')->default('carrot')->options(function () use (&$calls): array {
        $calls++;

        return ['carrot' => 'Carrot'];
      });
    });

    $this->assertSame('carrot', $this->collect($form, [])->value('x'));
    $this->assertSame(1, $calls);
  }

  public function testEachSuppliedItemIsLookedUpThroughTheQuery(): void {
    $queries = [];
    $form = Form::create('T')->panel('p', 'p', function (PanelBuilder $p) use (&$queries): void {
      $p->search('X', 'x')->multiple()->optionsFrom(static function (string $query) use (&$queries): array {
        $queries[] = $query;

        return [$query => ucfirst($query)];
      });
    });

    $answers = $this->collect($form, ['x' => ['carrot', 'potato', 'carrot']]);

    $this->assertSame(['carrot', 'potato', 'carrot'], $answers->value('x'));
    // One lookup per distinct item: the same query twice answers the same way.
    $this->assertSame(['carrot', 'potato'], $queries);
  }

  public function testSourceThatCannotAnswerFailsTheCollection(): void {
    $form = Form::create('T')->panel('p', 'p', function (PanelBuilder $p): void {
      $p->search('X', 'x')->optionsFrom(static function (): array {
        throw new \RuntimeException('The pantry is unreachable.');
      });
    });

    $this->expectException(CollectException::class);
    $this->expectExceptionMessage('Could not load options for field "x": The pantry is unreachable.');

    $this->collect($form, ['x' => 'carrot']);
  }

  public function testFieldItsConditionHidesCarriesNoAnswerAndNoProvenance(): void {
    $answers = $this->collect($this->sourcesForm(), ['name' => 'Pear'], $this->project(FALSE));

    $this->assertFalse($answers->has('note'));
    $this->assertArrayNotHasKey('note', $answers->provenance);
    $this->assertArrayNotHasKey('note', $answers->items);
  }

  public function testConditionReadsTheSettledAnswersRatherThanTheDeclaredOnes(): void {
    $form = Form::create('T')->panel('p', 'p', function (PanelBuilder $p): void {
      $p->text('Name', 'name')->default('');
      $p->text('Slug', 'slug')->derive(new Derive('{{name}}', 'machine'));
      // The rule reads a computed answer, so it can only hold once that answer
      // has been computed rather than when it was declared.
      $p->text('Note', 'note')->default('seen')->when(new Condition('slug', eq: 'golden_beetroot'));
    });

    $this->assertTrue($this->collect($form, ['name' => 'Golden Beetroot'])->has('note'));
    $this->assertFalse($this->collect($form, ['name' => 'Pear'])->has('note'));
  }

  public function testRulesThatWriteValueApplyOnceTheAnswersSettle(): void {
    $answers = $this->collect($this->sourcesForm(), ['delivery' => 'doorstep', 'wrap' => TRUE], $this->project(FALSE));

    $this->assertFalse($answers->value('wrap'));

    $answers = $this->collect($this->sourcesForm(), ['delivery' => 'gift', 'wrap' => TRUE], $this->project(FALSE));

    $this->assertTrue($answers->value('wrap'));
  }

  public function testRuleAimedAtRowThatOnlyShowsIsIgnored(): void {
    $form = Form::create('T')
      ->fixup(new Fixup(set: 'hint', to: 'written'))
      ->fixup(new Fixup(set: 'name', from: 'hint'))
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->markup('Read the label.', id: 'hint');
        $p->text('Name', 'name')->default('Pear');
      });

    // Neither rule reaches an answer: the row it names carries none, so the
    // settled value stands rather than being overwritten with nothing.
    $this->assertSame('Pear', $this->collect($form, [])->value('name'));
  }

  public function testAnswersDescribeTheQuestionsTheyAnswer(): void {
    $form = Form::create('Orchard')
      ->panel('Main', 'main', function (PanelBuilder $p): void {
        $p->template('Code', 'code')->pattern('{{head}}-{{tail}}')->default('a-b');
        $p->panel('Deep', 'deep', function (PanelBuilder $q): void {
          $q->text('Inner', 'inner')->default('deep');
        });
      });

    $answers = $this->collect($form, []);

    $code = $answers->item('code');
    $this->assertInstanceOf(Answer::class, $code);
    $this->assertSame('Code', $code->label);
    $this->assertSame(FieldType::Template, $code->type);
    $this->assertSame(['Main'], $code->panels);
    $this->assertSame(['head' => 'a', 'tail' => 'b'], $answers->parts('code'));

    $inner = $answers->item('inner');
    $this->assertInstanceOf(Answer::class, $inner);
    $this->assertSame(['Main', 'Deep'], $inner->panels);

    $this->assertStringContainsString('Main', $answers->toSummary());
    $this->assertStringContainsString('Deep', $answers->toSummary());
  }

  public function testAnswersFollowTheOrderTheFormDeclaresThem(): void {
    $form = Form::create('Orchard')
      ->panel('First', 'first', function (PanelBuilder $p): void {
        $p->text('A', 'a')->default('a');
        $p->panel('Nested', 'nested', function (PanelBuilder $q): void {
          $q->text('B', 'b')->default('b');
        });
        $p->text('C', 'c')->default('c');
      })
      ->panel('Second', 'second', function (PanelBuilder $p): void {
        $p->text('D', 'd')->default('d');
      });

    // A panel asks its own questions before the ones beneath it, whatever order
    // the rows were placed in.
    $this->assertSame(['a', 'c', 'b', 'd'], array_keys($this->collect($form, [])->values));
  }

  public function testTheTreeIsWalkedForEveryRowItHolds(): void {
    $root = Form::create('Orchard')
      ->panel('Main', 'main', function (PanelBuilder $p): void {
        $p->markup('Read the label.', id: 'hint');
        $p->text('Name', 'name')->default('Pear');
        $p->progress('Packing', 'packing');
        $p->panel('Deep', 'deep', function (PanelBuilder $q): void {
          $q->text('Inner', 'inner')->default('deep');
        });
      })
      ->root();

    $this->assertSame(['name', 'inner'], array_map(static fn(Field $field): string => $field->id(), Tree::fields($root)));
    $this->assertSame(['hint', 'name', 'packing', 'inner'], Tree::ids($root));
  }

  public function testRowThatOnlyShowsContributesNothingWhereverItSits(): void {
    $panel = (new Panel('main', 'Delivery'))->layout(new PanelLayout());
    $panel->in('content')->add(new Markup('note', 'Note'));
    $panel->in('content')->add((new Field('courier', 'Courier'))->default('Valley Runs'));

    $answers = (new Collector())->answers($panel);

    $this->assertSame(['courier' => 'Valley Runs'], $answers->values);
    $this->assertArrayNotHasKey('note', $answers->provenance);
  }

  public function testResolverIsNotAskedAgainWhileItsWholeInputStands(): void {
    $calls = 0;
    $form = Form::create('T')->panel('p', 'p', function (PanelBuilder $p) use (&$calls): void {
      $p->select('Item', 'item')->default('carrot')->options(function (Context $context) use (&$calls): array {
        $calls++;

        return ['carrot' => 'Carrot'];
      });
    });

    $collector = new Collector();
    $root = $form->root();

    $this->assertSame('carrot', $collector->answers($root)->value('item'));
    $this->assertSame('carrot', $collector->answers($root)->value('item'));
    $this->assertSame(1, $calls);

    // Another run is another question, so the rows the first one produced are
    // not handed back for it.
    $collector->answers($root, [], new Context('orchard'));
    $this->assertSame(2, $calls);
  }

  public function testResolverThatCannotAnswerFailsTheCollection(): void {
    $form = Form::create('T')->panel('p', 'p', function (PanelBuilder $p): void {
      $p->select('X', 'x')->options(static function (Context $context): array {
        throw new \RuntimeException('The pantry is unreachable.');
      });
    });

    $this->expectException(CollectException::class);
    $this->expectExceptionMessage('Could not load options for field "x": The pantry is unreachable.');

    $this->collect($form, []);
  }

  public function testQuerySourceIsNotConsultedWhereItConstrainsNothing(): void {
    $calls = 0;
    $form = Form::create('T')->panel('p', 'p', function (PanelBuilder $p) use (&$calls): void {
      $p->text('Open', 'open')->default('no');
      // Hints are never a closed set, so nothing is measured against them.
      $p->suggest('Hint', 'hint')->optionsFrom(static function (string $query) use (&$calls): array {
        $calls++;

        return [];
      });
      // A field its condition hides was never asked for, so nothing is looked
      // up for it either.
      $p->search('Hidden', 'hidden')->optionsFrom(static function (string $query) use (&$calls): array {
        $calls++;

        return [];
      })->when(new Condition('open', eq: 'yes'));
    });

    $answers = $this->collect($form, ['hint' => 'Quince', 'hidden' => 'carrot']);

    $this->assertSame('Quince', $answers->value('hint'));
    $this->assertFalse($answers->has('hidden'));
    $this->assertSame(0, $calls);
  }

  /**
   * Collect a form's answers with no screen at all.
   *
   * @param \DrevOps\PhpTui\Builder\Form $form
   *   The form to collect.
   * @param array<string,mixed> $supplied
   *   The values supplied for its fields.
   * @param \DrevOps\PhpTui\Handler\Context|null $context
   *   The run the collection belongs to.
   * @param \DrevOps\PhpTui\Handler\HandlerRegistry|null $registry
   *   The registry of behaviour reused across forms.
   *
   * @return \DrevOps\PhpTui\Answers\Answers
   *   The answers.
   */
  protected function collect(Form $form, array $supplied = [], ?Context $context = NULL, ?HandlerRegistry $registry = NULL): Answers {
    // The rules that write a value once the answers settle belong to the form
    // rather than to any block, so they travel beside the tree.
    return (new Collector($registry, $form->currentFixups()))->answers($form->root(), $supplied, $context);
  }

  /**
   * A form whose answers can arrive from every source there is.
   *
   * @return \DrevOps\PhpTui\Builder\Form
   *   The form.
   */
  protected function sourcesForm(): Form {
    return Form::create('Orchard')
      ->fixup($this->wrapRule())
      ->panel('Main', 'main', function (PanelBuilder $p): void {
        $p->text('Name', 'name')->default('Weekly Box')->discover(new JsonValue('box.json', 'name'));
        $p->text('Slug', 'slug')->derive(new Derive('{{name}}', 'machine'))->discover(new JsonValue('box.json', 'slug'));
        $p->text('Season', 'season')->default('spring')->discover(new Dotenv('SEASON'));
        $p->number('Crates', 'crates')->min(1)->max(9)->default(2)->discover(new JsonValue('box.json', 'crates'));
        $p->select('Delivery', 'delivery')->options(['doorstep' => 'Doorstep', 'gift' => 'Gift'])->default('doorstep');
        $p->confirm('Wrap?', 'wrap')->default(TRUE);
        $p->text('Note', 'note')->default('n/a')->when(new Condition('delivery', eq: 'gift'));
      });
  }

  /**
   * The rule stripping the wrapping off anything that is not a gift.
   *
   * @return \DrevOps\PhpTui\Builder\Fixup
   *   The rule.
   */
  protected function wrapRule(): Fixup {
    return new Fixup(set: 'wrap', to: FALSE, when: new Condition('delivery', ne: 'gift'));
  }

  /**
   * A run against a directory holding answers of its own.
   *
   * @param bool $update
   *   Whether those answers are detected.
   *
   * @return \DrevOps\PhpTui\Handler\Context
   *   The context.
   */
  protected function project(bool $update): Context {
    vfsStream::setup('project', NULL, [
      'box.json' => '{"name": "Detected Box", "slug": "detected-slug", "crates": 99}',
      '.env' => 'SEASON=summer',
    ]);

    return new Context(vfsStream::url('project'), [], $update);
  }

}
