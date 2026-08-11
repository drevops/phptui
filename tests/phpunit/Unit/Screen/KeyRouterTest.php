<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Screen;

use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Block\FieldType;
use DrevOps\Tui\Block\Legend;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Mode;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Screen\KeyRouter;
use DrevOps\Tui\Screen\Layout\DefaultLayout;
use DrevOps\Tui\Theme\DefaultTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests key routing: a key stops at the innermost thing that binds it.
 */
#[CoversClass(KeyRouter::class)]
#[Group('screen')]
final class KeyRouterTest extends TestCase {

  public function testFocusLandsOnTheFirstBlockThatTakesIt(): void {
    $courier = new Field('courier', 'Courier');
    $router = $this->router(new Markup('intro', 'Pick the produce.'), $courier);

    $this->assertSame($courier, $router->focused());
    $this->assertTrue($courier->isFocused());
  }

  public function testFocusSkipsEveryBlockThatDoesNotTakeIt(): void {
    $courier = new Field('courier', 'Courier');
    $weight = new Field('weight', 'Weight');
    $router = $this->router($courier, new Markup('note', 'Weighed at the bench.'), $weight);

    $router->handle(Key::named(KeyName::Down));

    $this->assertSame($weight, $router->focused());
    $this->assertTrue($weight->isFocused());
    $this->assertFalse($courier->isFocused());
  }

  public function testFocusStopsAtTheEndsRatherThanWrapping(): void {
    $courier = new Field('courier', 'Courier');
    $router = $this->router($courier, new Field('weight', 'Weight'));

    $router->handle(Key::named(KeyName::Up));
    $this->assertSame($courier, $router->focused());
  }

  public function testAnOpenFieldTakesEveryPrintableKeyAsSomethingTyped(): void {
    $courier = new Field('courier', 'Courier');
    $router = $this->router($courier);

    $router->handle(Key::named(KeyName::Enter));
    $router->handle(Key::char('?'));

    // The key stopped at the field and became a character, so it never reached
    // the panel and never opened help.
    $this->assertFalse($router->isShowingHelp());
    $this->assertStringContainsString('?', $courier->render($this->theme()));
  }

  public function testClosedFieldBindsNoPrintableKeySoTheSameOneTravelsOutward(): void {
    $router = $this->router((new Field('courier', 'Courier'))->help('Every crate is weighed.'));

    $router->handle(Key::char('?'));

    $this->assertTrue($router->isShowingHelp());
  }

  public function testAnyKeyDismissesHelpOnceItIsShowing(): void {
    $router = $this->router((new Field('courier', 'Courier'))->help('Every crate is weighed.'));

    $router->handle(Key::char('?'));
    $router->handle(Key::named(KeyName::Down));

    $this->assertFalse($router->isShowingHelp());
  }

  public function testTheHelpShowingIsTheHelpOfTheFieldItWasAskedOf(): void {
    $courier = (new Field('courier', 'Courier'))->help('Every crate is weighed.');
    $router = $this->router($courier);

    $this->assertNotInstanceOf(Field::class, $router->helping());

    $router->handle(Key::char('?'));

    $this->assertSame($courier, $router->helping());
  }

  public function testFieldWithNoHelpAdvertisesNoneAndOpensNone(): void {
    $router = $this->router(new Field('courier', 'Courier'));

    $router->handle(Key::char('?'));

    $this->assertFalse($router->isShowingHelp());
  }

  public function testActivatingFieldOpensIt(): void {
    $courier = new Field('courier', 'Courier');
    $router = $this->router($courier);

    $router->handle(Key::named(KeyName::Enter));

    $this->assertSame(Mode::Edit, $courier->mode());
  }

  public function testCancellingAnOpenFieldClosesItAndKeepsTheAnswer(): void {
    $courier = (new Field('courier', 'Courier'))->default('Valley Runs');
    $router = $this->router($courier);

    $router->handle(Key::named(KeyName::Enter));
    $router->handle(Key::char('X'));
    $router->handle(Key::named(KeyName::Escape));

    $this->assertSame('Valley Runs', $courier->value());
    $this->assertSame(Mode::View, $courier->mode());
  }

  public function testAcceptingAnOpenFieldTakesWhatWasTypedIntoIt(): void {
    $courier = new Field('courier', 'Courier');
    $router = $this->router($courier);

    $router->handle(Key::named(KeyName::Enter));

    foreach (str_split('Coast') as $char) {
      $router->handle(Key::char($char));
    }

    $router->handle(Key::named(KeyName::Enter));

    $this->assertSame('Coast', $courier->value());
  }

  public function testSpaceTogglesInsideAnOpenListBecauseTheEditorBindsIt(): void {
    $basket = (new Field('basket', 'Basket contents', FieldType::Select))
      ->multiple()
      ->entry('apple', 'Apple')
      ->entry('carrot', 'Carrot');
    $router = $this->router($basket);

    $router->handle(Key::named(KeyName::Enter));
    $router->handle(Key::named(KeyName::Space));
    $router->handle(Key::named(KeyName::Enter));

    $this->assertSame(['apple'], $basket->value());
  }

  public function testCursorKeysReachAnOpenListRatherThanMovingBetweenFields(): void {
    $basket = (new Field('basket', 'Basket contents', FieldType::Select))->entry('apple', 'Apple')->entry('carrot', 'Carrot');
    $router = $this->router($basket, new Field('weight', 'Weight'));

    $router->handle(Key::named(KeyName::Enter));
    $router->handle(Key::named(KeyName::Down));
    $router->handle(Key::named(KeyName::Enter));

    // The key stopped at the open field, so the cursor never left the row.
    $this->assertSame('carrot', $basket->value());
    $this->assertSame($basket, $router->focused());
  }

  public function testPanelWithNothingFocusableFocusesNothing(): void {
    $router = $this->router(new Markup('intro', 'Pick the produce.'));

    $this->assertNotInstanceOf(Field::class, $router->focused());

    // Nothing takes the cursor, so nothing moves it either.
    $router->handle(Key::named(KeyName::Down));
    $this->assertNotInstanceOf(Field::class, $router->focused());
  }

  public function testSelectingTheNestedPanelGoesIntoItAndEscapeComesBackOut(): void {
    $advanced = (new Panel('advanced', 'Advanced'))->layout(new DefaultLayout());
    $certifier = new Field('certifier', 'Certifier');
    $advanced->in('content')->add($certifier);

    $router = $this->router(new Field('courier', 'Courier'), $advanced);

    $router->handle(Key::named(KeyName::Down));
    $router->handle(Key::named(KeyName::Enter));

    $this->assertSame($advanced, $router->current());
    $this->assertTrue($advanced->isEntered());
    $this->assertSame($certifier, $router->focused());
    $this->assertSame(['Delivery', 'Advanced'], $router->trail());

    $router->handle(Key::named(KeyName::Escape));

    // Coming back restores the screen, the trail and the row it was left on.
    $this->assertSame('main', $router->current()->id());
    $this->assertFalse($advanced->isEntered());
    $this->assertSame($advanced, $router->focused());
    $this->assertSame(['Delivery'], $router->trail());
  }

  public function testGoingBackFromTheOutermostPanelGoesNowhere(): void {
    $router = $this->router(new Field('courier', 'Courier'));

    $router->handle(Key::named(KeyName::Escape));

    $this->assertSame('main', $router->current()->id());
  }

  public function testGoingIntoThePanelPreparesItOnce(): void {
    $prepared = 0;
    $advanced = (new Panel('advanced', 'Advanced'))->layout(new DefaultLayout());
    $advanced->preload(static function () use (&$prepared): void {
      $prepared++;
    });

    $router = $this->router($advanced);

    $router->handle(Key::named(KeyName::Enter));
    $router->handle(Key::named(KeyName::Escape));
    $router->handle(Key::named(KeyName::Enter));

    $this->assertSame(1, $prepared);
  }

  public function testTheLegendIsRewrittenFromWhicheverBinderIsInnermost(): void {
    $router = $this->router(new Field('courier', 'Courier'));
    $legend = new Legend();

    $closed = $router->refresh($legend)->render($this->theme());

    $router->handle(Key::named(KeyName::Enter));
    $open = $router->refresh($legend)->render($this->theme());

    $this->assertSame('↑/↓ to move · ↵ to select · ESC to go back', $closed);
    $this->assertSame('↵ to accept · ESC to cancel', $open);
  }

  public function testEveryBlockInThePanelAnswersToTheBindingsTheRouterSpreads(): void {
    $advanced = (new Panel('advanced', 'Advanced'))->layout(new DefaultLayout());
    $certifier = new Field('certifier', 'Certifier', FieldType::Select);
    $advanced->in('content')->add($certifier);

    $keys = KeyMapManager::create('vim');
    $router = $this->router(new Field('courier', 'Courier'), $advanced);
    $router->bind($keys);

    // A preset reaches the panel, the panels beneath it and their fields, so a
    // retuned key means the same thing everywhere on the screen.
    $router->handle(Key::char('j'));
    $this->assertSame($advanced, $router->focused());

    $router->handle(Key::named(KeyName::Enter));

    $this->assertSame($keys->forField(FieldType::Select), $certifier->bindings());
  }

  /**
   * A key router over a panel holding the given blocks.
   */
  protected function router(object ...$blocks): KeyRouter {
    $panel = (new Panel('main', 'Delivery'))->layout(new DefaultLayout());

    foreach ($blocks as $block) {
      /** @var \DrevOps\Tui\Block\BlockInterface $block */
      $panel->in('content')->add($block);
    }

    return new KeyRouter($panel);
  }

  /**
   * A theme with colour off, so the assertions read as plain strings.
   */
  protected function theme(): DefaultTheme {
    return new DefaultTheme(80, ['color' => FALSE]);
  }

  public function testCursorStepsBetweenTheColumnsOfAnArrangementRunningAcross(): void {
    $form = Form::create('Market hall')
      ->panel('hall', 'Hall', function (PanelBuilder $p): void {
        $p->layout('two-column');

        $p->in('left');
        $p->text('item', 'Item')->default('Pear');
        $p->number('crates', 'Crates')->default(6);

        $p->in('right');
        $p->confirm('gift', 'Gift wrap?')->default(FALSE);
      });

    $panel = $form->root()->children()[0];
    $router = (new KeyRouter($panel))->bind(KeyMapManager::create());

    // Every region of an arrangement running across is drawn beside its
    // siblings, so the whole of it is one visual row and each region is one
    // step along it - landing on the first row that region holds.
    $this->assertSame('item', $this->cursorId($router));

    $router->handle(Key::named(KeyName::Right));
    $this->assertSame('gift', $this->cursorId($router));

    $router->handle(Key::named(KeyName::Left));
    $this->assertSame('item', $this->cursorId($router));
  }

  /**
   * The id of the field the cursor is on.
   */
  protected function cursorId(KeyRouter $router): string {
    $focused = $router->focused();

    return $focused instanceof Field ? $focused->id() : '';
  }

}
