<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Block;

use DrevOps\Tui\Block\BlockFactory;
use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Progress;
use DrevOps\Tui\Block\Tree;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Discovery\JsonValue;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Model\RenderMode;
use DrevOps\Tui\Model\Weekday;
use DrevOps\Tui\Primitive\ProgressReporter;
use DrevOps\Tui\Tests\Fixtures\Form\AllFieldsForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests reading a declared tree back out of the definition derived from it.
 */
#[CoversClass(BlockFactory::class)]
#[CoversClass(Tree::class)]
final class BlockFactoryTest extends TestCase {

  public function testEveryFieldTypeSurvivesTheRoundTrip(): void {
    $form = AllFieldsForm::create();

    $this->assertSameTree($form->root(), (new BlockFactory())->create($form->build()));
  }

  public function testEveryDeclarationSurvivesTheRoundTrip(): void {
    $form = $this->form();

    $this->assertSameTree($form->root(), (new BlockFactory())->create($form->build()));
  }

  public function testWhatTheRowsDeclareIsReadBack(): void {
    $root = (new BlockFactory())->create($this->form()->build());
    $fields = [];

    foreach (Tree::fields($root) as $field) {
      $fields[$field->id()] = $field;
    }

    $crop = $fields['crop'] ?? NULL;
    $this->assertInstanceOf(Field::class, $crop);
    $this->assertSame('The crop this basket was picked from.', $crop->descriptionText());
    $this->assertSame('Type a few letters to filter.', $crop->helpText());
    $this->assertSame('E.g. Golden Beetroot', $crop->placeholderText());
    $this->assertSame('ORCHARD_CROP', $crop->envName());
    $this->assertSame(['LEGACY_CROP'], $crop->aliases());
    $this->assertTrue($crop->isRequired());
    $this->assertSame('A crop is owed.', $crop->requiredMessage());
    $this->assertInstanceOf(Condition::class, $crop->rule());
    $this->assertInstanceOf(Derive::class, $crop->derivation());
    $this->assertInstanceOf(JsonValue::class, $crop->discovery());
    $this->assertSame('stand-in', $crop->schemaDefaultValue());
    $this->assertTrue($crop->hasSchemaDefault());

    $picks = $fields['picks'] ?? NULL;
    $this->assertInstanceOf(Field::class, $picks);
    $this->assertTrue($picks->isMultiple());
    $this->assertSame(2, $picks->selectionBounds()?->min);
    $this->assertSame(4, $picks->pageSize());
    $this->assertSame(['a', 'c'], $picks->selectableValues());
    $this->assertSame(['Baskets', 'a', '', 'b', 'c'], array_map(static fn(Option $entry): string => $entry->label, $picks->entries()));

    $scale = $fields['scale'] ?? NULL;
    $this->assertInstanceOf(Field::class, $scale);
    $this->assertSame([1 => 'Poor', 5 => 'Excellent'], $scale->ratingCaptions());

    $due = $fields['due'] ?? NULL;
    $this->assertInstanceOf(Field::class, $due);
    $this->assertSame(Weekday::Sunday, $due->dateBounds()?->weekStart);

    $body = $fields['body'] ?? NULL;
    $this->assertInstanceOf(Field::class, $body);
    $this->assertSame(RenderMode::Standalone, $body->renderMode());
    $this->assertTrue($body->hasExternalEditor());

    $hint = $fields['hint'] ?? NULL;
    $this->assertInstanceOf(Field::class, $hint);
    $this->assertTrue($hint->hasGhost());
    $this->assertSame(['apple', 'apricot'], $hint->completion());

    $remote = $fields['remote'] ?? NULL;
    $this->assertInstanceOf(Field::class, $remote);
    $this->assertInstanceOf(\Closure::class, $remote->source());
    $this->assertSame(3, $remote->queryMinLength());

    $loaded = $fields['loaded'] ?? NULL;
    $this->assertInstanceOf(Field::class, $loaded);
    $this->assertInstanceOf(\Closure::class, $loaded->loader());

    $narrowed = $fields['narrowed'] ?? NULL;
    $this->assertInstanceOf(Field::class, $narrowed);
    $this->assertInstanceOf(\Closure::class, $narrowed->resolver());

    $secret = $fields['secret'] ?? NULL;
    $this->assertInstanceOf(Field::class, $secret);
    $this->assertTrue($secret->isRevealable());
    $this->assertTrue($secret->hasConfirmation());

    $cfg = $fields['cfg'] ?? NULL;
    $this->assertInstanceOf(Field::class, $cfg);
    $this->assertSame('/tmp', $cfg->pickerStart());
    $this->assertTrue($cfg->showsHidden());
    $this->assertSame(['yml'], $cfg->pickerConstraints()->extensions);
  }

  public function testWhatThePanelsDeclareIsReadBack(): void {
    $root = (new BlockFactory())->create($this->form()->build());
    $panels = $root->children();

    $this->assertSame(['Orchard', 'Orchard'], [$root->id(), $root->title()]);
    $this->assertSame('Read the label before packing.', $panels[0]->descriptionText());
    $this->assertSame([2], $panels[0]->gridRows());
    $this->assertTrue($panels[0]->prepare());

    $nested = $panels[0]->children();
    $this->assertTrue($nested[1]->isModal());
    $this->assertSame('Pack it', $nested[1]->currentButtons()->submitLabel);
  }

  public function testWhatTheRowsThatOnlyShowOrRunDeclareIsReadBack(): void {
    $rows = $this->rows((new BlockFactory())->create($this->form()->build()));

    $note = $rows['note'] ?? NULL;
    $this->assertInstanceOf(Markup::class, $note);
    $this->assertSame('Notice', $note->titleText());
    $this->assertSame('Every crate is weighed.', $note->bodyText());
    $this->assertTrue($note->isBordered());
    $this->assertSame([['Crate', 'Weight']], [$note->tableSpec()?->headers]);
    $this->assertInstanceOf(Condition::class, $note->rule());

    $packing = $rows['packing'] ?? NULL;
    $this->assertInstanceOf(Progress::class, $packing);
    $this->assertSame('Packing crates', $packing->caption());
    $this->assertSame(3, $packing->total());
    $this->assertInstanceOf(\Closure::class, $packing->workload());
  }

  /**
   * A form declaring one of everything a definition can carry.
   *
   * @return \DrevOps\Tui\Builder\Form
   *   The form.
   */
  protected function form(): Form {
    return Form::create('Orchard', 'produce')
      ->envPrefix('ORCH_')
      ->panel('main', 'Main', function (PanelBuilder $p): void {
        $p->description('Read the label before packing.');
        $p->layout(2);
        $p->preload(static function (): void {
        });
        $p->markup('note', 'Every crate is weighed.', 'Notice')->bordered()->table(['Crate', 'Weight'], [['A', '1 kg']])->when(new Condition('crop', ne: ''));
        $p->progress('packing', 'Packing crates')->steps(3)->when(new Condition('crop', ne: ''))->run(static function (ProgressReporter $reporter): void {
          $reporter->advance('packing');
        });
        $p->text('crop', 'Crop')
          ->description('The crop this basket was picked from.')
          ->help('Type a few letters to filter.')
          ->placeholder('E.g. Golden Beetroot')
          ->required(message: 'A crop is owed.')
          ->env('ORCHARD_CROP')
          ->envAliases(['LEGACY_CROP'])
          ->when(new Condition('grower', ne: ''))
          ->derive(new Derive('{{grower}}'))
          ->discover(new JsonValue('box.json', 'crop'))
          ->default(static fn(Context $context): string => $context->version)
          ->schemaDefault('stand-in')
          ->validate(static fn(): ?string => NULL)
          ->transform(static fn(mixed $value): mixed => $value);
        $p->text('grower', 'Grower')->default('sunny');
        $p->select('picks', 'Picks')->multiple()->minSelections(2)->maxSelections(3)->pageSize(4)
          ->heading('Baskets')
          ->option('a')
          ->separator()
          ->option('b', 'b', disabled: TRUE, disabled_reason: 'sold out')
          ->option('c');
        $p->rating('scale', 'Scale')->min(1)->max(5)->captions([1 => 'Poor', 5 => 'Excellent']);
        $p->calendar('due', 'Due')->minDate('2026-01-01')->maxDate('2026-12-31')->weekStart(Weekday::Sunday);
        $p->textarea('body', 'Body')->standalone()->externalEditor();
        $p->suggest('hint', 'Hint')->ghost()->complete(['apple', 'apricot']);
        $p->search('remote', 'Remote')->optionsFrom(static fn(string $query): array => [])->minQuery(3);
        $p->select('loaded', 'Loaded')->options(static fn(): array => ['a' => 'Alpha']);
        $p->select('narrowed', 'Narrowed')->options(static fn(Context $context): array => ['a' => 'Alpha']);
        $p->password('secret', 'Secret')->revealable()->confirmation();
        $p->filePicker('cfg', 'Config')->startIn('/tmp')->showHidden()->extensions(['yml']);
        $p->template('code', 'Code')->pattern('{{head}}-{{tail}}')->default('a-b');
        $p->panel('deep', 'Deep', function (PanelBuilder $q): void {
          $q->text('inner', 'Inner')->default('deep');
        });
        $p->panel('dialog', 'Dialog', function (PanelBuilder $q): void {
          $q->modal('Pack it', 'Leave it');
          $q->confirm('sure', 'Sure?');
        });
      })
      ->panel('other', 'Other', function (PanelBuilder $p): void {
        $p->toggle('visibility', 'Visibility')->options(['public' => 'Public', 'private' => 'Private'])->default('public');
      })
      ->layout(2);
  }

  /**
   * Every row a tree holds that only shows something or only runs work.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to walk.
   *
   * @return array<string,\DrevOps\Tui\Block\Markup|\DrevOps\Tui\Block\Progress>
   *   The rows, keyed by id.
   */
  protected function rows(Panel $panel): array {
    $rows = [];

    foreach ($panel->currentLayout()->in('content')->blocks() as $block) {
      if ($block instanceof Markup || $block instanceof Progress) {
        $rows[$block->id()] = $block;
      }

      if ($block instanceof Panel) {
        $rows = [...$rows, ...$this->rows($block)];
      }
    }

    return $rows;
  }

  /**
   * Assert that two trees declare the same questions in the same order.
   *
   * @param \DrevOps\Tui\Block\Panel $declared
   *   The tree the builder wrote.
   * @param \DrevOps\Tui\Block\Panel $derived
   *   The tree read back out of the definition.
   */
  protected function assertSameTree(Panel $declared, Panel $derived): void {
    $this->assertSame(Tree::ids($declared), Tree::ids($derived));
    $this->assertSame($this->shapes($declared), $this->shapes($derived));
  }

  /**
   * What every field of a tree declares, as plain values.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to walk.
   *
   * @return array<string,array<string,mixed>>
   *   The declaration of each field, keyed by id.
   */
  protected function shapes(Panel $panel): array {
    $shapes = [];

    foreach (Tree::fields($panel) as $field) {
      $shapes[$field->id()] = [
        'label' => $field->label(),
        'type' => $field->type()->value,
        'description' => $field->descriptionText(),
        'help' => $field->helpText(),
        'placeholder' => $field->placeholderText(),
        'required' => $field->isRequired(),
        'message' => $field->requiredMessage(),
        'multiple' => $field->isMultiple(),
        'entries' => array_map(static fn(Option $entry): array => [$entry->value, $entry->label, $entry->kind->value, $entry->disabled], $field->entries()),
        'bounds' => $field->numberBounds()?->describe(),
        'dates' => $field->dateBounds()?->describe(),
        'selections' => $field->selectionBounds()?->describe(),
        'template' => $field->template()?->pattern(),
        'captions' => $field->ratingCaptions(),
        'page' => $field->pageSize(),
        'env' => $field->envName(),
        'aliases' => $field->aliases(),
        'render' => $field->renderMode()->name,
        'ghost' => $field->hasGhost(),
        'revealable' => $field->isRevealable(),
        'confirm' => $field->hasConfirmation(),
        'editor' => $field->hasExternalEditor(),
        'picker' => [$field->pickerStart(), $field->showsHidden(), $field->pickerConstraints()->extensions],
        'query' => $field->queryMinLength(),
        'schema_default' => [$field->hasSchemaDefault(), $field->schemaDefaultValue()],
        'when' => $field->rule()?->toArray(),
        'derive' => $field->derivation()?->toArray(),
        'discover' => $field->discovery() instanceof JsonValue ? $field->discovery()->toArray() : NULL,
        'declares' => [
          $field->validator() instanceof \Closure,
          $field->transformer() instanceof \Closure,
          $field->loader() instanceof \Closure,
          $field->resolver() instanceof \Closure,
          $field->source() instanceof \Closure,
        ],
        // A closure default is the declaration rather than a value, so it is
        // compared by whether one was declared at all.
        'default' => $field->value() instanceof \Closure ? FieldType::Text->value : $field->value(),
      ];
    }

    return $shapes;
  }

}
