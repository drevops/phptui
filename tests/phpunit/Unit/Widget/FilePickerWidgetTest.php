<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Widget;

use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\FilePickerMode;
use DrevOps\Tui\Model\SelectionBounds;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\WidgetRunner;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\AbstractWidget;
use DrevOps\Tui\Widget\FilePickerWidget;
use DrevOps\Tui\Widget\Capability\PagingCapableTrait;
use DrevOps\Tui\Widget\Capability\SelectionBoundedTrait;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the file picker widget.
 */
#[CoversClass(FilePickerWidget::class)]
#[CoversClass(AbstractWidget::class)]
#[CoversClass(PagingCapableTrait::class)]
#[CoversClass(SelectionBoundedTrait::class)]
#[Group('widget')]
final class FilePickerWidgetTest extends TestCase {

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
        'Widget' => ['Foo.php' => '', 'Bar.php' => ''],
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
    $widget = new FilePickerWidget($this->root);

    // The first entry is the first directory, sorted case-insensitively.
    $this->assertSame($this->root . '/docs', $widget->value());

    $view = $this->render($widget);
    $this->assertStringContainsString('docs/', $view);
    $this->assertStringContainsString('README.md', $view);
    // Hidden entries stay out of sight until revealed.
    $this->assertStringNotContainsString('.env', $view);
    $this->assertStringNotContainsString('.hidden', $view);
  }

  public function testDescendAndAscend(): void {
    $widget = new FilePickerWidget($this->root);

    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::named(KeyName::Down));
    $this->assertSame($this->root . '/src', $widget->value());

    $widget->handle(Key::named(KeyName::Right));
    // Inside src the first entry is the Theme directory.
    $this->assertSame($this->root . '/src/Theme', $widget->value());

    // Ascending returns to the parent with the directory just left highlighted.
    $widget->handle(Key::named(KeyName::Left));
    $this->assertSame($this->root . '/src', $widget->value());
  }

  public function testCannotAscendAboveStart(): void {
    $widget = new FilePickerWidget($this->root);

    $widget->handle(Key::named(KeyName::Left));
    $widget->handle(Key::named(KeyName::Left));

    $this->assertSame($this->root . '/docs', $widget->value());
  }

  public function testRightOnFileDoesNotDescend(): void {
    // README.md is the first file; highlight it, then Right is a no-op.
    $widget = new FilePickerWidget($this->root, mode: FilePickerMode::File);

    // Files-only lists directories (navigable) then files.
    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::named(KeyName::Down));
    $this->assertSame($this->root . '/composer.json', $widget->value());

    $widget->handle(Key::named(KeyName::Right));
    $this->assertSame($this->root . '/composer.json', $widget->value());
  }

  public function testAnyModeEnterOnDirectorySelectsIt(): void {
    $widget = new FilePickerWidget($this->root);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame($this->root . '/docs', $value);
    $this->assertTrue($widget->isComplete());
  }

  public function testAnyModeSelectFileAfterDescending(): void {
    $widget = new FilePickerWidget($this->root);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Right),
      Key::named(KeyName::Enter),
    ));

    // Right descends into docs; Enter accepts its first file.
    $this->assertSame($this->root . '/docs/guide.md', $value);
  }

  public function testFileModeEnterOnDirectoryDescends(): void {
    $widget = new FilePickerWidget($this->root, mode: FilePickerMode::File);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Enter),
      Key::named(KeyName::Enter),
    ));

    // The first Enter descends into docs (a directory is not selectable);
    // the second accepts its first file.
    $this->assertSame($this->root . '/docs/guide.md', $value);
  }

  public function testDirectoryModeHidesFilesAndSelectsDirectory(): void {
    $widget = new FilePickerWidget($this->root, mode: FilePickerMode::Directory);

    $view = $this->render($widget);
    $this->assertStringContainsString('docs/', $view);
    // Files are hidden entirely in directory mode.
    $this->assertStringNotContainsString('README.md', $view);
    $this->assertStringNotContainsString('composer.json', $view);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));
    $this->assertSame($this->root . '/docs', $value);
  }

  public function testExtensionFilterLimitsFiles(): void {
    $widget = new FilePickerWidget($this->root, mode: FilePickerMode::File, extensions: ['MD']);

    // Descend into src (docs, empty, src -> src is third).
    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::named(KeyName::Right));

    $view = $this->render($widget);
    // Directories stay navigable; only .md files pass the (case-insensitive)
    // extension filter, so util.php is filtered out.
    $this->assertStringContainsString('Theme/', $view);
    $this->assertStringContainsString('readme.md', $view);
    $this->assertStringNotContainsString('util.php', $view);
  }

  public function testTabTogglesHiddenEntries(): void {
    $widget = new FilePickerWidget($this->root);

    $this->assertStringNotContainsString('.env', $this->render($widget));

    $widget->handle(Key::named(KeyName::Tab));

    $view = $this->render($widget);
    $this->assertStringContainsString('.env', $view);
    $this->assertStringContainsString('.hidden/', $view);
  }

  public function testTypeToFilterNarrowsEntries(): void {
    $widget = new FilePickerWidget($this->root);

    foreach (str_split('read') as $char) {
      $widget->handle(Key::char($char));
    }

    // Only README.md contains "read".
    $this->assertSame('read', $widget->filter());
    $this->assertSame($this->root . '/README.md', $widget->value());
    $this->assertStringContainsString('README.md', $this->render($widget));

    // Clearing the filter restores the full listing.
    foreach (range(1, 4) as $ignored) {
      $widget->handle(Key::named(KeyName::Backspace));
    }
    $this->assertSame($this->root . '/docs', $widget->value());
  }

  public function testBackspaceAscendsWhenFilterEmpty(): void {
    $widget = new FilePickerWidget($this->root);

    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::named(KeyName::Right));
    $this->assertSame($this->root . '/src/Theme', $widget->value());

    $widget->handle(Key::named(KeyName::Backspace));
    $this->assertSame($this->root . '/src', $widget->value());
  }

  public function testMultipleTogglesAndAccepts(): void {
    $widget = new FilePickerWidget($this->root, multiple: TRUE);

    $widget->handle(Key::named(KeyName::Space));
    $this->assertSame([$this->root . '/docs'], $widget->value());

    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::named(KeyName::Space));
    $this->assertSame([$this->root . '/docs', $this->root . '/src'], $widget->value());

    // Toggling an already-selected entry removes it.
    $widget->handle(Key::named(KeyName::Space));
    $this->assertSame([$this->root . '/docs'], $widget->value());

    $widget->handle(Key::named(KeyName::Enter));
    $this->assertTrue($widget->isComplete());
    $this->assertSame([$this->root . '/docs'], $widget->value());
  }

  public function testMultipleAccumulatesAcrossDirectories(): void {
    $widget = new FilePickerWidget($this->root, multiple: TRUE);

    // Select the docs directory, then descend into src and select Theme.
    $widget->handle(Key::named(KeyName::Space));
    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::named(KeyName::Right));
    $widget->handle(Key::named(KeyName::Space));

    $this->assertSame([$this->root . '/docs', $this->root . '/src/Theme'], $widget->value());
  }

  public function testMultipleSpaceIgnoresNonSelectableDirectory(): void {
    $widget = new FilePickerWidget($this->root, mode: FilePickerMode::File, multiple: TRUE);
    $theme = new DefaultTheme(76, ['unicode' => FALSE, 'color' => FALSE]);

    // The first entry is a directory, which files-only mode cannot select.
    $widget->handle(Key::named(KeyName::Space));
    $this->assertSame([], $widget->value());

    // Selectable files carry a checkbox; navigable directories carry a spacer.
    $view = $widget->view($theme);
    $this->assertStringContainsString('[ ] README.md', $view);
    $this->assertStringContainsString('docs/', $view);
  }

  public function testMultipleSpaceInEmptyDirectoryIsSafe(): void {
    $widget = new FilePickerWidget($this->root, multiple: TRUE);

    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::named(KeyName::Right));
    $widget->handle(Key::named(KeyName::Space));

    $this->assertSame([], $widget->value());
  }

  public function testSeedWithMissingBasenameHighlightsTop(): void {
    // A default under the start whose entry does not exist opens at the start
    // directory with the top entry highlighted.
    $widget = new FilePickerWidget($this->root, $this->root . '/nope.txt');

    $this->assertSame($this->root . '/docs', $widget->value());
  }

  public function testRootBreadcrumb(): void {
    $widget = new FilePickerWidget('/');

    $lines = explode("\n", Ansi::strip($widget->view(new DefaultTheme())));
    $this->assertSame('/', $lines[0]);
  }

  public function testNonexistentStartIsEmpty(): void {
    $widget = new FilePickerWidget($this->root . '/missing');

    $this->assertSame('', $widget->value());
    $this->assertStringContainsString('(empty)', $this->render($widget));
  }

  public function testMultipleSeedsSelectionFromDefault(): void {
    $widget = new FilePickerWidget($this->root, [$this->root . '/README.md'], multiple: TRUE);

    $this->assertSame([$this->root . '/README.md'], $widget->value());
    // The browser opens at the seeded path's directory with it highlighted.
    $this->assertStringContainsString('README.md', $this->render($widget));
  }

  public function testSingleSeededDefaultOpensAtItsDirectory(): void {
    $widget = new FilePickerWidget($this->root, $this->root . '/src/readme.md');

    $this->assertSame($this->root . '/src/readme.md', $widget->value());
    // The breadcrumb reflects the opened sub-directory.
    $this->assertStringContainsString('root/src', $this->render($widget));
  }

  public function testSeedIgnoredWhenOutsideStart(): void {
    $widget = new FilePickerWidget($this->root, '/somewhere/else.txt');

    // A default outside the start directory is ignored; the browser opens at
    // the start.
    $this->assertSame($this->root . '/docs', $widget->value());
  }

  public function testEmptyDirectory(): void {
    $widget = new FilePickerWidget($this->root);

    // Highlight and descend into the empty directory.
    $widget->handle(Key::named(KeyName::Down));
    $this->assertSame($this->root . '/empty', $widget->value());

    $widget->handle(Key::named(KeyName::Right));
    $this->assertSame('', $widget->value());
    $this->assertStringContainsString('(empty)', $this->render($widget));

    // Moving, descending and accepting in an empty directory are all no-ops.
    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::named(KeyName::Right));
    $widget->handle(Key::named(KeyName::Enter));
    $this->assertFalse($widget->isComplete());
    $this->assertSame('', $widget->value());
  }

  public function testCancel(): void {
    $widget = new FilePickerWidget($this->root);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Escape)));

    $this->assertNull($value);
    $this->assertTrue($widget->isCancelled());
  }

  public function testAsciiRendering(): void {
    $widget = new FilePickerWidget($this->root);
    $theme = new DefaultTheme(76, ['unicode' => FALSE, 'color' => FALSE]);

    $view = $widget->view($theme);

    // The cursor row carries the ASCII marker; directories carry a slash.
    $this->assertStringContainsString('> docs/', $view);
    $this->assertStringContainsString('src/', $view);
  }

  public function testMultipleAsciiCheckboxes(): void {
    $widget = new FilePickerWidget($this->root, multiple: TRUE);
    $theme = new DefaultTheme(76, ['unicode' => FALSE, 'color' => FALSE]);

    $this->assertStringContainsString('[ ] docs/', $widget->view($theme));

    $widget->handle(Key::named(KeyName::Space));
    $this->assertStringContainsString('[x] docs/', $widget->view($theme));
  }

  public function testHintsRenderPerMode(): void {
    $theme = new DefaultTheme(76, ['unicode' => FALSE, 'color' => FALSE]);

    // A single picker binds no toggle key, so that fragment drops and Accept
    // reads "select"; the browse and hidden fragments are always present.
    $single = Ansi::strip($theme->renderHints(KeyMapManager::create()->forField(FieldType::FilePicker), ...(new FilePickerWidget($this->root))->hints()));
    $this->assertStringNotContainsString('space select', $single);
    $this->assertStringContainsString('open', $single);
    $this->assertStringContainsString('tab hidden', $single);

    // Multiple mode leads with the toggle key and Accept reads "accept".
    $multiple = Ansi::strip($theme->renderHints(KeyMapManager::create()->forField(FieldType::FilePicker, TRUE), ...(new FilePickerWidget($this->root, multiple: TRUE))->hints()));
    $this->assertStringContainsString('space select', $multiple);
    $this->assertStringContainsString('accept', $multiple);
  }

  public function testScrollsLargeDirectory(): void {
    $files = [];
    foreach (range(0, 29) as $index) {
      $files[sprintf('file%02d.txt', $index)] = '';
    }
    vfsStream::setup('big', NULL, $files);
    $widget = new FilePickerWidget(vfsStream::url('big'));
    $theme = new DefaultTheme(76, ['color' => FALSE]);

    $top = $widget->view($theme);
    $this->assertStringContainsString('file00.txt', $top);
    $this->assertStringNotContainsString('file29.txt', $top);
    // A window that clips below shows the down indicator only.
    $this->assertStringContainsString('▼', $top);
    $this->assertStringNotContainsString('▲', $top);

    foreach (range(1, 29) as $ignored) {
      $widget->handle(Key::named(KeyName::Down));
    }

    $bottom = $widget->view($theme);
    $this->assertStringContainsString('file29.txt', $bottom);
    $this->assertStringNotContainsString('file00.txt', $bottom);
    $this->assertStringContainsString('▲', $bottom);
  }

  public function testValueReflectsHighlightBeforeAccept(): void {
    $widget = new FilePickerWidget($this->root);

    // Before acceptance the value tracks the highlighted entry.
    $this->assertSame($this->root . '/docs', $widget->value());

    $widget->handle(Key::named(KeyName::Down));
    $this->assertSame($this->root . '/empty', $widget->value());

    // Moving back up restores the earlier highlight.
    $widget->handle(Key::named(KeyName::Up));
    $this->assertSame($this->root . '/docs', $widget->value());
  }

  public function testDefaultsToWorkingDirectoryWhenStartEmpty(): void {
    $widget = new class($this->root . '/docs') extends FilePickerWidget {

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
    $view = $this->render($widget);
    $this->assertStringContainsString('docs', $view);
    $this->assertStringContainsString('guide.md', $view);
  }

  public function testMultipleRejectsBelowMinWithInlineError(): void {
    $widget = new FilePickerWidget($this->root, multiple: TRUE, selection_bounds: new SelectionBounds(2, NULL));

    // Selecting one entry is below the minimum of two.
    $widget->handle(Key::named(KeyName::Space));
    $widget->handle(Key::named(KeyName::Enter));

    $this->assertFalse($widget->isComplete());
    $this->assertStringContainsString('Select at least 2 items.', $this->render($widget));
  }

  public function testMultipleAcceptsWithinBounds(): void {
    $widget = new FilePickerWidget($this->root, multiple: TRUE, selection_bounds: new SelectionBounds(1, 2));

    $widget->handle(Key::named(KeyName::Space));
    $widget->handle(Key::named(KeyName::Enter));

    $this->assertTrue($widget->isComplete());
    $this->assertSame([$this->root . '/docs'], $widget->value());
  }

  public function testMultipleSelectionHintShownInHeader(): void {
    $widget = new FilePickerWidget($this->root, multiple: TRUE, selection_bounds: new SelectionBounds(2, 3));

    // The active limit is surfaced before it is reached.
    $this->assertStringContainsString('between 2 and 3 items', $this->render($widget));
  }

  /**
   * Render a widget's view with the default theme, stripped of ANSI codes.
   *
   * @param \DrevOps\Tui\Widget\FilePickerWidget $widget
   *   The widget.
   *
   * @return string
   *   The plain-text view.
   */
  protected function render(FilePickerWidget $widget): string {
    return Ansi::strip($widget->view(new DefaultTheme()));
  }

}
