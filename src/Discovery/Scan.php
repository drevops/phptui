<?php

declare(strict_types=1);

namespace DrevOps\Tui\Discovery;

/**
 * Discovers a list of directory entries, optionally filtered by type.
 *
 * A missing directory yields NULL - nothing to discover, so the field keeps
 * its default - while an existing empty directory yields an empty list, a
 * genuine discovery of "no entries".
 *
 * @package DrevOps\Tui\Discovery
 */
class Scan extends AbstractDiscover {

  /**
   * Construct a scan discovery rule.
   *
   * @param string $dir
   *   The directory to scan, relative to the project directory.
   * @param \DrevOps\Tui\Discovery\ScanType $type
   *   The entry type to keep.
   */
  public function __construct(public readonly string $dir, public readonly ScanType $type = ScanType::Any) {
  }

  /**
   * {@inheritdoc}
   */
  public function discover(string $directory): mixed {
    $full = $this->join($directory, $this->dir);

    if (!is_dir($full)) {
      return NULL;
    }

    $entries = scandir($full);
    // @codeCoverageIgnoreStart
    if ($entries === FALSE) {
      return NULL;
    }
    // @codeCoverageIgnoreEnd
    $out = [];

    foreach ($entries as $entry) {
      if ($entry === '.') {
        continue;
      }

      if ($entry === '..') {
        continue;
      }

      $path = $full . '/' . $entry;

      if ($this->type === ScanType::Dir && !is_dir($path)) {
        continue;
      }
      if ($this->type === ScanType::File && !is_file($path)) {
        continue;
      }

      $out[] = $entry;
    }

    sort($out);

    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return ['scan' => ['dir' => $this->dir, 'type' => $this->type->value]];
  }

}
