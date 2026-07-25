<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Model;

use DrevOps\Tui\Model\FilePickerConstraints;
use DrevOps\Tui\Model\FilePickerMode;
use DrevOps\Tui\Model\FormException;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the file picker constraints value object.
 */
#[CoversClass(FilePickerConstraints::class)]
#[Group('model')]
final class FilePickerConstraintsTest extends TestCase {

  /**
   * The virtual filesystem root the path checks run against.
   */
  protected string $root;

  protected function setUp(): void {
    parent::setUp();
    vfsStream::setup('root', NULL, [
      'small.txt' => str_repeat('a', 10),
      'big.txt' => str_repeat('a', 5000),
      'photo.jpg' => str_repeat('a', 10),
      'sub' => [],
    ]);
    $this->root = vfsStream::url('root');
  }

  /**
   * Tests that declared extensions are normalized to dot-less lowercase.
   *
   * @param list<string> $given
   *   The declared extensions.
   * @param list<string> $expected
   *   The normalized extensions.
   */
  #[DataProvider('dataProviderNormalizesExtensions')]
  public function testNormalizesExtensions(array $given, array $expected): void {
    $this->assertSame($expected, (new FilePickerConstraints(extensions: $given))->extensions);
  }

  public static function dataProviderNormalizesExtensions(): \Iterator {
    yield 'already normal' => [['md', 'txt'], ['md', 'txt']];
    yield 'strips dots and lowercases' => [['.MD', 'Yml'], ['md', 'yml']];
    yield 'drops empties and re-indexes' => [['md', '', '.'], ['md']];
    yield 'none' => [[], []];
  }

  #[DataProvider('dataProviderRejectsMaxSizeBelowOne')]
  public function testRejectsMaxSizeBelowOne(int $max_size): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage(sprintf('File picker constraints declare a maximum size of %d below one byte.', $max_size));

    new FilePickerConstraints(maxSize: $max_size);
  }

  public static function dataProviderRejectsMaxSizeBelowOne(): \Iterator {
    yield 'zero' => [0];
    yield 'negative' => [-100];
  }

  /**
   * Tests whether the constraints report themselves as unconstrained.
   *
   * @param \DrevOps\Tui\Model\FilePickerMode $mode
   *   The selection mode.
   * @param list<string> $extensions
   *   The allowed extensions.
   * @param int|null $max_size
   *   The maximum file size in bytes, or NULL for no size limit.
   * @param bool $expected
   *   Whether the constraints enforce nothing.
   */
  #[DataProvider('dataProviderIsUnconstrained')]
  public function testIsUnconstrained(FilePickerMode $mode, array $extensions, ?int $max_size, bool $expected): void {
    $this->assertSame($expected, (new FilePickerConstraints($mode, $extensions, $max_size))->isUnconstrained());
  }

  public static function dataProviderIsUnconstrained(): \Iterator {
    yield 'nothing set' => [FilePickerMode::Any, [], NULL, TRUE];
    yield 'mode set' => [FilePickerMode::File, [], NULL, FALSE];
    yield 'extensions set' => [FilePickerMode::Any, ['md'], NULL, FALSE];
    yield 'max size set' => [FilePickerMode::Any, [], 100, FALSE];
  }

  #[DataProvider('dataProviderAllowsType')]
  public function testAllowsType(FilePickerMode $mode, bool $is_dir, bool $expected): void {
    $this->assertSame($expected, (new FilePickerConstraints($mode))->allowsType($is_dir));
  }

  public static function dataProviderAllowsType(): \Iterator {
    yield 'any allows dir' => [FilePickerMode::Any, TRUE, TRUE];
    yield 'any allows file' => [FilePickerMode::Any, FALSE, TRUE];
    yield 'file rejects dir' => [FilePickerMode::File, TRUE, FALSE];
    yield 'file allows file' => [FilePickerMode::File, FALSE, TRUE];
    yield 'directory allows dir' => [FilePickerMode::Directory, TRUE, TRUE];
    yield 'directory rejects file' => [FilePickerMode::Directory, FALSE, FALSE];
  }

  /**
   * Tests whether a filename's extension passes the allowed set.
   *
   * @param list<string> $extensions
   *   The allowed extensions.
   * @param string $name
   *   The entry name or path.
   * @param bool $expected
   *   Whether the extension is allowed.
   */
  #[DataProvider('dataProviderExtensionAllowed')]
  public function testExtensionAllowed(array $extensions, string $name, bool $expected): void {
    $this->assertSame($expected, (new FilePickerConstraints(extensions: $extensions))->extensionAllowed($name));
  }

  public static function dataProviderExtensionAllowed(): \Iterator {
    yield 'no limit allows any' => [[], 'util.php', TRUE];
    yield 'matches' => [['md'], 'readme.md', TRUE];
    yield 'case insensitive' => [['md'], 'README.MD', TRUE];
    yield 'does not match' => [['md'], 'util.php', FALSE];
    yield 'no extension against a limit' => [['md'], 'LICENSE', FALSE];
  }

  public function testViolationUnconstrainedPassesAnything(): void {
    // An unconstrained picker enforces nothing, not even existence.
    $this->assertNull((new FilePickerConstraints())->violation($this->root . '/nope'));
  }

  public function testViolationFileMode(): void {
    $constraints = new FilePickerConstraints(FilePickerMode::File);

    $this->assertNull($constraints->violation($this->root . '/small.txt'));
    $this->assertSame('an existing file', $constraints->violation($this->root . '/sub'));
    $this->assertSame('an existing file', $constraints->violation($this->root . '/nope'));
  }

  public function testViolationDirectoryMode(): void {
    $constraints = new FilePickerConstraints(FilePickerMode::Directory);

    $this->assertNull($constraints->violation($this->root . '/sub'));
    $this->assertSame('an existing directory', $constraints->violation($this->root . '/small.txt'));
    $this->assertSame('an existing directory', $constraints->violation($this->root . '/nope'));
  }

  public function testViolationAnyModeExtension(): void {
    $constraints = new FilePickerConstraints(extensions: ['jpg']);

    $this->assertNull($constraints->violation($this->root . '/photo.jpg'));
    $this->assertSame('a file with a permitted extension (jpg)', $constraints->violation($this->root . '/small.txt'));
    // A directory has no extension, so the extension limit does not touch it.
    $this->assertNull($constraints->violation($this->root . '/sub'));
    // A missing path fails the existence baseline before extension.
    $this->assertSame('an existing path', $constraints->violation($this->root . '/nope'));
  }

  public function testViolationMaxSize(): void {
    $constraints = new FilePickerConstraints(maxSize: 100);

    $this->assertNull($constraints->violation($this->root . '/small.txt'));
    $this->assertSame('a file no larger than 100 B', $constraints->violation($this->root . '/big.txt'));
    // A directory has no size to weigh, so the size limit does not touch it.
    $this->assertNull($constraints->violation($this->root . '/sub'));
  }

  public function testViolationChecksExistenceBeforeExtensionAndSize(): void {
    // With files-only, extension and size all set, a directory fails on the
    // type check first - existence and kind are the baseline.
    $constraints = new FilePickerConstraints(FilePickerMode::File, ['txt'], 5);

    $this->assertSame('an existing file', $constraints->violation($this->root . '/sub'));
    // A file of the wrong extension fails on extension before size.
    $this->assertSame('a file with a permitted extension (txt)', $constraints->violation($this->root . '/photo.jpg'));
    // A right-extension file over the limit fails on size.
    $this->assertSame('a file no larger than 5 B', $constraints->violation($this->root . '/small.txt'));
  }

  #[DataProvider('dataProviderViolationEmptyValuePasses')]
  public function testViolationEmptyValuePasses(mixed $value): void {
    // An empty value is the required check's concern, not this object's.
    $this->assertNull((new FilePickerConstraints(FilePickerMode::File))->violation($value));
  }

  public static function dataProviderViolationEmptyValuePasses(): \Iterator {
    yield 'empty string' => [''];
    yield 'empty list' => [[]];
  }

  public function testViolationListReturnsFirstOffender(): void {
    $constraints = new FilePickerConstraints(maxSize: 100);

    $this->assertNull($constraints->violation([$this->root . '/small.txt', $this->root . '/photo.jpg']));
    $this->assertSame('a file no larger than 100 B', $constraints->violation([$this->root . '/small.txt', $this->root . '/big.txt']));
  }

  public function testViolationListSkipsNonStringAndEmptyItems(): void {
    $constraints = new FilePickerConstraints(FilePickerMode::File);

    // A non-string or empty item carries no path to weigh and is skipped.
    $this->assertNull($constraints->violation([$this->root . '/small.txt', '', 42]));
  }

  /**
   * Tests the human phrase describing the active limits.
   *
   * @param \DrevOps\Tui\Model\FilePickerMode $mode
   *   The selection mode.
   * @param list<string> $extensions
   *   The allowed extensions.
   * @param int|null $max_size
   *   The maximum file size in bytes, or NULL for no size limit.
   * @param string $expected
   *   The expected phrase.
   */
  #[DataProvider('dataProviderDescribe')]
  public function testDescribe(FilePickerMode $mode, array $extensions, ?int $max_size, string $expected): void {
    $this->assertSame($expected, (new FilePickerConstraints($mode, $extensions, $max_size))->describe());
  }

  public static function dataProviderDescribe(): \Iterator {
    yield 'unconstrained' => [FilePickerMode::Any, [], NULL, ''];
    yield 'files only' => [FilePickerMode::File, [], NULL, 'Files only.'];
    yield 'directories only' => [FilePickerMode::Directory, [], NULL, 'Directories only.'];
    yield 'extensions' => [FilePickerMode::Any, ['jpg', 'png'], NULL, 'Extensions: jpg, png.'];
    yield 'max size' => [FilePickerMode::Any, [], 2097152, 'Max 2 MB.'];
    yield 'all limits' => [FilePickerMode::File, ['jpg'], 2097152, 'Files only. Extensions: jpg. Max 2 MB.'];
  }

  #[DataProvider('dataProviderFormatBytes')]
  public function testFormatBytes(int $bytes, string $expected): void {
    $this->assertSame($expected, FilePickerConstraints::formatBytes($bytes));
  }

  public static function dataProviderFormatBytes(): \Iterator {
    yield 'bytes' => [500, '500 B'];
    yield 'whole kilobytes' => [1024, '1 KB'];
    yield 'fractional kilobytes' => [1536, '1.5 KB'];
    yield 'megabytes' => [2097152, '2 MB'];
    // Beyond the largest unit the scale stops, so the value stays in that unit.
    yield 'above the largest unit' => [1125899906842624, '1024 TB'];
  }

}
