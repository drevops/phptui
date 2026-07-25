<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Model\Buttons;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\Modal;
use DrevOps\Tui\Model\Panel;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\HelpSection;
use DrevOps\Tui\Render\Navigator;
use DrevOps\Tui\Render\Viewport;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Spacing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the theme's rendering via headless frame probes.
 */
#[CoversClass(DefaultTheme::class)]
#[CoversClass(HelpSection::class)]
#[Group('tui')]
final class ThemeRenderTest extends TestCase {

  public function testFieldLineSelectedRightAlignsBadge(): void {
    $lines = $this->theme()->renderFieldLine(new Field('name', 'Name', '', FieldType::Text, ''), new Answers(['name' => 'Acme'], ['name' => Provenance::Edited]), TRUE);

    // A single-line value is one row.
    $this->assertCount(1, $lines);
    $this->assertStringContainsString('❯ Name  Acme', Ansi::strip($lines[0]));
    $this->assertStringContainsString('edited', Ansi::strip($lines[0]));
    $this->assertSame(40, Ansi::width($lines[0]));
  }

  public function testFieldLineDefaultHasNoBadge(): void {
    $lines = $this->theme()->renderFieldLine(new Field('name', 'Name', '', FieldType::Text, ''), new Answers(['name' => 'Acme'], ['name' => Provenance::Default]), FALSE);

    $this->assertStringNotContainsString('default', $lines[0]);
    $this->assertStringContainsString('Name  Acme', Ansi::strip($lines[0]));
  }

  public function testFieldLineRendersValues(): void {
    $theme = $this->theme();

    $bool = Ansi::strip($theme->renderFieldLine(new Field('b', 'B', '', FieldType::Confirm, FALSE), new Answers(['b' => TRUE], ['b' => Provenance::Default]), FALSE)[0]);
    $this->assertStringContainsString('B  yes', $bool);

    $list = Ansi::strip($theme->renderFieldLine(new Field('m', 'M', '', FieldType::Select, [], multiple: TRUE), new Answers(['m' => ['a', 'b']], ['m' => Provenance::Default]), FALSE)[0]);
    $this->assertStringContainsString('M  a, b', $list);
  }

  public function testFieldLineMasksPasswordValue(): void {
    $field = new Field('token', 'Token', '', FieldType::Password, '');

    $line = Ansi::strip($this->theme()->renderFieldLine($field, new Answers(['token' => 's3cret-long'], ['token' => Provenance::Edited]), FALSE)[0]);

    $this->assertStringNotContainsString('s3cret-long', $line);
    // The mask has a fixed length so it does not leak the value's length.
    $this->assertStringContainsString('Token  ••••••••', $line);

    $empty = Ansi::strip($this->theme()->renderFieldLine($field, new Answers(['token' => ''], ['token' => Provenance::Default]), FALSE)[0]);
    $this->assertStringNotContainsString('•', $empty);
  }

  public function testRenderInlineEditorPutsViewInPlaceOfValue(): void {
    $field = new Field('cdn', 'CDN', '', FieldType::Confirm, FALSE);

    $lines = $this->theme()->renderInlineEditor($field, "line one\nline two", TRUE);

    // The view's first line sits on the label row where the value would be; a
    // further line aligns under that value column.
    $this->assertSame('❯ CDN  line one', Ansi::strip($lines[0]));
    $this->assertMatchesRegularExpression('/^ +line two$/', Ansi::strip($lines[1]));
    $this->assertCount(2, $lines);
  }

  public function testBodyExpandsMultiLineValueAcrossRows(): void {
    $panel = new Panel('p', 'P', '', [new Field('notes', 'Notes', '', FieldType::Textarea, '')]);
    $answers = new Answers(['notes' => "Crisp and sweet\nHint of citrus"], ['notes' => Provenance::Edited]);

    [$lines] = $this->theme()->renderBody($panel, $answers, 0);

    // Each body entry is one physical row: an embedded newline would desync the
    // box border, the badge alignment and the scroll maths.
    foreach ($lines as $line) {
      $this->assertStringNotContainsString("\n", $line);
    }

    $stripped = array_map(Ansi::strip(...), $lines);

    // The first value line rides the label row; the rest align under the value
    // column.
    $this->assertStringContainsString('Notes  Crisp and sweet', $stripped[0]);
    $this->assertMatchesRegularExpression('/^ +Hint of citrus$/', $stripped[1]);

    // The provenance badge rides the label row only.
    $this->assertStringContainsString('edited', $stripped[0]);
    $this->assertStringNotContainsString('edited', $stripped[1]);
  }

  public function testBodyExpandsInlineEditorMultiLineView(): void {
    $field = new Field('notes', 'Notes', '', FieldType::Textarea, '');
    $panel = new Panel('p', 'P', '', [$field]);

    // The editor hands back a multi-line caret view for the field being edited.
    [$lines] = $this->theme()->renderBody($panel, new Answers(), 0, $field, "Crisp and sweet\nHint of citrus");

    foreach ($lines as $line) {
      $this->assertStringNotContainsString("\n", $line);
    }

    $stripped = array_map(Ansi::strip(...), $lines);
    $this->assertStringContainsString('Notes  Crisp and sweet', $stripped[0]);
    $this->assertMatchesRegularExpression('/^ +Hint of citrus$/', $stripped[1]);
  }

  public function testPanelSummaryCollapsesMultiLineValue(): void {
    $panel = new Panel('sub', 'Sub', '', [new Field('notes', 'Notes', '', FieldType::Textarea, '')]);
    $answers = new Answers(['notes' => "Crisp and sweet\nHint of citrus"], []);

    $summary = $this->theme()->summarizePanel($panel, $answers);

    // A summary is a single line: newlines collapse so a multi-line value does
    // not break the row it sits on.
    $this->assertStringNotContainsString("\n", $summary);
    $this->assertStringContainsString('Crisp and sweet', $summary);
    $this->assertStringContainsString('Hint of citrus', $summary);
  }

  #[DataProvider('dataProviderBodyNormalizesLineEndingsInMultiLineValue')]
  public function testBodyNormalizesLineEndingsInMultiLineValue(string $value): void {
    $panel = new Panel('p', 'P', '', [new Field('notes', 'Notes', '', FieldType::Textarea, '')]);
    $answers = new Answers(['notes' => $value], []);

    [$lines] = $this->theme()->renderBody($panel, $answers, 0);

    // A carriage return would send the terminal cursor back to the row start
    // and overprint the row, so every line ending an external editor's save
    // can carry in splits into rows the same way a newline does.
    foreach ($lines as $line) {
      $this->assertStringNotContainsString("\r", $line);
      $this->assertStringNotContainsString("\n", $line);
    }

    $stripped = array_map(Ansi::strip(...), $lines);
    $this->assertStringContainsString('Notes  Crisp and sweet', $stripped[0]);
    $this->assertMatchesRegularExpression('/^ +Hint of citrus$/', $stripped[1]);
  }

  public static function dataProviderBodyNormalizesLineEndingsInMultiLineValue(): \Iterator {
    yield 'newline' => ["Crisp and sweet\nHint of citrus"];
    yield 'carriage return and newline' => ["Crisp and sweet\r\nHint of citrus"];
    yield 'carriage return' => ["Crisp and sweet\rHint of citrus"];
  }

  #[DataProvider('dataProviderPanelSummaryNormalizesLineEndings')]
  public function testPanelSummaryNormalizesLineEndings(string $value): void {
    $panel = new Panel('sub', 'Sub', '', [new Field('notes', 'Notes', '', FieldType::Textarea, '')]);
    $answers = new Answers(['notes' => $value], []);

    $summary = $this->theme()->summarizePanel($panel, $answers);

    $this->assertStringNotContainsString("\r", $summary);
    $this->assertStringNotContainsString("\n", $summary);
    $this->assertStringContainsString('Crisp and sweet Hint of citrus', $summary);
  }

  public static function dataProviderPanelSummaryNormalizesLineEndings(): \Iterator {
    yield 'newline' => ["Crisp and sweet\nHint of citrus"];
    yield 'carriage return and newline' => ["Crisp and sweet\r\nHint of citrus"];
    yield 'carriage return' => ["Crisp and sweet\rHint of citrus"];
  }

  public function testPanelLineShowsDrillIndicator(): void {
    $line = Ansi::strip($this->theme()->renderPanelLine(new Panel('adv', 'Advanced', ''), TRUE));

    $this->assertStringContainsString('❯ Advanced', $line);
    $this->assertStringContainsString('›', $line);
  }

  public function testBodyReportsCursorLine(): void {
    $panel = new Panel('p', 'P', '', [
      new Field('a', 'A', 'desc a', FieldType::Text, ''),
      new Field('b', 'B', '', FieldType::Text, ''),
    ]);

    [$lines, $cursor_line] = $this->theme()->renderBody($panel, new Answers(), 1);

    $this->assertSame(2, $cursor_line);
    $this->assertStringContainsString('❯ B', Ansi::strip($lines[2]));
  }

  public function testBodyRendersNoteCardAndSkipsItInTheCursorCount(): void {
    $panel = new Panel('p', 'P', '', [
      new Field('name', 'Name', '', FieldType::Text, ''),
      new Field('intro', 'Getting started', "First line.\nSecond line.", FieldType::Note, ''),
      new Field('agree', 'Agree', '', FieldType::Confirm, FALSE),
    ]);

    // Cursor index 1 is the second navigable field; the note is not counted.
    [$lines, $cursor_line] = $this->theme()->renderBody($panel, new Answers(['name' => 'Acme', 'agree' => FALSE], []), 1);

    $body = Ansi::strip(implode("\n", $lines));
    $this->assertStringContainsString('Getting started', $body);
    $this->assertStringContainsString('First line.', $body);
    $this->assertStringContainsString('Second line.', $body);
    // The cursor lands on the field after the note, never the note itself.
    $this->assertStringContainsString('❯ Agree', Ansi::strip($lines[$cursor_line]));
    $this->assertStringNotContainsString('❯ Getting started', $body);
  }

  public function testBodySkipsEmptyNote(): void {
    $panel = new Panel('p', 'P', '', [
      new Field('name', 'Name', '', FieldType::Text, ''),
      new Field('blank', '', '', FieldType::Note, ''),
    ]);

    [$lines] = $this->theme()->renderBody($panel, new Answers(['name' => 'Acme'], []), 0);

    // A note with neither title nor body contributes no lines.
    $this->assertStringContainsString('Name', Ansi::strip(implode("\n", $lines)));
    $this->assertSame([], $this->theme()->renderNoteLines(new Field('blank', '', '', FieldType::Note, ''), new Answers()));
  }

  public function testNoteInterpolatesAnswersInTitleAndBody(): void {
    $note = new Field('echo', 'Hello {{name}}', 'You picked {{fruit}}.', FieldType::Note, '');

    $lines = Ansi::strip(implode("\n", $this->theme()->renderNoteLines($note, new Answers(['name' => 'Ada', 'fruit' => 'pear'], []))));

    $this->assertStringContainsString('Hello Ada', $lines);
    $this->assertStringContainsString('You picked pear.', $lines);
  }

  public function testPaddedSpacingSeparatesNoteFromTheFieldAbove(): void {
    $theme = new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Padded]);
    $panel = new Panel('p', 'P', '', [
      new Field('name', 'Name', '', FieldType::Text, ''),
      new Field('intro', 'Intro', 'Body.', FieldType::Note, ''),
    ]);

    [$lines] = $theme->renderBody($panel, new Answers(['name' => 'Acme'], []), 0);
    $stripped = array_map(Ansi::strip(...), $lines);

    // Padded spacing inserts a blank line before the note card.
    $index = array_search('  Intro', $stripped, TRUE);
    $this->assertIsInt($index);
    $this->assertGreaterThan(0, $index);
    $this->assertSame('', $stripped[$index - 1]);
  }

  public function testRenderNoteLinesBoxesBorderedNote(): void {
    // The theme frame is borderless, so an opt-in note border falls back to the
    // single-line box; its glyphs come only from the note.
    $lines = $this->theme()->renderNoteLines(new Field('boxed', 'Boxed', 'In a box.', FieldType::Note, '', bordered: TRUE), new Answers());
    $joined = Ansi::strip(implode("\n", $lines));

    $this->assertStringContainsString('Boxed', $joined);
    $this->assertStringContainsString('In a box.', $joined);
    $this->assertStringContainsString('┌', $joined);
    $this->assertStringContainsString('┐', $joined);
    $this->assertStringContainsString('└', $joined);
    $this->assertStringContainsString('┘', $joined);
  }

  public function testBodyIncludesSubPanels(): void {
    $panel = new Panel('p', 'P', '', [new Field('a', 'A', '', FieldType::Text, '')], [
      new Panel('sub', 'Sub', 'sub desc'),
    ]);

    [$lines, $cursor_line] = $this->theme()->renderBody($panel, new Answers(), 1);

    // The cursor is on the sub-panel (index 1, after the single field).
    $this->assertSame(1, $cursor_line);
    $this->assertStringContainsString('❯ Sub', Ansi::strip($lines[1]));
    $this->assertStringContainsString('sub desc', Ansi::strip($lines[2]));
  }

  public function testBodyIncludesPanelSummary(): void {
    $hub = new Panel('hub', 'Hub', '', [], [
      new Panel('general', 'General', 'the general panel', [new Field('name', 'Name', '', FieldType::Text, '')]),
    ]);

    [$lines] = $this->theme()->renderBody($hub, new Answers(['name' => 'Acme'], []), 0);

    // The hub shows the sub-panel's title, description and value summary.
    $body = Ansi::strip(implode("\n", $lines));
    $this->assertStringContainsString('General', $body);
    $this->assertStringContainsString('the general panel', $body);
    $this->assertStringContainsString('Acme', $body);
  }

  public function testPanelSummaryJoinsActiveValues(): void {
    $panel = new Panel('p', 'P', '', [
      new Field('a', 'A', '', FieldType::Text, ''),
      new Field('b', 'B', '', FieldType::Text, ''),
      new Field('gated', 'Gated', '', FieldType::Text, ''),
      new Field('m', 'M', '', FieldType::Select, [], multiple: TRUE),
      new Field('c', 'C', '', FieldType::Text, ''),
      new Field('d', 'D', '', FieldType::Text, ''),
    ]);
    $answers = new Answers(['a' => 'Acme', 'b' => 'Beta', 'm' => ['w', 'x', 'y', 'z'], 'c' => 'Gamma', 'd' => 'Delta'], []);

    // "gated" is skipped (no answer), the multiselect condenses to a pluralized
    // count, and only the first four active values appear ("Delta" is dropped).
    $this->assertSame('Acme · Beta · 4 items selected · Gamma', $this->theme()->summarizePanel($panel, $answers));
  }

  public function testSummaryLineClipsToWidth(): void {
    $line = Ansi::strip($this->theme()->renderSummaryLine(str_repeat('x', 100), FALSE));

    $this->assertLessThanOrEqual(40, mb_strlen($line));
    $this->assertStringContainsString('…', $line);
  }

  public function testSelectedItemIsBold(): void {
    $theme = new DefaultTheme(40);
    $field = new Field('name', 'Name', '', FieldType::Text, '');
    $answers = new Answers(['name' => 'Acme'], ['name' => Provenance::Default]);

    // The selected row is bold (SGR 1); a non-selected row is not.
    $this->assertStringContainsString("\033[1", $theme->renderFieldLine($field, $answers, TRUE)[0]);
    $this->assertStringNotContainsString("\033[1", $theme->renderFieldLine($field, $answers, FALSE)[0]);

    // The selected item's description and summary rows are bold too.
    $this->assertStringContainsString("\033[1", $theme->renderDescriptionLine('help', TRUE));
    $this->assertStringNotContainsString("\033[1", $theme->renderDescriptionLine('help', FALSE));
    $this->assertStringContainsString("\033[1", $theme->renderSummaryLine('sum', TRUE));
  }

  public function testFrameShowsIndicatorsAndWindow(): void {
    $body = array_map(static fn(int $i): string => 'line' . $i, range(0, 9));

    $frame = $this->theme()->renderFrame(['HEAD'], $body, ['FOOT'], new Viewport(3, TRUE, TRUE), 4);

    $this->assertStringContainsString('▲', $frame);
    $this->assertStringContainsString('▼', $frame);
    $this->assertStringContainsString('HEAD', $frame);
    $this->assertStringContainsString('FOOT', $frame);
    $this->assertStringContainsString('line3', $frame);
    $this->assertStringNotContainsString('line0', $frame);
  }

  public function testBreadcrumbLine(): void {
    $navigator = new Navigator(new Panel('hub', 'Hub', '', [], [new Panel('d', 'Drupal', '')]));

    $this->assertSame('Hub', Ansi::strip($this->theme()->renderBreadcrumbLine($navigator)));
  }

  public function testBanner(): void {
    $banner = Ansi::strip($this->theme()->renderBanner("LOGO\nline", '1.2.3'));

    $this->assertStringContainsString('LOGO', $banner);
    $this->assertStringContainsString('Version: 1.2.3', $banner);

    $this->assertStringNotContainsString('Version', Ansi::strip($this->theme()->renderBanner('LOGO', '')));
  }

  public function testHintsLineIsThemed(): void {
    $line = (new DefaultTheme())->renderHints(KeyMapManager::create()->navigation(), new Hint('move', Action::MoveUp, Action::MoveDown));

    // Themed with the footer role (dim gray) and composed from arrow glyphs.
    $this->assertStringContainsString("\033[90m", $line);
    $this->assertStringContainsString('↑/↓ move', Ansi::strip($line));
  }

  public function testRenderHintsJoinsFragmentsInBothModes(): void {
    $keys = KeyMapManager::create()->forField(FieldType::Select, TRUE);
    $hints = [new Hint('select', Action::Toggle), new Hint('none/all', Action::SelectNone, Action::SelectAll)];

    $unicode = Ansi::strip((new DefaultTheme())->renderHints($keys, ...$hints));
    $this->assertSame('space select · ←/→ none/all', $unicode);

    // The glyphs degrade with the theme's Unicode mode.
    $ascii = Ansi::strip((new DefaultTheme(76, ['unicode' => FALSE]))->renderHints($keys, ...$hints));
    $this->assertStringContainsString('</> none/all', $ascii);
  }

  public function testRenderHintsEmptyWhenNothingBound(): void {
    $nav = KeyMapManager::create()->navigation();

    // Newline is not a navigation action, so the whole line collapses to empty.
    $this->assertSame('', (new DefaultTheme())->renderHints($nav, new Hint('newline', Action::NewLine)));
  }

  public function testRenderHelpListsSectionsAndCloseHint(): void {
    $nav = KeyMapManager::create()->navigation();
    $text = KeyMapManager::create()->forField(FieldType::Text);

    $help = Ansi::strip((new DefaultTheme())->renderHelp(
      $nav,
      new HelpSection('Navigation', $nav, new Hint('move', Action::MoveUp, Action::MoveDown)),
      new HelpSection('Text', $text, new Hint('accept', Action::Accept)),
      // A section whose hints resolve to nothing lists its heading only.
      new HelpSection('Empty', $nav, new Hint('newline', Action::NewLine)),
    ));

    $this->assertStringContainsString('Keyboard help', $help);
    $this->assertStringContainsString('Navigation', $help);
    $this->assertStringContainsString('↑/↓ move', $help);
    $this->assertStringContainsString('Text', $help);
    $this->assertStringContainsString('Empty', $help);
    $this->assertStringContainsString('? close', $help);
  }

  #[DataProvider('dataProviderKeyHint')]
  public function testKeyHint(Key $key, string $unicode, string $ascii): void {
    $this->assertSame($unicode, (new DefaultTheme())->keyHint($key));
    $this->assertSame($ascii, (new DefaultTheme(76, ['color' => FALSE, 'unicode' => FALSE]))->keyHint($key));
  }

  public static function dataProviderKeyHint(): \Iterator {
    yield 'up' => [Key::named(KeyName::Up), '↑', '^'];
    yield 'down' => [Key::named(KeyName::Down), '↓', 'v'];
    yield 'left' => [Key::named(KeyName::Left), '←', '<'];
    yield 'right' => [Key::named(KeyName::Right), '→', '>'];
    yield 'enter' => [Key::named(KeyName::Enter), '↵', '<'];
    yield 'escape' => [Key::named(KeyName::Escape), 'esc', 'esc'];
    yield 'interrupt' => [Key::named(KeyName::Interrupt), 'ctrl-c', 'ctrl-c'];
    yield 'tab' => [Key::named(KeyName::Tab), 'tab', 'tab'];
    yield 'space' => [Key::named(KeyName::Space), 'space', 'space'];
    yield 'backspace' => [Key::named(KeyName::Backspace), '⌫', 'bksp'];
    yield 'delete' => [Key::named(KeyName::Delete), 'del', 'del'];
    yield 'home' => [Key::named(KeyName::Home), 'home', 'home'];
    yield 'end' => [Key::named(KeyName::End), 'end', 'end'];
    yield 'page up' => [Key::named(KeyName::PageUp), 'pgup', 'pgup'];
    yield 'page down' => [Key::named(KeyName::PageDown), 'pgdn', 'pgdn'];
    yield 'wheel up' => [Key::named(KeyName::MouseWheelUp), '↑', '^'];
    yield 'wheel down' => [Key::named(KeyName::MouseWheelDown), '↓', 'v'];
    yield 'character' => [Key::char('j'), 'j', 'j'];
    yield 'control character spelled out' => [Key::char("\x05"), 'ctrl-e', 'ctrl-e'];
  }

  public function testKeysHintDropsUnboundActions(): void {
    $nav = KeyMapManager::create()->navigation();
    $theme = new DefaultTheme();

    $this->assertSame('↑/↓ move', $theme->keysHint($nav, 'move', Action::MoveUp, Action::MoveDown));
    // Newline is not bound in the navigation scope, so the fragment is empty.
    $this->assertSame('', $theme->keysHint($nav, 'newline', Action::NewLine));
  }

  public function testRenderEditorDerivesHintFromKeys(): void {
    $keys = KeyMapManager::create()->forField(FieldType::Text);
    $hints = [new Hint('accept', Action::Accept), new Hint('cancel', Action::Cancel)];
    $editor = Ansi::strip((new DefaultTheme())->renderEditor('Name', 'value', $hints, $keys));

    // The hint reflects the active bindings.
    $this->assertStringContainsString('↵ accept', $editor);
    $this->assertStringContainsString('esc cancel', $editor);
  }

  public function testHorizontalArrowGlyphs(): void {
    $unicode = new DefaultTheme();
    $this->assertSame('←', $unicode->arrowLeft());
    $this->assertSame('→', $unicode->arrowRight());

    $ascii = new DefaultTheme(76, ['unicode' => FALSE]);
    $this->assertSame('<', $ascii->arrowLeft());
    $this->assertSame('>', $ascii->arrowRight());
  }

  public function testHintLineJoinsWithDotGlyph(): void {
    $line = (new DefaultTheme())->renderHintLine('enter accept', 'esc cancel');

    $this->assertSame('enter accept · esc cancel', Ansi::strip($line));
    $this->assertStringContainsString("\033[90m", $line);

    $ascii = (new DefaultTheme(76, ['unicode' => FALSE]))->renderHintLine('a', 'b');
    $this->assertSame('a * b', Ansi::strip($ascii));
  }

  public function testEditorHeaderUnderlinesLabel(): void {
    $header = (new DefaultTheme())->renderEditorHeader('Site name');

    // The label styled as a title, over a rule of the same visible width.
    $this->assertSame("Site name\n" . str_repeat('─', 9), Ansi::strip($header));
    $this->assertStringContainsString("\033[1;36mSite name\033[0m", $header);
    $this->assertStringContainsString("\033[90m", $header);

    $ascii = (new DefaultTheme(76, ['color' => FALSE, 'unicode' => FALSE]))->renderEditorHeader('Site name');
    $this->assertSame("Site name\n---------", $ascii);

    // An empty label still yields a visible rule.
    $this->assertSame("\n─", Ansi::strip((new DefaultTheme())->renderEditorHeader('')));
  }

  public function testButtonBar(): void {
    $bar = (new DefaultTheme())->renderButtonBar(['Submit', 'Cancel'], 0);

    // Both buttons render inline on one row.
    $this->assertStringContainsString('[ Submit ]', Ansi::strip($bar));
    $this->assertStringContainsString('[ Cancel ]', Ansi::strip($bar));
    // The selected button (index 0) uses the cursor style (bold reverse).
    $this->assertStringContainsString("\033[1;7m[ Submit ]", $bar);

    // With none selected, nothing is cursor-styled.
    $this->assertStringNotContainsString("\033[1;7m", (new DefaultTheme())->renderButtonBar(['Submit', 'Cancel'], -1));
  }

  public function testDimRecedesText(): void {
    // With colour, dim wraps the text; with colour off, it is left untouched.
    $this->assertSame("\033[2mx\033[0m", (new DefaultTheme(40))->dim('x'));
    $this->assertSame('x', $this->theme()->dim('x'));
  }

  public function testRenderModalCentersDialogOverBackdrop(): void {
    $theme = $this->theme();
    $modal = new Panel('c', 'Confirm', 'Proceed with care.', [
      new Field('opt', 'Option', '', FieldType::Text, 'val'),
    ], [], new Modal(new Buttons(TRUE, 'Yes', 'No')));
    // A backdrop taller than the dialog, so the dialog centres within it.
    $backdrop = $theme->renderFrame(['Demo'], ['Alpha  1', 'Beta  2', 'Gamma  3', 'Delta  4', 'Epsilon  5', 'Zeta  6', 'Eta  7', 'Theta  8'], [], new Viewport(0, FALSE, FALSE), 8);

    $out = Ansi::strip($theme->renderModal($modal, new Answers(['opt' => 'val'], ['opt' => Provenance::Default]), 0, NULL, '', 0, $backdrop, 10));

    $this->assertStringContainsString('Confirm', $out);
    $this->assertStringContainsString('Proceed with care.', $out);
    $this->assertStringContainsString('Option', $out);
    $this->assertStringContainsString('[ Yes ]', $out);
    $this->assertStringContainsString('[ No ]', $out);
    // Compositing preserves the backdrop's height: the dialog floats within it.
    $this->assertCount(count(explode("\n", Ansi::strip($backdrop))), explode("\n", $out));
  }

  public function testRenderModalWithoutFields(): void {
    $theme = $this->theme();
    $modal = new Panel('n', 'Notice', 'Saved successfully.', [], [], new Modal());
    $backdrop = "aaaaaa\nbbbbbb\ncccccc\ndddddd\neeeeee\nffffff\ngggggg\nhhhhhh";

    $out = Ansi::strip($theme->renderModal($modal, new Answers([], []), -1, NULL, '', -1, $backdrop, 8));

    // A text-only dialog still shows its message and its default buttons.
    $this->assertStringContainsString('Notice', $out);
    $this->assertStringContainsString('Saved successfully.', $out);
    $this->assertStringContainsString('[ Submit ]', $out);
    $this->assertStringContainsString('[ Cancel ]', $out);
  }

  public function testRenderModalForcesBorderOnBorderlessTheme(): void {
    $theme = new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None]);
    $modal = new Panel('e', 'Empty', '', [], [], new Modal());
    $backdrop = "aaaaaa\nbbbbbb\ncccccc\ndddddd\neeeeee\nffffff";

    $out = Ansi::strip($theme->renderModal($modal, new Answers([], []), -1, NULL, '', -1, $backdrop, 6));

    // Even a borderless theme boxes the dialog so it reads as floating above.
    $this->assertStringContainsString('┌', $out);
    $this->assertStringContainsString('Empty', $out);
    $this->assertStringContainsString('[ Submit ]', $out);
  }

  public function testRenderModalScrollsBodyAndPinsButtonsWhenTall(): void {
    $theme = $this->theme();
    $fields = [];
    $values = [];
    for ($i = 1; $i <= 10; $i++) {
      $fields[] = new Field('f' . $i, 'Field ' . $i, '', FieldType::Text, 'v' . $i);
      $values['f' . $i] = 'v' . $i;
    }
    $modal = new Panel('big', 'Big', 'Many fields.', $fields, [], new Modal(new Buttons(TRUE, 'Save', 'Discard')));
    // A short backdrop forces the padding; a small height forces the body to
    // scroll under the pinned button footer rather than clipping it.
    $backdrop = "aaaa\nbbbb\ncccc";

    $out = Ansi::strip($theme->renderModal($modal, new Answers($values, []), 0, NULL, '', -1, $backdrop, 12));

    $this->assertStringContainsString('Field 1', $out);
    $this->assertStringContainsString('[ Save ]', $out);
    $this->assertStringContainsString('[ Discard ]', $out);
    // The last field scrolls out of view, but the buttons never do.
    $this->assertStringNotContainsString('Field 10', $out);
  }

  /**
   * A colourless borderless theme of fixed width.
   *
   * The explicit border and spacing keep these layout assertions on the
   * plain look, independent of the theme's bordered-and-padded defaults.
   *
   * @return \DrevOps\Tui\Theme\DefaultTheme
   *   The theme.
   */
  protected function theme(): DefaultTheme {
    return new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]);
  }

}
