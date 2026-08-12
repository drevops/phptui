<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Input;

use DrevOps\PhpTui\Input\Key;
use DrevOps\PhpTui\Input\KeyName;
use DrevOps\PhpTui\Input\KeyParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the raw key parser.
 */
#[CoversClass(KeyParser::class)]
#[Group('input')]
final class KeyParserTest extends TestCase {

  /**
   * Byte buffers parse into the expected key sequence.
   *
   * @param string $bytes
   *   The raw bytes.
   * @param list<string> $expected
   *   The expected key descriptions.
   */
  #[DataProvider('dataProviderParse')]
  public function testParse(string $bytes, array $expected): void {
    $keys = array_map($this->describe(...), (new KeyParser())->parse($bytes));

    $this->assertSame($expected, $keys);
  }

  /**
   * Data provider for testParse().
   *
   * @return \Iterator<string,array{string,list<string>}>
   *   Byte buffers and expected key descriptions.
   */
  public static function dataProviderParse(): \Iterator {
    yield 'chars' => ['abc', ['char:a', 'char:b', 'char:c']];
    yield 'enter cr' => ["\r", ['Enter']];
    yield 'enter lf' => ["\n", ['Enter']];
    yield 'backspace' => ["\x7f", ['Backspace']];
    yield 'tab' => ["\t", ['Tab']];
    yield 'space' => [' ', ['Space']];
    yield 'ctrl-c interrupt' => ["\x03", ['Interrupt']];
    yield 'up' => ["\033[A", ['Up']];
    yield 'down' => ["\033[B", ['Down']];
    yield 'right' => ["\033[C", ['Right']];
    yield 'left' => ["\033[D", ['Left']];
    yield 'home csi' => ["\033[H", ['Home']];
    yield 'end csi' => ["\033[F", ['End']];
    yield 'pageup' => ["\033[5~", ['PageUp']];
    yield 'pagedown' => ["\033[6~", ['PageDown']];
    yield 'delete' => ["\033[3~", ['Delete']];
    yield 'home tilde' => ["\033[1~", ['Home']];
    yield 'end tilde' => ["\033[4~", ['End']];
    yield 'bare escape' => ["\033", ['Escape']];
    yield 'escape then char' => ["\033x", ['Escape', 'char:x']];
    yield 'unknown csi degrades' => ["\033[Z", ['Escape']];
    // Modifier parameters (Shift/Ctrl arrows) resolve to the unmodified key
    // and never leak their parameter bytes as typed characters.
    yield 'shift up' => ["\033[1;2A", ['Up']];
    yield 'ctrl right' => ["\033[1;5C", ['Right']];
    yield 'modified delete' => ["\033[3;5~", ['Delete']];
    // SS3 (application cursor keys mode) mirrors the CSI finals.
    yield 'ss3 up' => ["\033OA", ['Up']];
    yield 'ss3 end' => ["\033OF", ['End']];
    yield 'ss3 unknown degrades' => ["\033OZ", ['Escape']];
    yield 'ss3 truncated degrades' => ["\033O", ['Escape']];
    yield 'mouse wheel up' => ["\033[<64;10;5M", ['MouseWheelUp']];
    yield 'mouse wheel down' => ["\033[<65;10;5M", ['MouseWheelDown']];
    yield 'mouse other ignored' => ["\033[<0;1;1M", []];
    yield 'combo' => ["a\033[Bb", ['char:a', 'Down', 'char:b']];
    // A sequence truncated at a read boundary is swallowed whole rather than
    // leaking its scanned prefix as typed characters.
    yield 'truncated csi introducer degrades' => ["\033[", ['Escape']];
    yield 'incomplete csi degrades' => ["\033[1", ['Escape']];
    yield 'incomplete mouse degrades' => ["\033[<1", ['Escape']];
    // A UTF-8 sequence is one typed character, not one key per byte.
    yield 'multibyte char' => ['é', ['char:é']];
    yield 'multibyte between ascii' => ['aéb', ['char:a', 'char:é', 'char:b']];
    yield 'four byte emoji' => ['😀', ['char:😀']];
    yield 'stray continuation byte stays a byte' => ["\x80", ["char:\x80"]];
  }

  /**
   * Describe a key for assertion.
   *
   * @param \DrevOps\PhpTui\Input\Key $key
   *   The key.
   *
   * @return string
   *   The description.
   */
  protected function describe(Key $key): string {
    if ($key->isChar()) {
      return 'char:' . ($key->char ?? '');
    }

    return $key->name instanceof KeyName ? $key->name->name : '?';
  }

}
