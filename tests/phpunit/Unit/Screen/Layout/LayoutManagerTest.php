<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Screen\Layout;

use DrevOps\PhpTui\Screen\Axis;
use DrevOps\PhpTui\Screen\Layout\AbstractLayout;
use DrevOps\PhpTui\Screen\Layout\DefaultLayout;
use DrevOps\PhpTui\Screen\Layout\GridLayout;
use DrevOps\PhpTui\Screen\Layout\LayoutInterface;
use DrevOps\PhpTui\Screen\Layout\LayoutManager;
use DrevOps\PhpTui\Screen\Layout\PanelLayout;
use DrevOps\PhpTui\Screen\Layout\TwoColumnLayout;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the layout registry: three ways to reach a layout, one per call.
 */
#[CoversClass(LayoutManager::class)]
#[Group('screen')]
final class LayoutManagerTest extends TestCase {

  protected function setUp(): void {
    LayoutManager::reset();
  }

  protected function tearDown(): void {
    LayoutManager::reset();
  }

  public function testTheShippedLayoutsAreTheClassesThatShip(): void {
    $built = [];

    // Read from the directory rather than from a list, so a layout added to the
    // library is reachable the moment its file is, under a name that is its
    // class name and cannot drift from it.
    foreach (LayoutManager::names() as $name) {
      $built[$name] = LayoutManager::create($name)::class;
    }

    $this->assertSame([
      'default' => DefaultLayout::class,
      'panel' => PanelLayout::class,
      'two-column' => TwoColumnLayout::class,
    ], $built);
  }

  public function testTheDirectoryIsReadThroughStreamWrapper(): void {
    // A packaged library reads its own directory over phar://, which glob()
    // cannot do. vfsStream is a stream wrapper too, so it reproduces that
    // without building a PHAR.
    vfsStream::setup('layouts', NULL, [
      'PanelLayout.php' => '',
      'DefaultLayout.php' => '',
      'LayoutInterface.php' => '',
      'README.md' => '',
      'Nested' => [],
    ]);

    $files = (new \ReflectionMethod(LayoutManager::class, 'files'))->invoke(NULL, vfsStream::url('layouts'));

    $this->assertSame(['DefaultLayout', 'LayoutInterface', 'PanelLayout'], $files);
  }

  public function testEachCallHandsBackLayoutOfItsOwn(): void {
    // Two forms picking the same layout must not share its regions, or one
    // would see the other's blocks.
    $this->assertNotSame(LayoutManager::create('default'), LayoutManager::create('default'));
  }

  public function testConsumerRegistersItsOwnUnderShortName(): void {
    LayoutManager::register('sidebar', SidebarLayoutFixture::class);

    $this->assertInstanceOf(SidebarLayoutFixture::class, LayoutManager::create('sidebar'));
    $this->assertSame(['default', 'panel', 'two-column', 'sidebar'], LayoutManager::names());
  }

  public function testClassNameWorksWithoutRegistering(): void {
    $this->assertInstanceOf(SidebarLayoutFixture::class, LayoutManager::create(SidebarLayoutFixture::class));
  }

  public function testAnUnknownNameListsTheOnesThereAre(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown layout "sidebar". Registered: default, panel, two-column.');

    LayoutManager::create('sidebar');
  }

  public function testRegisteringSomethingThatIsNotLayoutIsRefused(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Layout class "stdClass" must implement ' . LayoutInterface::class . '.');

    LayoutManager::register('bogus', \stdClass::class);
  }

  public function testBuildingSomethingThatIsNotLayoutIsRefused(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown layout "stdClass".');

    LayoutManager::create(\stdClass::class);
  }

  public function testArrangementNobodyCanBuildIsRefusedWhereItIsNamed(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Layout class "' . AbstractLayout::class . '" cannot be built from a name alone.');

    LayoutManager::register('abstract', AbstractLayout::class);
  }

  public function testBuildingArrangementNobodyCanBuildIsRefusedToo(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Layout class "' . AbstractLayout::class . '" cannot be built from a name alone.');

    LayoutManager::create(AbstractLayout::class);
  }

  public function testArrangementBuiltFromShapeIsNotReachedByName(): void {
    // A name carries no shape, and two grids of different shapes are different
    // arrangements, so the grid is not among the names a form can pick.
    $this->assertNotContains('grid', LayoutManager::names());

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown layout "grid". Registered: default, panel, two-column.');

    LayoutManager::create('grid');
  }

  public function testArrangementBuiltFromShapeIsRefusedByItsClassToo(): void {
    // The other two routes hand over no more than a name does, so what a name
    // cannot reach a class name cannot reach either.
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Layout class "' . GridLayout::class . '" cannot be built from a name alone.');

    LayoutManager::create(GridLayout::class);
  }

  public function testRegisteringArrangementBuiltFromShapeIsRefused(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Layout class "' . GridLayout::class . '" cannot be built from a name alone.');

    LayoutManager::register('grid', GridLayout::class);
  }

}

/**
 * A layout registered by a consumer rather than shipped.
 */
final class SidebarLayoutFixture extends AbstractLayout {

  /**
   * Construct the layout.
   */
  public function __construct() {
    parent::__construct(Axis::Columns);

    $this->region('sidebar')->fixed(24);
    $this->region('main')->scrolls();
  }

}
