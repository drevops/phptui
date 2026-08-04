<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Testing;

use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\CancelException;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Model\Fixup;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Collector;
use DrevOps\Tui\Screen\Layout\AbstractLayout;
use DrevOps\Tui\Screen\Layout\PanelLayout;
use DrevOps\Tui\Testing\ScreenTester;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\DefaultTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the harness that drives a screen from scripted keystrokes.
 */
#[CoversClass(ScreenTester::class)]
#[Group('screen')]
final class ScreenTesterTest extends TestCase {

  public function testEveryFrameThatReachedTheTerminalIsReadableAfterwards(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')));
    $tester->run(Key::named(KeyName::Enter), Key::named(KeyName::Escape));

    $this->assertCount(3, $tester->frames());
    $this->assertStringContainsString('to accept', $tester->frame(1));

    // A negative index counts back from the frame the session ended on.
    $this->assertSame($tester->frame(2), $tester->frame(-1));
  }

  public function testAskingForFrameThatWasNeverDrawnSaysSo(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')));
    $tester->run();

    $this->expectException(\OutOfBoundsException::class);
    $this->expectExceptionMessage('The session drew 1 frames, so there is none at index 4.');

    $tester->frame(4);
  }

  public function testResultsCannotBeReadBeforeTheSessionRuns(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')));

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Call run() before reading the results.');

    $tester->answers();
  }

  public function testOutputCannotBeReadBeforeTheSessionRuns(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')));

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Call run() before reading the results.');

    $tester->output();
  }

  public function testWhatWasOnScreenSurvivesAnAbandonedSession(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')));

    try {
      $tester->run(Key::named(KeyName::Down), Key::named(KeyName::Right), Key::named(KeyName::Enter));
    }
    catch (CancelException) {
      // The session collected nothing, but it drew plenty.
    }

    $this->assertStringContainsString('[ Cancel ]', $tester->display());

    $this->expectException(\LogicException::class);

    $tester->answers();
  }

  public function testDisplayIsTheOutputWithoutItsEscapeSequences(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')))->options(['color' => TRUE]);
    $tester->run();

    // Colour on, so the raw output carries the sequences the display drops.
    $this->assertStringContainsString("\033[", $tester->output());
    $this->assertStringNotContainsString("\033[", $tester->display());
  }

  public function testTheThemeTheBlocksDrawThroughCanBeReplaced(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')))
      ->theme(new DefaultTheme(40, ['color' => FALSE, 'unicode' => FALSE]));

    $tester->run();

    // The ASCII stand-in rather than the glyph, because the theme said so.
    $this->assertStringContainsString('> Courier', $tester->frame());
  }

  public function testTheKeysTheScreenAnswersToCanBeRetuned(): void {
    $weight = new Field('weight', 'Basket weight');
    $tester = $this->tester($this->panel(new Field('courier', 'Courier'), $weight))->keys(KeyMapManager::create('vim'));

    $tester->run(Key::char('j'));

    $this->assertTrue($weight->isFocused());
  }

  public function testWhatResolvesTheOpeningAnswersCanBeSupplied(): void {
    $panel = $this->panel(
      (new Field('courier', 'Courier'))->default('Valley Runs'),
      (new Field('label', 'Crate label'))->default('none'),
    );

    $tester = $this->tester($panel)->collector(new Collector(NULL, [new Fixup(set: 'label', from: 'courier')]));

    $this->assertSame('Valley Runs', $tester->run()->value('label'));
  }

  public function testTheRunTheSessionBelongsToCanBeSet(): void {
    $panel = $this->panel((new Field('courier', 'Courier'))->discover(static fn(Context $context): string => 'Runs from ' . $context->directory));

    $tester = $this->tester($panel)->context(new Context('/orchard', [], TRUE));
    $answers = $tester->run();

    $this->assertSame('Runs from /orchard', $answers->value('courier'));
    $this->assertSame(Provenance::Detected, $answers->provenanceOf('courier'));
  }

  public function testValuesCanBeSuppliedAsTheCallerOfTheFormWould(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')))->supplied(['courier' => 'Coast Runs']);

    $this->assertSame('Coast Runs', $tester->run()->value('courier'));
  }

  public function testTheLayoutTheScreenIsArrangedByCanBePicked(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')))->layout(TallHeaderLayoutFixture::class);
    $tester->run();

    $lines = explode("\n", $tester->frame());

    // The header takes two rows here, so what follows it starts a row later.
    $this->assertSame('Delivery', rtrim($lines[0]));
    $this->assertSame('', rtrim($lines[1]));
    $this->assertStringContainsString('Courier', $lines[2]);
  }

  public function testTheFrameCanBeDrawnInsideBorder(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')))->border(Border::Line);
    $tester->run();

    $this->assertStringStartsWith('┌', $tester->frame());
  }

  public function testTheLastFrameCanBeLeftOnScreen(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')))->clearOnExit(FALSE);
    $tester->run();

    // Nothing was written after the frame, so it is still what a reader sees.
    $this->assertStringContainsString($tester->frame(), $tester->display());
    $this->assertSame(1, substr_count($tester->display(), 'Delivery'));
  }

  public function testTheTerminalSizeDecidesWhatFits(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')))->rows(5)->cols(30);
    $tester->run();

    $lines = explode("\n", $tester->frame());

    $this->assertCount(5, $lines);
    $this->assertSame(30, Ansi::width($lines[0]));
  }

  public function testWidthNoFrameCouldBeLaidOutToIsRefused(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The terminal width must be at least 1 column.');

    $this->tester($this->panel(new Field('courier', 'Courier')))->cols(0);
  }

  /**
   * A tester over a panel, sized so a frame reads the same everywhere.
   */
  protected function tester(Panel $panel): ScreenTester {
    return (new ScreenTester($panel))->rows(10)->cols(60);
  }

  /**
   * A panel holding the given blocks in its content region.
   */
  protected function panel(object ...$blocks): Panel {
    $panel = (new Panel('main', 'Delivery'))->layout(new PanelLayout());

    foreach ($blocks as $block) {
      /** @var \DrevOps\Tui\Block\BlockInterface $block */
      $panel->in('content')->add($block);
    }

    return $panel;
  }

}

/**
 * A layout whose header takes two rows rather than one.
 */
final class TallHeaderLayoutFixture extends AbstractLayout {

  /**
   * Construct the layout.
   */
  public function __construct() {
    parent::__construct(Axis::Rows);

    $this->region('header')->fixed(2);
    $this->region('content')->scrolls();
    $this->region('footer')->fixed(1);
  }

}
