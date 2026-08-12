<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Block;

/**
 * Which of its two shapes a field is drawing.
 *
 * One field owns both. The label and the selector stay put across them; only
 * the value region changes shape, which is why a field in edit mode is still
 * one row of its panel rather than something new on the screen.
 *
 * @package DrevOps\PhpTui\Block
 */
enum Mode: string {

  // One line, carrying the answer.
  case View = 'view';

  // Open, collecting the answer.
  case Edit = 'edit';

}
