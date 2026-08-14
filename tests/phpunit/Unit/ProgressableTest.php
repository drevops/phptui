<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit;

use DrevOps\PhpTui\Block\Field;
use DrevOps\PhpTui\Block\Panel;
use DrevOps\PhpTui\Builder\FieldBuilder;
use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\Input\Key;
use DrevOps\PhpTui\Input\KeyName;
use DrevOps\PhpTui\Screen\Collector;
use DrevOps\PhpTui\Screen\ScreenController;
use DrevOps\PhpTui\Testing\TuiTester;
use DrevOps\PhpTui\Theme\DefaultTheme;
use DrevOps\PhpTui\Tui;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests progressable option loaders: options from a callback, loaded lazily.
 */
#[CoversClass(FieldBuilder::class)]
#[CoversClass(PanelBuilder::class)]
#[CoversClass(Field::class)]
#[CoversClass(Panel::class)]
#[CoversClass(Collector::class)]
#[CoversClass(ScreenController::class)]
#[CoversClass(DefaultTheme::class)]
#[Group('tui')]
final class ProgressableTest extends TestCase {

  public function testHeadlessCollectionResolvesTheLoaderOnceAndValidates(): void {
    $calls = 0;

    $answers = (new Tui($this->form($calls)))->collect('{"fruit":"pear"}');

    // The loader ran once, and the value validates against its options.
    $this->assertSame('pear', $answers->value('fruit'));
    $this->assertSame(1, $calls);
  }

  public function testHeadlessRejectsValueOutsideTheLoadedOptions(): void {
    $calls = 0;

    $this->expectException(CollectException::class);

    (new Tui($this->form($calls)))->collect('{"fruit":"grape"}');
  }

  public function testTheLoaderRunsWhenDrillingIntoThePanel(): void {
    $calls = 0;

    // Two Enters: drill into the panel (resolving the loader), then open the
    // select to reveal its now-loaded options.
    $tester = (new TuiTester($this->form($calls)))->rows(12);
    $tester->run(Key::named(KeyName::Enter), Key::named(KeyName::Enter));

    $display = $tester->display();

    $this->assertSame(1, $calls);
    // A loading ellipsis is painted before the options resolve, and the loaded
    // option labels show once the select opens.
    $this->assertStringContainsString('…', $display);
    $this->assertStringContainsString('Apple', $display);
    $this->assertStringContainsString('Pear', $display);
  }

  public function testPanelPreloadRunsOnceBeforeItsFieldLoaders(): void {
    $calls = 0;
    $prepared = [];

    // The preload prepares data that the field loader then reads - proving the
    // preload runs first.
    $form = Form::create('Order')->panel('New order', 'order', function (PanelBuilder $p) use (&$calls, &$prepared): void {
      $p->preload(function () use (&$calls, &$prepared): void {
        $calls++;
        $prepared = ['apple' => 'Apple', 'pear' => 'Pear'];
      });
      $p->select('Fruit', 'fruit')->options(function () use (&$prepared): array {
        return $prepared;
      });
    });

    $tester = (new TuiTester($form))->rows(12);
    $tester->run(Key::named(KeyName::Enter), Key::named(KeyName::Enter));

    $this->assertSame(1, $calls);
    $this->assertStringContainsString('Apple', $tester->display());
  }

  public function testPanelPreloadRunsWithoutAnyFieldLoaders(): void {
    $calls = 0;

    $form = Form::create('Order')->panel('New order', 'order', function (PanelBuilder $p) use (&$calls): void {
      $p->preload(function () use (&$calls): void {
        $calls++;
      });
      $p->text('Note', 'note');
    });

    $tester = (new TuiTester($form))->rows(12);
    $tester->run(Key::named(KeyName::Enter));

    $this->assertSame(1, $calls);
  }

  /**
   * A single-panel order form whose fruit options come from a counting loader.
   *
   * @param int $calls
   *   A counter incremented each time the loader runs.
   *
   * @return \DrevOps\PhpTui\Builder\Form
   *   The form.
   */
  protected function form(int &$calls): Form {
    return Form::create('Order')->panel('New order', 'order', function (PanelBuilder $p) use (&$calls): void {
      $p->select('Fruit', 'fruit')->options(function () use (&$calls): array {
        $calls++;

        return ['apple' => 'Apple', 'pear' => 'Pear'];
      });
    });
  }

}
