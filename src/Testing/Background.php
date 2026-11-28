<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Testing;

/**
 * A scripted continue of a suspended session in the background.
 *
 * Placed right after the suspend key in the keystrokes a scripted terminal
 * delivers, it makes the session carry on detached, as the shell's `bg` does.
 * The session ticks at each Tick that follows, and re-attaches at the first
 * scripted item that is neither a Tick nor a Resize, as `fg` does. Without it,
 * a suspended session continues in the foreground at once.
 *
 * @code
 * $tester->live($tick)->run(Key::named(KeyName::Suspend), new Background(), new Tick(), "\r");
 * @endcode
 *
 * @package DrevOps\PhpTui\Testing
 */
final readonly class Background {

}
