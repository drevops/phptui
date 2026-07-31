<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Block;

use DrevOps\Tui\Block\AbstractBlock;
use DrevOps\Tui\Block\Actions;
use DrevOps\Tui\Block\Breadcrumb;
use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Legend;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Progress;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Model\TableSpec;
use DrevOps\Tui\Tests\Traits\ResetsTranslatorTrait;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Translation\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the blocks: each composes the theme's elements into its own output.
 */
#[CoversClass(AbstractBlock::class)]
#[CoversClass(Breadcrumb::class)]
#[CoversClass(Legend::class)]
#[CoversClass(Markup::class)]
#[CoversClass(Actions::class)]
#[CoversClass(Progress::class)]
#[Group('block')]
final class BlockTest extends TestCase {

  use ResetsTranslatorTrait;

  public function testBreadcrumbJoinsItsSegments(): void {
    $theme = $this->theme();

    $this->assertSame('Orchard › Delivery', (new Breadcrumb('Orchard', 'Delivery'))->render($theme));
  }

  public function testBreadcrumbOfOneSegmentDrawsNoSeparator(): void {
    $this->assertSame('Orchard', (new Breadcrumb('Orchard'))->render($this->theme()));
  }

  public function testBreadcrumbRedrawsItselfAsTheTrailChanges(): void {
    $breadcrumb = new Breadcrumb('Orchard');

    $this->assertSame('Orchard › Delivery', $breadcrumb->trail('Orchard', 'Delivery')->render($this->theme()));
  }

  public function testLegendReadsAsKeyThenWhatItDoes(): void {
    $legend = (new Legend())->entry('↵', 'accept')->entry('ESC', 'cancel');

    $this->assertSame('↵ to accept · ESC to cancel', $legend->render($this->theme()));
  }

  public function testLegendWithNoKeysDrawsNothing(): void {
    $this->assertSame('', (new Legend())->render($this->theme()));
  }

  public function testLegendReadsItsKeysOutOfTheBindingsItAdvertises(): void {
    $keys = KeyMapManager::create()->navigation();
    $legend = (new Legend())->advertise($keys, new Hint('move', Action::MoveUp, Action::MoveDown), new Hint('select', Action::Activate));

    // The glyph comes from the live binding rather than from a second copy of
    // it, so a retuned key changes the line that advertises it.
    $this->assertSame('↑/↓ to move · ↵ to select', $legend->render($this->theme()));
  }

  public function testLegendDropsFragmentWhoseActionsNothingReaches(): void {
    $keys = KeyMapManager::create()->navigation();
    $legend = (new Legend())->advertise($keys, new Hint('grab', Action::Grab), new Hint('quit', Action::Quit));

    // Nothing in this scope grabs, so the fragment is dropped rather than drawn
    // as a label with no key in front of it.
    $this->assertSame('Q to quit', $legend->render($this->theme()));
  }

  public function testAdvertisedKeysReplaceTheOnesWrittenByHand(): void {
    $legend = (new Legend())->entry('↵', 'accept');

    $legend->advertise(KeyMapManager::create()->navigation(), new Hint('quit', Action::Quit));
    $this->assertSame('Q to quit', $legend->render($this->theme()));

    $this->assertSame('', $legend->clear()->render($this->theme()));
  }

  public function testAdvertisedKeysAreDrawnInTheActiveLanguage(): void {
    Translator::setShared(new Translator('uk'));

    // A fragment is translated where it is declared and the line around it
    // where it is drawn, so what reaches a reader is the active language.
    $legend = (new Legend())->advertise(KeyMapManager::create()->navigation(), new Hint('move', Action::MoveUp));

    $this->assertSame('↑ to перемістити', $legend->render($this->theme()));
  }

  public function testMarkupDrawsItsBodyAndItsTitleWhenItHasOne(): void {
    $theme = $this->theme();

    $this->assertSame('Weighed at the bench.', (new Markup('note', 'Weighed at the bench.'))->render($theme));
    $this->assertSame("Yields\nTwelve crates.", (new Markup('yields', 'Twelve crates.', 'Yields'))->render($theme));
  }

  public function testMarkupKeepsTheLineBreaksItWasGiven(): void {
    $rendered = (new Markup('note', "First.\nSecond."))->render($this->theme());

    $this->assertSame(['First.', 'Second.'], explode("\n", $rendered));
  }

  public function testMarkupInBorderIsBoxedAroundTheSameContent(): void {
    $markup = (new Markup('notice', 'Deliveries leave at dawn.', 'Notice'))->bordered();

    $this->assertTrue($markup->isBordered());

    $lines = explode("\n", $markup->render($this->theme()));

    $this->assertStringStartsWith('╭', $lines[0]);
    $this->assertStringContainsString('Notice', $lines[1]);
    $this->assertStringContainsString('Deliveries leave at dawn.', $lines[2]);
    $this->assertStringStartsWith('╰', $lines[3]);
  }

  public function testMarkupAsTableAlignsTheRowsUnderTheirHeaders(): void {
    $markup = (new Markup('yields', 'Yields per crate'))->table(['Produce', 'Crates'], [['Apple', '12'], ['Carrot', '8']]);

    $this->assertSame(['Produce', 'Crates'], $markup->tableSpec()?->headers);

    $rendered = $markup->render($this->theme());

    $this->assertStringContainsString('Yields per crate', $rendered);
    $this->assertStringContainsString('│ Produce │ Crates │', $rendered);
    $this->assertStringContainsString('│ Apple   │ 12     │', $rendered);
    $this->assertStringContainsString('│ Carrot  │ 8      │', $rendered);
  }

  public function testMarkupWithNothingToSayDrawsNoCardAroundIt(): void {
    // A card with neither a title, a body nor a grid has nothing to frame, so
    // it draws nothing rather than an empty box.
    $this->assertSame('', (new Markup('spacer', ''))->bordered()->render($this->theme()));
  }

  public function testMarkupCarriesNoTableUntilItIsGivenOne(): void {
    $this->assertNotInstanceOf(TableSpec::class, (new Markup('yields', 'Yields per crate'))->tableSpec());
    $this->assertFalse((new Markup('yields', 'Yields per crate'))->isBordered());
  }

  public function testProgressTrailsTheIndicatorWithWhatTheWorkIsDoing(): void {
    $bar = (new Progress('packing', 'Packing crates'))->steps(10)->advance(4, 'Apples');
    $spinner = (new Progress('fetching', 'Fetching the price list'))->label('Orchards');

    $this->assertSame('Packing crates [████░░░░░░] 4/10 Apples', $bar->render($this->theme()));
    $this->assertSame('⠋ Fetching the price list Orchards', $spinner->render($this->theme()));
  }

  public function testActionsFrameEveryLabelAndMarkTheFocusedOne(): void {
    $actions = (new Actions())->action('submit', 'Submit')->action('cancel', 'Cancel');

    $this->assertSame('[ Submit ]  [ Cancel ]', $actions->render($this->theme()));
  }

  public function testActionsTakeAnyLabelsTheFormDeclares(): void {
    $actions = (new Actions())->action('save', 'Save draft')->action('submit', 'Submit');

    $this->assertSame(['save', 'submit'], $actions->names());
  }

  public function testProgressDrawsBarWhenTheWorkReportsTotal(): void {
    $progress = (new Progress('packing', 'Packing crates'))->steps(10)->advance(4);

    $this->assertSame('Packing crates [████░░░░░░] 4/10', $progress->render($this->theme()));
  }

  public function testProgressDrawsSpinnerWhenTheLengthIsUnknown(): void {
    $progress = new Progress('fetching', 'Fetching the price list');

    $this->assertSame('⠋ Fetching the price list', $progress->render($this->theme()));
  }

  public function testProgressAdvancesNoFurtherThanItsTotal(): void {
    $progress = (new Progress('packing', 'Packing'))->steps(3)->advance(9);

    $this->assertSame('Packing [██████████] 3/3', $progress->render($this->theme()));
  }

  #[DataProvider('dataProviderEveryBlockRefusesThemeThatCannotDrawIt')]
  public function testEveryBlockRefusesThemeThatCannotDrawIt(\Closure $make, string $says): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($says);

    // A theme is only required to be a ThemeInterface; the elements a block
    // needs are declared separately, so one that declares none cannot draw it -
    // and the failure names both rather than leaving a blank line.
    $make()->render($this->createStub(ThemeInterface::class));
  }

  public static function dataProviderEveryBlockRefusesThemeThatCannotDrawIt(): \Iterator {
    yield 'breadcrumb' => [static fn(): Breadcrumb => new Breadcrumb('Orchard'), 'cannot draw a breadcrumb'];
    yield 'legend' => [static fn(): Legend => (new Legend())->entry('↵', 'accept'), 'cannot draw a legend'];
    yield 'markup' => [static fn(): Markup => new Markup('intro', 'Pick the produce.'), 'cannot draw markup'];
    yield 'actions' => [static fn(): Actions => (new Actions())->action('submit', 'Submit'), 'cannot draw actions'];
    yield 'progress' => [static fn(): Progress => new Progress('packing', 'Packing'), 'cannot draw progress'];
    yield 'panel' => [static fn(): Panel => new Panel('main', 'Delivery'), 'cannot draw a panel'];
    yield 'field' => [static fn(): Field => new Field('courier', 'Courier'), 'cannot draw a field'];
  }

  /**
   * A theme with colour off, so the assertions read as plain strings.
   */
  protected function theme(): DefaultTheme {
    return new DefaultTheme(80, ['color' => FALSE]);
  }

}
