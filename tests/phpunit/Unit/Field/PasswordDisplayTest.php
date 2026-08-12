<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Field;

use DrevOps\PhpTui\Field\PasswordDisplay;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the password display cycle.
 */
#[CoversClass(PasswordDisplay::class)]
#[Group('field')]
final class PasswordDisplayTest extends TestCase {

  #[DataProvider('dataProviderNext')]
  public function testNext(PasswordDisplay $from, PasswordDisplay $to): void {
    $this->assertSame($to, $from->next());
  }

  /**
   * Data provider for testNext().
   *
   * @return \Iterator<string, array{\DrevOps\PhpTui\Field\PasswordDisplay, \DrevOps\PhpTui\Field\PasswordDisplay}>
   *   The current display and the one that follows it.
   */
  public static function dataProviderNext(): \Iterator {
    yield 'hidden to masked' => [PasswordDisplay::Hidden, PasswordDisplay::Masked];
    yield 'masked to plaintext' => [PasswordDisplay::Masked, PasswordDisplay::Plaintext];
    yield 'plaintext to hidden' => [PasswordDisplay::Plaintext, PasswordDisplay::Hidden];
  }

}
