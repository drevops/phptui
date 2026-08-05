<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

/**
 * What a file picker may select: any entry, files only or directories only.
 *
 * The mode governs which entries are selectable, not which are navigable: a
 * directory is always enterable so files beneath it stay reachable, even when
 * only files may be chosen.
 *
 * @package DrevOps\Tui\Block
 */
enum FilePickerMode {

  case Any;
  case File;
  case Directory;

}
