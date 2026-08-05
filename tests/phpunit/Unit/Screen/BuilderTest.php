<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Screen;

use DrevOps\Tui\Block\Actions;
use DrevOps\Tui\Block\Breadcrumb;
use DrevOps\Tui\Block\Buttons;
use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Legend;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\FormException;
use DrevOps\Tui\Screen\Assembler;
use DrevOps\Tui\Screen\Collector;
use DrevOps\Tui\Screen\ScreenRenderer;
use DrevOps\Tui\Tests\Fixtures\Screen\Layout\BareLayout;
use DrevOps\Tui\Tests\Fixtures\Screen\Layout\HomelessLayout;
use DrevOps\Tui\Tests\Fixtures\Screen\Layout\StallLayout;
use DrevOps\Tui\Theme\DefaultTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the hierarchy is what is there, not what you have to type.
 */
#[CoversClass(PanelBuilder::class)]
#[CoversClass(Assembler::class)]
#[Group('screen')]
final class BuilderTest extends TestCase {

  public function testThreeFieldFormNamesNoneOfTheHierarchy(): void {
    $panel = $this->panel(function (PanelBuilder $p): void {
      $p->text('courier', 'Courier')->default('Valley Runs');
      $p->number('weight', 'Basket weight')->default(1200);
      $p->confirm('organic', 'Organic only?')->default(TRUE);
    });

    $this->assertSame(['courier' => 'Valley Runs', 'weight' => 1200, 'organic' => TRUE], (new Collector())->collect($panel));
  }

  public function testMarkupBetweenTwoFieldsDoesNotChangeTheShapeOfTheCode(): void {
    $panel = $this->panel(function (PanelBuilder $p): void {
      $p->text('courier', 'Courier')->default('Valley Runs');
      $p->markup('weighing', 'Every crate is weighed at the packing bench.');
      $p->number('weight', 'Basket weight')->default(1200);
    });

    $blocks = $panel->currentLayout()->in('content')->blocks();

    $this->assertInstanceOf(Field::class, $blocks[0]);
    $this->assertInstanceOf(Markup::class, $blocks[1]);
    $this->assertInstanceOf(Field::class, $blocks[2]);
  }

  public function testTheAssemblerFurnishesTheScreenTheLayoutWouldNot(): void {
    $panel = $this->panel(function (PanelBuilder $p): void {
      $p->text('courier', 'Courier')->default('Valley Runs');
    });

    $screen = (new Assembler())->assemble($panel);

    // The layout declared three regions and named no block; the assembler put
    // the standard furniture in them - the buttons beside the panel rather
    // than among the rows it declared.
    $this->assertInstanceOf(Breadcrumb::class, $screen->in('header')->blocks()[0]);
    $this->assertInstanceOf(Legend::class, $screen->in('footer')->blocks()[0]);
    $this->assertSame($panel, $screen->in('content')->blocks()[0]);
    $this->assertInstanceOf(Actions::class, $screen->in('content')->blocks()[1]);

    // And nothing at all in the panel: the form declared one row, and one row
    // is what it still holds.
    $this->assertCount(1, $panel->in('content')->blocks());
  }

  public function testFormThatHidesItsButtonsIsAssembledWithNone(): void {
    $panel = $this->panel(function (PanelBuilder $p): void {
      $p->text('courier', 'Courier')->default('Valley Runs');
    })->buttons(new Buttons(FALSE));

    $this->assertSame([$panel], (new Assembler())->assemble($panel)->in('content')->blocks());
  }

  public function testLayoutSendsEachPieceWhereItSaysRatherThanWhereItsNamesSuggest(): void {
    $panel = $this->panel(function (PanelBuilder $p): void {
      $p->text('courier', 'Courier')->default('Valley Runs');
    });

    $screen = (new Assembler())->assemble($panel, StallLayout::class);

    // It declares neither a header nor a footer and still shows the trail,
    // because it says where the trail goes rather than being read for names.
    $this->assertInstanceOf(Breadcrumb::class, $screen->in('aside')->blocks()[0]);
    $this->assertInstanceOf(Legend::class, $screen->in('aside')->blocks()[1]);
    $this->assertSame($panel, $screen->in('main')->blocks()[0]);
    $this->assertInstanceOf(Actions::class, $screen->in('main')->blocks()[1]);
  }

  public function testLayoutThatRefusesFurnitureIsDrawnWithoutIt(): void {
    $screen = (new Assembler())->assemble($this->panel(), BareLayout::class);

    // Refusing is not the same as having nowhere: the regions are there, and
    // the layout still keeps the trail and the keys off the screen.
    $this->assertSame([], $screen->in('header')->blocks());
    $this->assertSame([], $screen->in('footer')->blocks());
    $this->assertCount(2, $screen->in('content')->blocks());
  }

  public function testLayoutWithNowhereToDrawTheFormIsRefusedWhereItIsNamed(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('keeps no place for the form itself, so there is nowhere to draw it');

    (new Assembler())->assemble($this->panel(), HomelessLayout::class);
  }

  public function testAnAssembledScreenDrawsEndToEnd(): void {
    $panel = $this->panel(function (PanelBuilder $p): void {
      $p->text('courier', 'Courier')->default('Valley Runs');
    });

    $screen = (new Assembler())->assemble($panel);
    $rendered = (new ScreenRenderer(new DefaultTheme(40, ['color' => FALSE])))->render($screen, 6, 40);
    $lines = array_map(rtrim(...), explode("\n", $rendered));

    $this->assertSame('Delivery', $lines[0]);
    $this->assertSame('  Courier  Valley Runs', $lines[1]);
    $this->assertCount(6, $lines);
    $this->assertStringContainsString('to move', $lines[5]);
  }

  public function testTheAssembledLegendIsReadOutOfThePanelsOwnBindings(): void {
    $legend = (new Assembler())->assemble($this->panel())->in('footer')->blocks()[0];

    $this->assertInstanceOf(Legend::class, $legend);
    $this->assertSame('↑/↓ to move · ↵ to select · ESC to go back', $legend->render(new DefaultTheme(80, ['color' => FALSE])));
  }

  /**
   * Declare a panel and hand back the block it declared.
   *
   * @param \Closure|null $declare
   *   The declaration, given the panel builder; NULL declares an empty panel.
   *
   * @return \DrevOps\Tui\Block\Panel
   *   The panel block.
   */
  protected function panel(?\Closure $declare = NULL): Panel {
    $builder = new PanelBuilder('main', 'Delivery');

    if ($declare instanceof \Closure) {
      $declare($builder);
    }

    $builder->seal();

    return $builder->block();
  }

}
