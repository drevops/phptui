<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Screen;

use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Screen\DefaultLayout;
use DrevOps\Tui\Screen\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests key routing: a key stops at the innermost thing that binds it.
 */
#[CoversClass(Router::class)]
#[Group('screen')]
final class RouterTest extends TestCase {

  public function testFocusLandsOnTheFirstBlockThatTakesIt(): void {
    $courier = new Field('courier', 'Courier');
    $router = $this->router(new Markup('intro', 'Pick the produce.'), $courier);

    $this->assertSame($courier, $router->focused());
  }

  public function testFocusSkipsEveryBlockThatDoesNotTakeIt(): void {
    $weight = new Field('weight', 'Weight');
    $router = $this->router(
      new Field('courier', 'Courier'),
      new Markup('note', 'Weighed at the bench.'),
      $weight,
    );

    $router->handle(Key::named(KeyName::Down));

    $this->assertSame($weight, $router->focused());
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

    // The key stopped at the field, so it never reached the panel and never
    // opened help.
    $this->assertFalse($router->isShowingHelp());
  }

  public function testAClosedFieldBindsNoPrintableKeySoTheSameOneTravelsOutward(): void {
    $router = $this->router((new Field('courier', 'Courier'))->help('Every crate is weighed.'));

    $router->handle(Key::char('?'));

    $this->assertTrue($router->isShowingHelp());
  }

  public function testAFieldWithNoHelpAdvertisesNoneAndOpensNone(): void {
    $router = $this->router(new Field('courier', 'Courier'));

    $router->handle(Key::char('?'));

    $this->assertFalse($router->isShowingHelp());
  }

  public function testActivatingAFieldOpensIt(): void {
    $courier = new Field('courier', 'Courier');
    $router = $this->router($courier);

    $router->handle(Key::named(KeyName::Enter));

    $this->assertTrue($courier->mode()->name === 'Edit');
  }

  public function testCancellingAnOpenFieldClosesItAndKeepsTheAnswer(): void {
    $courier = (new Field('courier', 'Courier'))->default('Valley Runs');
    $router = $this->router($courier);

    $router->handle(Key::named(KeyName::Enter));
    $courier->draft('Coast Runs');
    $router->handle(Key::named(KeyName::Escape));

    $this->assertSame('Valley Runs', $courier->value());
  }

  public function testAcceptingAnOpenFieldTakesTheDraft(): void {
    $courier = (new Field('courier', 'Courier'))->default('Valley Runs');
    $router = $this->router($courier);

    $router->handle(Key::named(KeyName::Enter));
    $courier->draft('Coast Runs');
    $router->handle(Key::named(KeyName::Enter));

    $this->assertSame('Coast Runs', $courier->value());
  }

  public function testAPanelWithNothingFocusableFocusesNothing(): void {
    $router = $this->router(new Markup('intro', 'Pick the produce.'));

    $this->assertNull($router->focused());
  }

  /**
   * A router over a panel holding the given blocks.
   */
  protected function router(object ...$blocks): Router {
    $panel = (new Panel('main', 'Delivery'))->layout(new DefaultLayout());

    foreach ($blocks as $block) {
      /** @var \DrevOps\Tui\Block\BlockInterface $block */
      $panel->in('content')->add($block);
    }

    return new Router($panel);
  }

}
