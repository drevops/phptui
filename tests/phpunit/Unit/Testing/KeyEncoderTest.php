<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Testing;

use DrevOps\PhpTui\Input\Key;
use DrevOps\PhpTui\Input\KeyName;
use DrevOps\PhpTui\Input\KeyParser;
use DrevOps\PhpTui\Testing\KeyEncoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests encoding a key back into the bytes a parser decodes into it.
 */
#[CoversClass(KeyEncoder::class)]
#[Group('testing')]
final class KeyEncoderTest extends TestCase {

  #[DataProvider('dataProviderRoundTrip')]
  public function testRoundTrip(Key $key): void {
    $bytes = KeyEncoder::encode($key);
    $parsed = (new KeyParser())->parse($bytes);

    $this->assertCount(1, $parsed);
    $this->assertTrue($parsed[0]->equals($key), sprintf('Expected "%s", parsed "%s" from %s.', $key->token(), $parsed[0]->token(), json_encode($bytes)));
  }

  public static function dataProviderRoundTrip(): \Iterator {
    foreach (KeyName::cases() as $name) {
      yield $name->name => [Key::named($name)];
    }

    yield 'char letter' => [Key::char('a')];
    yield 'char digit' => [Key::char('5')];
    yield 'char symbol' => [Key::char('/')];
  }

  public function testEncodesCharacterVerbatim(): void {
    $this->assertSame('x', KeyEncoder::encode(Key::char('x')));
  }

}
