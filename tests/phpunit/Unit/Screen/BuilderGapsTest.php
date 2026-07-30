<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Screen;

use DrevOps\Tui\Block\Actions;
use DrevOps\Tui\Block\Legend;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Progress;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Screen\Assembler;
use DrevOps\Tui\Screen\Collector;
use DrevOps\Tui\Screen\PanelBuilder;
use DrevOps\Tui\Screen\TwoColumnLayout;
use DrevOps\Tui\Theme\DefaultTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the declarations and guards the main paths never reach.
 */
#[CoversClass(Actions::class)]
#[CoversClass(Assembler::class)]
#[CoversClass(Legend::class)]
#[CoversClass(Markup::class)]
#[CoversClass(Panel::class)]
#[CoversClass(PanelBuilder::class)]
#[CoversClass(Progress::class)]
#[Group('screen')]
final class BuilderGapsTest extends TestCase {

  public function testTheFirstActionDeclaredTakesFocusUntilAnotherIsGiven(): void {
    // Drawn with colour, since focused and unfocused differ by style alone.
    $theme = new DefaultTheme(80);
    $actions = (new Actions())->action('submit', 'Submit')->action('cancel', 'Cancel');

    $first = $actions->render($theme);
    $second = $actions->focus('cancel')->render($theme);

    $this->assertNotSame($first, $second);
    $this->assertSame('[ Submit ]  [ Cancel ]', Ansi::strip($second));
    $this->assertStringContainsString($theme->actionSelected('Cancel'), $second);
  }

  public function testFocusingAnActionThatWasNeverDeclaredSaysWhichExist(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown action "save". This block declares: submit, cancel.');

    (new Actions())->action('submit', 'Submit')->action('cancel', 'Cancel')->focus('save');
  }

  public function testActionsWithholdTheEndOfTheFormAndSayWhy(): void {
    $actions = new Actions();

    $this->assertNull($actions->refusal());
    $this->assertSame('Basket contents is required.', $actions->refuse('Basket contents is required.')->refusal());
    $this->assertNull($actions->refuse(NULL)->refusal());
  }

  public function testALegendForgetsWhatNoLongerApplies(): void {
    $legend = (new Legend())->entry('↵', 'accept');

    $this->assertSame('', $legend->clear()->render($this->theme()));
  }

  public function testEveryBlockThatCarriesAnIdSaysWhatItIs(): void {
    $this->assertSame('intro', (new Markup('intro', 'Pick the produce.'))->id());
    $this->assertSame('packing', (new Progress('packing', 'Packing'))->id());
  }

  public function testWorkWithNoStepsCannotReportProgress(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Work with no steps cannot report progress; leave the total unset for a spinner.');

    (new Progress('packing', 'Packing'))->steps(0);
  }

  public function testLeavingAPanelMakesItDrawAsARowAgain(): void {
    $panel = (new Panel('advanced', 'Advanced'))->layout(new TwoColumnLayout())->enter();

    $this->assertSame('Advanced', $panel->leave()->render($this->theme()));
  }

  public function testABuilderPlacesBlocksInWhicheverRegionWasNamed(): void {
    $panel = (new PanelBuilder('main', 'Delivery', new TwoColumnLayout()))
      ->in('left')->field('courier', 'Courier')->done()
      ->in('right')->markup('note', 'Weighed at the bench.')
      ->build();

    $this->assertCount(1, $panel->in('left')->blocks());
    $this->assertCount(1, $panel->in('right')->blocks());
  }

  public function testNamingARegionTheLayoutNeverDeclaredIsCaughtWhereItIsWritten(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown region "sidebar".');

    (new PanelBuilder('main', 'Delivery', new TwoColumnLayout()))->in('sidebar');
  }

  public function testABuilderTakesABlockItWasHandedReadyMade(): void {
    $panel = (new PanelBuilder('main', 'Delivery'))->add(new Markup('intro', 'Pick the produce.'))->build();

    $this->assertCount(1, $panel->in('content')->blocks());
  }

  public function testAFieldDeclaresEveryCapabilityItClaimsThroughItsBuilder(): void {
    $panel = (new PanelBuilder('main', 'Delivery'))
      ->field('basket', 'Basket contents')
        ->entry('apple', 'Apple')
        ->default('apple')
        ->constrain('one of the produce listed')
        ->validate(static fn(mixed $value): ?string => $value === 'apple' ? NULL : 'Pick apple.')
        ->when(static fn(): bool => TRUE)
        ->help('Every crate is weighed at the packing bench.')
      ->done()
      ->build();

    $this->assertSame(['basket' => 'apple'], (new Collector())->collect($panel));
  }

  public function testAColumnsLayoutTrimsWhenItsFixedRegionsCannotAllFit(): void {
    $layout = new class() extends \DrevOps\Tui\Screen\AbstractLayout {

      public function __construct() {
        parent::__construct(\DrevOps\Tui\Screen\Axis::Columns);

        $this->region('sidebar')->fixed(24);
        $this->region('main')->fixed(20);
      }

    };

    // Twelve columns cannot hold a sidebar of twenty-four and a main of any
    // width: the fixed region is cut back rather than the sizes overrunning.
    $sizes = $layout->arrange(12);

    $this->assertSame(12, array_sum($sizes));
    $this->assertSame(['sidebar' => 12, 'main' => 0], $sizes);
  }

  public function testALayoutOfFixedRegionsAloneNeedsNoRemainderDivided(): void {
    $layout = new class() extends \DrevOps\Tui\Screen\AbstractLayout {

      public function __construct() {
        parent::__construct(\DrevOps\Tui\Screen\Axis::Rows);

        $this->region('top')->fixed(2);
        $this->region('bottom')->fixed(3);
      }

    };

    $this->assertSame(['top' => 2, 'bottom' => 3], $layout->arrange(40));
  }

  public function testAMarkupBlockDrawsItsTitleAboveItsBody(): void {
    $this->assertSame("Yields\nTwelve crates.", (new Markup('yields', 'Twelve crates.', 'Yields'))->render($this->theme()));
  }

  public function testSpinningWorkAdvancesItsFrameRatherThanACount(): void {
    $progress = new Progress('fetching', 'Fetching');

    $first = $progress->render($this->theme());
    $second = $progress->advance()->render($this->theme());

    $this->assertNotSame($first, $second);
  }

  public function testTheAssemblerOffersTheButtonsThatEndAForm(): void {
    $this->assertSame(['submit', 'cancel'], (new Assembler())->actions()->names());
  }

  /**
   * A theme with colour off, so the assertions read as plain strings.
   */
  protected function theme(): DefaultTheme {
    return new DefaultTheme(80, ['color' => FALSE]);
  }

}
