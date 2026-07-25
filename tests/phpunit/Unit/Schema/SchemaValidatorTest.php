<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Schema;

use DrevOps\Tui\Builder\FieldBuilder;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Schema\SchemaValidator;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the schema validator.
 */
#[CoversClass(SchemaValidator::class)]
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
    yield 'required empty string' => [['name' => ''], 'Question "name" is required.'];
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

  public function testNumericStringOptionMembership(): void {
    $form = Form::create('T')
      ->panel('p', 'p', fn(PanelBuilder $p): FieldBuilder => $p->toggle('flag')->option('0', 'Off')->option('1', 'On'))
      ->build();
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
      ->build();
    $validator = new SchemaValidator($form);

    $this->assertSame([], $validator->validate(['cfg' => $root . '/ok.yml']));
    $this->assertSame(['Question "cfg" must be an existing file.'], $validator->validate(['cfg' => $root . '/missing.yml']));
    $this->assertSame(['Question "cfg" must be a file no larger than 100 B.'], $validator->validate(['cfg' => $root . '/big.yml']));
  }

  public function testFilePickerConstraintsIgnoredOnNonPickerField(): void {
    // A picker limit mistakenly set on a non-picker field never applies: the
    // field's plain string value is not weighed as a filesystem path.
    $form = Form::create('T')
      ->panel('p', 'p', fn(PanelBuilder $p): FieldBuilder => $p->text('name')->maxSize(100))
      ->build();

    $this->assertSame([], (new SchemaValidator($form))->validate(['name' => 'not-a-real-path']));
  }

  /**
   * Build a form exercising every validation branch.
   */
  protected function form(): FormDefinition {
    return Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->note('intro', 'Intro')->description('Welcome.');
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
      ->build();
  }

}
