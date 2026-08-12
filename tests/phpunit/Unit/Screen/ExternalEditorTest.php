<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Screen;

use DrevOps\PhpTui\Screen\ExternalEditor;
use DrevOps\PhpTui\Tests\Fixtures\Screen\SpawnStubEditor;
use DrevOps\PhpTui\Tests\Fixtures\Terminal\RecordingTerminal;
use DrevOps\PhpTui\Tests\Traits\IsolatesEnvTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the external editor service.
 */
#[CoversClass(ExternalEditor::class)]
#[Group('screen')]
final class ExternalEditorTest extends TestCase {

  use IsolatesEnvTrait;

  protected function tearDown(): void {
    $this->restoreEnv();
    parent::tearDown();
  }

  #[DataProvider('dataProviderCommand')]
  public function testCommand(?string $visual, ?string $editor, ?string $expected): void {
    $this->putEnv('VISUAL', $visual);
    $this->putEnv('EDITOR', $editor);

    $this->assertSame($expected, (new ExternalEditor())->command());
  }

  public static function dataProviderCommand(): \Iterator {
    yield 'visual wins over editor' => ['vim', 'nano', 'vim'];
    yield 'editor is the fallback' => [NULL, 'nano', 'nano'];
    yield 'neither set' => [NULL, NULL, NULL];
    yield 'blank visual falls through to editor' => ['   ', 'nano', 'nano'];
    yield 'surrounding whitespace trimmed' => [NULL, '  nano  ', 'nano'];
    yield 'all blank is none' => ['', '  ', NULL];
  }

  public function testIsAvailable(): void {
    $this->putEnv('VISUAL', NULL);
    $this->putEnv('EDITOR', 'vi');
    $this->assertTrue((new ExternalEditor())->isAvailable());

    $this->putEnv('EDITOR', NULL);
    $this->assertFalse((new ExternalEditor())->isAvailable());
  }

  public function testEditReturnsNullWhenNoEditor(): void {
    $this->putEnv('VISUAL', NULL);
    $this->putEnv('EDITOR', NULL);

    $this->assertNull((new ExternalEditor())->edit('seed'));
  }

  public function testEditReturnsNullWhenTempFileFails(): void {
    $this->putEnv('EDITOR', 'vi');

    $editor = new class extends ExternalEditor {

      #[\Override]
      protected function tempFile(): ?string {
        return NULL;
      }

      #[\Override]
      protected function spawn(string $command, string $file): int {
        throw new \RuntimeException('the editor must not launch when no temp file exists');
      }

    };

    $this->assertNull($editor->edit('seed'));
  }

  public function testEditReturnsNullWhenSeedWriteFails(): void {
    $this->putEnv('EDITOR', 'vi');

    $editor = new class extends ExternalEditor {

      #[\Override]
      protected function seed(string $file, string $initial): bool {
        return FALSE;
      }

      #[\Override]
      protected function spawn(string $command, string $file): int {
        throw new \RuntimeException('the editor must not launch when the seed write fails');
      }

    };

    $this->assertNull($editor->edit('seed'));
  }

  public function testEditCapturesSavedBufferAndRestoresTerminal(): void {
    $this->putEnv('EDITOR', 'vi');

    $editor = new SpawnStubEditor('edited value', 0);
    $terminal = new RecordingTerminal();

    $this->assertSame('edited value', $editor->edit('seed', $terminal));
    $this->assertSame('seed', $editor->seenInitial);
    $this->assertTrue($terminal->restored, 'the terminal was suspended');
    $this->assertTrue($terminal->resumed, 'the terminal was restored');
    $this->assertFileDoesNotExist($editor->seenFile);
  }

  public function testEditWorksWithoutTerminal(): void {
    $this->putEnv('EDITOR', 'vi');

    $editor = new SpawnStubEditor('no terminal', 0);

    $this->assertSame('no terminal', $editor->edit('seed'));
    $this->assertFileDoesNotExist($editor->seenFile);
  }

  public function testEditReturnsNullOnNonZeroExit(): void {
    $this->putEnv('EDITOR', 'vi');

    $editor = new SpawnStubEditor('ignored', 1);
    $terminal = new RecordingTerminal();

    $this->assertNull($editor->edit('seed', $terminal));
    $this->assertTrue($terminal->resumed, 'the terminal is restored even on abort');
    $this->assertFileDoesNotExist($editor->seenFile);
  }

  public function testEditReturnsNullWhenEditorDeletesTheFile(): void {
    $this->putEnv('EDITOR', 'vi');

    $this->assertNull((new SpawnStubEditor(NULL, 0, TRUE))->edit('seed'));
  }

  #[DataProvider('dataProviderEditNormalizesTrailingNewline')]
  public function testEditNormalizesTrailingNewline(string $saved, string $expected): void {
    $this->putEnv('EDITOR', 'vi');

    $this->assertSame($expected, (new SpawnStubEditor($saved, 0))->edit('seed'));
  }

  public static function dataProviderEditNormalizesTrailingNewline(): \Iterator {
    yield 'single trailing lf dropped' => ["one\ntwo\n", "one\ntwo"];
    yield 'trailing crlf dropped' => ["text\r\n", 'text'];
    yield 'only one trailing newline dropped' => ["text\n\n", "text\n"];
    yield 'interior newlines preserved' => ["a\n\nb\n", "a\n\nb"];
    yield 'no trailing newline unchanged' => ['plain', 'plain'];
    yield 'empty stays empty' => ['', ''];
  }

}
