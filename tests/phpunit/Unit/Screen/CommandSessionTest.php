<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Screen;

use DrevOps\PhpTui\Answers\Answers;
use DrevOps\PhpTui\Block\FieldType;
use DrevOps\PhpTui\Block\Legend;
use DrevOps\PhpTui\Block\Panel;
use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\Input\Action;
use DrevOps\PhpTui\Input\Binding;
use DrevOps\PhpTui\Input\Command;
use DrevOps\PhpTui\Input\Hint;
use DrevOps\PhpTui\Input\Key;
use DrevOps\PhpTui\Input\KeyMapManager;
use DrevOps\PhpTui\Input\KeyName;
use DrevOps\PhpTui\Input\Scope;
use DrevOps\PhpTui\Screen\KeyRouter;
use DrevOps\PhpTui\Screen\ScreenController;
use DrevOps\PhpTui\Testing\ScreenTester;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests caller commands and caller letters in a running session.
 */
#[CoversClass(ScreenController::class)]
#[CoversClass(KeyRouter::class)]
#[CoversClass(Panel::class)]
#[CoversClass(Legend::class)]
#[CoversClass(Hint::class)]
#[Group('screen')]
final class CommandSessionTest extends TestCase {

  public function testCommandRunsItsHandlerWithTheAnswers(): void {
    $seen = [];
    $save = new Command('save', static function (Answers $answers) use (&$seen): void {
      $seen[] = $answers->value('name');
    }, 's');

    $answers = $this->tester($this->order(), $save)->run(Key::named(KeyName::Enter), 's', Key::named(KeyName::Down), 's');

    $this->assertSame(['Weekly', 'Weekly'], $seen);
    $this->assertSame('Weekly', $answers->value('name'));
  }

  public function testCommandKeyIsTypedIntoAnOpenField(): void {
    $calls = 0;
    $save = new Command('save', static function () use (&$calls): void {
      $calls++;
    }, 's');

    $answers = $this->tester($this->order(), $save)->run(Key::named(KeyName::Enter), Key::named(KeyName::Enter), 's', Key::named(KeyName::Enter));

    $this->assertSame(0, $calls);
    $this->assertSame('Weeklys', $answers->value('name'));
  }

  public function testCommandRunsWithTheCursorOnTheButtons(): void {
    $calls = 0;
    $save = new Command('save', static function () use (&$calls): void {
      $calls++;
    }, 's');

    $this->tester($this->order(), $save)->run(Key::named(KeyName::Down), Key::named(KeyName::Down), 's');

    $this->assertSame(1, $calls);
  }

  public function testLegendAdvertisesCommandsOnlyWhereTheyRun(): void {
    $tester = $this->tester($this->order(), new Command('save', static function (): void {}, 's'));
    $tester->run(Key::named(KeyName::Enter), Key::named(KeyName::Enter));

    // The hub and the panel it leads into both answer the command.
    $this->assertStringContainsString('S to save · Q to quit', $tester->frame(0));
    $this->assertStringContainsString('S to save · Q to quit', $tester->frame(1));
    $this->assertStringNotContainsString('to save', $tester->frame());
  }

  public function testLegendAdvertisesCommandsInDeclarationOrder(): void {
    $tester = $this->tester($this->order(), new Command('save', static function (): void {}, 's'), new Command('open', static function (): void {}, 'o'));
    $tester->run();

    $this->assertStringContainsString('S to save · O to open', $tester->frame());
  }

  public function testMultipleSelectSelectsAllAndNoneOnCallerLetters(): void {
    $letters = [
      new Binding(Scope::field(FieldType::Select, multiple: TRUE), Action::SelectAll, 'a'),
      new Binding(Scope::field(FieldType::Select, multiple: TRUE), Action::SelectNone, 'n'),
    ];

    $all = $this->tester($this->order(), ...$letters);
    $answers = $all->run(Key::named(KeyName::Enter), Key::named(KeyName::Down), Key::named(KeyName::Enter), 'a', Key::named(KeyName::Enter));

    $this->assertSame(['apple', 'pear', 'plum'], $answers->value('basket'));
    $this->assertStringContainsString('N/A to select none or all', $all->frame(3));

    $answers = $this->tester($this->order(), ...$letters)->run(Key::named(KeyName::Enter), Key::named(KeyName::Down), Key::named(KeyName::Enter), 'a', 'n', 'p', Key::named(KeyName::Enter));

    // The bound letters never reach the filter, while an unbound one does.
    $this->assertSame([], $answers->value('basket'));
  }

  /**
   * A tester for a form, answering to the default keys plus some of its own.
   */
  protected function tester(Panel $panel, Binding|Command ...$overrides): ScreenTester {
    return (new ScreenTester($panel))->rows(16)->cols(120)->keys(KeyMapManager::create('default', array_values($overrides)));
  }

  /**
   * An order form whose one panel holds a text field and a multiple select.
   */
  protected function order(): Panel {
    return Form::create('Order')
      ->panel('Produce order', 'order', static function (PanelBuilder $p): void {
        $p->text('Order name', 'name')->default('Weekly');
        $p->select('Basket', 'basket')->multiple()->options(['apple' => 'Apple', 'pear' => 'Pear', 'plum' => 'Plum']);
      })
      ->root();
  }

}
