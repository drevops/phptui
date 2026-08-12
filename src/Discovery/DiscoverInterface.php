<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Discovery;

/**
 * A declarative discovery rule evaluated against a project directory.
 *
 * @package DrevOps\PhpTui\Discovery
 */
interface DiscoverInterface {

  /**
   * Detect a value within a directory.
   *
   * @param string $directory
   *   The project directory to inspect.
   *
   * @return mixed
   *   The detected value, or NULL when nothing was found.
   */
  public function discover(string $directory): mixed;

  /**
   * The rule as the raw array shape used by the JSON schema.
   *
   * @return array<string,mixed>
   *   The raw rule.
   */
  public function toArray(): array;

}
