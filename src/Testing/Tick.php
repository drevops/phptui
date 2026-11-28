<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Testing;

/**
 * A scripted moment in which the wait for a key times out.
 *
 * Placed among the keystrokes a scripted terminal delivers, it makes the next
 * wait report no input, so a live session runs its tick there. A session that
 * is not live never waits, and skips it.
 *
 * @code
 * $tester->live(fn(int $count) => $clock->body('Tick ' . $count))->run(new Tick(), new Tick());
 * @endcode
 *
 * @package DrevOps\PhpTui\Testing
 */
final readonly class Tick {

}
