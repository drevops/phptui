<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Builder;

use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Legend;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Progress;
use DrevOps\Tui\Block\Tree;
use DrevOps\Tui\Builder\FieldBuilder;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Model\FormException;
use DrevOps\Tui\Model\TableSpec;
use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Layout\AbstractLayout;
use DrevOps\Tui\Screen\Layout\LayoutManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the tree of blocks a declaration writes.
 */
#[CoversClass(Form::class)]
#[CoversClass(PanelBuilder::class)]
#[CoversClass(FieldBuilder::class)]
#[Group('builder')]
final class BlockTreeTest extends TestCase {

  protected function setUp(): void {
    LayoutManager::reset();
  }

  protected function tearDown(): void {
    LayoutManager::reset();
  }

  /**
   * Tests that a declaration writes the kind of block its answer needs.
   *
   * @param \Closure $declare
   *   The declaration, given the panel builder.
   * @param class-string $expected
   *   The block class it writes.
   */
  #[DataProvider('dataProviderDeclarationWritesTheBlockItsAnswerNeeds')]
  public function testDeclarationWritesTheBlockItsAnswerNeeds(\Closure $declare, string $expected): void {
    $panel = $this->panel($declare);

    $this->assertInstanceOf($expected, $panel->in('content')->blocks()[0]);
  }

  /**
   * Data provider for testDeclarationWritesTheBlockItsAnswerNeeds().
   *
   * @return \Iterator<string,array{\Closure,string}>
   *   A declaration, and the block class it writes.
   */
  public static function dataProviderDeclarationWritesTheBlockItsAnswerNeeds(): \Iterator {
    yield 'a question collects' => [static fn(PanelBuilder $p): FieldBuilder => $p->text('courier', 'Courier'), Field::class];
    yield 'a choice collects' => [static fn(PanelBuilder $p): FieldBuilder => $p->select('basket', 'Basket'), Field::class];
    yield 'a note only shows' => [static fn(PanelBuilder $p): Markup => $p->note('intro', 'Fresh produce'), Markup::class];
    yield 'markup only shows' => [static fn(PanelBuilder $p): Markup => $p->markup('intro', 'Fresh produce'), Markup::class];
    yield 'a progress row only runs' => [static fn(PanelBuilder $p): Progress => $p->progress('packing', 'Packing'), Progress::class];
  }

  public function testBlocksAreInTheRegionInTheOrderTheyWereWritten(): void {
    $panel = $this->panel(static function (PanelBuilder $p): void {
      $p->text('courier', 'Courier');
      $p->markup('weighing', 'Weighed at the packing bench.');
      $p->number('weight', 'Basket weight');
    });

    $ids = array_map(static fn(object $block): string => method_exists($block, 'id') ? (string) $block->id() : '', $panel->in('content')->blocks());

    $this->assertSame(['courier', 'weighing', 'weight'], $ids);
  }

  public function testTheBlockIsPlacedAsItIsWrittenRatherThanCopiedLater(): void {
    $builder = new PanelBuilder('main', 'Delivery');
    $field = $builder->text('courier', 'Courier');
    $builder->seal();

    $this->assertSame($field->block(), $builder->block()->in('content')->blocks()[0]);
  }

  public function testWithoutDeclaredLayoutPanelKeepsItsOneRegion(): void {
    $this->assertSame(['content'], $this->panel(static function (PanelBuilder $p): void {
      $p->text('courier', 'Courier');
    })->currentLayout()->names());
  }

  public function testNamedLayoutGivesThePanelTheRegionsItDeclares(): void {
    $panel = $this->panel(static function (PanelBuilder $p): void {
      $p->layout('two-column');
      $p->in('left')->text('courier', 'Courier');
      $p->in('right')->number('weight', 'Basket weight');
    });

    $this->assertSame(['left', 'right'], $panel->currentLayout()->names());
    $this->assertCount(1, $panel->in('left')->blocks());
    $this->assertCount(1, $panel->in('right')->blocks());
  }

  public function testBlockGoesInTheFirstRegionUntilAnotherIsNamed(): void {
    $panel = $this->panel(static function (PanelBuilder $p): void {
      $p->layout('two-column');
      $p->text('courier', 'Courier');
    });

    $this->assertCount(1, $panel->in('left')->blocks());
    $this->assertCount(0, $panel->in('right')->blocks());
  }

  public function testGridArrangesTheSubPanelsWithoutTouchingTheRegions(): void {
    $panel = $this->panel(static function (PanelBuilder $p): void {
      $p->layout(2);
      $p->panel('left', 'Left', static fn(PanelBuilder $sp): FieldBuilder => $sp->text('one', 'One'));
      $p->panel('right', 'Right', static fn(PanelBuilder $sp): FieldBuilder => $sp->text('two', 'Two'));
    });

    $this->assertSame([2], $panel->gridRows());
    $this->assertSame(['content'], $panel->currentLayout()->names());
    $this->assertCount(2, $panel->children());
  }

  /**
   * Tests that an arrangement a panel could not honour is refused.
   *
   * @param \Closure $declare
   *   The declaration, given the panel builder.
   * @param string $message
   *   The message it is refused with.
   */
  #[DataProvider('dataProviderArrangementThatCouldNotBeHonouredIsRefused')]
  public function testArrangementThatCouldNotBeHonouredIsRefused(\Closure $declare, string $message): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage($message);

    $this->panel($declare);
  }

  /**
   * Data provider for testArrangementThatCouldNotBeHonouredIsRefused().
   *
   * @return \Iterator<string,array{\Closure,string}>
   *   A declaration the builder refuses, and the message it refuses it with.
   */
  public static function dataProviderArrangementThatCouldNotBeHonouredIsRefused(): \Iterator {
    yield 'a name beside a grid' => [
      static function (PanelBuilder $p): void {
        $p->layout('two-column', 2);
      },
      'Panel "main" declares a layout name beside a grid of sub-panels',
    ];

    yield 'two names' => [
      static function (PanelBuilder $p): void {
        $p->layout('two-column', 'default');
      },
      'Panel "main" declares 2 layouts; a panel is arranged by one.',
    ];

    yield 'a name after the blocks' => [
      static function (PanelBuilder $p): void {
        $p->text('courier', 'Courier');
        $p->layout('two-column');
      },
      'Panel "main" declares a layout after placing blocks in the one it had',
    ];
  }

  /**
   * Tests that a presentation is the same block laid out another way.
   *
   * @param \Closure $declare
   *   The declaration, given the panel builder.
   * @param bool $bordered
   *   Whether the block is drawn in a border.
   * @param bool $tabular
   *   Whether the block carries a grid.
   */
  #[DataProvider('dataProviderMarkupPresentationsAreOneBlockDrawnThreeWays')]
  public function testMarkupPresentationsAreOneBlockDrawnThreeWays(\Closure $declare, bool $bordered, bool $tabular): void {
    $block = $this->panel($declare)->in('content')->blocks()[0];

    $this->assertInstanceOf(Markup::class, $block);
    $this->assertSame($bordered, $block->isBordered());
    $this->assertSame($tabular, $block->tableSpec() instanceof TableSpec);
  }

  /**
   * Data provider for testMarkupPresentationsAreOneBlockDrawnThreeWays().
   *
   * @return \Iterator<string,array{\Closure,bool,bool}>
   *   A declaration, whether it is bordered, and whether it carries a grid.
   */
  public static function dataProviderMarkupPresentationsAreOneBlockDrawnThreeWays(): \Iterator {
    yield 'prose' => [
      static function (PanelBuilder $p): void {
        $p->markup('weighing', 'Every crate is weighed at the packing bench.');
      },
      FALSE,
      FALSE,
    ];

    yield 'a card' => [
      static function (PanelBuilder $p): void {
        $p->markup('notice', 'Deliveries leave at dawn.')->bordered();
      },
      TRUE,
      FALSE,
    ];

    yield 'a grid' => [
      static function (PanelBuilder $p): void {
        $p->markup('yields', 'Yields per crate')->table(['Produce', 'Crates'], [['Apple', '12']]);
      },
      FALSE,
      TRUE,
    ];

    yield 'a note is the same block' => [
      static function (PanelBuilder $p): void {
        $p->note('packing', 'Ready to pack')->body('Framed with a border.')->bordered();
      },
      TRUE,
      FALSE,
    ];
  }

  public function testNoteAndMarkupCarryTheSameTitleAndBody(): void {
    $panel = $this->panel(static function (PanelBuilder $p): void {
      $p->note('note', 'Ready to pack')->body('Packed at dawn.');
      $p->markup('markup', 'Packed at dawn.', 'Ready to pack');
    });

    $blocks = $panel->in('content')->blocks();
    $note = $blocks[0];
    $markup = $blocks[1];

    $this->assertInstanceOf(Markup::class, $note);
    $this->assertInstanceOf(Markup::class, $markup);
    $this->assertSame([$markup->titleText(), $markup->bodyText()], [$note->titleText(), $note->bodyText()]);
  }

  public function testMarkupAppearsOnlyWhenAnEarlierAnswerCallsForIt(): void {
    $block = $this->panel(static function (PanelBuilder $p): void {
      $p->markup('certified', 'Organic crates need current certification.')->when(new Condition('organic', eq: TRUE));
    })->in('content')->blocks()[0];

    $this->assertInstanceOf(Markup::class, $block);
    $this->assertTrue($block->isActive(['organic' => TRUE]));
    $this->assertFalse($block->isActive(['organic' => FALSE]));
  }

  public function testSectionAppearsOnlyWhenAnEarlierAnswerCallsForIt(): void {
    $panel = $this->panel(static function (PanelBuilder $p): void {
      $p->panel('certification', 'Certification', static function (PanelBuilder $sp): void {
        $sp->when(new Condition('organic', eq: TRUE));
        $sp->text('certifier', 'Certifier');
      });
    });

    $section = $panel->children()[0];

    $this->assertTrue($section->isActive(['organic' => TRUE]));
    $this->assertFalse($section->isActive(['organic' => FALSE]));
  }

  public function testChainOfRulesStepsEveryBlockInFromWhatItWaitsOn(): void {
    $form = Form::create('Orchard')
      ->panel('order', 'Produce order', static function (PanelBuilder $p): void {
        $p->confirm('organic', 'Organic only?');
        $p->text('certifier', 'Certifier')->when(new Condition('organic', eq: TRUE));
        $p->markup('crates', 'Certified crates are packed apart.')->when(new Condition('certifier', ne: ''));

        $p->panel('renewal', 'Renewal', static function (PanelBuilder $sp): void {
          $sp->when(new Condition('certifier', ne: ''));
          $sp->text('renewed_on', 'Renewed on');
          $sp->confirm('reminder', 'Send a reminder?')->when(new Condition('renewed_on', ne: ''));
        });

        $p->number('quantity', 'Quantity');
        // Nothing can take a legend off the form, so it waits on no answer and
        // the walk passes over it rather than stamping a chain on it.
        $p->add(new Legend());
      });

    $depths = [];

    foreach (Tree::panels($form->root()->children()[0]) as $section) {
      $depths[$section->id()] = $section->nesting();

      foreach ($section->blocks() as $block) {
        if ($block instanceof Field || $block instanceof Markup) {
          $depths[$block->id()] = $block->nesting();
        }
      }
    }

    // One step per rule in the chain, whatever kind of block carries it: a
    // section's rule is one such step, counted once by everything it holds, and
    // a block with a rule of its own is the next step in from there.
    $this->assertSame([
      'order' => 0,
      'organic' => 0,
      'certifier' => 1,
      'crates' => 2,
      'quantity' => 0,
      'renewal' => 2,
      'renewed_on' => 2,
      'reminder' => 3,
    ], $depths);
  }

  public function testRulesThatNameEachOtherStopRatherThanDeepenForever(): void {
    $form = Form::create('Orchard')
      ->panel('order', 'Produce order', static function (PanelBuilder $p): void {
        $p->confirm('organic', 'Organic only?')->when(new Condition('certified', eq: TRUE));
        $p->confirm('certified', 'Certified?')->when(new Condition('organic', eq: TRUE));
      });

    $depths = [];

    foreach ($form->root()->children()[0]->in('content')->blocks() as $block) {
      if ($block instanceof Field) {
        $depths[$block->id()] = $block->nesting();
      }
    }

    // Each rule names the other, so the walk reaches a block it is already on:
    // that step back contributes nothing and the chain ends there rather than
    // deepening forever.
    $this->assertSame(['organic' => 2, 'certified' => 1], $depths);
  }

  public function testNestedPanelIsBlockInTheRegionAndPanelYouCanGoInto(): void {
    $panel = $this->panel(static function (PanelBuilder $p): void {
      $p->text('courier', 'Courier');
      $p->panel('advanced', 'Advanced', static fn(PanelBuilder $sp): FieldBuilder => $sp->text('webroot', 'Web root'));
    });

    $blocks = $panel->in('content')->blocks();

    $this->assertInstanceOf(Panel::class, $blocks[1]);
    $this->assertSame([$blocks[1]], $panel->children());
  }

  public function testEveryDeclaredPanelHangsFromOneRoot(): void {
    $form = Form::create('Orchard')
      ->panel('delivery', 'Delivery', static fn(PanelBuilder $p): FieldBuilder => $p->text('courier', 'Courier'))
      ->panel('packing', 'Packing', static fn(PanelBuilder $p): FieldBuilder => $p->text('bench', 'Bench'));

    $root = $form->root();

    $this->assertSame('Orchard', $root->title());
    $this->assertSame(['delivery', 'packing'], array_map(static fn(Panel $panel): string => $panel->id(), $root->children()));
    // The tree is the declaration, so asking for it again is the same tree
    // rather than a second copy of it.
    $this->assertSame($root, $form->root());
  }

  public function testTheTreeIsWrittenOnceAndHandedBackAsItStands(): void {
    $form = Form::create('Orchard')->panel('delivery', 'Delivery', static function (PanelBuilder $p): void {
      $p->select('basket', 'Basket contents')->option('apple', 'Apple')->default('apple');
    });

    $block = $form->root()->children()[0]->in('content')->blocks()[0];

    $this->assertInstanceOf(Field::class, $block);
    $this->assertSame('apple', $block->value());

    // The tree is written once and handed back as it stands, so a second call
    // reaches the very blocks the first one did.
    $this->assertSame($block, $form->root()->children()[0]->in('content')->blocks()[0]);
  }

  public function testLayoutDeclaringNoRegionHasNowhereToPutBlock(): void {
    LayoutManager::register('bare', BareLayoutFixture::class);

    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Panel "main" is arranged by a layout declaring no region');

    $this->panel(static function (PanelBuilder $p): void {
      $p->layout('bare');
    });
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

/**
 * An arrangement with nowhere to put anything.
 */
final class BareLayoutFixture extends AbstractLayout {

  /**
   * Construct the layout.
   */
  public function __construct() {
    parent::__construct(Axis::Rows);
  }

}
