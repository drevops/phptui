<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Field;

use DrevOps\Tui\Block\Legend;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Input\ScopedKeyMap;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\FilePickerConstraints;
use DrevOps\Tui\Model\FilePickerMode;
use DrevOps\Tui\Model\SelectionBounds;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\FieldRunner;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Field\AbstractField;
use DrevOps\Tui\Field\Capability\PagingCapableTrait;
use DrevOps\Tui\Field\Capability\SelectionBoundedTrait;
use DrevOps\Tui\Field\FilePicker;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the file picker field.
 */
#[CoversClass(FilePicker::class)]
#[CoversClass(AbstractField::class)]
#[CoversTrait(PagingCapableTrait::class)]
#[CoversTrait(SelectionBoundedTrait::class)]
#[Group('field')]
final class FilePickerTest extends TestCase {

  /**
   * The virtual start directory.
   */
  protected string $root;

  protected function setUp(): void {
    parent::setUp();
    vfsStream::setup('root', NULL, [
      'docs' => ['guide.md' => '', 'intro.txt' => ''],
      'src' => [
        'Theme' => ['Ocean.php' => ''],
        'Utils' => ['Foo.php' => '', 'Bar.php' => ''],
        'readme.md' => '',
        'util.php' => '',
      ],
      'empty' => [],
      '.hidden' => ['secret.txt' => ''],
      '.env' => '',
      'README.md' => '',
      'composer.json' => '',
    ]);
    $this->root = vfsStream::url('root');
  }

  public function testOpensAtStartDirectoriesFirst(): void {
    $field = new FilePicker($this->root);

    // The first entry is the first directory, sorted case-insensitively.
    $this->assertSame($this->root . '/docs', $field->value());

    $view = $this->render($field);
    $this->assertStringContainsString('docs/', $view);
    $this->assertStringContainsString('README.md', $view);
    // Hidden entries stay out of sight until revealed.
    $this->assertStringNotContainsString('.env', $view);
    $this->assertStringNotContainsString('.hidden', $view);
  }

  public function testDescendAndAscend(): void {
    $field = new FilePicker($this->root);

    $field->handle(Key::named(KeyName::Down));
    $field->handle(Key::named(KeyName::Down));
    $this->assertSame($this->root . '/src', $field->value());

    $field->handle(Key::named(KeyName::Right));
    // Inside src the first entry is the Theme directory.
    $this->assertSame($this->root . '/src/Theme', $field->value());

    // Ascending returns to the parent with the directory just left highlighted.
    $field->handle(Key::named(KeyName::Left));
    $this->assertSame($this->root . '/src', $field->value());
  }

  public function testCannotAscendAboveStart(): void {
    $field = new FilePicker($this->root);

    $field->handle(Key::named(KeyName::Left));
    $field->handle(Key::named(KeyName::Left));

    $this->assertSame($this->root . '/docs', $field->value());
  }

  public function testRightOnFileDoesNotDescend(): void {
    // README.md is the first file; highlight it, then Right is a no-op.
    $field = new FilePicker($this->root, constraints: new FilePickerConstraints(FilePickerMode::File));

    // Files-only lists directories (navigable) then files.
    $field->handle(Key::named(KeyName::Down));
    $field->handle(Key::named(KeyName::Down));
    $field->handle(Key::named(KeyName::Down));
    $this->assertSame($this->root . '/composer.json', $field->value());

    $field->handle(Key::named(KeyName::Right));
    $this->assertSame($this->root . '/composer.json', $field->value());
  }

  public function testAnyModeEnterOnDirectorySelectsIt(): void {
    $field = new FilePicker($this->root);

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame($this->root . '/docs', $value);
    $this->assertTrue($field->isComplete());
  }

  public function testAnyModeSelectFileAfterDescending(): void {
    $field = new FilePicker($this->root);

    $value = FieldRunner::run($field, ArrayKeyStream::of(
      Key::named(KeyName::Right),
      Key::named(KeyName::Enter),
    ));

    // Right descends into docs; Enter accepts its first file.
    $this->assertSame($this->root . '/docs/guide.md', $value);
  }

  public function testFileModeEnterOnDirectoryDescends(): void {
    $field = new FilePicker($this->root, constraints: new FilePickerConstraints(FilePickerMode::File));

    $value = FieldRunner::run($field, ArrayKeyStream::of(
      Key::named(KeyName::Enter),
      Key::named(KeyName::Enter),
    ));

    // The first Enter descends into docs (a directory is not selectable);
    // the second accepts its first file.
    $this->assertSame($this->root . '/docs/guide.md', $value);
  }

  public function testDirectoryModeHidesFilesAndSelectsDirectory(): void {
    $field = new FilePicker($this->root, constraints: new FilePickerConstraints(FilePickerMode::Directory));

    $view = $this->render($field);
    $this->assertStringContainsString('docs/', $view);
    // Files are hidden entirely in directory mode.
    $this->assertStringNotContainsString('README.md', $view);
    $this->assertStringNotContainsString('composer.json', $view);

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Enter)));
    $this->assertSame($this->root . '/docs', $value);
  }

  public function testExtensionFilterLimitsFiles(): void {
    $field = new FilePicker($this->root, constraints: new FilePickerConstraints(FilePickerMode::File, ['MD']));

    // Descend into src (docs, empty, src -> src is third).
    $field->handle(Key::named(KeyName::Down));
    $field->handle(Key::named(KeyName::Down));
    $field->handle(Key::named(KeyName::Right));

    $view = $this->render($field);
    // Directories stay navigable; only .md files pass the (case-insensitive)
    // extension filter, so util.php is filtered out.
    $this->assertStringContainsString('Theme/', $view);
    $this->assertStringContainsString('readme.md', $view);
    $this->assertStringNotContainsString('util.php', $view);
  }

  public function testTabTogglesHiddenEntries(): void {
    $field = new FilePicker($this->root);

    $this->assertStringNotContainsString('.env', $this->render($field));

    $field->handle(Key::named(KeyName::Tab));

    $view = $this->render($field);
    $this->assertStringContainsString('.env', $view);
    $this->assertStringContainsString('.hidden/', $view);
  }

  public function testTypeToFilterNarrowsEntries(): void {
    $field = new FilePicker($this->root);

    foreach (str_split('read') as $char) {
      $field->handle(Key::char($char));
    }

    // Only README.md contains "read".
    $this->assertSame('read', $field->filter());
    $this->assertSame($this->root . '/README.md', $field->value());
    $this->assertStringContainsString('README.md', $this->render($field));

    // Clearing the filter restores the full listing.
    foreach (range(1, 4) as $ignored) {
      $field->handle(Key::named(KeyName::Backspace));
    }
    $this->assertSame($this->root . '/docs', $field->value());
  }

  public function testTypeToFilterFoldsCaseBeyondAscii(): void {
    vfsStream::setup('accents', NULL, ['Äpfel.md' => '', 'pears.md' => '']);
    $field = new FilePicker(vfsStream::url('accents'));

    $field->handle(Key::char('ä'));

    // A lowercase non-ASCII query matches its uppercase entry, which a
    // byte-level fold would miss.
    $this->assertSame(vfsStream::url('accents') . '/Äpfel.md', $field->value());
    $this->assertStringNotContainsString('pears.md', $this->render($field));
  }

  public function testBackspaceAscendsWhenFilterEmpty(): void {
    $field = new FilePicker($this->root);

    $field->handle(Key::named(KeyName::Down));
    $field->handle(Key::named(KeyName::Down));
    $field->handle(Key::named(KeyName::Right));
    $this->assertSame($this->root . '/src/Theme', $field->value());

    $field->handle(Key::named(KeyName::Backspace));
    $this->assertSame($this->root . '/src', $field->value());
  }

  public function testMultipleTogglesAndAccepts(): void {
    $field = new FilePicker($this->root, multiple: TRUE);

    $field->handle(Key::named(KeyName::Space));
    $this->assertSame([$this->root . '/docs'], $field->value());

    $field->handle(Key::named(KeyName::Down));
    $field->handle(Key::named(KeyName::Down));
    $field->handle(Key::named(KeyName::Space));
    $this->assertSame([$this->root . '/docs', $this->root . '/src'], $field->value());

    // Toggling an already-selected entry removes it.
    $field->handle(Key::named(KeyName::Space));
    $this->assertSame([$this->root . '/docs'], $field->value());

    $field->handle(Key::named(KeyName::Enter));
    $this->assertTrue($field->isComplete());
    $this->assertSame([$this->root . '/docs'], $field->value());
  }

  public function testMultipleAccumulatesAcrossDirectories(): void {
    $field = new FilePicker($this->root, multiple: TRUE);

    // Select the docs directory, then descend into src and select Theme.
    $field->handle(Key::named(KeyName::Space));
    $field->handle(Key::named(KeyName::Down));
    $field->handle(Key::named(KeyName::Down));
    $field->handle(Key::named(KeyName::Right));
    $field->handle(Key::named(KeyName::Space));

    $this->assertSame([$this->root . '/docs', $this->root . '/src/Theme'], $field->value());
  }

  public function testMultipleSpaceIgnoresNonSelectableDirectory(): void {
    $field = new FilePicker($this->root, constraints: new FilePickerConstraints(FilePickerMode::File), multiple: TRUE);
    $theme = new DefaultTheme(76, ['unicode' => FALSE, 'color' => FALSE]);

    // The first entry is a directory, which files-only mode cannot select.
    $field->handle(Key::named(KeyName::Space));
    $this->assertSame([], $field->value());

    // Selectable files carry a checkbox; navigable directories carry a spacer.
    $view = $field->view($theme);
    $this->assertStringContainsString('[ ] README.md', $view);
    $this->assertStringContainsString('docs/', $view);
  }

  public function testMultipleSpaceInEmptyDirectoryIsSafe(): void {
    $field = new FilePicker($this->root, multiple: TRUE);

    $field->handle(Key::named(KeyName::Down));
    $field->handle(Key::named(KeyName::Right));
    $field->handle(Key::named(KeyName::Space));

    $this->assertSame([], $field->value());
  }

  public function testSeedWithMissingBasenameHighlightsTop(): void {
    // A default under the start whose entry does not exist opens at the start
    // directory with the top entry highlighted.
    $field = new FilePicker($this->root, $this->root . '/nope.txt');

    $this->assertSame($this->root . '/docs', $field->value());
  }

  public function testRootBreadcrumb(): void {
    $field = new FilePicker('/');

    $lines = explode("\n", Ansi::strip($field->view(new DefaultTheme())));
    $this->assertSame('/', $lines[0]);
  }

  public function testNonexistentStartIsEmpty(): void {
    $field = new FilePicker($this->root . '/missing');

    $this->assertSame('', $field->value());
    $this->assertStringContainsString('(empty)', $this->render($field));
  }

  public function testMultipleSeedsSelectionFromDefault(): void {
    $field = new FilePicker($this->root, [$this->root . '/README.md'], multiple: TRUE);

    $this->assertSame([$this->root . '/README.md'], $field->value());
    // The browser opens at the seeded path's directory with it highlighted.
    $this->assertStringContainsString('README.md', $this->render($field));
  }

  public function testSingleSeededDefaultOpensAtItsDirectory(): void {
    $field = new FilePicker($this->root, $this->root . '/src/readme.md');

    $this->assertSame($this->root . '/src/readme.md', $field->value());
    // The breadcrumb reflects the opened sub-directory.
    $this->assertStringContainsString('root/src', $this->render($field));
  }

  public function testSeedIgnoredWhenOutsideStart(): void {
    $field = new FilePicker($this->root, '/somewhere/else.txt');

    // A default outside the start directory is ignored; the browser opens at
    // the start.
    $this->assertSame($this->root . '/docs', $field->value());
  }

  public function testEmptyDirectory(): void {
    $field = new FilePicker($this->root);

    // Highlight and descend into the empty directory.
    $field->handle(Key::named(KeyName::Down));
    $this->assertSame($this->root . '/empty', $field->value());

    $field->handle(Key::named(KeyName::Right));
    $this->assertSame('', $field->value());
    $this->assertStringContainsString('(empty)', $this->render($field));

    // Moving, descending and accepting in an empty directory are all no-ops.
    $field->handle(Key::named(KeyName::Down));
    $field->handle(Key::named(KeyName::Right));
    $field->handle(Key::named(KeyName::Enter));
    $this->assertFalse($field->isComplete());
    $this->assertSame('', $field->value());
  }

  public function testCancel(): void {
    $field = new FilePicker($this->root);

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Escape)));

    $this->assertNull($value);
    $this->assertTrue($field->isCancelled());
  }

  public function testAsciiRendering(): void {
    $field = new FilePicker($this->root);
    $theme = new DefaultTheme(76, ['unicode' => FALSE, 'color' => FALSE]);

    $view = $field->view($theme);

    // The cursor row carries the ASCII marker; directories carry a slash.
    $this->assertStringContainsString('> docs/', $view);
    $this->assertStringContainsString('src/', $view);
  }

  public function testMultipleAsciiCheckboxes(): void {
    $field = new FilePicker($this->root, multiple: TRUE);
    $theme = new DefaultTheme(76, ['unicode' => FALSE, 'color' => FALSE]);

    $this->assertStringContainsString('[ ] docs/', $field->view($theme));

    $field->handle(Key::named(KeyName::Space));
    $this->assertStringContainsString('[x] docs/', $field->view($theme));
  }

  public function testHintsRenderPerMode(): void {
    $theme = new DefaultTheme(76, ['unicode' => FALSE, 'color' => FALSE]);

    // A single picker binds no toggle key, so that fragment drops and Accept
    // reads "select"; the browse and hidden fragments are always present.
    $single = Ansi::strip($this->legendOf($theme, KeyMapManager::create()->forField(FieldType::FilePicker), ...(new FilePicker($this->root))->hints()));
    $this->assertStringNotContainsString('SPACE to select', $single);
    $this->assertStringContainsString('open', $single);
    $this->assertStringContainsString('TAB to show hidden', $single);

    // Multiple mode leads with the toggle key and Accept reads "accept".
    $multiple = Ansi::strip($this->legendOf($theme, KeyMapManager::create()->forField(FieldType::FilePicker, TRUE), ...(new FilePicker($this->root, multiple: TRUE))->hints()));
    $this->assertStringContainsString('SPACE to select', $multiple);
    $this->assertStringContainsString('accept', $multiple);
  }

  public function testScrollsLargeDirectory(): void {
    $files = [];
    foreach (range(0, 29) as $index) {
      $files[sprintf('file%02d.txt', $index)] = '';
    }
    vfsStream::setup('big', NULL, $files);
    $field = new FilePicker(vfsStream::url('big'));
    $theme = new DefaultTheme(76, ['color' => FALSE]);

    $top = $field->view($theme);
    $this->assertStringContainsString('file00.txt', $top);
    $this->assertStringNotContainsString('file29.txt', $top);
    // A window that clips below shows the down indicator only.
    $this->assertStringContainsString('▼', $top);
    $this->assertStringNotContainsString('▲', $top);

    foreach (range(1, 29) as $ignored) {
      $field->handle(Key::named(KeyName::Down));
    }

    $bottom = $field->view($theme);
    $this->assertStringContainsString('file29.txt', $bottom);
    $this->assertStringNotContainsString('file00.txt', $bottom);
    $this->assertStringContainsString('▲', $bottom);
  }

  public function testValueReflectsHighlightBeforeAccept(): void {
    $field = new FilePicker($this->root);

    // Before acceptance the value tracks the highlighted entry.
    $this->assertSame($this->root . '/docs', $field->value());

    $field->handle(Key::named(KeyName::Down));
    $this->assertSame($this->root . '/empty', $field->value());

    // Moving back up restores the earlier highlight.
    $field->handle(Key::named(KeyName::Up));
    $this->assertSame($this->root . '/docs', $field->value());
  }

  public function testDefaultsToWorkingDirectoryWhenStartEmpty(): void {
    $field = new class($this->root . '/docs') extends FilePicker {

      public function __construct(protected string $directory) {
        parent::__construct('');
      }

      #[\Override]
      protected function currentDirectory(): string {
        return $this->directory;
      }

    };

    // With no start the browser roots at the current working directory, so
    // the breadcrumb is its basename and its entries are listed.
    $view = $this->render($field);
    $this->assertStringContainsString('docs', $view);
    $this->assertStringContainsString('guide.md', $view);
  }

  public function testMultipleRejectsBelowMinWithInlineError(): void {
    $field = new FilePicker($this->root, multiple: TRUE, selection_bounds: new SelectionBounds(2));

    // Selecting one entry is below the minimum of two.
    $field->handle(Key::named(KeyName::Space));
    $field->handle(Key::named(KeyName::Enter));

    $this->assertFalse($field->isComplete());
    $this->assertStringContainsString('Select at least 2 items.', $this->render($field));
  }

  public function testMultipleAcceptsWithinBounds(): void {
    $field = new FilePicker($this->root, multiple: TRUE, selection_bounds: new SelectionBounds(1, 2));

    $field->handle(Key::named(KeyName::Space));
    $field->handle(Key::named(KeyName::Enter));

    $this->assertTrue($field->isComplete());
    $this->assertSame([$this->root . '/docs'], $field->value());
  }

  public function testMultipleSelectionHintShownBelowEntries(): void {
    $field = new FilePicker($this->root, multiple: TRUE, selection_bounds: new SelectionBounds(2, 3));

    // The active limit is surfaced, capitalized, below the entries.
    $this->assertStringContainsString('Select between 2 and 3 items.', $this->render($field));
  }

  public function testRejectsOversizeFileWithInlineError(): void {
    vfsStream::setup('sized', NULL, ['big.txt' => str_repeat('a', 200), 'tiny.txt' => str_repeat('a', 10)]);
    $root = vfsStream::url('sized');
    $field = new FilePicker($root, constraints: new FilePickerConstraints(maxSize: 100));

    // big.txt (200 bytes) is highlighted first and exceeds the 100-byte limit.
    $field->handle(Key::named(KeyName::Enter));

    $this->assertFalse($field->isComplete());
    $this->assertStringContainsString('Choose a file no larger than 100 B.', $this->render($field));
  }

  public function testAcceptsFileWithinSizeLimit(): void {
    vfsStream::setup('sized', NULL, ['tiny.txt' => str_repeat('a', 10)]);
    $root = vfsStream::url('sized');
    $field = new FilePicker($root, constraints: new FilePickerConstraints(maxSize: 100));

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame($root . '/tiny.txt', $value);
    $this->assertTrue($field->isComplete());
  }

  public function testConstraintHintShownBelowEntries(): void {
    $field = new FilePicker($this->root, constraints: new FilePickerConstraints(FilePickerMode::File, ['md'], 2097152));

    // The active limits are surfaced below the entries as a hint.
    $this->assertStringContainsString('Files only. Extensions: md. Max 2 MB.', $this->render($field));
  }

  public function testConstraintHintGivesWayToInlineError(): void {
    vfsStream::setup('sized', NULL, ['big.txt' => str_repeat('a', 200)]);
    $root = vfsStream::url('sized');
    $field = new FilePicker($root, constraints: new FilePickerConstraints(maxSize: 100));

    $field->handle(Key::named(KeyName::Enter));

    $view = $this->render($field);
    // The inline error replaces the persistent hint so the two never stack.
    $this->assertStringContainsString('Choose a file no larger than 100 B.', $view);
    $this->assertStringNotContainsString('Max 100 B.', $view);
  }

  /**
   * Render a field's view with the default theme, stripped of ANSI codes.
   *
   * @param \DrevOps\Tui\Field\FilePicker $field
   *   The field.
   *
   * @return string
   *   The plain-text view.
   */
  protected function render(FilePicker $field): string {
    return Ansi::strip($field->view(new DefaultTheme()));
  }

  /**
   * The legend a set of bindings and hint fragments comes to.
   *
   * @param \DrevOps\Tui\Theme\DefaultTheme $theme
   *   The theme.
   * @param \DrevOps\Tui\Input\ScopedKeyMap $keys
   *   The bindings a key press resolves against.
   * @param \DrevOps\Tui\Input\Hint ...$hints
   *   What those keys do.
   *
   * @return string
   *   The drawn legend.
   */
  protected function legendOf(DefaultTheme $theme, ScopedKeyMap $keys, Hint ...$hints): string {
    return (new Legend())->advertise($keys, ...$hints)->render($theme);
  }

}
