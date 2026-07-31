<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Builder;

use DrevOps\Tui\Builder\DefinitionFactory;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Discovery\Dotenv;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Model\DateBounds;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\FilePickerMode;
use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Model\Modal;
use DrevOps\Tui\Model\NumberBounds;
use DrevOps\Tui\Model\OptionKind;
use DrevOps\Tui\Model\Panel;
use DrevOps\Tui\Model\RenderMode;
use DrevOps\Tui\Model\SelectionBounds;
use DrevOps\Tui\Model\TableSpec;
use DrevOps\Tui\Model\Template;
use DrevOps\Tui\Model\Weekday;
use DrevOps\Tui\Primitive\ProgressReporter;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests that every declared property crosses from the tree to the definition.
 */
#[CoversClass(DefinitionFactory::class)]
#[Group('builder')]
final class DefinitionFactoryTest extends TestCase {

  /**
   * Tests that a declared property reaches the field the definition carries.
   *
   * @param string $id
   *   The field id.
   * @param \Closure $assert
   *   The assertion, given the field the definition carries.
   */
  #[DataProvider('dataProviderDeclaredPropertyReachesTheDefinition')]
  public function testDeclaredPropertyReachesTheDefinition(string $id, \Closure $assert): void {
    $field = $this->definition()->field($id);

    $this->assertInstanceOf(Field::class, $field);

    $assert($field);
  }

  /**
   * Data provider for testDeclaredPropertyReachesTheDefinition().
   *
   * @return \Iterator<string,array{string,\Closure}>
   *   A field id, and what the definition must carry for it.
   */
  public static function dataProviderDeclaredPropertyReachesTheDefinition(): \Iterator {
    yield 'the text around a row' => [
      'courier',
      static function (Field $field): void {
        Assert::assertSame('Courier', $field->label);
        Assert::assertSame('Who carries the crates.', $field->description);
        Assert::assertSame('Opens on its own page.', $field->help);
        Assert::assertSame('E.g. Valley Runs', $field->placeholder);
        Assert::assertSame(RenderMode::Standalone, $field->render);
      },
    ];

    yield 'what is owed, and what is said when it is missing' => [
      'courier',
      static function (Field $field): void {
        Assert::assertTrue($field->required);
        Assert::assertSame('Name the courier.', $field->requiredMessage);
      },
    ];

    yield 'what refuses a value and what normalizes one' => [
      'courier',
      static function (Field $field): void {
        Assert::assertInstanceOf(\Closure::class, $field->validate);
        Assert::assertInstanceOf(\Closure::class, $field->transform);
        Assert::assertSame('too short', ($field->validate)('a'));
        Assert::assertSame('VALLEY RUNS', ($field->transform)('valley runs'));
      },
    ];

    yield 'the environment variables answering it' => [
      'courier',
      static function (Field $field): void {
        Assert::assertSame('ORCHARD_COURIER', $field->envName);
        Assert::assertSame(['OLD_COURIER', 'LEGACY_COURIER'], $field->envAliases);
      },
    ];

    yield 'the candidates completing what is typed' => [
      'courier',
      static function (Field $field): void {
        Assert::assertSame(['Valley Runs', 'Ridge Haulage'], $field->completion);
      },
    ];

    yield 'the rows, with their headings, dividers and reasons' => [
      'basket',
      static function (Field $field): void {
        Assert::assertSame(OptionKind::Heading, $field->options[0]->kind);
        Assert::assertSame('Fruit', $field->options[0]->label);
        Assert::assertSame(OptionKind::Separator, $field->options[2]->kind);
        Assert::assertTrue($field->options[3]->disabled);
        Assert::assertSame('out of season', $field->options[3]->disabledReason);
        Assert::assertSame('Crisp and green.', $field->options[1]->description);
      },
    ];

    yield 'how many rows show, and how many may be picked' => [
      'basket',
      static function (Field $field): void {
        $bounds = $field->selectionBounds;

        Assert::assertTrue($field->multiple);
        Assert::assertSame(4, $field->pageSize);
        Assert::assertInstanceOf(SelectionBounds::class, $bounds);
        Assert::assertSame(1, $bounds->min);
        Assert::assertSame(2, $bounds->max);
      },
    ];

    yield 'the rows a loader owes' => [
      'crate',
      static function (Field $field): void {
        Assert::assertInstanceOf(\Closure::class, $field->optionsLoader);
        Assert::assertSame(['small' => 'Small'], ($field->optionsLoader)());
      },
    ];

    yield 'the rows the answers resolve' => [
      'grade',
      static function (Field $field): void {
        Assert::assertInstanceOf(\Closure::class, $field->optionsResolver);
        Assert::assertSame(['first' => 'First pick'], ($field->optionsResolver)(new Context('', [], FALSE)));
      },
    ];

    yield 'the rows a live query resolves' => [
      'orchard',
      static function (Field $field): void {
        Assert::assertInstanceOf(\Closure::class, $field->optionsSource);
        Assert::assertSame(2, $field->queryMinLength);
        Assert::assertSame(['valley' => 'Valley'], ($field->optionsSource)('val', []));
      },
    ];

    yield 'the leading match previewed after the caret' => [
      'orchard',
      static function (Field $field): void {
        Assert::assertTrue($field->ghost);
      },
    ];

    yield 'how large a number may be' => [
      'weight',
      static function (Field $field): void {
        $bounds = $field->bounds;

        Assert::assertInstanceOf(NumberBounds::class, $bounds);
        Assert::assertSame(200, $bounds->min);
        Assert::assertSame(9000, $bounds->max);
        Assert::assertSame(50, $bounds->step);
      },
    ];

    yield 'the scale a rating sits on, and what its points mean' => [
      'ripeness',
      static function (Field $field): void {
        $scale = $field->bounds;

        Assert::assertInstanceOf(NumberBounds::class, $scale);
        Assert::assertSame(1, $scale->min);
        Assert::assertSame(3, $scale->max);
        Assert::assertSame([1 => 'Green', 3 => 'Ripe'], $field->ratingCaptions);
      },
    ];

    yield 'how early or late a date may be' => [
      'harvest',
      static function (Field $field): void {
        $bounds = $field->dateBounds;

        Assert::assertInstanceOf(DateBounds::class, $bounds);
        Assert::assertSame('2026-07-01', $bounds->min?->format('Y-m-d'));
        Assert::assertSame('2026-07-31', $bounds->max?->format('Y-m-d'));
        Assert::assertSame(Weekday::Sunday, $bounds->weekStart);
      },
    ];

    yield 'what may be picked from the filesystem' => [
      'manifest',
      static function (Field $field): void {
        Assert::assertSame(FilePickerMode::File, $field->pickerConstraints->mode);
        Assert::assertSame(['csv'], $field->pickerConstraints->extensions);
        Assert::assertSame(2048, $field->pickerConstraints->maxSize);
        Assert::assertSame('/orchard', $field->pickerStart);
        Assert::assertTrue($field->pickerShowHidden);
      },
    ];

    yield 'the shape an answer is filled into' => [
      'code',
      static function (Field $field): void {
        $template = $field->template;

        Assert::assertInstanceOf(Template::class, $template);
        Assert::assertSame('{{orchard}}-{{fruit}}', $template->pattern());
        Assert::assertSame('Orchard', $template->labelOf('orchard'));
        Assert::assertInstanceOf(\Closure::class, $template->validatorOf('fruit'));
      },
    ];

    yield 'the two states a toggle is in' => [
      'visibility',
      static function (Field $field): void {
        Assert::assertSame(FieldType::Toggle, $field->type);
        Assert::assertSame('Public', $field->option('public')?->label);
        Assert::assertSame('private', $field->default);
      },
    ];

    yield 'the switches one kind of editor honours' => [
      'secret',
      static function (Field $field): void {
        Assert::assertTrue($field->revealable);
        Assert::assertTrue($field->confirm);
      },
    ];

    yield 'the handoff to the editor of your own' => [
      'notes',
      static function (Field $field): void {
        Assert::assertTrue($field->externalEditor);
      },
    ];

    yield 'the static default machine output stands in with' => [
      'stamp',
      static function (Field $field): void {
        Assert::assertInstanceOf(\Closure::class, $field->default);
        Assert::assertTrue($field->hasSchemaDefault);
        Assert::assertSame('2026-07-15', $field->schemaDefault);
      },
    ];

    yield 'what computes an answer from the others' => [
      'slug',
      static function (Field $field): void {
        Assert::assertInstanceOf(Derive::class, $field->derive);
        Assert::assertSame('{{ courier }}', $field->derive->template);
      },
    ];

    yield 'what detects an answer outside the form' => [
      'timezone',
      static function (Field $field): void {
        Assert::assertInstanceOf(Dotenv::class, $field->discover);
        Assert::assertSame('TZ', $field->discover->key);
      },
    ];

    yield 'what makes a field appear at all' => [
      'organic',
      static function (Field $field): void {
        Assert::assertSame(['field' => 'visibility', 'eq' => 'public'], $field->when?->toArray());
        Assert::assertSame(1, $field->conditionalDepth);
      },
    ];

    yield 'a card, laid out as a card' => [
      'intro',
      static function (Field $field): void {
        Assert::assertSame(FieldType::Note, $field->type);
        Assert::assertSame('Fresh produce order', $field->label);
        Assert::assertSame('Pick what is ripe today.', $field->description);
        Assert::assertTrue($field->bordered);
      },
    ];

    yield 'a card, laid out as a grid' => [
      'yields',
      static function (Field $field): void {
        Assert::assertInstanceOf(TableSpec::class, $field->table);
        Assert::assertSame(['Produce', 'Crates'], $field->table->headers);
        Assert::assertSame([['Apple', '12']], $field->table->rows);
      },
    ];

    yield 'the work a row runs, and the reporter it drives' => [
      'packing',
      static function (Field $field): void {
        Assert::assertSame(FieldType::Progress, $field->type);
        Assert::assertSame(3, $field->progressSteps);
        Assert::assertInstanceOf(\Closure::class, $field->progressWork);

        $seen = [];
        ($field->progressWork)(new ProgressReporter(static function (?string $label) use (&$seen): void {
          $seen[] = $label;
        }));

        Assert::assertSame(['crate one', 'crate two', 'crate three'], $seen);
      },
    ];
  }

  public function testEveryRowKeepsThePlaceItWasWrittenIn(): void {
    $panel = $this->definition()->panels[0];

    $ids = array_map(static fn(Field $field): string => $field->id, $panel->fields);

    $this->assertSame(['intro', 'courier', 'yields', 'weight', 'packing'], $ids);
  }

  public function testPanelCarriesItsOwnDeclarationAcross(): void {
    $panel = $this->definition()->panels[0];

    $this->assertSame('delivery', $panel->id);
    $this->assertSame('Delivery', $panel->title);
    $this->assertSame('What leaves the orchard today.', $panel->description);
    $this->assertInstanceOf(\Closure::class, $panel->preload);
    $this->assertSame([2], $panel->layout);
    $this->assertSame(['produce', 'confirm'], array_map(static fn(Panel $nested): string => $nested->id, $panel->panels));
  }

  public function testModalPanelCrossesWithTheWayOutOfIt(): void {
    $panel = $this->definition()->panels[0]->panels[1];

    $this->assertTrue($panel->isModal());
    $this->assertInstanceOf(Modal::class, $panel->modal);
    $this->assertSame('Accept', $panel->modal->buttons->submitLabel);
    $this->assertSame('Discard', $panel->modal->buttons->cancelLabel);
  }

  public function testAnOrdinaryPanelCarriesNoModal(): void {
    $this->assertFalse($this->definition()->panels[0]->panels[0]->isModal());
  }

  /**
   * The definition a declaration using every feature group derives.
   *
   * @return \DrevOps\Tui\Model\FormDefinition
   *   The definition.
   */
  protected function definition(): FormDefinition {
    return Form::create('Orchard', 'the delivery')
      ->panel('delivery', 'Delivery', function (PanelBuilder $p): void {
        $p->description('What leaves the orchard today.');
        $p->preload(static function (): void {
        });
        $p->layout(2);

        $p->note('intro', 'Fresh produce order')->description('Pick what is ripe today.')->border();

        $p->text('courier', 'Courier')
          ->description('Who carries the crates.')
          ->help('Opens on its own page.')
          ->placeholder('E.g. Valley Runs')
          ->standalone()
          ->required(TRUE, 'Name the courier.')
          ->validate(static fn(mixed $value): ?string => is_string($value) && strlen($value) > 2 ? NULL : 'too short')
          ->transform(static fn(mixed $value): string => is_string($value) ? strtoupper($value) : '')
          ->env('ORCHARD_COURIER')
          ->envAliases(['OLD_COURIER', 'LEGACY_COURIER'])
          ->complete(['Valley Runs', 'Ridge Haulage']);

        $p->markup('yields', 'Yields per crate')->table(['Produce', 'Crates'], [['Apple', '12']]);

        $p->number('weight', 'Basket weight')->min(200)->max(9000)->step(50);

        $p->progress('packing', 'Packing the box')->steps(3)->run(static function (ProgressReporter $reporter): void {
          $reporter->advance('crate one');
          $reporter->advance('crate two');
          $reporter->advance('crate three');
        });

        $p->panel('produce', 'Produce', function (PanelBuilder $sp): void {
          $sp->select('basket', 'Basket contents')
            ->multiple()
            ->heading('Fruit')
            ->option('apple', 'Apple', 'Crisp and green.')
            ->separator()
            ->option('quince', 'Quince', '', TRUE, 'out of season')
            ->minSelections(1)
            ->maxSelections(2)
            ->pageSize(4)
            ->default(['apple']);

          $sp->select('crate', 'Crate size')->options(static fn(): array => ['small' => 'Small'])->default('small');
          $sp->select('grade', 'Grade')->options(static fn(Context $context): array => ['first' => 'First pick'])->default('first');
          $sp->suggest('orchard', 'Orchard')->optionsFrom(static fn(string $query, array $answers): array => ['valley' => 'Valley'])->minQuery(2)->ghost();
          $sp->rating('ripeness', 'Ripeness')->min(1)->max(3)->captions([1 => 'Green', 3 => 'Ripe']);
          $sp->calendar('harvest', 'Harvest date')->minDate('2026-07-01')->maxDate('2026-07-31')->weekStart(Weekday::Sunday);
          $sp->filePicker('manifest', 'Manifest')->filesOnly()->extensions(['csv'])->maxSize(2048)->startIn('/orchard')->showHidden();
          $sp->template('code', 'Crate code')->pattern('{{orchard}}-{{fruit}}')->slot('orchard', 'Orchard')->slot('fruit', '', static fn(string $value): ?string => NULL);
          $sp->toggle('visibility', 'Visibility')->options(['public' => 'Public', 'private' => 'Private'])->default('private');
          $sp->password('secret', 'Secret')->revealable()->confirmation();
          $sp->textarea('notes', 'Notes')->externalEditor();
          $sp->text('stamp', 'Stamp')->default(static fn(Context $context): string => '2026-07-15')->schemaDefault('2026-07-15');
          $sp->text('slug', 'Slug')->derive(new Derive('{{ courier }}'));
          $sp->suggest('timezone', 'Timezone')->discover(new Dotenv('TZ'));
          $sp->confirm('organic', 'Organic only?')->when(new Condition('visibility', eq: 'public'));
        });

        $p->panel('confirm', 'Confirm delivery', static function (PanelBuilder $m): void {
          $m->modal('Accept', 'Discard');
          $m->text('signature', 'Signature');
        });
      })
      ->build();
  }

}
