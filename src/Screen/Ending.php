<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Screen;

/**
 * The two ways a form ends from the buttons that end it.
 *
 * Finishing it answers for the fields that are owed an answer; abandoning it
 * never does, and that is the whole of the difference between them.
 *
 * @package DrevOps\PhpTui\Screen
 */
enum Ending: string {

  // The form is finished, and its answers stand.
  case Submit = 'submit';

  // The form is abandoned, and its answers are never asked for.
  case Cancel = 'cancel';

}
