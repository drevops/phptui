<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Schema;

use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Tree;
use DrevOps\Tui\Builder\FieldBuilder;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Model\FilePickerConstraints;
use DrevOps\Tui\Schema\SchemaValidator;
use DrevOps\Tui\Screen\Layout\PanelLayout;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the schema validator.
 */
#[CoversClass(SchemaValidator::class)]
#[CoversClass(Tree::class)]
#[Group('schema')]
final class SchemaValidatorTest extends TestCase {

  /**
   * An answer set yields no errors, or exactly the one expected error.
   *
   * @param array<string,mixed> $answers
   *   The answers to validate.
   * @param string|null $expected_error
   *   The expected error message, or NULL when the set must be valid.
   */
  #[DataProvider('dataProviderValidate')]
  public function testValidate(array $answers, ?string $expected_error): void {
    $errors = (new SchemaValidator($this->form()))->validate($answers);

    $this->assertSame($expected_error === NULL ? [] : [$expected_error], $errors);
  }

  /**
   * Data provider for testValidate().
   *
   * @return \Iterator<string,array{array<string,mixed>,string|null}>
   *   Answer sets and the expected error (NULL for a valid set).
   */
  public static function dataProviderValidate(): \Iterator {
    yield 'valid full set' => [['name' => 'Acme', 'profile' => 'standard', 'agree' => TRUE, 'mods' => ['a', 'b']], NULL];
    yield 'missing required' => [['profile' => 'standard'], 'Missing required question "name".'];
    yield 'required empty string' => [['name' => ''], 'Question "name": name is required.'];
    yield 'wrong type' => [['name' => 'Acme', 'agree' => 'yes'], 'Question "agree" must be a boolean.'];
    yield 'invalid select option' => [['name' => 'Acme', 'profile' => 'bogus'], 'Question "profile": value "bogus" is not one of: standard, minimal.'];
    yield 'disabled select option' => [['name' => 'Acme', 'profile' => 'demo'], 'Question "profile": option "demo" is disabled: unavailable.'];
    yield 'invalid multiselect option' => [['name' => 'Acme', 'mods' => ['a', 'z']], 'Question "mods": value "z" is not one of: a, b.'];
    yield 'disabled multiselect option' => [['name' => 'Acme', 'mods' => ['a', 'c']], 'Question "mods": option "c" is disabled.'];
    yield 'multiselect wrong type' => [['name' => 'Acme', 'mods' => 'notalist'], 'Question "mods" must be a list.'];
    yield 'multiselect count within bounds' => [['name' => 'Acme', 'picks' => ['a', 'b']], NULL];
    yield 'multiselect count below min' => [['name' => 'Acme', 'picks' => ['a']], 'Question "picks" must be between 2 and 3 items.'];
    yield 'multiselect count above max' => [['name' => 'Acme', 'picks' => ['a', 'b', 'c', 'd']], 'Question "picks" must be between 2 and 3 items.'];
    yield 'multiselect count empty violates min' => [['name' => 'Acme', 'picks' => []], 'Question "picks" must be between 2 and 3 items.'];
    yield 'unknown question' => [['name' => 'Acme', 'bogus' => 'x'], 'Unknown question "bogus".'];
    // A note is a known field but carries no answer: a stray value for it is
    // neither flagged unknown nor validated (a list would fail a string field).
    yield 'note value ignored' => [['name' => 'Acme', 'intro' => ['not', 'a', 'string']], NULL];
    // 'custom' is required but only appears when profile == custom.
    yield 'inactive required field skipped' => [['name' => 'Acme', 'profile' => 'standard'], NULL];
    yield 'number int accepted' => [['name' => 'Acme', 'port' => 8080], NULL];
    yield 'number string rejected' => [['name' => 'Acme', 'port' => '8080'], 'Question "port" must be a number.'];
    yield 'number out of range' => [['name' => 'Acme', 'port' => 99999], 'Question "port" must be between 1 and 65535.'];
    yield 'date valid' => [['name' => 'Acme', 'due' => '2026-07-15'], NULL];
    // A wrongly-padded value and an impossible calendar date are both rejected.
    yield 'date unpadded rejected' => [['name' => 'Acme', 'due' => '2026-7-5'], 'Question "due" must be a date (YYYY-MM-DD).'];
    yield 'date impossible rejected' => [['name' => 'Acme', 'due' => '2026-02-30'], 'Question "due" must be a date (YYYY-MM-DD).'];
    // The inclusive endpoints are accepted; a date on either side is rejected.
    yield 'date at lower endpoint' => [['name' => 'Acme', 'due' => '2026-01-01'], NULL];
    yield 'date at upper endpoint' => [['name' => 'Acme', 'due' => '2026-12-31'], NULL];
    yield 'date before range' => [['name' => 'Acme', 'due' => '2025-12-31'], 'Question "due" must be between 2026-01-01 and 2026-12-31.'];
    yield 'date after range' => [['name' => 'Acme', 'due' => '2027-01-01'], 'Question "due" must be between 2026-01-01 and 2026-12-31.'];
    yield 'pause bool accepted' => [['name' => 'Acme', 'ack' => TRUE], NULL];
    yield 'pause string rejected' => [['name' => 'Acme', 'ack' => 'yes'], 'Question "ack" must be a boolean.'];
    yield 'search member accepted' => [['name' => 'Acme', 'engine' => 'solr'], NULL];
    yield 'search unknown rejected' => [['name' => 'Acme', 'engine' => 'bogus'], 'Question "engine": value "bogus" is not one of: solr, none.'];
    yield 'multisearch member accepted' => [['name' => 'Acme', 'tags' => ['a']], NULL];
    yield 'multisearch unknown rejected' => [['name' => 'Acme', 'tags' => ['z']], 'Question "tags": value "z" is not one of: a, b.'];
    yield 'toggle member accepted' => [['name' => 'Acme', 'visibility' => 'private'], NULL];
    yield 'toggle unknown rejected' => [['name' => 'Acme', 'visibility' => 'bogus'], 'Question "visibility": value "bogus" is not one of: public, private.'];
    yield 'file picker string accepted' => [['name' => 'Acme', 'cfg' => '/etc/app.yml'], NULL];
    yield 'file picker list rejected' => [['name' => 'Acme', 'cfg' => ['x']], 'Question "cfg" must be a string.'];
    yield 'multi file picker list accepted' => [['name' => 'Acme', 'paths' => ['/a', '/b']], NULL];
    yield 'multi file picker string rejected' => [['name' => 'Acme', 'paths' => 'notalist'], 'Question "paths" must be a list.'];
    yield 'reorder full permutation accepted' => [['name' => 'Acme', 'ranking' => ['z', 'x', 'y']], NULL];
    yield 'reorder wrong type' => [['name' => 'Acme', 'ranking' => 'notalist'], 'Question "ranking" must be a list.'];
    yield 'reorder incomplete rejected' => [['name' => 'Acme', 'ranking' => ['x', 'y']], 'Question "ranking": must rank every option exactly once (x, y, z).'];
    yield 'reorder unknown item rejected' => [['name' => 'Acme', 'ranking' => ['x', 'y', 'w']], 'Question "ranking": value "w" is not one of: x, y, z.'];
  }

  /**
   * An empty value on a required field reports the field's required message.
   *
   * @param string $id
   *   The id of the field under test.
   * @param mixed $value
   *   The value supplied for the field under test.
   * @param string|null $expected_error
   *   The expected error message, or NULL when the value is accepted.
   */
  #[DataProvider('dataProviderRequired')]
  public function testRequired(string $id, mixed $value, ?string $expected_error): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('name', 'Produce name')->required();
        $p->select('crates', 'Crates')->multiple()->required()->option('a')->option('b');
        $p->text('plot', 'Garden plot name')->required(message: 'The garden plot name is required.');
        $p->text('note', 'Delivery note');
      })
      ->root();

    $answers = ['name' => 'Pear', 'crates' => ['a'], 'plot' => 'North bed', 'note' => ''];
    $answers[$id] = $value;

    $this->assertSame($expected_error === NULL ? [] : [$expected_error], (new SchemaValidator($form))->validate($answers));
  }

  /**
   * Data provider for testRequired().
   *
   * @return \Iterator<string,array{string,mixed,string|null}>
   *   The field id, the value supplied for it and the expected error.
   */
  public static function dataProviderRequired(): \Iterator {
    yield 'empty string' => ['name', '', 'Question "name": Produce name is required.'];
    yield 'empty list' => ['crates', [], 'Question "crates": Crates is required.'];
    yield 'declared message wins over the label' => ['plot', '', 'Question "plot": The garden plot name is required.'];
    yield 'non-empty value accepted' => ['name', 'Pear', NULL];
    // Emptiness is only rejected where the field asked for it.
    yield 'empty value on an optional field accepted' => ['note', '', NULL];
  }

  public function testNumericStringOptionMembership(): void {
    $form = Form::create('T')
      ->panel('p', 'p', fn(PanelBuilder $p): FieldBuilder => $p->toggle('flag')->option('0', 'Off')->option('1', 'On'))
      ->root();
    $validator = new SchemaValidator($form);

    // A numeric-string value stays valid: values are compared as strings.
    $this->assertSame([], $validator->validate(['flag' => '1']));
    $this->assertSame(['Question "flag": value "2" is not one of: 0, 1.'], $validator->validate(['flag' => '2']));
  }

  public function testValidatesFilePickerConstraints(): void {
    vfsStream::setup('root', NULL, ['ok.yml' => str_repeat('a', 10), 'big.yml' => str_repeat('a', 500)]);
    $root = vfsStream::url('root');
    $form = Form::create('T')
      ->panel('p', 'p', fn(PanelBuilder $p): FieldBuilder => $p->filePicker('cfg')->filesOnly()->extensions(['yml'])->maxSize(100))
      ->root();
    $validator = new SchemaValidator($form);

    $this->assertSame([], $validator->validate(['cfg' => $root . '/ok.yml']));
    $this->assertSame(['Question "cfg" must be an existing file.'], $validator->validate(['cfg' => $root . '/missing.yml']));
    $this->assertSame(['Question "cfg" must be a file no larger than 100 B.'], $validator->validate(['cfg' => $root . '/big.yml']));
  }

  public function testFilePickerConstraintsIgnoredOnNonPickerField(): void {
    // A hand-built field takes a picker limit its kind never weighs a value
    // against, so the plain string value passes rather than being read as a
    // filesystem path.
    $panel = (new Panel('p', 'p'))->layout(new PanelLayout());
    $panel->in('content')->add((new Field('name', 'Name'))->picker(new FilePickerConstraints(maxSize: 100)));

    $this->assertSame([], (new SchemaValidator($panel))->validate(['name' => 'not-a-real-path']));
  }

  public function testRequiredQuestionTheAnswersNeverAskIsNotOwed(): void {
    // The section is not there, so the question inside it was never asked and
    // a payload that leaves it out is complete.
    $this->assertSame([], (new SchemaValidator($this->gatedForm()))->validate(['organic' => FALSE]));
  }

  public function testRequiredQuestionTheAnswersDoAskIsOwed(): void {
    // The very same payload plus the answer that puts the section on the form
    // now owes the question, and the refusal names it.
    $this->assertSame(['Missing required question "certifier".'], (new SchemaValidator($this->gatedForm()))->validate(['organic' => TRUE]));
  }

  public function testValueTheAnswersTakeAwayCannotWidenAnotherQuestionsOptions(): void {
    // The category sits in a section this payload switches off, so collection
    // drops it before the variety's options resolve - membership here has to
    // read the same dropped set, or the validator allows a pick the form
    // refuses.
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->confirm('organic', 'Organic only?');

        $p->panel('sourcing', 'Sourcing', function (PanelBuilder $sp): void {
          $sp->when(new Condition('organic', eq: TRUE));
          $sp->text('category', 'Category');
        });

        $p->select('variety', 'Variety')->options(static fn(Context $context): array => ($context->answers['category'] ?? '') === 'stone' ? ['plum' => 'Plum'] : ['apple' => 'Apple']);
      });

    $refusals = (new SchemaValidator($form->root()))->validate(['organic' => FALSE, 'category' => 'stone', 'variety' => 'plum']);

    $this->assertNotSame([], $refusals);
    $this->assertStringContainsString('variety', implode(' ', $refusals));
  }

  public function testValueTheAnswersTakeAwayCannotAskForAnythingElse(): void {
    // The section is not there, so the value the payload carries for a question
    // inside it was never asked for - and a value nobody was asked for cannot
    // be what puts a required question elsewhere on the form.
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->confirm('organic', 'Organic only?');

        $p->panel('certification', 'Certification', function (PanelBuilder $sp): void {
          $sp->when(new Condition('organic', eq: TRUE));
          $sp->text('certifier', 'Certifier');
        });

        $p->text('auditor', 'Auditor')->required()->when(new Condition('certifier', eq: 'Valley Orchard'));
      })
      ->root();

    $this->assertSame([], (new SchemaValidator($form))->validate(['organic' => FALSE, 'certifier' => 'Valley Orchard']));
    // The same chain still owes the question once the section is there.
    $this->assertSame(['Missing required question "auditor".'], (new SchemaValidator($form))->validate(['organic' => TRUE, 'certifier' => 'Valley Orchard']));
  }

  public function testQuestionsThatTakeEachOtherAwaySettleRatherThanSpin(): void {
    // Each rule holds only while the other's answer is absent, so dropping one
    // puts the other back and the reading has no fixed point. It is bounded
    // for exactly this, so the set is answered rather than spun on.
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('crate', 'Crate')->when(new Condition('pallet', ne: 'B'));
        $p->text('pallet', 'Pallet')->when(new Condition('crate', ne: 'A'));
      })
      ->root();

    $this->assertSame([], (new SchemaValidator($form))->validate(['crate' => 'A', 'pallet' => 'B']));
  }

  /**
   * A form whose required question is asked only behind an earlier answer.
   *
   * @return \DrevOps\Tui\Block\Panel
   *   The panel every declared panel hangs from.
   */
  protected function gatedForm(): Panel {
    return Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->confirm('organic', 'Organic only?');

        $p->panel('certification', 'Certification', function (PanelBuilder $sp): void {
          $sp->when(new Condition('organic', eq: TRUE));
          $sp->text('certifier', 'Certifier')->required();
        });
      })
      ->root();
  }

  /**
   * Build a form exercising every validation branch.
   */
  protected function form(): Panel {
    return Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->note('intro', 'Intro')->body('Welcome.');
        $p->text('name')->required();
        $p->select('profile')->option('standard')->option('minimal')->option('demo', 'Demo', disabled: TRUE, disabled_reason: 'unavailable');
        $p->confirm('agree');
        $p->select('mods')->multiple()->option('a')->option('b')->option('c', 'C', disabled: TRUE);
        $p->select('picks')->multiple()->minSelections(2)->maxSelections(3)->option('a')->option('b')->option('c')->option('d');
        $p->text('custom')->required()->when(new Condition('profile', eq: 'custom'));
        $p->number('port')->min(1)->max(65535);
        $p->calendar('due')->minDate('2026-01-01')->maxDate('2026-12-31');
        $p->pause('ack');
        $p->search('engine')->option('solr')->option('none');
        $p->search('tags')->multiple()->option('a')->option('b');
        $p->toggle('visibility')->option('public')->option('private');
        $p->filePicker('cfg');
        $p->filePicker('paths')->multiple();
        $p->reorder('ranking')->option('x')->option('y')->option('z');
      })
      ->root();
  }

}
