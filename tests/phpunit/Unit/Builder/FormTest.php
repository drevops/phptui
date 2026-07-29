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
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\FilePickerMode;
use DrevOps\Tui\Model\Fixup;
use DrevOps\Tui\Model\FormException;
use DrevOps\Tui\Model\Modal;
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
#[Group('model')]
final class FormTest extends TestCase {

  public function testBuildsExpectedForm(): void {
    $fixup = new Fixup(set: 'a', to: 'b', when: new Condition('x', eq: 'y'));

    $form = Form::create('My app', 'the project')
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
      })
      ->build();

    $this->assertSame('My app', $form->title);
    $this->assertSame('the project', $form->subject);
    $this->assertSame('LOGO', $form->banner);
    $this->assertTrue($form->buttons->show);
    $this->assertSame('Install', $form->buttons->submitLabel);
    $this->assertSame('Quit', $form->buttons->cancelLabel);
    $this->assertSame('APP_', $form->envPrefix);
    $this->assertSame([$fixup], $form->fixups);
    $this->assertSame('General settings.', $form->panels[0]->description);

    $name = $form->field('name');
    $this->assertInstanceOf(Field::class, $name);
    $this->assertSame('Site name', $name->label);
    $this->assertSame('The name.', $name->description);
    $this->assertSame(FieldType::Text, $name->type);
    $this->assertSame('Acme', $name->default);
    $this->assertTrue($name->required);

    $machine = $form->field('machine_name');
    $this->assertInstanceOf(Field::class, $machine);
    $this->assertSame('{{ name }}', $machine->derive?->template);

    $profile = $form->field('profile');
    $this->assertInstanceOf(Field::class, $profile);
    $this->assertSame(FieldType::Select, $profile->type);
    $this->assertSame('standard', $profile->default);
    $this->assertSame('Standard', $profile->option('standard')?->label);

    $services = $form->field('services');
    $this->assertInstanceOf(Field::class, $services);
    $this->assertSame(FieldType::Select, $services->type);
    $this->assertTrue($services->multiple);
    $this->assertSame('Search', $services->option('solr')?->description);

    $docs = $form->field('docs');
    $this->assertInstanceOf(Field::class, $docs);
    $this->assertSame(FieldType::Confirm, $docs->type);
    $this->assertTrue($docs->default);
    $this->assertSame(['field' => 'profile', 'eq' => 'standard'], $docs->when?->toArray());

    $visibility = $form->field('visibility');
    $this->assertInstanceOf(Field::class, $visibility);
    $this->assertSame(FieldType::Toggle, $visibility->type);
    $this->assertSame('private', $visibility->default);
    $this->assertSame('Public', $visibility->option('public')?->label);

    $secret = $form->field('secret');
    $this->assertInstanceOf(Field::class, $secret);
    $this->assertSame(FieldType::Password, $secret->type);
    $this->assertTrue($secret->revealable);
    $this->assertTrue($secret->confirm);

    $timezone = $form->field('timezone');
    $this->assertInstanceOf(Field::class, $timezone);
    $this->assertSame(FieldType::Suggest, $timezone->type);
    $this->assertInstanceOf(Dotenv::class, $timezone->discover);
    $this->assertSame('TZ', $timezone->discover->key);

    $ranking = $form->field('ranking');
    $this->assertInstanceOf(Field::class, $ranking);
    $this->assertSame(FieldType::Reorder, $ranking->type);
    // A partial declared default is completed to a full ranking in declared
    // order: the given values first, the remaining options appended.
    $this->assertSame(['good', 'fast', 'cheap'], $ranking->default);

    $webroot = $form->field('webroot');
    $this->assertInstanceOf(Field::class, $webroot);
    $this->assertSame('web', $webroot->default);
    $this->assertSame('Advanced', $form->panels[0]->panels[0]->title);
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
      ->build();

    // Type defaults when none is declared.
    $this->assertSame('', $form->field('t')?->default);
    $this->assertSame('', $form->field('s')?->default);
    $this->assertSame([], $form->field('m')?->default);
    $this->assertFalse($form->field('c')?->default);
    $this->assertSame('', $form->field('g')?->default);
    $this->assertSame(0, $form->field('n')?->default);
    // A date with no explicit default is empty; the widget opens on today.
    $this->assertSame('', $form->field('dt')?->default);
    $this->assertSame('', $form->field('ta')?->default);
    $this->assertSame('', $form->field('pw')?->default);
    // The password options are opt-in, so they default off.
    $this->assertFalse($form->field('pw')->revealable);
    $this->assertFalse($form->field('pw')->confirm);
    $this->assertSame('', $form->field('se')?->default);
    $this->assertSame([], $form->field('ms')?->default);
    // A toggle defaults to its first option, since it always holds a value.
    $this->assertSame('on', $form->field('tg')?->default);
    // A single picker defaults to an empty path; a multiple picker to no paths.
    $this->assertSame('', $form->field('fp')?->default);
    $this->assertSame([], $form->field('mfp')?->default);
    // A reorder with no declared default ranks every option in declared order.
    $this->assertSame(['a', 'b', 'c'], $form->field('rk')?->default);
    // The picker options are opt-in, so they default off.
    $this->assertSame(FilePickerMode::Any, $form->field('fp')->pickerConstraints->mode);
    $this->assertSame('', $form->field('fp')->pickerStart);
    $this->assertSame([], $form->field('fp')->pickerConstraints->extensions);
    $this->assertFalse($form->field('fp')->pickerShowHidden);
    // A pause defaults to acknowledged so headless runs never block on it.
    $this->assertTrue($form->field('pa')?->default);

    // Label and option-label fall back to the id/value.
    $this->assertSame('t', $form->field('t')->label);
    $this->assertSame('a', $form->field('s')->option('a')?->label);

    // Form-level defaults (the global TUI runtime is tested on the Tui facade).
    $this->assertSame('', $form->subject);
    $this->assertTrue($form->buttons->show);
    $this->assertSame('Submit', $form->buttons->submitLabel);
    $this->assertSame('', $form->envPrefix);
    $this->assertSame('', $form->panels[0]->description);
  }

  public function testStandaloneOptsOutOfInlineEditing(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->confirm('a');
        $panel->select('b')->option('x')->standalone();
        // A later standalone(FALSE) restores inline editing.
        $panel->text('c')->standalone()->standalone(FALSE);
      })
      ->build();

    // A field is edited inline by default.
    $this->assertSame(RenderMode::Inline, $form->field('a')?->render);
    // Declaring it standalone opts out to the full-screen editor.
    $this->assertSame(RenderMode::Standalone, $form->field('b')?->render);
    // standalone(FALSE) restores inline editing.
    $this->assertSame(RenderMode::Inline, $form->field('c')?->render);
  }

  public function testExternalEditorFlag(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->textarea('notes', 'Notes')->externalEditor();
        $panel->textarea('plain', 'Plain');
      })
      ->build();

    $this->assertTrue($form->field('notes')?->externalEditor);
    $this->assertFalse($form->field('plain')?->externalEditor);
  }

  public function testNoteField(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->note('intro', 'Getting started')->description('Fill in each field.');
        $panel->note('bare');
        $panel->note('boxed', 'Boxed')->border();
        $panel->note('stock', 'Stock')->table(['Fruit', 'Qty'], [['Apple', '3'], ['Pear', '5']]);
      })
      ->build();

    $intro = $form->field('intro');
    $this->assertInstanceOf(Field::class, $intro);
    $this->assertSame(FieldType::Note, $intro->type);
    // The title is the label and the body is carried by the description.
    $this->assertSame('Getting started', $intro->label);
    $this->assertSame('Fill in each field.', $intro->description);
    // A note is not bordered unless it opts in.
    $this->assertFalse($intro->bordered);
    // A note carries no table unless it opts in.
    $this->assertNotInstanceOf(TableSpec::class, $intro->table);

    // An omitted title stays empty rather than falling back to the id.
    $this->assertSame('', $form->field('bare')?->label);

    // ->border() draws the card inside a box.
    $this->assertTrue($form->field('boxed')?->bordered);

    // ->table() stores the header cells and body rows on the field.
    $stock = $form->field('stock');
    $this->assertInstanceOf(TableSpec::class, $stock?->table);
    $this->assertSame(['Fruit', 'Qty'], $stock->table->headers);
    $this->assertSame([['Apple', '3'], ['Pear', '5']], $stock->table->rows);
  }

  public function testValidateAndTransformStored(): void {
    $validator = fn (mixed $v): ?string => NULL;
    $transformer = fn (mixed $v): mixed => $v;

    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel) use ($validator, $transformer): void {
        $panel->text('x')->validate($validator)->transform($transformer);
      })
      ->build();

    $field = $form->field('x');
    $this->assertInstanceOf(Field::class, $field);
    $this->assertSame($validator, $field->validate);
    $this->assertSame($transformer, $field->transform);
  }

  public function testRequiredFlagAndMessageStored(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->text('plain', 'Plain');
        $panel->text('name', 'Produce name')->required();
        $panel->text('plot', 'Garden plot name')->required(message: 'The garden plot name is required.');
        $panel->text('note', 'Delivery note')->required(FALSE);
      })
      ->build();

    $plain = $form->field('plain');
    $this->assertInstanceOf(Field::class, $plain);
    $this->assertFalse($plain->required);
    $this->assertSame('', $plain->requiredMessage);

    $name = $form->field('name');
    $this->assertInstanceOf(Field::class, $name);
    $this->assertTrue($name->required);
    $this->assertSame('', $name->requiredMessage);

    $plot = $form->field('plot');
    $this->assertInstanceOf(Field::class, $plot);
    $this->assertTrue($plot->required);
    $this->assertSame('The garden plot name is required.', $plot->requiredMessage);

    $note = $form->field('note');
    $this->assertInstanceOf(Field::class, $note);
    $this->assertFalse($note->required);
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
      ->build();

    $this->assertSame($list, $form->field('name')?->completion);
    $this->assertSame($closure, $form->field('repo')?->completion);
    // A field with no completion source defaults to an empty list.
    $this->assertSame([], $form->field('plain')?->completion);
  }

  public function testEnvNameAndAliasesStored(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->text('crate_size', 'Crate size')->env('LEGACY_CRATE')->envAliases(['OLD_CRATE', 'OLDER_CRATE']);
        $panel->text('grade', 'Grade');
      })
      ->build();

    $crate = $form->field('crate_size');
    $this->assertInstanceOf(Field::class, $crate);
    $this->assertSame('LEGACY_CRATE', $crate->envName);
    $this->assertSame(['OLD_CRATE', 'OLDER_CRATE'], $crate->envAliases);

    // A field that names nothing keeps the mechanical name and no aliases.
    $grade = $form->field('grade');
    $this->assertInstanceOf(Field::class, $grade);
    $this->assertSame('', $grade->envName);
    $this->assertSame([], $grade->envAliases);
  }

  public function testEnvAliasesAreReindexed(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->text('crate_size', 'Crate size')->envAliases([2 => 'OLD_CRATE', 5 => 'OLDER_CRATE']);
      })
      ->build();

    $this->assertSame(['OLD_CRATE', 'OLDER_CRATE'], $form->field('crate_size')?->envAliases);
  }

  public function testGhostTextOptInStored(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->suggest('fruit', 'Fruit')->options(['Apple' => 'Apple'])->ghost();
        $panel->suggest('berry', 'Berry')->options(['Fig' => 'Fig'])->ghost(FALSE);
        $panel->suggest('plain', 'Plain')->options(['Pear' => 'Pear']);
      })
      ->build();

    $this->assertTrue($form->field('fruit')?->ghost);
    $this->assertFalse($form->field('berry')?->ghost);
    // Ghost-text is opt-in, so a field that never asks for it stays without.
    $this->assertFalse($form->field('plain')?->ghost);
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
      ->build();

    $crate = $form->field('crate');
    $this->assertInstanceOf(Field::class, $crate);
    $this->assertInstanceOf(Template::class, $crate->template);
    $this->assertSame('{{orchard}}-{{grade}}', $crate->template->pattern());
    $this->assertSame(['orchard', 'grade'], $crate->template->placeholders());
    $this->assertSame('Orchard', $crate->template->labelOf('orchard'));
    $this->assertSame($grade, $crate->template->validatorOf('grade'));
    $this->assertNotInstanceOf(\Closure::class, $crate->template->validatorOf('orchard'));
    $this->assertSame('valley-a', $crate->default);
  }

  public function testSlotWithoutLabelOrValidatorLeavesBothUnset(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->template('crate', 'Crate')->pattern('{{a}}-{{b}}')->slot('a');
      })
      ->build();

    $template = $form->field('crate')?->template;
    $this->assertInstanceOf(Template::class, $template);
    $this->assertSame('a', $template->labelOf('a'));
    $this->assertNotInstanceOf(\Closure::class, $template->validatorOf('a'));
  }

  public function testHintAndPlaceholderCarryOntoTheField(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->text('crop', 'Crop')
          ->description('The crop being logged.')
          ->hint('Type a few letters to filter.')
          ->placeholder('E.g. Golden Beetroot');
      })
      ->build();

    $crop = $form->field('crop');
    $this->assertInstanceOf(Field::class, $crop);
    $this->assertSame('The crop being logged.', $crop->description);
    $this->assertSame('Type a few letters to filter.', $crop->hint);
    $this->assertSame('E.g. Golden Beetroot', $crop->placeholder);
  }

  public function testHintAndPlaceholderDefaultToEmpty(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->text('crop', 'Crop');
      })
      ->build();

    $crop = $form->field('crop');
    $this->assertInstanceOf(Field::class, $crop);
    $this->assertSame('', $crop->hint);
    $this->assertSame('', $crop->placeholder);
  }

  public function testTemplateWithoutPatternThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Field "crate" is a template field but declares no pattern');

    Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->template('crate', 'Crate');
      })
      ->build();
  }

  public function testPatternIgnoredOnNonTemplateField(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->text('name', 'Name')->pattern('{{a}}-{{b}}');
      })
      ->build();

    $this->assertNotInstanceOf(Template::class, $form->field('name')?->template);
  }

  public function testNumberBoundsAssembled(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->number('port', 'Port')->min(1)->max(65535)->step(5);
        $panel->number('plain', 'Plain');
      })
      ->build();

    $port = $form->field('port');
    $this->assertInstanceOf(Field::class, $port);
    $this->assertInstanceOf(NumberBounds::class, $port->bounds);
    $this->assertSame(1, $port->bounds->min);
    $this->assertSame(65535, $port->bounds->max);
    $this->assertSame(5, $port->bounds->step);

    // A number with nothing declared carries no bounds - behaviour unchanged.
    $this->assertNotInstanceOf(NumberBounds::class, $form->field('plain')?->bounds);
  }

  public function testRatingScaleAssembled(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->rating('nps', 'Recommend us')->min(0)->max(10);
        $panel->rating('taste', 'Taste');
      })
      ->build();

    $nps = $form->field('nps');
    $this->assertInstanceOf(Field::class, $nps);
    $this->assertInstanceOf(NumberBounds::class, $nps->bounds);
    $this->assertSame(0, $nps->bounds->min);
    $this->assertSame(10, $nps->bounds->max);
    $this->assertSame(0, $nps->default);

    // A rating with nothing declared still carries a scale: one to five,
    // sitting on its lowest point.
    $taste = $form->field('taste');
    $this->assertInstanceOf(NumberBounds::class, $taste?->bounds);
    $this->assertSame(1, $taste->bounds->min);
    $this->assertSame(5, $taste->bounds->max);
    $this->assertNull($taste->bounds->step);
    $this->assertSame(1, $taste->default);
  }

  public function testRatingKeepsDeclaredDefault(): void {
    $form = Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->rating('taste')->default(4))
      ->build();

    $this->assertSame(4, $form->field('taste')?->default);
  }

  public function testRatingCaptionsAssembled(): void {
    $form = Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->rating('taste')->captions([1 => 'Poor', 5 => 'Excellent']))
      ->build();

    $this->assertSame([1 => 'Poor', 5 => 'Excellent'], $form->field('taste')?->ratingCaptions);
  }

  public function testRatingStepThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Field "r" declares a step of 2 on a scale whose points are its steps');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->rating('r')->step(2))
      ->build();
  }

  #[DataProvider('dataProviderRatingCollapsedScaleThrows')]
  public function testRatingCollapsedScaleThrows(int $min, int $max): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage(sprintf('Field "r" declares a scale from %d to %d; a scale needs at least two points', $min, $max));

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->rating('r')->min($min)->max($max))
      ->build();
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
      ->build();

    $birthday = $form->field('birthday');
    $this->assertInstanceOf(Field::class, $birthday);
    $this->assertInstanceOf(DateBounds::class, $birthday->dateBounds);
    $this->assertSame('2000-01-01', $birthday->dateBounds->min?->format('Y-m-d'));
    $this->assertSame('2030-12-31', $birthday->dateBounds->max?->format('Y-m-d'));
    $this->assertSame(Weekday::Sunday, $birthday->dateBounds->weekStart);

    // A date with nothing declared still carries bounds, defaulting to a
    // Monday-first, open range.
    $plain = $form->field('plain');
    $this->assertInstanceOf(DateBounds::class, $plain?->dateBounds);
    $this->assertNotInstanceOf(\DateTimeImmutable::class, $plain->dateBounds->min);
    $this->assertNotInstanceOf(\DateTimeImmutable::class, $plain->dateBounds->max);
    $this->assertSame(Weekday::Monday, $plain->dateBounds->weekStart);
  }

  public function testDateInvalidBoundThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Field "d" declares an invalid date "2026-13-01".');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->calendar('d')->minDate('2026-13-01'))
      ->build();
  }

  public function testDateMinAfterMaxThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Field "d" declares min date 2026-12-31 after max date 2026-01-01.');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->calendar('d')->minDate('2026-12-31')->maxDate('2026-01-01'))
      ->build();
  }

  public function testDateBoundsIgnoredOnNonDateField(): void {
    $form = Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->text('t')->minDate('2020-01-01')->weekStart(Weekday::Sunday))
      ->build();

    // The date setters are inert on a non-date field: no bounds are attached.
    $this->assertNotInstanceOf(DateBounds::class, $form->field('t')?->dateBounds);
  }

  public function testNumberMinGreaterThanMaxThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Field "n" declares min 10 greater than max 1.');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->number('n')->min(10)->max(1))
      ->build();
  }

  public function testNumberNonPositiveStepThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Field "n" declares a non-positive step 0.');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->number('n')->step(0))
      ->build();
  }

  public function testFilePickerNonPositiveMaxSizeThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Field "f" declares a maximum file size of 0 below one byte.');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->filePicker('f')->maxSize(0))
      ->build();
  }

  public function testPageSizeAssembled(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->search('paged', 'Paged')->options(['a' => 'A'])->pageSize(5);
        $panel->search('plain', 'Plain')->options(['a' => 'A']);
      })
      ->build();

    $this->assertSame(5, $form->field('paged')?->pageSize);

    // A field with nothing declared carries no page size and uses the default.
    $this->assertNull($form->field('plain')?->pageSize);
  }

  public function testNonPositivePageSizeThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Field "n" declares a non-positive page size 0.');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->search('n')->pageSize(0))
      ->build();
  }

  public function testMultipleOnUnsupportedTypeThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Field "t" of type "text" does not collect several values');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->text('t')->multiple())
      ->build();
  }

  public function testSelectionBoundsAssembled(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->select('tags', 'Tags')->multiple()->minSelections(2)->maxSelections(4)->option('a')->option('b');
        $panel->search('svc', 'Services')->multiple()->minSelections(1)->option('x');
        $panel->filePicker('files', 'Files')->multiple()->maxSelections(3);
        $panel->select('plain', 'Plain')->multiple()->option('a');
      })
      ->build();

    $tags = $form->field('tags');
    $this->assertInstanceOf(Field::class, $tags);
    $this->assertInstanceOf(SelectionBounds::class, $tags->selectionBounds);
    $this->assertSame(2, $tags->selectionBounds->min);
    $this->assertSame(4, $tags->selectionBounds->max);

    // A min-only bound leaves the ceiling open.
    $svc = $form->field('svc');
    $this->assertInstanceOf(SelectionBounds::class, $svc?->selectionBounds);
    $this->assertSame(1, $svc->selectionBounds->min);
    $this->assertNull($svc->selectionBounds->max);

    // A file picker also takes selection bounds; a max-only bound leaves the
    // floor open.
    $files = $form->field('files');
    $this->assertInstanceOf(SelectionBounds::class, $files?->selectionBounds);
    $this->assertNull($files->selectionBounds->min);
    $this->assertSame(3, $files->selectionBounds->max);

    // A multiple field with no selection limits carries none.
    $this->assertNotInstanceOf(SelectionBounds::class, $form->field('plain')?->selectionBounds);
  }

  public function testSelectionLimitOnNonMultipleThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Field "s" declares selection limits but is not multiple');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->select('s')->minSelections(2)->option('a'))
      ->build();
  }

  public function testSelectionMinGreaterThanMaxThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Field "s" declares min 5 selections greater than max 2.');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->select('s')->multiple()->minSelections(5)->maxSelections(2))
      ->build();
  }

  public function testSelectionMinBelowOneThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Selection bounds declare a minimum of 0 below one.');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->select('s')->multiple()->minSelections(0))
      ->build();
  }

  public function testFilePickerOptions(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->filePicker('config', 'Config')->startIn('/opt')->filesOnly()->extensions(['yml', 'yaml'])->showHidden()->maxSize(1048576);
        $panel->filePicker('assets', 'Assets')->multiple()->directoriesOnly();
      })
      ->build();

    $form_field = $form->field('config');
    $this->assertInstanceOf(Field::class, $form_field);
    $this->assertSame(FieldType::FilePicker, $form_field->type);
    $this->assertSame(FilePickerMode::File, $form_field->pickerConstraints->mode);
    $this->assertSame('/opt', $form_field->pickerStart);
    $this->assertSame(['yml', 'yaml'], $form_field->pickerConstraints->extensions);
    $this->assertSame(1048576, $form_field->pickerConstraints->maxSize);
    $this->assertTrue($form_field->pickerShowHidden);

    $assets = $form->field('assets');
    $this->assertInstanceOf(Field::class, $assets);
    $this->assertSame(FieldType::FilePicker, $assets->type);
    $this->assertTrue($assets->multiple);
    $this->assertSame(FilePickerMode::Directory, $assets->pickerConstraints->mode);
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
      ->build();

    $profile = $form->field('profile');
    $this->assertInstanceOf(Field::class, $profile);

    $options = $profile->options;
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
      ->build();

    $field = $form->field('s');
    $this->assertInstanceOf(Field::class, $field);

    // The second declaration overrides the first in place; the separator stays.
    $this->assertCount(2, $field->options);
    $this->assertSame('Second', $field->option('a')?->label);
    $this->assertSame(['a'], $field->selectableValues());
  }

  public function testDuplicateFieldIdThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Duplicate field id "x".');

    Form::create('T')
      ->panel('a', 'A', fn(PanelBuilder $p): FieldBuilder => $p->text('x'))
      ->panel('b', 'B', fn(PanelBuilder $p): FieldBuilder => $p->text('x'))
      ->build();
  }

  public function testToggleWithoutTwoOptionsThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Toggle field "t" must have exactly two options, 1 given.');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->toggle('t')->option('only'))
      ->build();
  }

  #[DataProvider('dataProviderToggleInvalidDefaultThrows')]
  public function testToggleInvalidDefaultThrows(mixed $default): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Toggle field "t" default must be one of: a, b.');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->toggle('t')->option('a')->option('b')->default($default))
      ->build();
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
      ->build();

    // The implicit default is the first option's value "0" as a string, not a
    // numeric-string coerced to int by the array key.
    $this->assertSame('0', $form->field('flag')?->default);
  }

  public function testReorderToleratesDirtyDefault(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $p): void {
        $p->reorder('rk')->option('a')->option('b')->default('notalist');
        $p->reorder('rk2')->option('a')->option('b')->default(['b', 42, 'a']);
      })
      ->build();

    // A non-list default falls back to the full declared order.
    $this->assertSame(['a', 'b'], $form->field('rk')?->default);
    // Non-string entries are ignored; the remaining values still complete it.
    $this->assertSame(['b', 'a'], $form->field('rk2')?->default);
  }

  public function testReorderWithFewerThanTwoOptionsThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Reorder field "r" must have at least two options, 1 given.');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->reorder('r')->option('only'))
      ->build();
  }

  public function testReorderWithStructuralOptionThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Reorder field "r" allows only plain options - no headings, separators or disabled rows.');

    Form::create('T')
      ->panel('p', 'P', fn(PanelBuilder $p): FieldBuilder => $p->reorder('r')->option('a')->separator()->option('b'))
      ->build();
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
      ->build();

    $modal = $form->panels[0]->panels[0];
    $this->assertTrue($modal->isModal());
    $this->assertSame('This cannot be undone.', $modal->description);

    $config = $modal->modal;
    $this->assertInstanceOf(Modal::class, $config);
    $this->assertSame('Yes', $config->buttons->submitLabel);
    $this->assertSame('No', $config->buttons->cancelLabel);
    $this->assertTrue($config->buttons->show);
  }

  public function testModalDefaultsButtonLabels(): void {
    $form = Form::create('T')
      ->panel('m', 'M', fn(PanelBuilder $p): PanelBuilder => $p->modal())
      ->build();

    $config = $form->panels[0]->modal;
    $this->assertInstanceOf(Modal::class, $config);
    $this->assertSame('Submit', $config->buttons->submitLabel);
    $this->assertSame('Cancel', $config->buttons->cancelLabel);
  }

  public function testModalPanelWithSubPanelThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Modal panel "confirm" cannot contain sub-panels.');

    Form::create('T')
      ->panel('confirm', 'Confirm', function (PanelBuilder $m): void {
        $m->modal();
        $m->panel('nested', 'Nested', fn(PanelBuilder $n): FieldBuilder => $n->text('x'));
      })
      ->build();
  }

  public function testLayoutFlowsToTheDefinitionAndPanels(): void {
    $form = Form::create('Demo')
      ->layout(1, 2)
      ->panel('a', 'A', function (PanelBuilder $p): void {
        $p->layout(2);
        $p->panel('a1', 'A1', fn(PanelBuilder $sp): FieldBuilder => $sp->text('one', 'One'));
        $p->panel('a2', 'A2', fn(PanelBuilder $sp): FieldBuilder => $sp->text('two', 'Two'));
      })
      ->panel('b', 'B', fn(PanelBuilder $p): FieldBuilder => $p->text('three', 'Three'))
      ->panel('c', 'C', fn(PanelBuilder $p): FieldBuilder => $p->text('four', 'Four'))
      ->build();

    $this->assertSame([1, 2], $form->layout);
    $this->assertSame([2], $form->panels[0]->layout);
    // A panel without a declaration keeps the default row list.
    $this->assertSame([], $form->panels[1]->layout);
  }

  #[DataProvider('dataProviderLayoutMismatchThrows')]
  public function testLayoutMismatchThrows(\Closure $declare, string $message): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage($message);

    $declare();
  }

  public static function dataProviderLayoutMismatchThrows(): \Iterator {
    yield 'form slots below the panels' => [
      static function (): void {
        Form::create('Demo')
          ->layout(1)
          ->panel('a', 'A', fn(PanelBuilder $p): FieldBuilder => $p->text('one', 'One'))
          ->panel('b', 'B', fn(PanelBuilder $p): FieldBuilder => $p->text('two', 'Two'))
          ->build();
      },
      'The layout of "Demo" declares 1 slot(s) for 2 panel(s).',
    ];

    yield 'form slots above the panels' => [
      static function (): void {
        Form::create('Demo')
          ->layout(2, 2)
          ->panel('a', 'A', fn(PanelBuilder $p): FieldBuilder => $p->text('one', 'One'))
          ->panel('b', 'B', fn(PanelBuilder $p): FieldBuilder => $p->text('two', 'Two'))
          ->build();
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
          ->build();
      },
      'The layout of "a" declares 2 slot(s) for 1 panel(s).',
    ];

    yield 'zero-width row' => [
      static function (): void {
        Form::create('Demo')
          ->layout(0, 2)
          ->panel('a', 'A', fn(PanelBuilder $p): FieldBuilder => $p->text('one', 'One'))
          ->panel('b', 'B', fn(PanelBuilder $p): FieldBuilder => $p->text('two', 'Two'))
          ->build();
      },
      'Every layout row of "Demo" must hold at least one panel.',
    ];
  }

}
