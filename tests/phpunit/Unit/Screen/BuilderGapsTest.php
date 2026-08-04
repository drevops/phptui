<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Screen;

use DrevOps\Tui\Block\Actions;
use DrevOps\Tui\Block\Legend;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Progress;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Screen\Assembler;
use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Collector;
use DrevOps\Tui\Screen\Layout\AbstractLayout;
use DrevOps\Tui\Screen\Layout\TwoColumnLayout;
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

  public function testTheFirstActionDeclaredIsSelectedUntilAnotherIsGiven(): void {
    // Drawn with colour, since selected and unselected differ by style alone.
    $theme = new DefaultTheme(80);
    $actions = (new Actions())->action('submit', 'Submit')->action('cancel', 'Cancel');

    $first = $actions->render($theme);
    $second = $actions->select('cancel')->render($theme);

    $this->assertNotSame($first, $second);
    $this->assertSame('[ Submit ]  [ Cancel ]', Ansi::strip($second));
    $this->assertStringContainsString($theme->actionSelected('Cancel'), $second);
  }

  public function testSelectingAnActionThatWasNeverDeclaredSaysWhichExist(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown action "save". This block declares: submit, cancel.');

    (new Actions())->action('submit', 'Submit')->action('cancel', 'Cancel')->select('save');
  }

  public function testActionsWithholdTheEndOfTheFormAndSayWhy(): void {
    $actions = new Actions();

    $this->assertNull($actions->refusal());
    $this->assertSame('Basket contents is required.', $actions->refuse('Basket contents is required.')->refusal());
    $this->assertNull($actions->refuse(NULL)->refusal());
  }

  public function testLegendForgetsWhatNoLongerApplies(): void {
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

  public function testLeavingPanelMakesItDrawAsRowAgain(): void {
    $panel = (new Panel('advanced', 'Advanced'))->layout(new TwoColumnLayout())->enter();

    $this->assertSame('  Advanced ›', $panel->leave()->render($this->theme()));
  }

  public function testBuilderPlacesBlocksInWhicheverRegionWasNamed(): void {
    $panel = $this->panel(static function (PanelBuilder $p): void {
      $p->layout('two-column');
      $p->in('left')->text('courier', 'Courier');
      $p->in('right')->markup('note', 'Weighed at the bench.');
    });

    $this->assertCount(1, $panel->in('left')->blocks());
    $this->assertCount(1, $panel->in('right')->blocks());
  }

  public function testNamingRegionTheLayoutNeverDeclaredIsCaughtWhereItIsWritten(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown region "sidebar".');

    (new PanelBuilder('main', 'Delivery'))->layout('two-column')->in('sidebar');
  }

  public function testBuilderTakesBlockItWasHandedReadyMade(): void {
    $panel = $this->panel(static function (PanelBuilder $p): void {
      $p->add(new Markup('intro', 'Pick the produce.'));
    });

    $this->assertCount(1, $panel->in('content')->blocks());
  }

  public function testFieldDeclaresEveryCapabilityItClaimsThroughItsBuilder(): void {
    $panel = $this->panel(static function (PanelBuilder $p): void {
      $p->text('organic', 'Organic only?')->default('yes');
      $p->select('basket', 'Basket contents')
        ->option('apple', 'Apple')
        ->default('apple')
        ->required()
        ->validate(static fn(mixed $value): ?string => $value === 'apple' ? NULL : 'Pick apple.')
        ->when(new Condition('organic', eq: 'yes'))
        ->help('Every crate is weighed at the packing bench.');
    });

    $this->assertSame(['organic' => 'yes', 'basket' => 'apple'], (new Collector())->collect($panel));
  }

  public function testColumnsLayoutTrimsWhenItsFixedRegionsCannotAllFit(): void {
    $layout = new class() extends AbstractLayout {

      public function __construct() {
        parent::__construct(Axis::Columns);

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

  public function testLayoutOfFixedRegionsAloneNeedsNoRemainderDivided(): void {
    $layout = new class() extends AbstractLayout {

      public function __construct() {
        parent::__construct(Axis::Rows);

        $this->region('top')->fixed(2);
        $this->region('bottom')->fixed(3);
      }

    };

    $this->assertSame(['top' => 2, 'bottom' => 3], $layout->arrange(40));
  }

  public function testMarkupBlockDrawsItsTitleAboveItsBody(): void {
    $this->assertSame("Yields\nTwelve crates.", (new Markup('yields', 'Twelve crates.', 'Yields'))->render($this->theme()));
  }

  public function testSpinningWorkAdvancesItsFrameRatherThanCount(): void {
    $progress = new Progress('fetching', 'Fetching');

    $first = $progress->render($this->theme());
    $second = $progress->advance()->render($this->theme());

    $this->assertNotSame($first, $second);
  }

  public function testTheAssemblerOffersTheButtonsThatEndForm(): void {
    $this->assertSame(['submit', 'cancel'], (new Assembler())->actions()->names());
  }

  /**
   * A theme with colour off, so the assertions read as plain strings.
   */
  protected function theme(): DefaultTheme {
    return new DefaultTheme(80, ['color' => FALSE]);
  }

  /**
   * Declare a panel and hand back the block it declared.
   *
   * @param \Closure $declare
   *   The declaration, given the panel builder.
   *
   * @return \DrevOps\Tui\Block\Panel
   *   The panel block.
   */
  protected function panel(\Closure $declare): Panel {
    $builder = new PanelBuilder('main', 'Delivery');
    $declare($builder);
    $builder->seal();

    return $builder->block();
  }

}
