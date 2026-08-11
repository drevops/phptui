<?php

declare(strict_types=1);

namespace DrevOps\Tui\Input;

/**
 * A semantic input action, decoupled from the physical key that triggers it.
 *
 * Fields and the panel controller test a key press against an action via
 * {@see ScopedKeyMap}, not against a raw {@see KeyName}. The map binds each
 * action to one or more keys per scope, so a different context or a consumer
 * remap can bind a different key to the same action. The set of actions is
 * fixed; the bindings behind them are configurable.
 *
 * @package DrevOps\Tui\Input
 */
enum Action {

  // Navigation, shared by every scope.
  case MoveUp;
  case MoveDown;
  case MoveLeft;
  case MoveRight;

  // Lifecycle, shared by every scope.
  case Accept;
  case Cancel;

  // Panel navigation.
  case Activate;
  case Back;
  case Quit;
  case ScrollUp;
  case ScrollDown;
  case Help;

  // Text editing.
  case DeleteBack;
  case InsertSpace;
  case NewLine;
  case ExternalEdit;
  case Complete;

  // Number entry.
  case Increment;
  case Decrement;

  // Multiple choice.
  case Toggle;
  case SelectAll;
  case SelectNone;

  // Reorder.
  case Grab;

  // Confirm.
  case Yes;
  case No;

  // Password.
  case Reveal;

}
