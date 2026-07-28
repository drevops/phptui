<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Input;

use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Binding;
use DrevOps\Tui\Input\DefaultKeyMap;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMap;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Input\ScopedKeyMap;
use DrevOps\Tui\Input\VimKeyMap;
use DrevOps\Tui\Tests\Traits\ResetsRegistriesTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the configurable key map: presets, scopes, resolution and validation.
 */
#[CoversClass(Action::class)]
#[CoversClass(Binding::class)]
#[CoversClass(Scope::class)]
#[CoversClass(ScopedKeyMap::class)]
#[CoversClass(KeyMap::class)]
#[CoversClass(DefaultKeyMap::class)]
#[CoversClass(VimKeyMap::class)]
#[CoversClass(KeyMapManager::class)]
#[Group('input')]
final class KeyMapTest extends TestCase {

  use ResetsRegistriesTrait;

  protected function tearDown(): void {
    $this->restoreRegistries();
    parent::tearDown();
  }

  #[DataProvider('dataProviderDefaultBindings')]
  public function testDefaultBindings(Scope $scope, Key $key, Action $action, bool $expected): void {
    $map = KeyMapManager::create()->scope($scope);

    $this->assertSame($expected, $map->matches($key, $action));
  }

  public static function dataProviderDefaultBindings(): \Iterator {
    // Navigation.
    yield 'nav up moves up' => [Scope::navigation(), Key::named(KeyName::Up), Action::MoveUp, TRUE];
    yield 'nav enter activates' => [Scope::navigation(), Key::named(KeyName::Enter), Action::Activate, TRUE];
    yield 'nav enter is not accept' => [Scope::navigation(), Key::named(KeyName::Enter), Action::Accept, FALSE];
    yield 'nav q quits' => [Scope::navigation(), Key::char('q'), Action::Quit, TRUE];
    yield 'nav escape backs out' => [Scope::navigation(), Key::named(KeyName::Escape), Action::Back, TRUE];
    yield 'nav wheel scrolls' => [Scope::navigation(), Key::named(KeyName::MouseWheelDown), Action::ScrollDown, TRUE];
    yield 'nav ? opens help' => [Scope::navigation(), Key::char('?'), Action::Help, TRUE];
    // A bounded number steps with the arrows, which override base movement.
    yield 'number up increments' => [Scope::field(FieldType::Number), Key::named(KeyName::Up), Action::Increment, TRUE];
    yield 'number down decrements' => [Scope::field(FieldType::Number), Key::named(KeyName::Down), Action::Decrement, TRUE];
    yield 'number up is no longer move up' => [Scope::field(FieldType::Number), Key::named(KeyName::Up), Action::MoveUp, FALSE];
    yield 'number left still moves the caret' => [Scope::field(FieldType::Number), Key::named(KeyName::Left), Action::MoveLeft, TRUE];
    // A rating is walked along its scale, so both axes step it and neither
    // keeps its base movement meaning.
    yield 'rating right increments' => [Scope::field(FieldType::Rating), Key::named(KeyName::Right), Action::Increment, TRUE];
    yield 'rating up increments' => [Scope::field(FieldType::Rating), Key::named(KeyName::Up), Action::Increment, TRUE];
    yield 'rating left decrements' => [Scope::field(FieldType::Rating), Key::named(KeyName::Left), Action::Decrement, TRUE];
    yield 'rating down decrements' => [Scope::field(FieldType::Rating), Key::named(KeyName::Down), Action::Decrement, TRUE];
    yield 'rating right is no longer move right' => [Scope::field(FieldType::Rating), Key::named(KeyName::Right), Action::MoveRight, FALSE];
    yield 'rating enter accepts' => [Scope::field(FieldType::Rating), Key::named(KeyName::Enter), Action::Accept, TRUE];
    // Text.
    yield 'text enter accepts' => [Scope::field(FieldType::Text), Key::named(KeyName::Enter), Action::Accept, TRUE];
    yield 'text space inserts' => [Scope::field(FieldType::Text), Key::named(KeyName::Space), Action::InsertSpace, TRUE];
    yield 'text backspace deletes' => [Scope::field(FieldType::Text), Key::named(KeyName::Backspace), Action::DeleteBack, TRUE];
    yield 'text tab completes' => [Scope::field(FieldType::Text), Key::named(KeyName::Tab), Action::Complete, TRUE];
    // Suggest takes the same completion key while keeping the base movement
    // that arrows through its ranked list.
    yield 'suggest tab completes' => [Scope::field(FieldType::Suggest), Key::named(KeyName::Tab), Action::Complete, TRUE];
    yield 'suggest down still moves down' => [Scope::field(FieldType::Suggest), Key::named(KeyName::Down), Action::MoveDown, TRUE];
    // Template: Tab joins Down to step between slots, while Left and Right stay
    // on the base bindings so they move the caret inside the live slot.
    yield 'template tab steps forward' => [Scope::field(FieldType::Template), Key::named(KeyName::Tab), Action::MoveDown, TRUE];
    yield 'template down steps forward' => [Scope::field(FieldType::Template), Key::named(KeyName::Down), Action::MoveDown, TRUE];
    yield 'template up steps back' => [Scope::field(FieldType::Template), Key::named(KeyName::Up), Action::MoveUp, TRUE];
    yield 'template left still moves the caret' => [Scope::field(FieldType::Template), Key::named(KeyName::Left), Action::MoveLeft, TRUE];
    yield 'template enter accepts' => [Scope::field(FieldType::Template), Key::named(KeyName::Enter), Action::Accept, TRUE];
    // Textarea overrides the base.
    yield 'textarea enter is newline' => [Scope::field(FieldType::Textarea), Key::named(KeyName::Enter), Action::NewLine, TRUE];
    yield 'textarea enter is not accept' => [Scope::field(FieldType::Textarea), Key::named(KeyName::Enter), Action::Accept, FALSE];
    yield 'textarea tab accepts' => [Scope::field(FieldType::Textarea), Key::named(KeyName::Tab), Action::Accept, TRUE];
    // A control char is bindable in a text-entry scope; a printable one is not.
    yield 'textarea ctrl-e is external edit' => [Scope::field(FieldType::Textarea), Key::char("\x05"), Action::ExternalEdit, TRUE];
    // Password reveal toggle.
    yield 'password tab reveals' => [Scope::field(FieldType::Password), Key::named(KeyName::Tab), Action::Reveal, TRUE];
    // Confirm.
    yield 'confirm up toggles' => [Scope::field(FieldType::Confirm), Key::named(KeyName::Up), Action::Toggle, TRUE];
    yield 'confirm y is yes' => [Scope::field(FieldType::Confirm), Key::char('y'), Action::Yes, TRUE];
    yield 'confirm uppercase y is yes' => [Scope::field(FieldType::Confirm), Key::char('Y'), Action::Yes, TRUE];
    yield 'confirm enter accepts' => [Scope::field(FieldType::Confirm), Key::named(KeyName::Enter), Action::Accept, TRUE];
    // Toggle switch.
    yield 'toggle space flips' => [Scope::field(FieldType::Toggle), Key::named(KeyName::Space), Action::Toggle, TRUE];
    yield 'toggle enter accepts' => [Scope::field(FieldType::Toggle), Key::named(KeyName::Enter), Action::Accept, TRUE];
    // Multiple choice.
    yield 'multiselect space toggles' => [Scope::field(FieldType::Select, TRUE), Key::named(KeyName::Space), Action::Toggle, TRUE];
    yield 'multiselect right selects all' => [Scope::field(FieldType::Select, TRUE), Key::named(KeyName::Right), Action::SelectAll, TRUE];
    yield 'multiselect up moves up (inherited)' => [Scope::field(FieldType::Select, TRUE), Key::named(KeyName::Up), Action::MoveUp, TRUE];
    yield 'multisearch space toggles' => [Scope::field(FieldType::Search, TRUE), Key::named(KeyName::Space), Action::Toggle, TRUE];
    // File pickers reveal hidden entries on Tab; base arrows browse in and out.
    yield 'filepicker tab reveals' => [Scope::field(FieldType::FilePicker), Key::named(KeyName::Tab), Action::Reveal, TRUE];
    yield 'filepicker right browses in (inherited)' => [Scope::field(FieldType::FilePicker), Key::named(KeyName::Right), Action::MoveRight, TRUE];
    yield 'multifilepicker space toggles' => [Scope::field(FieldType::FilePicker, TRUE), Key::named(KeyName::Space), Action::Toggle, TRUE];
    yield 'multifilepicker tab reveals' => [Scope::field(FieldType::FilePicker, TRUE), Key::named(KeyName::Tab), Action::Reveal, TRUE];
    // Pause binds two keys to accept.
    yield 'pause enter accepts' => [Scope::field(FieldType::Pause), Key::named(KeyName::Enter), Action::Accept, TRUE];
    yield 'pause space accepts' => [Scope::field(FieldType::Pause), Key::named(KeyName::Space), Action::Accept, TRUE];
    // A scope with no overrides falls back to the base bindings.
    yield 'select falls back to base' => [Scope::field(FieldType::Select), Key::named(KeyName::Up), Action::MoveUp, TRUE];
  }

  public function testForFieldFallsBackToBaseInstance(): void {
    $map = KeyMapManager::create();

    // Select has no overrides, so it is the very same base instance.
    $this->assertSame($map->scope(Scope::base()), $map->forField(FieldType::Select));
  }

  #[DataProvider('dataProviderVimPreset')]
  public function testVimPreset(Scope $scope, Key $key, Action $action, bool $expected): void {
    $map = KeyMapManager::create('vim')->scope($scope);

    $this->assertSame($expected, $map->matches($key, $action));
  }

  public static function dataProviderVimPreset(): \Iterator {
    yield 'nav j moves down' => [Scope::navigation(), Key::char('j'), Action::MoveDown, TRUE];
    yield 'nav k moves up' => [Scope::navigation(), Key::char('k'), Action::MoveUp, TRUE];
    yield 'nav h moves left' => [Scope::navigation(), Key::char('h'), Action::MoveLeft, TRUE];
    yield 'nav l moves right' => [Scope::navigation(), Key::char('l'), Action::MoveRight, TRUE];
    yield 'nav arrows still work' => [Scope::navigation(), Key::named(KeyName::Down), Action::MoveDown, TRUE];
    yield 'select j moves down' => [Scope::field(FieldType::Select), Key::char('j'), Action::MoveDown, TRUE];
    // A text field must keep j typeable, so it is not movement there.
    yield 'text j is not movement' => [Scope::field(FieldType::Text), Key::char('j'), Action::MoveDown, FALSE];
  }

  public function testOverrideRetunesOneScope(): void {
    $map = KeyMapManager::create('default', [
      new Binding(Scope::field(FieldType::Select), Action::Accept, 'l', KeyName::Enter),
    ])->forField(FieldType::Select);

    $this->assertTrue($map->matches(Key::char('l'), Action::Accept));
    $this->assertTrue($map->matches(Key::named(KeyName::Enter), Action::Accept));
  }

  public function testScopeAccessorResolvesEachKind(): void {
    $map = KeyMapManager::create();

    $this->assertInstanceOf(ScopedKeyMap::class, $map->scope(Scope::base()));
    $this->assertSame($map->navigation(), $map->scope(Scope::navigation()));
    $this->assertSame($map->forField(FieldType::Textarea), $map->scope(Scope::field(FieldType::Textarea)));
  }

  public function testKeysForAndPrimaryAndUnbound(): void {
    $nav = KeyMapManager::create()->navigation();

    $this->assertTrue($nav->primary(Action::MoveUp)?->is(KeyName::Up));
    $this->assertCount(1, $nav->keysFor(Action::Quit));

    // Newline is not a navigation action.
    $this->assertNotInstanceOf(Key::class, $nav->primary(Action::NewLine));
    $this->assertSame([], $nav->keysFor(Action::NewLine));
    $this->assertFalse($nav->matches(Key::named(KeyName::Enter), Action::NewLine));
  }

  public function testNormalisesAllKeyForms(): void {
    $map = new KeyMap([
      new Binding(Scope::field(FieldType::Select), Action::Accept, Key::named(KeyName::Tab), KeyName::Enter, 'l'),
    ]);
    $select = $map->forField(FieldType::Select);

    $this->assertTrue($select->matches(Key::named(KeyName::Tab), Action::Accept));
    $this->assertTrue($select->matches(Key::named(KeyName::Enter), Action::Accept));
    $this->assertTrue($select->matches(Key::char('l'), Action::Accept));
  }

  #[DataProvider('dataProviderFailsLoudly')]
  public function testFailsLoudly(Binding $binding, string $message): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($message);

    KeyMapManager::create('default', [$binding]);
  }

  public static function dataProviderFailsLoudly(): \Iterator {
    yield 'conflict in one scope' => [
      new Binding(Scope::navigation(), Action::Quit, KeyName::Enter),
      'bound to both Activate and Quit in the navigation scope',
    ];
    yield 'printable char in the base scope' => [
      new Binding(Scope::base(), Action::Accept, 'x'),
      'base scope may not bind the printable character',
    ];
    yield 'printable char in a text-entry scope' => [
      new Binding(Scope::field(FieldType::Search), Action::MoveDown, 'j'),
      'search scope consumes typed characters',
    ];
    yield 'multi-character binding' => [
      new Binding(Scope::field(FieldType::Select), Action::Accept, 'ab'),
      'must be a single character',
    ];
  }

  public function testUnknownPresetThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown key-map preset "nope"');

    KeyMapManager::create('nope');
  }

  public function testRegisterAndSelectByName(): void {
    $this->snapshotRegistry(KeyMapManager::class);
    KeyMapManager::register('registered-vim', VimKeyMap::class);

    $map = KeyMapManager::create('registered-vim')->navigation();

    $this->assertTrue($map->matches(Key::char('j'), Action::MoveDown));
  }

  public function testRegisterRejectsNonPreset(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('must extend');

    KeyMapManager::register('bad', \stdClass::class);
  }

  public function testCreateAcceptsPresetClassName(): void {
    $map = KeyMapManager::create(VimKeyMap::class)->navigation();

    $this->assertTrue($map->matches(Key::char('k'), Action::MoveUp));
  }

  public function testEmptyPresetNameIsDefault(): void {
    $map = KeyMapManager::create('')->forField(FieldType::Textarea);

    $this->assertTrue($map->matches(Key::named(KeyName::Enter), Action::NewLine));
  }

  #[DataProvider('dataProviderScope')]
  public function testScope(Scope $scope, string $token, string $label, bool $consumes_text): void {
    $this->assertSame($token, $scope->token());
    $this->assertSame($label, $scope->label());
    $this->assertSame($consumes_text, $scope->consumesText());
  }

  public static function dataProviderScope(): \Iterator {
    yield 'base' => [Scope::base(), '@base', 'base', FALSE];
    yield 'navigation' => [Scope::navigation(), '@navigation', 'navigation', FALSE];
    yield 'text is a text-entry scope' => [Scope::field(FieldType::Text), 'field:Text', 'text', TRUE];
    yield 'template is a text-entry scope' => [Scope::field(FieldType::Template), 'field:Template', 'template', TRUE];
    yield 'select is not a text-entry scope' => [Scope::field(FieldType::Select), 'field:Select', 'select', FALSE];
    // The file picker filters by typing, so it is a text-entry scope.
    yield 'filepicker is a text-entry scope' => [Scope::field(FieldType::FilePicker), 'field:FilePicker', 'filepicker', TRUE];
    // A multiple select filters by typing, so it becomes a text-entry scope
    // with its own token, unlike the single select.
    yield 'multiple select is a distinct text-entry scope' => [Scope::field(FieldType::Select, TRUE), 'field:Select#multiple', 'select', TRUE];
    yield 'multiple file picker keeps its own token' => [Scope::field(FieldType::FilePicker, TRUE), 'field:FilePicker#multiple', 'filepicker', TRUE];
  }

  public function testBindingHoldsItsDeclaration(): void {
    $binding = new Binding(Scope::navigation(), Action::Quit, KeyName::Escape, 'x');

    $this->assertTrue($binding->scope->navigation);
    $this->assertSame(Action::Quit, $binding->action);
    $this->assertSame([KeyName::Escape, 'x'], $binding->keys);
  }

}
