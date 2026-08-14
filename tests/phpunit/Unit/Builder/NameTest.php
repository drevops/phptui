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
 * Tests the names a block is declared with.
 */
#[CoversClass(Name::class)]
#[Group('builder')]
final class NameTest extends TestCase {

  #[DataProvider('dataProviderPair')]
  public function testPair(string $id, string $label, string $expected_id, string $expected_label): void {
    $this->assertSame([$expected_id, $expected_label], Name::pair($id, $label));
  }

  public static function dataProviderPair(): \Iterator {
    yield 'both names stand as written' => ['name', 'Order name', 'name', 'Order name'];
    yield 'one name derives the id' => ['Order name', '', 'order_name', 'Order name'];
    yield 'a machine-shaped name derives itself' => ['basket', '', 'basket', 'basket'];
    yield 'punctuation is dropped' => ['Organic only?', '', 'organic_only', 'Organic only?'];
    yield 'accents fold to their base letters' => ['Épicerie', '', 'epicerie', 'Épicerie'];
    yield 'hyphens and brackets separate words' => ['Weight (per-crate)', '', 'weight_per_crate', 'Weight (per-crate)'];
    yield 'a declared id is never derived' => ['Order name', 'Order name', 'Order name', 'Order name'];
  }

  public function testPairRejectsLabelWithoutId(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('No id can be derived from label "???"; declare the id before it.');

    Name::pair('???', '');
  }

  public function testFieldDerivesId(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->text('Order name');
        $panel->number('qty', 'Quantity');
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

  public function testPanelRejectsMissingCallback(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Panel "orchard" is declared without a callback to build it with.');

    Form::create('T')->panel('orchard', 'Orchard');
  }

  public function testNoteDerivesId(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
        $panel->note('Getting started')->body('Fill in each field.');
      })
      ->root();

    $note = self::blockOf($form, Markup::class);

    $this->assertInstanceOf(Markup::class, $note);
    $this->assertSame('getting_started', $note->id());
    $this->assertSame('Getting started', $note->titleText());
  }

  public function testProgressDerivesId(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $panel): void {
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
