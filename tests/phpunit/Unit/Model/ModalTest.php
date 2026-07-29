<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Model;

use DrevOps\Tui\Model\Buttons;
use DrevOps\Tui\Model\FormException;
use DrevOps\Tui\Model\Modal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the modal presentation config value object.
 */
#[CoversClass(Modal::class)]
#[Group('tui')]
final class ModalTest extends TestCase {

  public function testDefaultButtons(): void {
    $modal = new Modal();

    $this->assertTrue($modal->buttons->show);
    $this->assertSame('Submit', $modal->buttons->submitLabel);
    $this->assertSame('Cancel', $modal->buttons->cancelLabel);
  }

  public function testCustomButtons(): void {
    $modal = new Modal(new Buttons(TRUE, 'Yes', 'No'));

    $this->assertSame('Yes', $modal->buttons->submitLabel);
    $this->assertSame('No', $modal->buttons->cancelLabel);
  }

  public function testHiddenButtonsRejected(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('A modal dialog must show its buttons.');

    new Modal(new Buttons(FALSE));
  }

}
