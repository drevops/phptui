<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Screen;

/**
 * How a region asks for its share of the axis its layout runs along.
 *
 * Three kinds cover every arrangement, and each answers a question the others
 * cannot: a header is one line whatever the terminal height, a body takes
 * whatever is left over, and a window is as deep as what it holds. A region
 * states the kind and the layout does the arithmetic, which is why this says
 * nothing about cells.
 *
 * @package DrevOps\PhpTui\Screen
 */
enum Sizing {

  // Exactly the cells it asked for, whatever the terminal size.
  case Fixed;

  // A share of whatever the other kinds leave behind.
  case Flex;

  // As many cells as what it holds comes to.
  case Content;

}
