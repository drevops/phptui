<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

/**
 * The kind of a row in a choice field's option list.
 *
 * Only Option rows are selectable; Separator and Heading rows are visual
 * structure that navigation skips and never collects.
 *
 * @package DrevOps\Tui\Block
 */
enum OptionKind: string {

  case Option = 'option';
  case Separator = 'separator';
  case Heading = 'heading';

}
