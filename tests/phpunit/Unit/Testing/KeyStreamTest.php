<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Testing;

use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Testing\ArrayKeyStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the key model and the in-memory key stream.
 */
#[CoversClass(Key::class)]
#[CoversClass(ArrayKeyStream::class)]
#[Group('testing')]
final class KeyStreamTest extends TestCase {

  public function testCharAndNamed(): void {
    $char = Key::char('a');
    $this->assertTrue($char->isChar());
    $this->assertSame('a', $char->char);
    $this->assertFalse($char->is(KeyName::Enter));

    $named = Key::named(KeyName::Enter);
    $this->assertFalse($named->isChar());
    $this->assertTrue($named->is(KeyName::Enter));
  }

  public function testOfExpandsStringsAndKeys(): void {
    $stream = ArrayKeyStream::of('ab', Key::named(KeyName::Enter), '');

    $first = $stream->read();
    $this->assertInstanceOf(Key::class, $first);
    $this->assertSame('a', $first->char);

    $second = $stream->read();
    $this->assertInstanceOf(Key::class, $second);
    $this->assertSame('b', $second->char);

    $third = $stream->read();
    $this->assertInstanceOf(Key::class, $third);
    $this->assertTrue($third->is(KeyName::Enter));

    $this->assertNotInstanceOf(Key::class, $stream->read());
  }

  public function testConstructReindexes(): void {
    $stream = new ArrayKeyStream([5 => Key::char('x')]);

    $key = $stream->read();
    $this->assertInstanceOf(Key::class, $key);
    $this->assertSame('x', $key->char);
    $this->assertNotInstanceOf(Key::class, $stream->read());
  }

  public function testEqualsAndToken(): void {
    $this->assertTrue(Key::named(KeyName::Enter)->equals(Key::named(KeyName::Enter)));
    $this->assertFalse(Key::named(KeyName::Enter)->equals(Key::named(KeyName::Escape)));

    $this->assertTrue(Key::char('a')->equals(Key::char('a')));
    $this->assertFalse(Key::char('a')->equals(Key::char('b')));

    // A named key and a character never collide.
    $this->assertFalse(Key::named(KeyName::Space)->equals(Key::char(' ')));

    $this->assertSame('name:Enter', Key::named(KeyName::Enter)->token());
    $this->assertSame('char:a', Key::char('a')->token());
  }

  public function testLabelSpellsOutKeyNobodyCanType(): void {
    // A control character is a key press with no character to show for it, so
    // the value object is where it is spelled - anything writing a key down
    // takes the spelling rather than inventing one.
    $this->assertSame('Enter', Key::named(KeyName::Enter)->label());
    $this->assertSame('j', Key::char('j')->label());
    $this->assertSame('ctrl-e', Key::char("\x05")->label());
  }

}
