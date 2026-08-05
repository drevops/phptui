<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Fixtures\Screen;

use DrevOps\Tui\Screen\ExternalEditor;

/**
 * An external editor whose spawn records inputs and simulates a save.
 */
class SpawnStubEditor extends ExternalEditor {

  /**
   * The buffer the spawn saw seeded in the file.
   */
  public string $seenInitial = '';

  /**
   * The temp file path the spawn saw.
   */
  public string $seenFile = '';

  /**
   * Construct the stub.
   *
   * @param string|null $writeBack
   *   The content the spawn writes to the file, or NULL to leave it as seeded.
   * @param int $code
   *   The exit code the spawn returns.
   * @param bool $deleteFile
   *   Whether the spawn removes the file instead of saving.
   */
  public function __construct(
    protected ?string $writeBack,
    protected int $code,
    protected bool $deleteFile = FALSE,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function spawn(string $command, string $file): int {
    $this->seenInitial = (string) file_get_contents($file);
    $this->seenFile = $file;

    if ($this->deleteFile) {
      unlink($file);
    }
    elseif ($this->writeBack !== NULL) {
      file_put_contents($file, $this->writeBack);
    }

    return $this->code;
  }

}
