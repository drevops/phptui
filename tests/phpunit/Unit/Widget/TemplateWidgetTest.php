<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Widget;

use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Model\Template;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\WidgetRunner;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\Capability\TextEditCapableTrait;
use DrevOps\Tui\Widget\TemplateWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the template widget.
 */
#[CoversClass(TemplateWidget::class)]
#[CoversTrait(TextEditCapableTrait::class)]
#[Group('widget')]
final class TemplateWidgetTest extends TestCase {

  public function testSeedsEverySlotFromTheAssembledValue(): void {
    $widget = new TemplateWidget(new Template('{{a}}-{{b}}'), 'one-two');

    $this->assertSame('one-two', $widget->value());
  }

  public function testSeedsEmptyWhenTheValueDoesNotMatch(): void {
    $widget = new TemplateWidget(new Template('{{a}}-{{b}}'), 'nope');

    $this->assertSame('-', $widget->value());
  }

  public function testTypingFillsTheSlotHoldingTheCaret(): void {
    $widget = new TemplateWidget(new Template('{{a}}-{{b}}'));

    $value = WidgetRunner::run($widget, ArrayKeyStream::of('one', Key::named(KeyName::Enter)));

    $this->assertSame('one-', $value);
  }

  #[DataProvider('dataProviderMovesBetweenSlots')]
  public function testMovesBetweenSlots(array $keys, string $expected): void {
    $widget = new TemplateWidget(new Template('{{a}}-{{b}}-{{c}}'));

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(...[...$keys, Key::named(KeyName::Enter)]));

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
    $widget = new TemplateWidget(new Template('{{a}}-{{b}}'), 'one-two');

    // Tab away and back, then delete a character: the value comes back with
    // the buffer, so the edit lands on the original text and not on an empty
    // slot.
    $value = WidgetRunner::run($widget, ArrayKeyStream::of($tab, $tab, Key::named(KeyName::Backspace), Key::named(KeyName::Enter)));

    $this->assertSame('on-two', $value);
  }

  public function testMovesTheCaretInsideTheActiveSlot(): void {
    $widget = new TemplateWidget(new Template('{{a}}-{{b}}'), 'one-two');

    // Left steps back inside the slot, so the inserted character lands before
    // the last one rather than at the end.
    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Left), 'X', Key::named(KeyName::Enter)));

    $this->assertSame('onXe-two', $value);
  }

  public function testValidatesTheSlotBeingLeftWithoutHoldingTheCaret(): void {
    $widget = new TemplateWidget($this->gradedTemplate());
    $theme = new DefaultTheme();

    $widget->handle(Key::char('z'));
    $widget->handle(Key::named(KeyName::Tab));

    // The rejected slot reports its error, but the caret has still moved on.
    $this->assertStringContainsString('Grade: use a single letter a-c', $widget->view($theme));
    $this->assertStringContainsString('filling in Crate', $widget->view($theme));
  }

  public function testClearsTheErrorWhenTheSlotBecomesValid(): void {
    $widget = new TemplateWidget($this->gradedTemplate());
    $theme = new DefaultTheme();

    $widget->handle(Key::char('z'));
    $widget->handle(Key::named(KeyName::Tab));
    $widget->handle(Key::named(KeyName::Tab));
    $widget->handle(Key::named(KeyName::Backspace));
    $widget->handle(Key::char('b'));
    $widget->handle(Key::named(KeyName::Tab));

    $this->assertStringNotContainsString('use a single letter a-c', $widget->view($theme));
  }

  public function testAcceptRejectsAnInvalidSlotAndTakesTheCaretToIt(): void {
    $widget = new TemplateWidget($this->gradedTemplate());
    $theme = new DefaultTheme();

    // Fill the second slot, then accept while the first is still invalid.
    $widget->handle(Key::named(KeyName::Tab));
    $widget->handle(Key::char('9'));
    $widget->handle(Key::named(KeyName::Enter));

    $this->assertFalse($widget->isComplete());
    $this->assertStringContainsString('Grade: use a single letter a-c', $widget->view($theme));
    $this->assertStringContainsString('filling in Grade', $widget->view($theme));
  }

  public function testAcceptsOnceEverySlotIsValid(): void {
    $widget = new TemplateWidget($this->gradedTemplate());

    $value = WidgetRunner::run($widget, ArrayKeyStream::of('a', Key::named(KeyName::Tab), '9', Key::named(KeyName::Enter)));

    $this->assertSame('a-9', $value);
  }

  public function testFieldValidatorRunsAgainstTheAssembledValue(): void {
    $widget = (new TemplateWidget(new Template('{{a}}-{{b}}')))
      ->setHandlers(validate: static fn(mixed $value): ?string => $value === 'one-two' ? NULL : 'Unknown crate.');
    $theme = new DefaultTheme();

    $widget->handle(Key::named(KeyName::Enter));
    $this->assertFalse($widget->isComplete());
    $this->assertStringContainsString('Unknown crate.', $widget->view($theme));
  }

  public function testTransformerAppliesToTheAssembledValue(): void {
    $widget = (new TemplateWidget(new Template('{{a}}-{{b}}'), 'one-two'))
      ->setHandlers(transform: static fn(mixed $value): string => is_string($value) ? strtoupper($value) : '');

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame('ONE-TWO', $value);
  }

  public function testCancel(): void {
    $widget = new TemplateWidget(new Template('{{a}}-{{b}}'), 'one-two');

    WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Escape)));

    $this->assertTrue($widget->isCancelled());
  }

  #[DataProvider('dataProviderRendersTheShape')]
  public function testRendersTheShape(bool $unicode, string $caret): void {
    $widget = new TemplateWidget(new Template('crate {{a}}-{{b}} ready', ['b' => 'Tail']), 'crate one-two ready');

    $view = $widget->view(new DefaultTheme(76, ['color' => FALSE, 'unicode' => $unicode]));

    // The fixed text frames the filled slots, and the caret marks the live one.
    $this->assertStringContainsString('crate one' . $caret . '-two ready', $view);
    $this->assertStringContainsString('filling in a', $view);
  }

  public static function dataProviderRendersTheShape(): \Iterator {
    yield 'unicode' => [TRUE, '█'];
    yield 'ascii' => [FALSE, '|'];
  }

  public function testEmptySlotShowsItsLabelAsHint(): void {
    $widget = new TemplateWidget(new Template('{{a}}-{{b}}', ['b' => 'Tail']));

    $view = $widget->view(new DefaultTheme(76, ['color' => FALSE, 'unicode' => FALSE]));

    // The caret sits on the first slot; the empty second one names itself so
    // the shape does not collapse to its fixed text alone.
    $this->assertStringContainsString('|-Tail', $view);
  }

  public function testFilledSlotShowsItsValueNotItsLabel(): void {
    $widget = new TemplateWidget(new Template('{{a}}-{{b}}', ['b' => 'Tail']), 'one-two');

    $view = $widget->view(new DefaultTheme(76, ['color' => FALSE, 'unicode' => FALSE]));

    $this->assertStringContainsString('-two', $view);
    $this->assertStringNotContainsString('Tail', $view);
  }

  public function testHintLeadsWithSlotNavigation(): void {
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new TemplateWidget(new Template('{{a}}-{{b}}')))->hints());

    $this->assertSame(['next/previous', 'accept', 'cancel'], $labels);
  }

  /**
   * A two-slot template whose first slot takes a single letter a-c.
   *
   * @return \DrevOps\Tui\Model\Template
   *   The template.
   */
  protected function gradedTemplate(): Template {
    return new Template('{{grade}}-{{crate}}', ['grade' => 'Grade', 'crate' => 'Crate'], [
      'grade' => static fn(string $value): ?string => preg_match('/^[a-c]$/', $value) === 1 ? NULL : 'use a single letter a-c',
    ]);
  }

}
