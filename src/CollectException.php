<?php

declare(strict_types=1);

namespace DrevOps\Tui;

/**
 * Thrown when a collection cannot take the answers it was given.
 *
 * A run with no screen has no way to retype a rejected value and no row to
 * report it on, so the whole collection fails rather than returning a set
 * with an answer that was never accepted. It shares a namespace with
 * {@see InterruptException} and {@see CancelException}: all three end a
 * collection without a complete answer set, so one import covers every
 * ending.
 *
 * @package DrevOps\Tui
 */
class CollectException extends \RuntimeException {

}
