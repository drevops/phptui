<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Model;

use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\Modal;
use DrevOps\Tui\Model\Panel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the panel model.
 */
#[CoversClass(Panel::class)]
#[Group('model')]
final class PanelTest extends TestCase {

  public function testItemCountSumsFieldsAndPanels(): void {
    $panel = new Panel('p', 'P', '', [
      new Field('a', 'A', '', FieldType::Text, ''),
      new Field('b', 'B', '', FieldType::Text, ''),
    ], [
      new Panel('sub', 'Sub', ''),
    ]);

    $this->assertSame(3, $panel->itemCount());
  }

  public function testIsModal(): void {
    $plain = new Panel('p', 'P', '');
    $this->assertFalse($plain->isModal());
    $this->assertNotInstanceOf(Modal::class, $plain->modal);

    $dialog = new Panel('d', 'D', '', [], [], new Modal());
    $this->assertTrue($dialog->isModal());
    $this->assertInstanceOf(Modal::class, $dialog->modal);
  }

}
