<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\Viewport;
use DrevOps\Tui\Tests\Traits\BuildsThemesTrait;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\DefaultTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the theme's rendering via headless frame probes.
 */
#[CoversClass(DefaultTheme::class)]
#[Group('theme')]
final class ThemeRenderTest extends TestCase {

  use BuildsThemesTrait;

  public function testHelpMarkerFallsBackToTextWithoutUnicode(): void {
    // The glyph is narrow by design so a fixed-advance surface cannot squeeze
    // it, but a surface with no Unicode at all still needs a mark.
    $this->assertSame('ⁱ', Ansi::strip((new DefaultTheme(40, ['color' => FALSE]))->helpMarker()));
    $this->assertSame('[?]', Ansi::strip((new DefaultTheme(40, ['color' => FALSE, 'unicode' => FALSE]))->helpMarker()));
  }

  public function testRenderTableDrawsAlignedGrid(): void {
    // The borderless theme falls back to the single-line box for an explicit
    // table, exactly as a bordered note does.
    $expected = [
      '┌───────┬────────┐',
      '│ Fruit │ Colour │',
      '├───────┼────────┤',
      '│ Apple │ Red    │',
      '└───────┴────────┘',
    ];

    $this->assertSame($expected, $this->plainTheme()->renderTable(['Fruit', 'Colour'], [['Apple', 'Red']]));
  }

  public function testRenderTableColorsCellsAndBorders(): void {
    $theme = new DefaultTheme(40, ['color' => TRUE, 'border' => Border::Line]);
    $joined = implode("\n", $theme->renderTable(['Fruit'], [['Apple']]));

    // Colour on wraps the cells and borders in ANSI; the grid still reads.
    $this->assertStringContainsString("\033[", $joined);
    $this->assertStringContainsString('Fruit', Ansi::strip($joined));
    $this->assertStringContainsString('Apple', Ansi::strip($joined));
  }

  public function testRenderTableHonoursUnicodeOff(): void {
    $theme = new DefaultTheme(40, ['color' => FALSE, 'unicode' => FALSE, 'border' => Border::Line]);
    $lines = $theme->renderTable(['Fruit'], [['Apple']]);

    $this->assertSame('+-------+', $lines[0]);
    $this->assertStringContainsString('| Apple |', implode("\n", $lines));
  }

  public function testRenderTableCapsAtFrameWidth(): void {
    // A narrow frame shrinks the column and ellipsizes the long cell so the
    // border stays whole - the inner width here is 10 - 4 = 6.
    $theme = new DefaultTheme(10, ['color' => FALSE, 'border' => Border::Line]);
    $lines = $theme->renderTable(['Name'], [['Watermelon']]);

    foreach ($lines as $line) {
      $this->assertLessThanOrEqual(6, Ansi::width($line));
    }
    $this->assertStringContainsString('…', implode("\n", $lines));
  }

  public function testFrameShowsIndicatorsAndWindow(): void {
    $body = array_map(static fn(int $i): string => 'line' . $i, range(0, 9));

    $frame = $this->plainTheme()->renderFrame(['HEAD'], $body, ['FOOT'], new Viewport(3, TRUE, TRUE), 4);

    $this->assertStringContainsString('▲', $frame);
    $this->assertStringContainsString('▼', $frame);
    $this->assertStringContainsString('HEAD', $frame);
    $this->assertStringContainsString('FOOT', $frame);
    $this->assertStringContainsString('line3', $frame);
    $this->assertStringNotContainsString('line0', $frame);
  }

  public function testBanner(): void {
    $banner = Ansi::strip($this->plainTheme()->renderBanner("LOGO\nline", '1.2.3'));

    $this->assertStringContainsString('LOGO', $banner);
    $this->assertStringContainsString('Version: 1.2.3', $banner);

    $this->assertStringNotContainsString('Version', Ansi::strip($this->plainTheme()->renderBanner('LOGO', '')));
  }

  public function testHintsLineIsThemed(): void {
    $line = (new DefaultTheme())->renderHints(KeyMapManager::create()->navigation(), new Hint('move', Action::MoveUp, Action::MoveDown));

    // Themed with the footer role (dim gray) and composed from arrow glyphs.
    $this->assertStringContainsString("\033[90m", $line);
    $this->assertStringContainsString('↑/↓ to move', Ansi::strip($line));
  }

  public function testRenderHintsJoinsFragmentsInBothModes(): void {
    $keys = KeyMapManager::create()->forField(FieldType::Select, TRUE);
    $hints = [new Hint('select', Action::Toggle), new Hint('none/all', Action::SelectNone, Action::SelectAll)];

    $unicode = Ansi::strip((new DefaultTheme())->renderHints($keys, ...$hints));
    $this->assertSame('SPACE to select · ←/→ to none/all', $unicode);

    // The glyphs degrade with the theme's Unicode mode.
    $ascii = Ansi::strip((new DefaultTheme(76, ['unicode' => FALSE]))->renderHints($keys, ...$hints));
    $this->assertStringContainsString('</> to none/all', $ascii);
  }

  public function testRenderHintsEmptyWhenNothingBound(): void {
    $nav = KeyMapManager::create()->navigation();

    // Newline is not a navigation action, so the whole line collapses to empty.
    $this->assertSame('', (new DefaultTheme())->renderHints($nav, new Hint('newline', Action::NewLine)));
  }

  public function testRenderHelpTitlesTheFieldAndOffersWayOut(): void {
    $nav = KeyMapManager::create()->navigation();

    $help = Ansi::strip((new DefaultTheme())->renderHelp('Basket contents', "Every crate is weighed at the packing bench.\n\nLabelled before it leaves.", $nav));

    $this->assertStringContainsString('Basket contents', $help);
    $this->assertStringContainsString('Every crate is weighed at the packing bench.', $help);

    // The paragraph break survives, which is the whole reason help has a page
    // its own rather than a row under the field.
    $this->assertStringContainsString("\n\nLabelled before it leaves.", $help);
    $this->assertStringContainsString('? to close', $help);
  }

  public function testRenderHelpExpandsMarkup(): void {
    $theme = new DefaultTheme(40, ['color' => FALSE, 'markdown' => TRUE]);

    // Help is the description's voice at greater length, so it takes the same
    // markup subset rather than reading as literal source.
    $this->assertStringContainsString('Use a short name', Ansi::strip($theme->renderHelp('Name', 'Use a **short** name', KeyMapManager::create()->navigation())));
    $this->assertStringNotContainsString('**', Ansi::strip($theme->renderHelp('Name', 'Use a **short** name', KeyMapManager::create()->navigation())));
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

    $this->assertSame('↑/↓ to move', Ansi::strip($theme->keysHint($nav, 'move', Action::MoveUp, Action::MoveDown)));
    // Newline is not bound in the navigation scope, so the fragment is empty.
    $this->assertSame('', $theme->keysHint($nav, 'newline', Action::NewLine));
  }

  public function testRenderEditorDerivesHintFromKeys(): void {
    $keys = KeyMapManager::create()->forField(FieldType::Text);
    $hints = [new Hint('accept', Action::Accept), new Hint('cancel', Action::Cancel)];
    $editor = Ansi::strip((new DefaultTheme())->renderEditor('Name', 'value', $hints, $keys));

    // The hint reflects the active bindings.
    $this->assertStringContainsString('↵ to accept', $editor);
    $this->assertStringContainsString('ESC to cancel', $editor);
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

    // The fragments arrive already styled by their own atoms, so the line only
    // styles what it adds: the separator between them.
    $this->assertStringContainsString((new DefaultTheme())->legendSeparator(), $line);

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
    $this->assertSame('x', $this->plainTheme()->dim('x'));
  }

}
