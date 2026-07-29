<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Model;

use DrevOps\Tui\Model\Buttons;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the submit/cancel button pair value object.
 */
#[CoversClass(Buttons::class)]
#[Group('model')]
final class ButtonsTest extends TestCase {

  public function testDefaults(): void {
    $buttons = new Buttons();

    $this->assertTrue($buttons->show);
    $this->assertSame('Submit', $buttons->submitLabel);
    $this->assertSame('Cancel', $buttons->cancelLabel);
  }

  public function testCustom(): void {
    $buttons = new Buttons(FALSE, 'Apply', 'Discard');

    $this->assertFalse($buttons->show);
    $this->assertSame('Apply', $buttons->submitLabel);
    $this->assertSame('Discard', $buttons->cancelLabel);
  }

}
