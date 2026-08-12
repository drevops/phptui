<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Traits;

/**
 * Sets environment variables for a test and restores them afterwards.
 *
 * A test calls putEnv() for each variable it touches; the adopting class
 * calls restoreEnv() from its tearDown() so every touched variable returns
 * to its pre-test value (or unset state) whatever the test outcome.
 */
trait IsolatesEnvTrait {

  /**
   * The original values of the touched variables, FALSE for unset ones.
   *
   * @var array<string,string|false>
   */
  protected array $envSnapshot = [];

  /**
   * Set (or unset) an env variable, recording its original for restoration.
   *
   * @param string $name
   *   The variable name.
   * @param string|null $value
   *   The value, or NULL to unset.
   */
  protected function putEnv(string $name, ?string $value): void {
    if (!array_key_exists($name, $this->envSnapshot)) {
      $this->envSnapshot[$name] = getenv($name);
    }

    putenv($value === NULL ? $name : $name . '=' . $value);
  }

  /**
   * Restore every variable touched through putEnv().
   */
  protected function restoreEnv(): void {
    foreach ($this->envSnapshot as $name => $value) {
      putenv($value === FALSE ? $name : $name . '=' . $value);
    }

    $this->envSnapshot = [];
  }

}
