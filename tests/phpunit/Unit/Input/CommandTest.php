<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Input;

use DrevOps\PhpTui\Block\FieldType;
use DrevOps\PhpTui\Input\Action;
use DrevOps\PhpTui\Input\Binding;
use DrevOps\PhpTui\Input\Command;
use DrevOps\PhpTui\Input\Hint;
use DrevOps\PhpTui\Input\Key;
use DrevOps\PhpTui\Input\KeyMap;
use DrevOps\PhpTui\Input\KeyMapManager;
use DrevOps\PhpTui\Input\KeyName;
use DrevOps\PhpTui\Input\Scope;
use DrevOps\PhpTui\Input\ScopedKeyMap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests caller commands: declaration, resolution and conflict detection.
 */
#[CoversClass(Command::class)]
#[CoversClass(KeyMap::class)]
#[CoversClass(ScopedKeyMap::class)]
#[Group('input')]
final class CommandTest extends TestCase {

  public function testCommandHoldsItsDeclaration(): void {
    $handler = static function (): void {};
    $command = new Command('save', $handler, 's', KeyName::Tab);

    $this->assertSame('save', $command->label);
    $this->assertSame($handler, $command->handler);
    $this->assertSame(['s', KeyName::Tab], $command->keys);
  }

  public function testCommandResolvesInNavigationOnly(): void {
    $save = new Command('save', static function (): void {}, 's', 'S');
    $map = KeyMapManager::create('default', [$save]);

    $this->assertSame($save, $map->navigation()->command(Key::char('s')));
    $this->assertSame($save, $map->navigation()->command(Key::char('S')));
    $this->assertNotInstanceOf(Command::class, $map->navigation()->command(Key::char('x')));
    $this->assertNotInstanceOf(Command::class, $map->scope(Scope::base())->command(Key::char('s')));
    $this->assertNotInstanceOf(Command::class, $map->forField(FieldType::Select)->command(Key::char('s')));
  }

  public function testCommandHintsFollowDeclarationOrder(): void {
    $map = KeyMapManager::create('default', [
      new Command('save', static function (): void {}, 's', 'S'),
      new Command('open', static function (): void {}, Key::named(KeyName::Tab)),
    ]);

    $hints = array_map(static fn(Hint $hint): array => [$hint->label, array_map(static fn(Key $key): string => $key->token(), $hint->keys), $hint->actions], $map->navigation()->commandHints());

    $this->assertSame([
      ['save', [Key::char('s')->token()], []],
      ['open', [Key::named(KeyName::Tab)->token()], []],
    ], $hints);
  }

  public function testRepeatedKeyInOneCommandIsAccepted(): void {
    $save = new Command('save', static function (): void {}, 's', Key::char('s'));
    $map = KeyMapManager::create('default', [$save]);

    $this->assertCount(1, $map->navigation()->commandHints());
    $this->assertSame($save, $map->navigation()->command(Key::char('s')));
  }

  public function testCommandMayTakeKeyFreedByRebinding(): void {
    $quit = new Command('quit now', static function (): void {}, 'q');
    $map = KeyMapManager::create('default', [new Binding(Scope::navigation(), Action::Quit, 'x'), $quit]);

    $this->assertSame($quit, $map->navigation()->command(Key::char('q')));
    $this->assertTrue($map->navigation()->matches(Key::char('x'), Action::Quit));
  }

  public function testPresetMayDeclareCommands(): void {
    $map = new KeyMap([new Command('save', static function (): void {}, 's')]);

    $this->assertInstanceOf(Command::class, $map->navigation()->command(Key::char('s')));
  }

  /**
   * Tests that a conflicting or malformed command throws at setup.
   *
   * @param string $preset
   *   The preset the overrides apply on top of.
   * @param list<\DrevOps\PhpTui\Input\Binding|\DrevOps\PhpTui\Input\Command> $overrides
   *   The overrides applied on top of the preset.
   * @param string $message
   *   The expected exception message fragment.
   */
  #[DataProvider('dataProviderConflictsThrow')]
  public function testConflictsThrow(string $preset, array $overrides, string $message): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($message);

    KeyMapManager::create($preset, $overrides);
  }

  public static function dataProviderConflictsThrow(): \Iterator {
    $noop = static function (): void {};

    yield 'navigation action' => ['default', [new Command('save', $noop, 'q')], 'Key "q" is bound to both Quit and the "save" command in the navigation scope.'];
    yield 'inherited base action' => ['default', [new Command('save', $noop, KeyName::Space)], 'bound to both InsertSpace and the "save" command'];
    yield 'space written as a character' => ['default', [new Command('save', $noop, ' ')], 'bound to both InsertSpace and the "save" command'];
    yield 'help key' => ['default', [new Command('save', $noop, '?')], 'bound to both Help and the "save" command'];
    yield 'vim letter' => ['vim', [new Command('save', $noop, 'j')], 'bound to both MoveDown and the "save" command'];
    yield 'another command' => ['default', [new Command('save', $noop, 's'), new Command('sort', $noop, 'o', 's')], 'Key "s" is bound to both the "save" and "sort" commands in the navigation scope.'];
    yield 'action declared after the command' => ['default', [new Command('save', $noop, 'x'), new Binding(Scope::navigation(), Action::Quit, 'x')], 'bound to both Quit and the "save" command'];
    yield 'interrupt key' => ['default', [new Command('save', $noop, 's', KeyName::Interrupt)], 'The "save" command cannot use the interrupt key, which always stops the session.'];
    yield 'interrupt byte' => ['default', [new Command('save', $noop, Key::char("\x03"))], 'The "save" command cannot use the interrupt key, which always stops the session.'];
    yield 'no keys' => ['default', [new Command('save', $noop)], 'The "save" command needs at least one key.'];
    yield 'empty label' => ['default', [new Command('  ', $noop, 's')], 'A command needs a label to show in the hint line.'];
    yield 'multi-character key' => ['default', [new Command('save', $noop, 'sv')], 'A character binding in the navigation scope must be a single character, got "sv".'];
  }

}
