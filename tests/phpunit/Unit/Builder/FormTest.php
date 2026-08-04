<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Builder;

use DrevOps\Tui\Builder\FieldBuilder;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\LayoutGuard;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Discovery\Dotenv;
use DrevOps\Tui\Model\DateBounds;
use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Progress;
use DrevOps\Tui\Block\Tree;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\FilePickerMode;
use DrevOps\Tui\Model\Fixup;
use DrevOps\Tui\Model\FormException;
use DrevOps\Tui\Model\NumberBounds;
use DrevOps\Tui\Model\OptionKind;
use DrevOps\Tui\Model\RenderMode;
use DrevOps\Tui\Model\SelectionBounds;
use DrevOps\Tui\Model\TableSpec;
use DrevOps\Tui\Model\Template;
use DrevOps\Tui\Model\Weekday;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the fluent form builder.
 */
#[CoversClass(Form::class)]
#[CoversClass(LayoutGuard::class)]
#[CoversClass(PanelBuilder::class)]
#[CoversClass(FieldBuilder::class)]
#[Group('builder')]
final class FormTest extends TestCase {

  public function testBuildsExpectedForm(): void {
    $fixup = new Fixup(set: 'a', to: 'b', when: new Condition('x', eq: 'y'));

    $builder = Form::create('My app')
      ->banner('LOGO')
      ->buttons(TRUE, 'Install', 'Quit')
      ->envPrefix('APP_')
      ->fixup($fixup)
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->description('General settings.');
        $p->text('name', 'Site name')->description('The name.')->required()->default('Acme');
        $p->text('machine_name', 'Machine name')->derive(new Derive('{{ name }}'));
        $p->select('profile', 'Profile')->options(['standard' => 'Standard', 'minimal' => 'Minimal'])->default('standard');
        $p->select('services', 'Services')->multiple()->option('solr', 'Solr', 'Search')->option('redis', 'Redis');
        $p->confirm('docs', 'Keep docs?')->default(TRUE)->when(new Condition('profile', eq: 'standard'));
        $p->toggle('visibility', 'Visibility')->options(['public' => 'Public', 'private' => 'Private'])->default('private');
        $p->password('secret', 'Secret')->revealable()->confirmation();
        $p->suggest('timezone', 'Timezone')->discover(new Dotenv('TZ'));
        $p->reorder('ranking', 'Priorities')->options(['fast' => 'Fast', 'cheap' => 'Cheap', 'good' => 'Good'])->default(['good', 'fast']);
        $p->panel('advanced', 'Advanced', function (PanelBuilder $sp): void {
          $sp->text('webroot', 'Web root')->default('web');
        });
      });

    $form = $builder->root();

    $this->assertSame('My app', $form->title());
    $this->assertSame('LOGO', $builder->currentBanner());
    $this->assertTrue($form->currentButtons()->show);
    $this->assertSame('Install', $form->currentButtons()->submitLabel);
    $this->assertSame('Quit', $form->currentButtons()->cancelLabel);
    $this->assertSame('APP_', $builder->currentEnvPrefix());
    $this->assertSame([$fixup], $builder->currentFixups());
    $this->assertSame('General settings.', $form->children()[0]->descriptionText());

    $name = self::fieldOf($form, 'name');
    $this->assertInstanceOf(Field::class, $name);
    $this->assertSame('Site name', $name->label());
    $this->assertSame('The name.', $name->descriptionText());
    $this->assertSame(FieldType::Text, $name->type());
    $this->assertSame('Acme', $name->value());
    $this->assertTrue($name->isRequired());

    $machine = self::fieldOf($form, 'machine_name');
    $this->assertInstanceOf(Field::class, $machine);
    $this->assertSame('{{ name }}', $machine->derivation()?->template);

    $profile = self::fieldOf($form, 'profile');
    $this->assertInstanceOf(Field::class, $profile);
    $this->assertSame(FieldType::Select, $profile->type());
    $this->assertSame('standard', $profile->value());
    $this->assertSame('Standard', $profile->entryOf('standard')?->label);

    $services = self::fieldOf($form, 'services');
    $this->assertInstanceOf(Field::class, $services);
    $this->assertSame(FieldType::Select, $services->type());
    $this->assertTrue($services->isMultiple());
    $this->assertSame('Search', $services->entryOf('solr')?->description);

    $docs = self::fieldOf($form, 'docs');
    $this->assertInstanceOf(Field::class, $docs);
    $this->assertSame(FieldType::Confirm, $docs->type());
    $this->assertTrue($docs->value());
    $condition = $docs->condition();
    $this->assertInstanceOf(Condition::class, $condition);
    $this->assertSame(['field' => 'profile', 'eq' => 'standard'], $condition->toArray());

    $visibility = self::fieldOf($form, 'visibility');
    $this->assertInstanceOf(Field::class, $visibility);
    $this->assertSame(FieldType::Toggle, $visibility->type());
    $this->assertSame('private', $visibility->value());
    $this->assertSame('Public', $visibility->entryOf('public')?->label);

    $secret = self::fieldOf($form, 'secret');
    $this->assertInstanceOf(Field::class, $secret);
    $this->assertSame(FieldType::Password, $secret->type());
    $this->assertTrue($secret->isRevealable());
    $this->assertTrue($secret->hasConfirmation());

    $timezone = self::fieldOf($form, 'timezone');
    $this->assertInstanceOf(Field::class, $timezone);
    $this->assertSame(FieldType::Suggest, $timezone->type());
    $this->assertInstanceOf(Dotenv::class, $timezone->discovery());
    $this->assertSame('TZ', $timezone->discovery()->key);

    $ranking = self::fieldOf($form, 'ranking');
    $this->assertInstanceOf(Field::class, $ranking);
    $this->assertSame(FieldType::Reorder, $ranking->type());
    // A partial declared default is completed to a full ranking in declared
    // order: the given values first, the remaining options appended.
    $this->assertSame(['good', 'fast', 'cheap'], $ranking->value());

    $webroot = self::fieldOf($form, 'webroot');
    $this->assertInstanceOf(Field::class, $webroot);
    $this->assertSame('web', $webroot->value());
    $this->assertSame('Advanced', $form->children()[0]->children()[0]->title());
  }

  public function testDefaultsAndFallbacks(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->text('t');
        $panel->select('s')->option('a');
        $panel->select('m')->multiple();
        $panel->confirm('c');
        $panel->suggest('g');
        $panel->number('n');
        $panel->calendar('dt');
        $panel->textarea('ta');
        $panel->password('pw');
        $panel->search('se')->option('a');
        $panel->search('ms')->multiple()->option('a');
        $panel->toggle('tg')->option('on', 'On')->option('off', 'Off');
        $panel->filePicker('fp');
        $panel->filePicker('mfp')->multiple();
        $panel->pause('pa');
        $panel->reorder('rk')->option('a')->option('b')->option('c');
      })
      ->root();

    // Type defaults when none is declared.
    $this->assertSame('', self::fieldOf($form, 't')?->value());
    $this->assertSame('', self::fieldOf($form, 's')?->value());
    $this->assertSame([], self::fieldOf($form, 'm')?->value());
    $this->assertFalse(self::fieldOf($form, 'c')?->value());
    $this->assertSame('', self::fieldOf($form, 'g')?->value());
    $this->assertSame(0, self::fieldOf($form, 'n')?->value());
    // A date with no explicit default is empty; the field opens on today.
    $this->assertSame('', self::fieldOf($form, 'dt')?->value());
    $this->assertSame('', self::fieldOf($form, 'ta')?->value());
    $this->assertSame('', self::fieldOf($form, 'pw')?->value());
    // The password options are opt-in, so they default off.
    $password = self::fieldOf($form, 'pw');
    $this->assertInstanceOf(Field::class, $password);
    $this->assertFalse($password->isRevealable());
    $this->assertFalse($password->hasConfirmation());
    $this->assertSame('', self::fieldOf($form, 'se')?->value());
    $this->assertSame([], self::fieldOf($form, 'ms')?->value());
    // A toggle defaults to its first option, since it always holds a value.
    $this->assertSame('on', self::fieldOf($form, 'tg')?->value());
    // A single picker defaults to an empty path; a multiple picker to no paths.
    $this->assertSame('', self::fieldOf($form, 'fp')?->value());
    $this->assertSame([], self::fieldOf($form, 'mfp')?->value());
    // A reorder with no declared default ranks every option in declared order.
    $this->assertSame(['a', 'b', 'c'], self::fieldOf($form, 'rk')?->value());
    // The picker options are opt-in, so they default off.
    $picker = self::fieldOf($form, 'fp');
    $this->assertInstanceOf(Field::class, $picker);
    $this->assertSame(FilePickerMode::Any, $picker->pickerConstraints()->mode);
    $this->assertSame('', $picker->pickerStart());
    $this->assertSame([], $picker->pickerConstraints()->extensions);
    $this->assertFalse($picker->showsHidden());
    // A pause defaults to acknowledged so headless runs never block on it.
    $this->assertTrue(self::fieldOf($form, 'pa')?->value());

    // Label and option-label fall back to the id/value.
    $this->assertSame('t', self::fieldOf($form, 't')?->label());
    $this->assertSame('a', self::fieldOf($form, 's')?->entryOf('a')?->label);

    // Form-level defaults (the global TUI runtime is tested on the Tui facade).
    $this->assertTrue($form->currentButtons()->show);
    $this->assertSame('Submit', $form->currentButtons()->submitLabel);
    $this->assertSame('', $form->children()[0]->descriptionText());
  }

  public function testStandaloneOptsOutOfInlineEditing(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->confirm('a');
        $panel->select('b')->option('x')->standalone();
        // A later standalone(FALSE) restores inline editing.
        $panel->text('c')->standalone()->standalone(FALSE);
      })
      ->root();

    // A field is edited inline by default.
    $this->assertSame(RenderMode::Inline, self::fieldOf($form, 'a')?->renderMode());
    // Declaring it standalone opts out to the full-screen editor.
    $this->assertSame(RenderMode::Standalone, self::fieldOf($form, 'b')?->renderMode());
    // standalone(FALSE) restores inline editing.
    $this->assertSame(RenderMode::Inline, self::fieldOf($form, 'c')?->renderMode());
  }

  public function testExternalEditorFlag(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->textarea('notes', 'Notes')->externalEditor();
        $panel->textarea('plain', 'Plain');
      })
      ->root();

    $this->assertTrue(self::fieldOf($form, 'notes')?->hasExternalEditor());
    $this->assertFalse(self::fieldOf($form, 'plain')?->hasExternalEditor());
  }

  public function testNoteField(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->note('intro', 'Getting started')->body('Fill in each field.');
        $panel->note('bare');
        $panel->note('boxed', 'Boxed')->bordered();
        $panel->note('stock', 'Stock')->table(['Fruit', 'Qty'], [['Apple', '3'], ['Pear', '5']]);
      })
      ->root();

    $intro = self::markupOf($form, 'intro');
    $this->assertInstanceOf(Markup::class, $intro);
    // The title is the label and the body is what the card shows.
    $this->assertSame('Getting started', $intro->titleText());
    $this->assertSame('Fill in each field.', $intro->bodyText());
    // A note is not bordered unless it opts in.
    $this->assertFalse($intro->isBordered());
    // A note carries no table unless it opts in.
    $this->assertNotInstanceOf(TableSpec::class, $intro->tableSpec());

    // An omitted title stays empty rather than falling back to the id.
    $this->assertSame('', self::markupOf($form, 'bare')?->titleText());

    // ->bordered() draws the card inside a box.
    $this->assertTrue(self::markupOf($form, 'boxed')?->isBordered());

    // ->table() stores the header cells and body rows on the block.
    $stock = self::markupOf($form, 'stock')?->tableSpec();
    $this->assertInstanceOf(TableSpec::class, $stock);
    $this->assertSame(['Fruit', 'Qty'], $stock->headers);
    $this->assertSame([['Apple', '3'], ['Pear', '5']], $stock->rows);
  }

  public function testValidateAndTransformStored(): void {
    $validator = fn (mixed $v): ?string => NULL;
    $transformer = fn (mixed $v): mixed => $v;

    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel) use ($validator, $transformer): void {
        $panel->text('x')->validate($validator)->transform($transformer);
      })
      ->root();

    $field = self::fieldOf($form, 'x');
    $this->assertInstanceOf(Field::class, $field);
    $this->assertSame($validator, $field->validator());
    $this->assertSame($transformer, $field->transformer());
  }

  public function testRequiredFlagAndMessageStored(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->text('plain', 'Plain');
        $panel->text('name', 'Produce name')->required();
        $panel->text('plot', 'Garden plot name')->required(message: 'The garden plot name is required.');
        $panel->text('note', 'Delivery note')->required(FALSE);
      })
      ->root();

    $plain = self::fieldOf($form, 'plain');
    $this->assertInstanceOf(Field::class, $plain);
    $this->assertFalse($plain->isRequired());
    $this->assertSame('', $plain->requiredMessage());

    $name = self::fieldOf($form, 'name');
    $this->assertInstanceOf(Field::class, $name);
    $this->assertTrue($name->isRequired());
    $this->assertSame('', $name->requiredMessage());

    $plot = self::fieldOf($form, 'plot');
    $this->assertInstanceOf(Field::class, $plot);
    $this->assertTrue($plot->isRequired());
    $this->assertSame('The garden plot name is required.', $plot->requiredMessage());

    $note = self::fieldOf($form, 'note');
    $this->assertInstanceOf(Field::class, $note);
    $this->assertFalse($note->isRequired());
  }

  public function testCompletionSourceStored(): void {
    $list = ['acme-site', 'acme-app'];
    $closure = fn (array $answers): array => [];

    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel) use ($list, $closure): void {
        $panel->text('name', 'Name')->complete($list);
        $panel->text('repo', 'Repo')->complete($closure);
        $panel->text('plain', 'Plain');
      })
      ->root();

    $this->assertSame($list, self::fieldOf($form, 'name')?->completion());
    $this->assertSame($closure, self::fieldOf($form, 'repo')?->completion());
    // A field with no completion source defaults to an empty list.
    $this->assertSame([], self::fieldOf($form, 'plain')?->completion());
  }

  public function testEnvNameAndAliasesStored(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->text('crate_size', 'Crate size')->env('LEGACY_CRATE')->envAliases(['OLD_CRATE', 'OLDER_CRATE']);
        $panel->text('grade', 'Grade');
      })
      ->root();

    $crate = self::fieldOf($form, 'crate_size');
    $this->assertInstanceOf(Field::class, $crate);
    $this->assertSame('LEGACY_CRATE', $crate->envName());
    $this->assertSame(['OLD_CRATE', 'OLDER_CRATE'], $crate->aliases());

    // A field that names nothing keeps the mechanical name and no aliases.
    $grade = self::fieldOf($form, 'grade');
    $this->assertInstanceOf(Field::class, $grade);
    $this->assertSame('', $grade->envName());
    $this->assertSame([], $grade->aliases());
  }

  public function testEnvAliasesAreReindexed(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->text('crate_size', 'Crate size')->envAliases([2 => 'OLD_CRATE', 5 => 'OLDER_CRATE']);
      })
      ->root();

    $this->assertSame(['OLD_CRATE', 'OLDER_CRATE'], self::fieldOf($form, 'crate_size')?->aliases());
  }

  public function testGhostTextOptInStored(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->suggest('fruit', 'Fruit')->options(['Apple' => 'Apple'])->ghost();
        $panel->suggest('berry', 'Berry')->options(['Fig' => 'Fig'])->ghost(FALSE);
        $panel->suggest('plain', 'Plain')->options(['Pear' => 'Pear']);
      })
      ->root();

    $this->assertTrue(self::fieldOf($form, 'fruit')?->hasGhost());
    $this->assertFalse(self::fieldOf($form, 'berry')?->hasGhost());
    // Ghost-text is opt-in, so a field that never asks for it stays without.
    $this->assertFalse(self::fieldOf($form, 'plain')?->hasGhost());
  }

  public function testTemplateAssembled(): void {
    $grade = fn (string $value): ?string => $value === 'a' ? NULL : 'nope';

    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel) use ($grade): void {
        $panel->template('crate', 'Crate label')
          ->pattern('{{orchard}}-{{grade}}')
          ->slot('orchard', 'Orchard')
          ->slot('grade', 'Grade', $grade)
          ->default('valley-a');
      })
      ->root();

    $crate = self::fieldOf($form, 'crate');
    $this->assertInstanceOf(Field::class, $crate);
    $this->assertInstanceOf(Template::class, $crate->template());
    $this->assertSame('{{orchard}}-{{grade}}', $crate->template()->pattern());
    $this->assertSame(['orchard', 'grade'], $crate->template()->placeholders());
    $this->assertSame('Orchard', $crate->template()->labelOf('orchard'));
    $this->assertSame($grade, $crate->template()->validatorOf('grade'));
    $this->assertNotInstanceOf(\Closure::class, $crate->template()->validatorOf('orchard'));
    $this->assertSame('valley-a', $crate->value());
  }

  public function testSlotWithoutLabelOrValidatorLeavesBothUnset(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->template('crate', 'Crate')->pattern('{{a}}-{{b}}')->slot('a');
      })
      ->root();

    $template = self::fieldOf($form, 'crate')?->template();
    $this->assertInstanceOf(Template::class, $template);
    $this->assertSame('a', $template->labelOf('a'));
    $this->assertNotInstanceOf(\Closure::class, $template->validatorOf('a'));
  }

  public function testHelpAndPlaceholderCarryOntoTheField(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->text('crop', 'Crop')
          ->description('The crop being logged.')
          ->help('Type a few letters to filter.')
          ->placeholder('E.g. Golden Beetroot');
      })
      ->root();

    $crop = self::fieldOf($form, 'crop');
    $this->assertInstanceOf(Field::class, $crop);
    $this->assertSame('The crop being logged.', $crop->descriptionText());
    $this->assertSame('Type a few letters to filter.', $crop->helpText());
    $this->assertSame('E.g. Golden Beetroot', $crop->placeholderText());
  }

  public function testHelpAndPlaceholderDefaultToEmpty(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->text('crop', 'Crop');
      })
      ->root();

    $crop = self::fieldOf($form, 'crop');
    $this->assertInstanceOf(Field::class, $crop);
    $this->assertSame('', $crop->helpText());
    $this->assertSame('', $crop->placeholderText());
  }

  public function testPatternIsRefusedOnNonTemplateField(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Field "name" of type "text" fills in no fixed shape; ->pattern() applies to template.');

    Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->text('name', 'Name')->pattern('{{a}}-{{b}}');
      })
      ->root();
  }

  public function testNumberBoundsAssembled(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->number('port', 'Port')->min(1)->max(65535)->step(5);
        $panel->number('plain', 'Plain');
      })
      ->root();

    $port = self::fieldOf($form, 'port');
    $this->assertInstanceOf(Field::class, $port);
    $this->assertInstanceOf(NumberBounds::class, $port->numberBounds());
    $this->assertSame(1, $port->numberBounds()->min);
    $this->assertSame(65535, $port->numberBounds()->max);
    $this->assertSame(5, $port->numberBounds()->step);

    // A number with nothing declared carries no bounds - behaviour unchanged.
    $this->assertNotInstanceOf(NumberBounds::class, self::fieldOf($form, 'plain')?->numberBounds());
  }

  public function testRatingScaleAssembled(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->rating('nps', 'Recommend us')->min(0)->max(10);
        $panel->rating('taste', 'Taste');
      })
      ->root();

    $nps = self::fieldOf($form, 'nps');
    $this->assertInstanceOf(Field::class, $nps);
    $this->assertInstanceOf(NumberBounds::class, $nps->numberBounds());
    $this->assertSame(0, $nps->numberBounds()->min);
    $this->assertSame(10, $nps->numberBounds()->max);
    $this->assertSame(0, $nps->value());

    // A rating with nothing declared still carries a scale: one to five,
    // sitting on its lowest point.
    $taste = self::fieldOf($form, 'taste');
    $this->assertInstanceOf(NumberBounds::class, $taste?->numberBounds());
    $this->assertSame(1, $taste->numberBounds()->min);
    $this->assertSame(5, $taste->numberBounds()->max);
    $this->assertNull($taste->numberBounds()->step);
    $this->assertSame(1, $taste->value());
  }

  public function testRatingKeepsDeclaredDefault(): void {
    $form = Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->rating('taste')->default(4))
      ->root();

    $this->assertSame(4, self::fieldOf($form, 'taste')?->value());
  }

  public function testRatingCaptionsAssembled(): void {
    $form = Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->rating('taste')->captions([1 => 'Poor', 5 => 'Excellent']))
      ->root();

    $this->assertSame([1 => 'Poor', 5 => 'Excellent'], self::fieldOf($form, 'taste')?->ratingCaptions());
  }

  #[DataProvider('dataProviderRatingCollapsedScaleThrows')]
  public function testRatingCollapsedScaleThrows(int $min, int $max): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage(sprintf('Field "r" declares a scale from %d to %d; a scale needs at least two points', $min, $max));

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->rating('r')->min($min)->max($max))
      ->root();
  }

  /**
   * Data provider for testRatingCollapsedScaleThrows().
   *
   * @return \Iterator<string, array{int, int}>
   *   The declared ends of a scale holding fewer than two points.
   */
  public static function dataProviderRatingCollapsedScaleThrows(): \Iterator {
    yield 'one point' => [3, 3];
    yield 'inverted' => [5, 2];
  }

  public function testDateBoundsAssembled(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->calendar('birthday', 'Birthday')->minDate('2000-01-01')->maxDate('2030-12-31')->weekStart(Weekday::Sunday);
        $panel->calendar('plain', 'Plain');
      })
      ->root();

    $birthday = self::fieldOf($form, 'birthday');
    $this->assertInstanceOf(Field::class, $birthday);
    $this->assertInstanceOf(DateBounds::class, $birthday->dateBounds());
    $this->assertSame('2000-01-01', $birthday->dateBounds()->min?->format('Y-m-d'));
    $this->assertSame('2030-12-31', $birthday->dateBounds()->max?->format('Y-m-d'));
    $this->assertSame(Weekday::Sunday, $birthday->dateBounds()->weekStart);

    // A date with nothing declared still carries bounds, defaulting to a
    // Monday-first, open range.
    $plain = self::fieldOf($form, 'plain');
    $this->assertInstanceOf(DateBounds::class, $plain?->dateBounds());
    $this->assertNotInstanceOf(\DateTimeImmutable::class, $plain->dateBounds()->min);
    $this->assertNotInstanceOf(\DateTimeImmutable::class, $plain->dateBounds()->max);
    $this->assertSame(Weekday::Monday, $plain->dateBounds()->weekStart);
  }

  public function testDateBoundsAreRefusedOnNonDateField(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Field "t" of type "text" picks no date; ->minDate() applies to calendar.');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->text('t')->minDate('2020-01-01'))
      ->root();
  }

  public function testPageSizeAssembled(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->search('paged', 'Paged')->options(['a' => 'A'])->pageSize(5);
        $panel->search('plain', 'Plain')->options(['a' => 'A']);
      })
      ->root();

    $this->assertSame(5, self::fieldOf($form, 'paged')?->pageSize());

    // A field with nothing declared carries no page size and uses the default.
    $this->assertNull(self::fieldOf($form, 'plain')?->pageSize());
  }

  public function testSelectionBoundsAssembled(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->select('tags', 'Tags')->multiple()->minSelections(2)->maxSelections(4)->option('a')->option('b');
        $panel->search('svc', 'Services')->multiple()->minSelections(1)->option('x');
        $panel->filePicker('files', 'Files')->multiple()->maxSelections(3);
        $panel->select('plain', 'Plain')->multiple()->option('a');
      })
      ->root();

    $tags = self::fieldOf($form, 'tags');
    $this->assertInstanceOf(Field::class, $tags);
    $this->assertInstanceOf(SelectionBounds::class, $tags->selectionBounds());
    $this->assertSame(2, $tags->selectionBounds()->min);
    $this->assertSame(4, $tags->selectionBounds()->max);

    // A min-only bound leaves the ceiling open.
    $svc = self::fieldOf($form, 'svc');
    $this->assertInstanceOf(SelectionBounds::class, $svc?->selectionBounds());
    $this->assertSame(1, $svc->selectionBounds()->min);
    $this->assertNull($svc->selectionBounds()->max);

    // A file picker also takes selection bounds; a max-only bound leaves the
    // floor open.
    $files = self::fieldOf($form, 'files');
    $this->assertInstanceOf(SelectionBounds::class, $files?->selectionBounds());
    $this->assertNull($files->selectionBounds()->min);
    $this->assertSame(3, $files->selectionBounds()->max);

    // A multiple field with no selection limits carries none.
    $this->assertNotInstanceOf(SelectionBounds::class, self::fieldOf($form, 'plain')?->selectionBounds());
  }

  public function testFilePickerOptions(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->filePicker('config', 'Config')->startIn('/opt')->filesOnly()->extensions(['yml', 'yaml'])->showHidden()->maxSize(1048576);
        $panel->filePicker('assets', 'Assets')->multiple()->directoriesOnly();
      })
      ->root();

    $form_field = self::fieldOf($form, 'config');
    $this->assertInstanceOf(Field::class, $form_field);
    $this->assertSame(FieldType::FilePicker, $form_field->type());
    $this->assertSame(FilePickerMode::File, $form_field->pickerConstraints()->mode);
    $this->assertSame('/opt', $form_field->pickerStart());
    $this->assertSame(['yml', 'yaml'], $form_field->pickerConstraints()->extensions);
    $this->assertSame(1048576, $form_field->pickerConstraints()->maxSize);
    $this->assertTrue($form_field->showsHidden());

    $assets = self::fieldOf($form, 'assets');
    $this->assertInstanceOf(Field::class, $assets);
    $this->assertSame(FieldType::FilePicker, $assets->type());
    $this->assertTrue($assets->isMultiple());
    $this->assertSame(FilePickerMode::Directory, $assets->pickerConstraints()->mode);
  }

  public function testOptionKindsAndDisabled(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $p): void {
        $p->select('profile')
          ->heading('Recommended')
          ->option('standard', 'Standard')
          ->separator()
          ->option('demo', 'Demo', 'A demo', disabled: TRUE, disabled_reason: 'requires PHP 8.4');
      })
      ->root();

    $profile = self::fieldOf($form, 'profile');
    $this->assertInstanceOf(Field::class, $profile);

    $options = $profile->entries();
    $this->assertCount(4, $options);
    $this->assertSame(OptionKind::Heading, $options[0]->kind);
    $this->assertSame('Recommended', $options[0]->label);
    $this->assertSame(OptionKind::Option, $options[1]->kind);
    $this->assertSame(OptionKind::Separator, $options[2]->kind);
    $this->assertTrue($options[3]->disabled);
    $this->assertSame('requires PHP 8.4', $options[3]->disabledReason);

    $this->assertSame(['standard'], $profile->selectableValues());
  }

  public function testRepeatedOptionValueOverridesInPlace(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $p): void {
        $p->select('s')->option('a', 'First')->separator()->option('a', 'Second');
      })
      ->root();

    $field = self::fieldOf($form, 's');
    $this->assertInstanceOf(Field::class, $field);

    // The second declaration overrides the first in place; the separator stays.
    $this->assertCount(2, $field->entries());
    $this->assertSame('Second', $field->entryOf('a')?->label);
    $this->assertSame(['a'], $field->selectableValues());
  }

  #[DataProvider('dataProviderToggleInvalidDefaultThrows')]
  public function testToggleInvalidDefaultThrows(mixed $default): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Toggle field "t" default must be one of: a, b.');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->toggle('t')->option('a')->option('b')->default($default))
      ->root();
  }

  /**
   * Data provider for testToggleInvalidDefaultThrows().
   *
   * @return \Iterator<string, array{mixed}>
   *   A default value that is not one of the toggle's option values.
   */
  public static function dataProviderToggleInvalidDefaultThrows(): \Iterator {
    yield 'unknown string' => ['c'];
    yield 'boolean' => [TRUE];
    yield 'integer' => [123];
    yield 'null' => [NULL];
  }

  public function testToggleNumericStringOptionsDefaultToFirstValue(): void {
    $form = Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->toggle('flag')->option('0', 'Off')->option('1', 'On'))
      ->root();

    // The implicit default is the first option's value "0" as a string, not a
    // numeric-string coerced to int by the array key.
    $this->assertSame('0', self::fieldOf($form, 'flag')?->value());
  }

  public function testReorderToleratesDirtyDefault(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $p): void {
        $p->reorder('rk')->option('a')->option('b')->default('notalist');
        $p->reorder('rk2')->option('a')->option('b')->default(['b', 42, 'a']);
      })
      ->root();

    // A non-list default falls back to the full declared order.
    $this->assertSame(['a', 'b'], self::fieldOf($form, 'rk')?->value());
    // Non-string entries are ignored; the remaining values still complete it.
    $this->assertSame(['b', 'a'], self::fieldOf($form, 'rk2')?->value());
  }

  public function testModalPanelBuildsWithConfiguredButtons(): void {
    $form = Form::create('T')
      ->panel('root', 'Root', function (PanelBuilder $p): void {
        $p->text('name');
        $p->panel('confirm', 'Delete?', function (PanelBuilder $m): void {
          $m->modal('Yes', 'No')->description('This cannot be undone.');
          $m->confirm('sure');
        });
      })
      ->root();

    $modal = $form->children()[0]->children()[0];
    $this->assertTrue($modal->isModal());
    $this->assertSame('This cannot be undone.', $modal->descriptionText());
    $this->assertSame('Yes', $modal->currentButtons()->submitLabel);
    $this->assertSame('No', $modal->currentButtons()->cancelLabel);
    $this->assertTrue($modal->currentButtons()->show);
  }

  public function testModalDefaultsButtonLabels(): void {
    $form = Form::create('T')
      ->panel('m', 'M', fn(PanelBuilder $p): PanelBuilder => $p->modal())
      ->root();

    $modal = $form->children()[0];
    $this->assertTrue($modal->isModal());
    $this->assertSame('Submit', $modal->currentButtons()->submitLabel);
    $this->assertSame('Cancel', $modal->currentButtons()->cancelLabel);
  }

  public function testLayoutFlowsToTheTreeAndItsPanels(): void {
    $form = Form::create('Demo')
      ->layout(1, 2)
      ->panel('a', 'A', function (PanelBuilder $p): void {
        $p->layout(2);
        $p->panel('a1', 'A1', fn(PanelBuilder $sp): FieldBuilder => $sp->text('one', 'One'));
        $p->panel('a2', 'A2', fn(PanelBuilder $sp): FieldBuilder => $sp->text('two', 'Two'));
      })
      ->panel('b', 'B', fn(PanelBuilder $p): FieldBuilder => $p->text('three', 'Three'))
      ->panel('c', 'C', fn(PanelBuilder $p): FieldBuilder => $p->text('four', 'Four'))
      ->root();

    $this->assertSame([1, 2], $form->gridRows());
    $this->assertSame([2], $form->children()[0]->gridRows());
    // A panel without a declaration keeps the default row list.
    $this->assertSame([], $form->children()[1]->gridRows());
  }

  #[DataProvider('dataProviderBuildThrows')]
  public function testBuildThrows(\Closure $declare, string $message): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage($message);

    $declare();
  }

  /**
   * Data provider for testBuildThrows().
   *
   * @return \Iterator<string, array{\Closure, string}>
   *   A declaration the builder refuses, and the message it refuses it with.
   */
  public static function dataProviderBuildThrows(): \Iterator {
    yield 'template without a pattern' => [
      static function (): void {
        Form::create('T')
          ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->template('crate', 'Crate'))
          ->root();
      },
      'Field "crate" is a template field but declares no pattern',
    ];

    yield 'rating with a step' => [
      static function (): void {
        Form::create('T')
          ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->rating('r')->step(2))
          ->root();
      },
      'Field "r" declares a step of 2 on a scale whose points are its steps',
    ];

    yield 'unparseable date bound' => [
      static function (): void {
        Form::create('T')
          ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->calendar('d')->minDate('2026-13-01'))
          ->root();
      },
      'Field "d" declares an invalid date "2026-13-01".',
    ];

    yield 'min date after max date' => [
      static function (): void {
        Form::create('T')
          ->panel('p', 'P', function (PanelBuilder $p): void {
            $p->calendar('d')->minDate('2026-12-31')->maxDate('2026-01-01');
          })
          ->root();
      },
      'Field "d" declares min date 2026-12-31 after max date 2026-01-01.',
    ];

    yield 'number min above max' => [
      static function (): void {
        Form::create('T')
          ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->number('n')->min(10)->max(1))
          ->root();
      },
      'Field "n" declares min 10 greater than max 1.',
    ];

    yield 'non-positive number step' => [
      static function (): void {
        Form::create('T')
          ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->number('n')->step(0))
          ->root();
      },
      'Field "n" declares a non-positive step 0.',
    ];

    yield 'non-positive file size' => [
      static function (): void {
        Form::create('T')
          ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->filePicker('f')->maxSize(0))
          ->root();
      },
      'Field "f" declares a maximum file size of 0 below one byte.',
    ];

    yield 'non-positive page size' => [
      static function (): void {
        Form::create('T')
          ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->search('n')->pageSize(0))
          ->root();
      },
      'Field "n" declares a non-positive page size 0.',
    ];

    yield 'multiple on a single-value type' => [
      static function (): void {
        Form::create('T')
          ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->text('t')->multiple())
          ->root();
      },
      'Field "t" of type "text" does not collect several values',
    ];

    yield 'selection limits without multiple' => [
      static function (): void {
        Form::create('T')
          ->panel('p', 'P', function (PanelBuilder $p): void {
            $p->select('s')->minSelections(2)->option('a');
          })
          ->root();
      },
      'Field "s" declares selection limits but is not multiple',
    ];

    yield 'selection min above max' => [
      static function (): void {
        Form::create('T')
          ->panel('p', 'P', function (PanelBuilder $p): void {
            $p->select('s')->multiple()->minSelections(5)->maxSelections(2);
          })
          ->root();
      },
      'Field "s" declares min 5 selections greater than max 2.',
    ];

    yield 'selection min below one' => [
      static function (): void {
        Form::create('T')
          ->panel('p', 'P', function (PanelBuilder $p): void {
            $p->select('s')->multiple()->minSelections(0);
          })
          ->root();
      },
      'Selection bounds declare a minimum of 0 below one.',
    ];

    yield 'field id used twice' => [
      static function (): void {
        Form::create('T')
          ->panel('a', 'A', fn(PanelBuilder $p): FieldBuilder => $p->text('x'))
          ->panel('b', 'B', fn(PanelBuilder $p): FieldBuilder => $p->text('x'))
          ->root();
      },
      'Duplicate field id "x".',
    ];

    yield 'toggle without two options' => [
      static function (): void {
        Form::create('T')
          ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->toggle('t')->option('only'))
          ->root();
      },
      'Toggle field "t" must have exactly two options, 1 given.',
    ];

    yield 'reorder with a single option' => [
      static function (): void {
        Form::create('T')
          ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->reorder('r')->option('only'))
          ->root();
      },
      'Reorder field "r" must have at least two options, 1 given.',
    ];

    yield 'reorder with a structural option' => [
      static function (): void {
        Form::create('T')
          ->panel('p', 'P', function (PanelBuilder $p): void {
            $p->reorder('r')->option('a')->separator()->option('b');
          })
          ->root();
      },
      'Reorder field "r" allows only plain options - no headings, separators or disabled rows.',
    ];

    yield 'modal panel holding a sub-panel' => [
      static function (): void {
        Form::create('T')
          ->panel('confirm', 'Confirm', function (PanelBuilder $m): void {
            $m->modal();
            $m->panel('nested', 'Nested', fn(PanelBuilder $n): FieldBuilder => $n->text('x'));
          })
          ->root();
      },
      'Modal panel "confirm" cannot contain sub-panels.',
    ];

    yield 'form slots below the panels' => [
      static function (): void {
        Form::create('Demo')
          ->layout(1)
          ->panel('a', 'A', fn(PanelBuilder $p): FieldBuilder => $p->text('one', 'One'))
          ->panel('b', 'B', fn(PanelBuilder $p): FieldBuilder => $p->text('two', 'Two'))
          ->root();
      },
      'The layout of "Demo" declares 1 slot(s) for 2 panel(s).',
    ];

    yield 'form slots above the panels' => [
      static function (): void {
        Form::create('Demo')
          ->layout(2, 2)
          ->panel('a', 'A', fn(PanelBuilder $p): FieldBuilder => $p->text('one', 'One'))
          ->panel('b', 'B', fn(PanelBuilder $p): FieldBuilder => $p->text('two', 'Two'))
          ->root();
      },
      'The layout of "Demo" declares 4 slot(s) for 2 panel(s).',
    ];

    yield 'panel slots mismatch its children' => [
      static function (): void {
        Form::create('Demo')
          ->panel('a', 'A', function (PanelBuilder $p): void {
            $p->layout(2);
            $p->panel('a1', 'A1', fn(PanelBuilder $sp): FieldBuilder => $sp->text('one', 'One'));
          })
          ->root();
      },
      'The layout of "a" declares 2 slot(s) for 1 panel(s).',
    ];

    yield 'zero-width row' => [
      static function (): void {
        Form::create('Demo')
          ->layout(0, 2)
          ->panel('a', 'A', fn(PanelBuilder $p): FieldBuilder => $p->text('one', 'One'))
          ->panel('b', 'B', fn(PanelBuilder $p): FieldBuilder => $p->text('two', 'Two'))
          ->root();
      },
      'Every layout row of "Demo" must hold at least one panel.',
    ];
  }

  #[DataProvider('dataProviderMistakeIsRefusedWhereItIsMade')]
  public function testMistakeIsRefusedWhereItIsMade(\Closure $declare, string $message): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage($message);

    // Nothing is sealed here: a mistake needing no more than the argument it
    // was given is refused at the call that made it.
    $declare(new PanelBuilder('p', 'P'));
  }

  /**
   * Data provider for testMistakeIsRefusedWhereItIsMade().
   *
   * @return \Iterator<string, array{\Closure, string}>
   *   A declaration refused at the call, and the message it is refused with.
   */
  public static function dataProviderMistakeIsRefusedWhereItIsMade(): \Iterator {
    yield 'non-positive number step' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->number('n')->step(0),
      'Field "n" declares a non-positive step 0.',
    ];

    yield 'step on a rating scale' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->rating('r')->step(2),
      'Field "r" declares a step of 2 on a scale whose points are its steps',
    ];

    yield 'unparseable earliest date' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->calendar('d')->minDate('2026-13-01'),
      'Field "d" declares an invalid date "2026-13-01".',
    ];

    yield 'unparseable latest date' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->calendar('d')->maxDate('the-first'),
      'Field "d" declares an invalid date "the-first".',
    ];

  }

  public function testProgressRowStillTakesTheStepsItRuns(): void {
    $root = Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): Progress => $p->progress('packing', 'Packing crates')->steps(4))
      ->root();

    $packing = $root->children()[0]->in('content')->blocks()[0];

    $this->assertInstanceOf(Progress::class, $packing);
    $this->assertSame(4, $packing->total());
  }

  /**
   * Tests that a kind-scoped setter refuses the kinds it does not apply to.
   *
   * @param \Closure $declare
   *   The declaration, given a panel builder.
   * @param string $message
   *   The message it is refused with.
   */
  #[DataProvider('dataProviderKindScopedSetterRefusesTheWrongKind')]
  public function testKindScopedSetterRefusesTheWrongKind(\Closure $declare, string $message): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage($message);

    $declare(new PanelBuilder('p', 'P'));
  }

  /**
   * Data provider for testKindScopedSetterRefusesTheWrongKind().
   *
   * Every setter the builder scopes to particular kinds appears once, called on
   * a kind it does not apply to, so a setter that stops refusing is caught.
   *
   * @return \Iterator<string, array{\Closure, string}>
   *   The declaration and the message refusing it.
   */
  public static function dataProviderKindScopedSetterRefusesTheWrongKind(): \Iterator {
    yield 'placeholder' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->confirm('f')->placeholder('E.g. Pear'),
      'Field "f" of type "confirm" draws no input buffer to ghost; ->placeholder() applies to text, suggest, number, textarea, password, search.',
    ];
    yield 'multiple' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->multiple(),
      'Field "f" of type "text" does not collect several values; ->multiple() applies to select, search, filepicker.',
    ];
    yield 'revealable' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->revealable(),
      'Field "f" of type "text" masks nothing to reveal; ->revealable() applies to password.',
    ];
    yield 'confirmation' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->confirmation(),
      'Field "f" of type "text" keeps no secret to confirm; ->confirmation() applies to password.',
    ];
    yield 'externalEditor' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->externalEditor(),
      'Field "f" of type "text" composes no long-form text to hand off; ->externalEditor() applies to textarea.',
    ];
    yield 'min' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->min(1),
      'Field "f" of type "text" counts through no numbers; ->min() applies to number, rating.',
    ];
    yield 'max' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->max(9),
      'Field "f" of type "text" counts through no numbers; ->max() applies to number, rating.',
    ];
    yield 'step' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->step(2),
      'Field "f" of type "text" counts through no numbers; ->step() applies to number.',
    ];
    yield 'captions' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->captions([1 => 'Poor']),
      'Field "f" of type "text" draws no scale to caption; ->captions() applies to rating.',
    ];
    yield 'minSelections' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->minSelections(2),
      'Field "f" of type "text" does not collect several values; ->minSelections() applies to select, search, filepicker.',
    ];
    yield 'maxSelections' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->maxSelections(3),
      'Field "f" of type "text" does not collect several values; ->maxSelections() applies to select, search, filepicker.',
    ];
    yield 'startIn' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->startIn('/orchard'),
      'Field "f" of type "text" browses no filesystem; ->startIn() applies to filepicker.',
    ];
    yield 'filesOnly' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->filesOnly(),
      'Field "f" of type "text" browses no filesystem; ->filesOnly() applies to filepicker.',
    ];
    yield 'directoriesOnly' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->directoriesOnly(),
      'Field "f" of type "text" browses no filesystem; ->directoriesOnly() applies to filepicker.',
    ];
    yield 'extensions' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->extensions(['csv']),
      'Field "f" of type "text" browses no filesystem; ->extensions() applies to filepicker.',
    ];
    yield 'showHidden' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->showHidden(),
      'Field "f" of type "text" browses no filesystem; ->showHidden() applies to filepicker.',
    ];
    yield 'maxSize' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->maxSize(64),
      'Field "f" of type "text" browses no filesystem; ->maxSize() applies to filepicker.',
    ];
    yield 'pageSize' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->pageSize(5),
      'Field "f" of type "text" draws no list to page; ->pageSize() applies to select, toggle, suggest, search, reorder, filepicker.',
    ];
    yield 'minDate' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->minDate('2026-01-01'),
      'Field "f" of type "text" picks no date; ->minDate() applies to calendar.',
    ];
    yield 'maxDate' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->maxDate('2026-12-31'),
      'Field "f" of type "text" picks no date; ->maxDate() applies to calendar.',
    ];
    yield 'weekStart' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->weekStart(Weekday::Sunday),
      'Field "f" of type "text" picks no date; ->weekStart() applies to calendar.',
    ];
    yield 'complete' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->number('f')->complete(['Pear']),
      'Field "f" of type "number" completes no typed text; ->complete() applies to text.',
    ];
    yield 'ghost' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->ghost(),
      'Field "f" of type "text" ranks no options to preview; ->ghost() applies to suggest.',
    ];
    yield 'pattern' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->pattern('{{a}}-{{b}}'),
      'Field "f" of type "text" fills in no fixed shape; ->pattern() applies to template.',
    ];
    yield 'slot' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->slot('a', 'A'),
      'Field "f" of type "text" fills in no fixed shape; ->slot() applies to template.',
    ];
    yield 'option' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->option('apple', 'Apple'),
      'Field "f" of type "text" shows no options; ->option() applies to select, toggle, suggest, search, reorder.',
    ];
    yield 'separator' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->separator(),
      'Field "f" of type "text" shows no options; ->separator() applies to select, toggle, suggest, search, reorder.',
    ];
    yield 'heading' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->heading('Fruit'),
      'Field "f" of type "text" shows no options; ->heading() applies to select, toggle, suggest, search, reorder.',
    ];
    yield 'options' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->text('f')->options(['apple' => 'Apple']),
      'Field "f" of type "text" shows no options; ->options() applies to select, toggle, suggest, search, reorder.',
    ];
    yield 'optionsFrom' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->select('f')->optionsFrom(static fn(string $query, array $answers): array => []),
      'Field "f" of type "select" runs no query; ->optionsFrom() applies to suggest, search.',
    ];
    yield 'minQuery' => [
      static fn(PanelBuilder $p): FieldBuilder => $p->select('f')->minQuery(2),
      'Field "f" of type "select" runs no query; ->minQuery() applies to suggest, search.',
    ];
  }

  #[DataProvider('dataProviderEveryRefusalIsTheSameFamily')]
  public function testEveryRefusalIsTheSameFamily(\Closure $declare): void {
    try {
      $declare();
    }
    catch (\InvalidArgumentException $exception) {
      // One catch covers the lot, whichever surface refused.
      $this->assertInstanceOf(FormException::class, $exception);

      return;
    }

    $this->fail('The declaration was taken rather than refused.');
  }

  /**
   * Data provider for testEveryRefusalIsTheSameFamily().
   *
   * @return \Iterator<string, array{\Closure}>
   *   A declaration refused by one of the surfaces that refuse one.
   */
  public static function dataProviderEveryRefusalIsTheSameFamily(): \Iterator {
    yield 'the builder, at the call' => [static fn(): FieldBuilder => (new PanelBuilder('p', 'P'))->number('n')->step(0)];
    yield 'the builder, at the seal' => [
      static fn(): Panel => Form::create('T')
        ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->number('n')->min(10)->max(1))
        ->root(),
    ];
    yield 'a block guard' => [static fn(): Field => (new Field('n', 'N'))->env('ORCHARD-BASKET')];
    yield 'a limit' => [static fn(): NumberBounds => new NumberBounds(10, 1)];
    yield 'the form, once its tree is built' => [
      static function (): Form {
        $form = Form::create('T');
        $form->root();

        return $form->panel('late', 'Late', fn(PanelBuilder $p): FieldBuilder => $p->text('x'));
      },
    ];
  }

  #[DataProvider('dataProviderDeclarationAfterTheTreeIsBuiltIsRefused')]
  public function testDeclarationAfterTheTreeIsBuiltIsRefused(\Closure $declare, string $message): void {
    $form = Form::create('Orchard')->panel('basket', 'Basket', fn(PanelBuilder $p): FieldBuilder => $p->text('fruit', 'Fruit'));
    $form->root();

    $this->expectException(FormException::class);
    $this->expectExceptionMessage($message);

    $declare($form);
  }

  /**
   * Data provider for testDeclarationAfterTheTreeIsBuiltIsRefused().
   *
   * @return \Iterator<string, array{\Closure, string}>
   *   A declaration arriving too late, and the message it is refused with.
   */
  public static function dataProviderDeclarationAfterTheTreeIsBuiltIsRefused(): \Iterator {
    yield 'a panel' => [
      static fn(Form $form): Form => $form->panel('late', 'Late', fn(PanelBuilder $p): FieldBuilder => $p->text('x')),
      'Form "Orchard" declares a panel after its tree was built; declare every panel before the form is collected or its tree is read.',
    ];

    yield 'a layout' => [
      static fn(Form $form): Form => $form->layout(1),
      'Form "Orchard" declares a layout after its tree was built',
    ];

    yield 'its buttons' => [
      static fn(Form $form): Form => $form->buttons(FALSE),
      'Form "Orchard" declares its buttons after its tree was built',
    ];
  }

  public function testTreeIsBuiltOnceAndHandedBackAsItStands(): void {
    $form = Form::create('Orchard')->panel('basket', 'Basket', fn(PanelBuilder $p): FieldBuilder => $p->text('fruit', 'Fruit'));

    $this->assertSame($form->root(), $form->root());
  }

  /**
   * The field of a given id, anywhere in the declared tree.
   *
   * @param \DrevOps\Tui\Block\Panel $root
   *   The panel every declared panel hangs from.
   * @param string $id
   *   The field id.
   *
   * @return \DrevOps\Tui\Block\Field|null
   *   The field, or NULL when the tree holds none of that id.
   */
  protected static function fieldOf(Panel $root, string $id): ?Field {
    foreach (Tree::fields($root) as $field) {
      if ($field->id() === $id) {
        return $field;
      }
    }

    return NULL;
  }

  /**
   * The markup block of a given id, anywhere in the declared tree.
   *
   * @param \DrevOps\Tui\Block\Panel $root
   *   The panel every declared panel hangs from.
   * @param string $id
   *   The block id.
   *
   * @return \DrevOps\Tui\Block\Markup|null
   *   The block, or NULL when the tree holds none of that id.
   */
  protected static function markupOf(Panel $root, string $id): ?Markup {
    foreach (Tree::panels($root) as $panel) {
      foreach ($panel->blocks() as $block) {
        if ($block instanceof Markup && $block->id() === $id) {
          return $block;
        }
      }
    }

    return NULL;
  }

}
