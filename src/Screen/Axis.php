<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Screen;

/**
 * The direction things inside a layout or a region run.
 *
 * Two values cover every arrangement, because the second dimension comes from
 * nesting rather than from a grid: a panel is a block that contains a layout,
 * so any arrangement is rows of columns of rows.
 *
 * @package DrevOps\PhpTui\Screen
 */
enum Axis: string {

  // Top to bottom.
  case Rows = 'rows';

  // Left to right.
  case Columns = 'columns';

}
