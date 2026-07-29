<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Input;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Tests\Traits\ResetsTranslatorTrait;
use DrevOps\Tui\Translation\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the key-hint fragment.
 */
#[CoversClass(Hint::class)]
#[Group('input')]
final class HintTest extends TestCase {

  use ResetsTranslatorTrait;

  public function testLabelIsEnglishWithoutTranslator(): void {
    $hint = new Hint('move', Action::MoveUp, Action::MoveDown);

    $this->assertSame('move', $hint->label);
    $this->assertSame([Action::MoveUp, Action::MoveDown], $hint->actions);
  }

  public function testLabelIsTranslated(): void {
    Translator::setShared(new Translator('es', [dirname(__DIR__, 2) . '/Fixtures/translations']));

    $hint = new Hint('move', Action::MoveUp);

    $this->assertSame('mover', $hint->label);
  }

}
