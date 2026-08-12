<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Discovery;

/**
 * Which directory entries a {@see Scan} rule keeps.
 *
 * @package DrevOps\PhpTui\Discovery
 */
enum ScanType: string {

  // Keep only directories.
  case Dir = 'dir';

  // Keep only files.
  case File = 'file';

  // Keep every entry.
  case Any = 'any';

}
