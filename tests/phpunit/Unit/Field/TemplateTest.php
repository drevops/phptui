<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Field;

use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Model\Template as TemplateModel;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\FieldRunner;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Field\Capability\TextEditCapableTrait;
use DrevOps\Tui\Field\Template;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the template field.
 */
#[CoversClass(Template::class)]
#[CoversTrait(TextEditCapableTrait::class)]
#[Group('field')]
final class TemplateTest extends TestCase {

  public function testSeedsEverySlotFromTheAssembledValue(): void {
    $field = new Template(new TemplateModel('{{a}}-{{b}}'), 'one-two');

    $this->assertSame('one-two', $field->value());
  }

  public function testSeedsEmptyWhenTheValueDoesNotMatch(): void {
    $field = new Template(new TemplateModel('{{a}}-{{b}}'), 'nope');

    $this->assertSame('-', $field->value());
  }

  public function testTypingFillsTheSlotHoldingTheCaret(): void {
    $field = new Template(new TemplateModel('{{a}}-{{b}}'));

    $value = FieldRunner::run($field, ArrayKeyStream::of('one', Key::named(KeyName::Enter)));

    $this->assertSame('one-', $value);
  }

  #[DataProvider('dataProviderMovesBetweenSlots')]
  public function testMovesBetweenSlots(array $keys, string $expected): void {
    $field = new Template(new TemplateModel('{{a}}-{{b}}-{{c}}'));

    $value = FieldRunner::run($field, ArrayKeyStream::of(...[...$keys, Key::named(KeyName::Enter)]));

    $this->assertSame($expected, $value);
  }

  public static function dataProviderMovesBetweenSlots(): \Iterator {
    $tab = Key::named(KeyName::Tab);
    $down = Key::named(KeyName::Down);
    $up = Key::named(KeyName::Up);

    yield 'tab advances' => [['x', $tab, 'y', $tab, 'z'], 'x-y-z'];
    yield 'down advances like tab' => [['x', $down, 'y', $down, 'z'], 'x-y-z'];
    yield 'up goes back' => [['x', $tab, 'y', $up, 'z'], 'xz-y-'];
    yield 'forward wraps to the first slot' => [[$tab, $tab, $tab, 'x'], 'x--'];
    yield 'back wraps to the last slot' => [[$up, 'x'], '--x'];
  }

  public function testEditsTheSlotItReturnsTo(): void {
    $tab = Key::named(KeyName::Tab);
    $field = new Template(new TemplateModel('{{a}}-{{b}}'), 'one-two');

    // Tab away and back, then delete a character: the value comes back with
    // the buffer, so the edit lands on the original text and not on an empty
    // slot.
    $value = FieldRunner::run($field, ArrayKeyStream::of($tab, $tab, Key::named(KeyName::Backspace), Key::named(KeyName::Enter)));

    $this->assertSame('on-two', $value);
  }

  public function testMovesTheCaretInsideTheActiveSlot(): void {
    $field = new Template(new TemplateModel('{{a}}-{{b}}'), 'one-two');

    // Left steps back inside the slot, so the inserted character lands before
    // the last one rather than at the end.
    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Left), 'X', Key::named(KeyName::Enter)));

    $this->assertSame('onXe-two', $value);
  }

  public function testValidatesTheSlotBeingLeftWithoutHoldingTheCaret(): void {
    $field = new Template($this->gradedTemplate());
    $theme = new DefaultTheme();

    $field->handle(Key::char('z'));
    $field->handle(Key::named(KeyName::Tab));

    // The rejected slot reports its error, but the caret has still moved on.
    $this->assertStringContainsString('Grade: use a single letter a-c', $field->view($theme));
    $this->assertStringContainsString('filling in Crate', $field->view($theme));
  }

  public function testClearsTheErrorWhenTheSlotBecomesValid(): void {
    $field = new Template($this->gradedTemplate());
    $theme = new DefaultTheme();

    $field->handle(Key::char('z'));
    $field->handle(Key::named(KeyName::Tab));
    $field->handle(Key::named(KeyName::Tab));
    $field->handle(Key::named(KeyName::Backspace));
    $field->handle(Key::char('b'));
    $field->handle(Key::named(KeyName::Tab));

    $this->assertStringNotContainsString('use a single letter a-c', $field->view($theme));
  }

  public function testAcceptRejectsAnInvalidSlotAndTakesTheCaretToIt(): void {
    $field = new Template($this->gradedTemplate());
    $theme = new DefaultTheme();

    // Fill the second slot, then accept while the first is still invalid.
    $field->handle(Key::named(KeyName::Tab));
    $field->handle(Key::char('9'));
    $field->handle(Key::named(KeyName::Enter));

    $this->assertFalse($field->isComplete());
    $this->assertStringContainsString('Grade: use a single letter a-c', $field->view($theme));
    $this->assertStringContainsString('filling in Grade', $field->view($theme));
  }

  public function testAcceptsOnceEverySlotIsValid(): void {
    $field = new Template($this->gradedTemplate());

    $value = FieldRunner::run($field, ArrayKeyStream::of('a', Key::named(KeyName::Tab), '9', Key::named(KeyName::Enter)));

    $this->assertSame('a-9', $value);
  }

  public function testAcceptRejectsSlotHoldingTheSeparator(): void {
    $field = new Template(new TemplateModel('{{a}}-{{b}}', ['a' => 'Head']));
    $theme = new DefaultTheme();

    // "one-x" would move the boundary, so the answer would read back as
    // a="one", b="x-two" - nothing like what was typed.
    FieldRunner::run($field, ArrayKeyStream::of('one-x', Key::named(KeyName::Tab), 'two', Key::named(KeyName::Enter)));

    $this->assertFalse($field->isComplete());
    $this->assertStringContainsString('Head: must not contain "-".', $field->view($theme));
    $this->assertStringContainsString('filling in Head', $field->view($theme));
  }

  public function testAcceptAllowsTheSeparatorInTheLastSlot(): void {
    $field = new Template(new TemplateModel('{{a}}-{{b}}'));

    // The last slot runs to the end of the string, so it can hold the
    // separator without moving any boundary.
    $value = FieldRunner::run($field, ArrayKeyStream::of('one', Key::named(KeyName::Tab), 'two-x', Key::named(KeyName::Enter)));

    $this->assertSame('one-two-x', $value);
  }

  public function testFieldValidatorRunsAgainstTheAssembledValue(): void {
    $field = (new Template(new TemplateModel('{{a}}-{{b}}')))
      ->setHandlers(validate: static fn(mixed $value): ?string => $value === 'one-two' ? NULL : 'Unknown crate.');
    $theme = new DefaultTheme();

    $field->handle(Key::named(KeyName::Enter));
    $this->assertFalse($field->isComplete());
    $this->assertStringContainsString('Unknown crate.', $field->view($theme));
  }

  public function testTransformerAppliesToTheAssembledValue(): void {
    $field = (new Template(new TemplateModel('{{a}}-{{b}}'), 'one-two'))
      ->setHandlers(transform: static fn(mixed $value): string => is_string($value) ? strtoupper($value) : '');

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame('ONE-TWO', $value);
  }

  public function testCancel(): void {
    $field = new Template(new TemplateModel('{{a}}-{{b}}'), 'one-two');

    FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Escape)));

    $this->assertTrue($field->isCancelled());
  }

  #[DataProvider('dataProviderRendersTheShape')]
  public function testRendersTheShape(bool $unicode, string $caret): void {
    $field = new Template(new TemplateModel('crate {{a}}-{{b}} ready', ['b' => 'Tail']), 'crate one-two ready');

    $view = $field->view(new DefaultTheme(76, ['color' => FALSE, 'unicode' => $unicode]));

    // The fixed text frames the filled slots, and the caret marks the live one.
    $this->assertStringContainsString('crate one' . $caret . '-two ready', $view);
    $this->assertStringContainsString('filling in a', $view);
  }

  public static function dataProviderRendersTheShape(): \Iterator {
    yield 'unicode' => [TRUE, '█'];
    yield 'ascii' => [FALSE, '|'];
  }

  public function testEmptySlotShowsItsLabelAsHint(): void {
    $field = new Template(new TemplateModel('{{a}}-{{b}}', ['b' => 'Tail']));

    $view = $field->view(new DefaultTheme(76, ['color' => FALSE, 'unicode' => FALSE]));

    // The caret sits on the first slot; the empty second one names itself so
    // the shape does not collapse to its fixed text alone.
    $this->assertStringContainsString('|-Tail', $view);
  }

  public function testFilledSlotShowsItsValueNotItsLabel(): void {
    $field = new Template(new TemplateModel('{{a}}-{{b}}', ['b' => 'Tail']), 'one-two');

    $view = $field->view(new DefaultTheme(76, ['color' => FALSE, 'unicode' => FALSE]));

    $this->assertStringContainsString('-two', $view);
    $this->assertStringNotContainsString('Tail', $view);
  }

  public function testHintLeadsWithSlotNavigation(): void {
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new Template(new TemplateModel('{{a}}-{{b}}')))->hints());

    $this->assertSame(['move between parts', 'accept', 'cancel'], $labels);
  }

  /**
   * A two-slot template whose first slot takes a single letter a-c.
   *
   * @return \DrevOps\Tui\Model\Template
   *   The template.
   */
  protected function gradedTemplate(): TemplateModel {
    return new TemplateModel('{{grade}}-{{crate}}', ['grade' => 'Grade', 'crate' => 'Crate'], [
      'grade' => static fn(string $value): ?string => preg_match('/^[a-c]$/', $value) === 1 ? NULL : 'use a single letter a-c',
    ]);
  }

}
