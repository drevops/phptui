<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Input;

use DrevOps\PhpTui\Block\FieldType;

/**
 * A vim-style preset: h/j/k/l navigate alongside the arrow keys.
 *
 * The letter keys are added only where they cannot be swallowed as typed input:
 * panel navigation, the single-choice list and the date calendar. Text fields
 * and the filtering
 * lists (search, suggest, checkbox) keep the arrow keys, because there a letter
 * is a character the user is typing - binding it to movement would make it
 * un-typeable, which the key map rejects outright. Everything else is inherited
 * from {@see DefaultKeyMap}.
 *
 * @package DrevOps\PhpTui\Input
 */
class VimKeyMap extends DefaultKeyMap {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function bindings(): array {
    $bindings = [
      new Binding(Scope::navigation(), Action::MoveUp, 'k', KeyName::Up),
      new Binding(Scope::navigation(), Action::MoveDown, 'j', KeyName::Down),
      new Binding(Scope::navigation(), Action::MoveLeft, 'h', KeyName::Left),
      new Binding(Scope::navigation(), Action::MoveRight, 'l', KeyName::Right),
    ];

    // k/j apply wherever typed input cannot swallow them; the calendar also
    // takes h/l for day movement.
    foreach ([FieldType::Select, FieldType::Calendar] as $type) {
      $bindings[] = new Binding(Scope::field($type), Action::MoveUp, 'k', KeyName::Up);
      $bindings[] = new Binding(Scope::field($type), Action::MoveDown, 'j', KeyName::Down);
    }

    $bindings[] = new Binding(Scope::field(FieldType::Calendar), Action::MoveLeft, 'h', KeyName::Left);
    $bindings[] = new Binding(Scope::field(FieldType::Calendar), Action::MoveRight, 'l', KeyName::Right);

    return array_merge(parent::bindings(), $bindings);
  }

}
