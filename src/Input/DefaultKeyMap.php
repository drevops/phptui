<?php

declare(strict_types=1);

namespace DrevOps\Tui\Input;

use DrevOps\Tui\Model\FieldType;

/**
 * The built-in key bindings: the defaults every form uses unless it opts out.
 *
 * A preset is a class listing its {@see Binding}s, the way a theme is a class
 * of styling methods. The base bindings are shared by every widget; the
 * navigation and per-widget-type bindings override the base only where a key
 * means something different (Enter inserts a newline in a textarea, Space
 * toggles a checkbox option, and so on). Subclass this and override
 * {@see bindings()} to ship an alternate preset - {@see VimKeyMap} does exactly
 * that.
 *
 * @package DrevOps\Tui\Input
 */
class DefaultKeyMap {

  /**
   * The bindings this preset declares.
   *
   * @return list<\DrevOps\Tui\Input\Binding>
   *   The bindings, base first, then per-scope overrides.
   */
  public function bindings(): array {
    $base = Scope::base();

    $bindings = [
      new Binding($base, Action::MoveUp, KeyName::Up),
      new Binding($base, Action::MoveDown, KeyName::Down),
      new Binding($base, Action::MoveLeft, KeyName::Left),
      new Binding($base, Action::MoveRight, KeyName::Right),
      new Binding($base, Action::Accept, KeyName::Enter),
      new Binding($base, Action::Cancel, KeyName::Escape),
      new Binding($base, Action::DeleteBack, KeyName::Backspace),
      new Binding($base, Action::InsertSpace, KeyName::Space),

      new Binding(Scope::navigation(), Action::Activate, KeyName::Enter),
      new Binding(Scope::navigation(), Action::Back, KeyName::Escape),
      new Binding(Scope::navigation(), Action::Quit, 'q'),
      new Binding(Scope::navigation(), Action::ScrollUp, KeyName::MouseWheelUp),
      new Binding(Scope::navigation(), Action::ScrollDown, KeyName::MouseWheelDown),
      new Binding(Scope::navigation(), Action::Help, '?'),

      // The arrow keys step a bounded number, overriding base movement.
      new Binding(Scope::field(FieldType::Number), Action::Increment, KeyName::Up),
      new Binding(Scope::field(FieldType::Number), Action::Decrement, KeyName::Down),

      // The text field accepts its inline ghost-text completion on Tab.
      new Binding(Scope::field(FieldType::Text), Action::Complete, KeyName::Tab),

      // Up and Down step between a template's slots, Tab joining Down as the
      // key a reader reaches for first; Left and Right stay on the base
      // bindings, moving the caret inside the slot being filled in.
      new Binding(Scope::field(FieldType::Template), Action::MoveDown, KeyName::Down, KeyName::Tab),
      new Binding(Scope::field(FieldType::Template), Action::MoveUp, KeyName::Up),

      new Binding(Scope::field(FieldType::Textarea), Action::NewLine, KeyName::Enter),
      new Binding(Scope::field(FieldType::Textarea), Action::Accept, KeyName::Tab),
      // The textarea hands off to the user's $EDITOR on Ctrl-E.
      new Binding(Scope::field(FieldType::Textarea), Action::ExternalEdit, Key::char("\x05")),

      new Binding(Scope::field(FieldType::Confirm), Action::Yes, 'y', 'Y'),
      new Binding(Scope::field(FieldType::Confirm), Action::No, 'n', 'N'),

      // The password reveal toggle cycles the display mode on Tab.
      new Binding(Scope::field(FieldType::Password), Action::Reveal, KeyName::Tab),

      new Binding(Scope::field(FieldType::Pause), Action::Accept, KeyName::Enter, KeyName::Space),
    ];

    // A two-state switch flips on any directional key or Space.
    foreach ([FieldType::Confirm, FieldType::Toggle] as $type) {
      $bindings[] = new Binding(Scope::field($type), Action::Toggle, KeyName::Left, KeyName::Right, KeyName::Space, KeyName::Up, KeyName::Down);
    }

    // The checkbox and the searchable checkbox share one set of list bindings,
    // carried by the multiple variant of each option-backed choice type.
    foreach ([FieldType::Select, FieldType::Search] as $type) {
      $bindings[] = new Binding(Scope::field($type, multiple: TRUE), Action::Toggle, KeyName::Space);
      $bindings[] = new Binding(Scope::field($type, multiple: TRUE), Action::SelectAll, KeyName::Right);
      $bindings[] = new Binding(Scope::field($type, multiple: TRUE), Action::SelectNone, KeyName::Left);
    }

    // The picker reveals hidden entries on Tab in both variants (Left/Right
    // browse in and out, inherited from the base); the multiple variant also
    // toggles the highlighted entry on Space.
    $bindings[] = new Binding(Scope::field(FieldType::FilePicker), Action::Reveal, KeyName::Tab);
    $bindings[] = new Binding(Scope::field(FieldType::FilePicker, multiple: TRUE), Action::Reveal, KeyName::Tab);
    $bindings[] = new Binding(Scope::field(FieldType::FilePicker, multiple: TRUE), Action::Toggle, KeyName::Space);

    // The reorder widget picks up and drops the highlighted item on Space;
    // Up and Down (inherited from the base) move the cursor, or the held item.
    $bindings[] = new Binding(Scope::field(FieldType::Reorder), Action::Grab, KeyName::Space);

    return $bindings;
  }

}
