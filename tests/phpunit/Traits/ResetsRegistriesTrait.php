<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Traits;

/**
 * Snapshots a class's static registry and restores it after the test.
 *
 * The theme and key-map managers hold process-wide `$registry` maps; a test
 * that registers an entry snapshots the registry first and restores it from
 * its tearDown() so later tests never see the leaked entry.
 */
trait ResetsRegistriesTrait {

  /**
   * The registry snapshots, keyed by owning class.
   *
   * @var array<class-string,mixed>
   */
  protected array $registrySnapshots = [];

  /**
   * Snapshot a class's static registry before mutating it.
   *
   * @param class-string $class
   *   The class owning a static `$registry` property.
   */
  protected function snapshotRegistry(string $class): void {
    if (!array_key_exists($class, $this->registrySnapshots)) {
      $this->registrySnapshots[$class] = (new \ReflectionProperty($class, 'registry'))->getValue();
    }
  }

  /**
   * Restore every snapshotted registry.
   */
  protected function restoreRegistries(): void {
    foreach ($this->registrySnapshots as $class => $value) {
      (new \ReflectionProperty($class, 'registry'))->setValue(NULL, $value);
    }

    $this->registrySnapshots = [];
  }

}
