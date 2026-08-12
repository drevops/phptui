<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Traits;

use DrevOps\PhpTui\Translation\Translator;

/**
 * Resets the process-wide translator after each test.
 *
 * The translator backing the t() function is process-wide state, so a test that
 * sets it must clear it or the language would leak into the next test.
 */
trait ResetsTranslatorTrait {

  /**
   * Clear the shared translator so no language leaks between tests.
   */
  protected function tearDown(): void {
    Translator::setShared(NULL);

    parent::tearDown();
  }

}
