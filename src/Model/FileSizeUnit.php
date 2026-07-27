<?php

declare(strict_types=1);

namespace DrevOps\Tui\Model;

/**
 * The units a byte count is scaled through when formatted for display.
 *
 * Ordered smallest to largest, each a factor of 1024 above the previous, so a
 * formatter can walk the cases while a value stays above one unit.
 *
 * @package DrevOps\Tui\Model
 */
enum FileSizeUnit: string {

  case Byte = 'B';
  case Kilobyte = 'KB';
  case Megabyte = 'MB';
  case Gigabyte = 'GB';
  case Terabyte = 'TB';

}
