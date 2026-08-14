<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Builder;

use DrevOps\PhpTui\Block\BlockInterface;
use DrevOps\PhpTui\Block\Field;
use DrevOps\PhpTui\Block\Markup;
use DrevOps\PhpTui\Block\Panel;
use DrevOps\PhpTui\Block\Progress;
use DrevOps\PhpTui\Block\Tree;
use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\Name;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\FormException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the id a block answers to, derived from the label it draws.
 */
#[CoversClass(Name::class)]
#[Group('builder')]
final class NameTest extends TestCase {

  #[DataProvider('dataProviderId')]
  public function testId(string $label, string $id, string $expected): void {
    $this->assertSame($expected, Name::id($label, $id));
  }

  public static function dataProviderId(): \Iterator {
    yield 'a label derives its id' => ['Order name', '', 'order_name'];
    yield 'a label already shaped like an id derives itself' => ['basket', '', 'basket'];
    yield 'punctuation is dropped' => ['Organic only?', '', 'organic_only'];
    yield 'accents fold to their base letters' => ['Épicerie', '', 'epicerie'];
    yield 'hyphens and brackets separate words' => ['Weight (per-crate)', '', 'weight_per_crate'];
    yield 'a declared id stands as written' => ['Order name', 'name', 'name'];
    yield 'a declared id is never machined' => ['Order name', 'Order Name', 'Order Name'];
  }

  public function testIdRejectsLabelWithNothingToDeriveFrom(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('No id can be derived from label "???"; declare the id after it.');

    Name::id('???', '');
  }

  public function testFieldDerivesId(): void {
    $form = Form::create('T')
      ->panel('P', 'p', function (PanelBuilder $panel): void {
        $panel->text('Order name');
        $panel->number('Quantity', 'qty');
      })
      ->root();

    $fields = Tree::fields($form);

    $this->assertSame(['order_name', 'qty'], array_map(static fn(Field $field): string => $field->id(), $fields));
    $this->assertSame(['Order name', 'Quantity'], array_map(static fn(Field $field): string => $field->label(), $fields));
  }

  public function testPanelDerivesId(): void {
    $form = Form::create('T')
      ->panel('Fresh produce', function (PanelBuilder $panel): void {
        $panel->panel('Root vegetables', function (PanelBuilder $sub): void {
          $sub->text('Crate label');
        });
      })
      ->root();

    $panels = Tree::panels($form);

    $this->assertSame(['T', 'fresh_produce', 'root_vegetables'], array_map(static fn(Panel $panel): string => $panel->id(), $panels));
    $this->assertSame(['T', 'Fresh produce', 'Root vegetables'], array_map(static fn(Panel $panel): string => $panel->title(), $panels));
  }

  public function testPanelKeepsDeclaredId(): void {
    $form = Form::create('T')
      ->panel('Fresh produce', 'produce', function (PanelBuilder $panel): void {
        $panel->panel('Root vegetables', 'roots', function (PanelBuilder $sub): void {
          $sub->text('Crate label');
        });
      })
      ->root();

    $this->assertSame(['T', 'produce', 'roots'], array_map(static fn(Panel $panel): string => $panel->id(), Tree::panels($form)));
  }

  public function testPanelRejectsMissingCallback(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Panel "Orchard" is declared without a callback to build it with.');

    Form::create('T')->panel('Orchard', 'orchard');
  }

  public function testNoteDerivesIdFromTitle(): void {
    $form = Form::create('T')
      ->panel('P', 'p', function (PanelBuilder $panel): void {
        $panel->note('Getting started')->body('Fill in each field.');
      })
      ->root();

    $note = self::blockOf($form, Markup::class);

    $this->assertInstanceOf(Markup::class, $note);
    $this->assertSame('getting_started', $note->id());
    $this->assertSame('Getting started', $note->titleText());
  }

  public function testMarkupDerivesIdFromBodyWhenItHasNoTitle(): void {
    $form = Form::create('T')
      ->panel('P', 'p', function (PanelBuilder $panel): void {
        $panel->markup('Weighed at the bench');
        $panel->markup('Picked this morning', 'Freshness');
      })
      ->root();

    $ids = [];

    foreach (Tree::panels($form) as $panel) {
      foreach ($panel->blocks() as $block) {
        if ($block instanceof Markup) {
          $ids[] = $block->id();
        }
      }
    }

    $this->assertSame(['weighed_at_the_bench', 'freshness'], $ids);
  }

  public function testProgressDerivesIdFromCaption(): void {
    $form = Form::create('T')
      ->panel('P', 'p', function (PanelBuilder $panel): void {
        $panel->progress('Fetching prices');
      })
      ->root();

    $progress = self::blockOf($form, Progress::class);

    $this->assertInstanceOf(Progress::class, $progress);
    $this->assertSame('fetching_prices', $progress->id());
    $this->assertSame('Fetching prices', $progress->caption());
  }

  /**
   * The first block of a kind in a panel tree.
   *
   * @param \DrevOps\PhpTui\Block\Panel $root
   *   The root panel.
   * @param class-string $class
   *   The kind of block to look for.
   *
   * @return \DrevOps\PhpTui\Block\BlockInterface|null
   *   The block, or NULL when the tree holds none of the kind.
   */
  protected static function blockOf(Panel $root, string $class): ?BlockInterface {
    foreach (Tree::panels($root) as $panel) {
      foreach ($panel->blocks() as $block) {
        if ($block instanceof $class) {
          return $block;
        }
      }
    }

    return NULL;
  }

}
