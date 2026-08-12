<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Screen;

/**
 * A piece of the standard furniture a session puts on a screen.
 *
 * A layout names no block, so something has to say that the trail belongs at
 * the top - and it cannot be a name the assembler goes looking for, because a
 * layout calling its regions anything else would silently lose whatever was
 * meant for it. So the layout answers instead, one piece at a time, and a
 * layout that keeps no place for a piece says so rather than being guessed at.
 *
 * @package DrevOps\PhpTui\Screen
 */
enum Furniture {

  // The trail of panels entered to get where the cursor is.
  case Trail;

  // The form itself: the panel you are in, and the buttons that end it.
  case Body;

  // The keys that apply right now.
  case Keys;

}
