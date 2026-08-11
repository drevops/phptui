<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit;

use DrevOps\Tui\Block\Actions;
use DrevOps\Tui\Block\Breadcrumb;
use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\FieldType;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Option;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Progress as ProgressBlock;
use DrevOps\Tui\Block\TableSpec;
use DrevOps\Tui\Block\Template;
use DrevOps\Tui\Builder\Fixup;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Derive\Deriver;
use DrevOps\Tui\Discovery\Dotenv;
use DrevOps\Tui\Field\Text;
use DrevOps\Tui\Field\Textarea;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Primitive\Output;
use DrevOps\Tui\Primitive\Progress;
use DrevOps\Tui\Primitive\Status;
use DrevOps\Tui\Screen\ExternalEditor;
use DrevOps\Tui\Terminal\Ansi;
use DrevOps\Tui\Testing\BufferedTerminal;
use DrevOps\Tui\Tests\Traits\BuildsThemesTrait;
use DrevOps\Tui\Tests\Traits\IsolatesEnvTrait;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Tui;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests that consumer text never carries a control byte to the terminal.
 *
 * Text a form is built from, and every value collected into one, is filtered
 * where it is taken in rather than where it is drawn - so the assertions here
 * read what a block holds and what a collection hands back, not only what
 * reaches the screen.
 */
#[CoversClass(Actions::class)]
#[CoversClass(Ansi::class)]
#[CoversClass(Breadcrumb::class)]
#[CoversClass(Deriver::class)]
#[CoversClass(ExternalEditor::class)]
#[CoversClass(Field::class)]
#[CoversClass(Fixup::class)]
#[CoversClass(Markup::class)]
#[CoversClass(Option::class)]
#[CoversClass(Output::class)]
#[CoversClass(Panel::class)]
#[CoversClass(Progress::class)]
#[CoversClass(ProgressBlock::class)]
#[CoversClass(TableSpec::class)]
#[CoversClass(Template::class)]
#[CoversClass(Textarea::class)]
#[Group('terminal')]
final class ControlBytesTest extends TestCase {

  use BuildsThemesTrait;
  use IsolatesEnvTrait;

  /**
   * The screen clear a form built from untrusted content would otherwise emit.
   */
  protected const string CLEAR = "\033[2J";

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->restoreEnv();

    parent::tearDown();
  }

  public function testMarkupBodyDrawsItsTextWithoutTheEscape(): void {
    $rendered = (new Markup('notice', 'Deliveries ' . self::CLEAR . ' leave at dawn.'))->render($this->plain());

    $this->assertStringNotContainsString("\033", $rendered);
    $this->assertStringContainsString('Deliveries', $rendered);
    $this->assertStringContainsString('leave at dawn.', $rendered);
  }

  public function testFieldRowDrawsItsLabelAndValueWithoutTheEscape(): void {
    $field = (new Field('courier', 'Cou' . self::CLEAR . 'rier'))->default('Valley ' . self::CLEAR . ' Runs');

    $this->assertSame('  Cou[2Jrier  Valley [2J Runs', $field->render($this->plain()));
  }

  #[DataProvider('dataProviderDeclaredTextIsFiltered')]
  public function testDeclaredTextIsFiltered(\Closure $declare, string $expected): void {
    $this->assertSame($expected, $declare(self::CLEAR));
  }

  /**
   * Data provider for testDeclaredTextIsFiltered().
   *
   * @return \Iterator<string, array{\Closure, string}>
   *   What declares the text and holds it, and the text it ends up holding.
   */
  public static function dataProviderDeclaredTextIsFiltered(): \Iterator {
    yield 'a markup body' => [static fn(string $b): string => (new Markup('n', 'Figs' . $b))->bodyText(), 'Figs[2J'];
    yield 'a markup title' => [static fn(string $b): string => (new Markup('n', ''))->title('Figs' . $b)->titleText(), 'Figs[2J'];
    yield 'a markup body set after construction' => [static fn(string $b): string => (new Markup('n', ''))->body('Figs' . $b)->bodyText(), 'Figs[2J'];
    yield 'a field label' => [static fn(string $b): string => (new Field('f', 'Figs' . $b))->label(), 'Figs[2J'];
    yield 'a field description' => [static fn(string $b): string => (new Field('f', 'F'))->description('Figs' . $b)->descriptionText(), 'Figs[2J'];
    yield 'a field help text' => [static fn(string $b): string => (new Field('f', 'F'))->help('Figs' . $b)->helpText(), 'Figs[2J'];
    yield 'a field placeholder' => [static fn(string $b): string => (new Field('f', 'F'))->placeholder('Figs' . $b)->placeholderText(), 'Figs[2J'];
    yield 'a field badge' => [static fn(string $b): string => (new Field('f', 'F'))->badge('Figs' . $b)->badgeText(), 'Figs[2J'];
    yield 'a required message' => [static fn(string $b): string => (new Field('f', 'F'))->required(TRUE, 'Figs' . $b)->requiredMessage(), 'Figs[2J'];
    yield 'a stated constraint' => [static fn(string $b): string => (string) (new Field('f', 'F'))->constrain('Figs' . $b)->constraint(), 'Figs[2J'];
    yield 'a rating caption' => [static fn(string $b): string => (new Field('f', 'F', FieldType::Rating))->captions([1 => 'Figs' . $b])->ratingCaptions()[1], 'Figs[2J'];
    yield 'an option label' => [static fn(string $b): string => (new Option('v', 'Figs' . $b))->label, 'Figs[2J'];
    yield 'an option value' => [static fn(string $b): string => (new Option('figs' . $b, 'Figs'))->value, 'figs[2J'];
    yield 'an option description' => [static fn(string $b): string => (new Option('v', 'V', 'Figs' . $b))->description, 'Figs[2J'];
    yield 'a disabled reason' => [static fn(string $b): string => (new Option('v', 'V', '', disabled: TRUE, disabled_reason: 'Figs' . $b))->disabledReason, 'Figs[2J'];
    yield 'an entry declared on a field' => [static fn(string $b): string => (string) (new Field('f', 'F', FieldType::Select))->entry('v', 'Figs' . $b)->optionOf('v')?->label, 'Figs[2J'];
    yield 'a heading between entries' => [static fn(string $b): string => (new Field('f', 'F', FieldType::Select))->heading('Figs' . $b)->options()[0]->label, 'Figs[2J'];
    yield 'a panel title' => [static fn(string $b): string => (new Panel('p', 'Figs' . $b))->title(), 'Figs[2J'];
    yield 'a panel description' => [static fn(string $b): string => (new Panel('p', 'P'))->description('Figs' . $b)->descriptionText(), 'Figs[2J'];
    yield 'a breadcrumb segment' => [static fn(string $b): string => (new Breadcrumb('Figs' . $b))->render(new DefaultTheme(80, ['color' => FALSE])), 'Figs[2J'];
    yield 'an action label' => [static fn(string $b): string => trim((new Actions())->action('go', 'Figs' . $b)->render(new DefaultTheme(80, ['color' => FALSE]))), '[ Figs[2J ]'];
    yield 'a withheld action reason' => [static fn(string $b): string => (string) (new Actions())->refuse('Figs' . $b)->refusal(), 'Figs[2J'];
    yield 'a progress caption' => [static fn(string $b): string => (new ProgressBlock('p', 'Figs' . $b))->caption(), 'Figs[2J'];
    yield 'a progress label' => [static fn(string $b): string => (new ProgressBlock('p', 'P'))->label('Figs' . $b)->labelText(), 'Figs[2J'];
    yield 'a progress label reported mid-run' => [static fn(string $b): string => (new ProgressBlock('p', 'P'))->steps(2)->advance(1, 'Figs' . $b)->labelText(), 'Figs[2J'];
    yield 'a grid header cell' => [static fn(string $b): string => (new TableSpec(['Figs' . $b], []))->headers[0], 'Figs[2J'];
    yield 'a grid body cell' => [static fn(string $b): string => (new TableSpec([], [['Figs' . $b]]))->rows[0][0], 'Figs[2J'];
    yield 'a template literal' => [static fn(string $b): string => (new Template('Figs' . $b . '-{{crate}}'))->literalAt(0), 'Figs[2J-'];
    yield 'a template slot label' => [static fn(string $b): string => (new Template('{{crate}}', ['crate' => 'Figs' . $b]))->labelOf('crate'), 'Figs[2J'];
  }

  #[DataProvider('dataProviderFieldValueIsFilteredWhereverItIsSet')]
  public function testFieldValueIsFilteredWhereverItIsSet(\Closure $set, mixed $expected): void {
    $this->assertSame($expected, $set(self::CLEAR));
  }

  /**
   * Data provider for testFieldValueIsFilteredWhereverItIsSet().
   *
   * @return \Iterator<string, array{\Closure, mixed}>
   *   What sets the value and reads it back, and the value it settles on.
   */
  public static function dataProviderFieldValueIsFilteredWhereverItIsSet(): \Iterator {
    yield 'a declared default' => [static fn(string $b): mixed => (new Field('f', 'F'))->default('Figs' . $b)->value(), 'Figs[2J'];
    yield 'a value accepted outright' => [
      static function (string $b): mixed {
        $field = new Field('f', 'F');
        $field->accept('Figs' . $b);

        return $field->value();
      },
      'Figs[2J',
    ];
    yield 'a list of values' => [static fn(string $b): mixed => (new Field('f', 'F', FieldType::Select))->multiple()->default(['Figs' . $b, 'Plums'])->value(), ['Figs[2J', 'Plums']];
    yield 'a value that is not text at all' => [static fn(string $b): mixed => (new Field('f', 'F', FieldType::Number))->default(12)->value(), 12];
    yield 'a value a fix-up writes' => [static fn(string $b): mixed => (new Fixup(set: 'f', to: 'Figs' . $b))->to, 'Figs[2J'];
  }

  public function testNormalizedValueIsFiltered(): void {
    $field = (new Field('courier', 'Courier'))->transform(static fn(mixed $value): string => 'Valley' . self::CLEAR . ' Runs');
    $field->accept('Coast Runs');

    $this->assertSame('Valley[2J Runs', $field->value());
  }

  public function testNormalizedValueIsFilteredWithNoScreen(): void {
    $form = Form::create('Orchard')->panel('delivery', 'Delivery', static function (PanelBuilder $panel): void {
      $panel->text('courier', 'Courier')->default('')->transform(static fn(mixed $value): string => 'Valley' . self::CLEAR . ' Runs');
    });

    $answers = (new Tui($form))->collect(json_encode(['courier' => 'Coast Runs'], JSON_THROW_ON_ERROR));

    $this->assertSame('Valley[2J Runs', $answers->value('courier'));
  }

  public function testComputedValueIsFiltered(): void {
    $form = Form::create('Orchard')->panel('delivery', 'Delivery', static function (PanelBuilder $panel): void {
      $panel->text('courier', 'Courier')->default('Valley Runs');
      $panel->text('slug', 'Slug')->derive(new Derive('{{courier}}' . self::CLEAR));
    });

    $this->assertSame('Valley Runs[2J', (new Tui($form))->collect()->value('slug'));
  }

  /**
   * Tests that a completion candidate is filtered from either source.
   *
   * @param list<string>|\Closure $source
   *   The declared candidates, or what computes them.
   */
  #[DataProvider('dataProviderCompletionCandidatesAreFiltered')]
  public function testCompletionCandidatesAreFiltered(array|\Closure $source): void {
    $field = (new Field('courier', 'Courier'))->complete($source);
    $editor = $field->open()->editor();

    $this->assertInstanceOf(Text::class, $editor);

    $editor->handle(Key::char('V'));
    $editor->applyCompletion();

    // The candidate lands in the buffer whole and is previewed as ghost text,
    // so a control byte in it would reach the terminal by either route.
    $this->assertSame('Valley[2J Runs', $editor->buffer());
    $this->assertStringNotContainsString("\033", $field->render($this->plain()));
  }

  /**
   * Data provider for testCompletionCandidatesAreFiltered().
   *
   * @return \Iterator<string, array{array|\Closure}>
   *   The declared candidates, or what computes them.
   */
  public static function dataProviderCompletionCandidatesAreFiltered(): \Iterator {
    yield 'a declared list' => [['Valley' . self::CLEAR . ' Runs']];
    yield 'a rule that computes one' => [static fn(array $answers): array => ['Valley' . self::CLEAR . ' Runs']];
  }

  public function testDraftIsFilteredAsItIsTyped(): void {
    $field = (new Field('note', 'Note'))->draft('Figs' . self::CLEAR);

    $this->assertStringNotContainsString("\033", $field->open()->render($this->plain()));
  }

  public function testMultiLineValueKeepsItsNewlinesAndTabs(): void {
    $field = (new Field('note', 'Note', FieldType::Textarea))->default("Figs\tand plums\nleave at dawn." . self::CLEAR);

    $this->assertSame("Figs\tand plums\nleave at dawn.[2J", $field->value());
  }

  public function testAnEntryDeclaredTwiceReplacesTheRowAfterFiltering(): void {
    $field = (new Field('basket', 'Basket', FieldType::Select))
      ->entry('apple', 'Apple')
      ->entry("apple\x00", 'Bruised apple');

    // The set stays unique: the filtered value matches the row already there.
    $this->assertCount(1, $field->options());
    $this->assertSame('Bruised apple', $field->optionOf('apple')?->label);
  }

  public function testMultiLineMarkupKeepsItsLines(): void {
    $markup = new Markup('notice', "Figs\r\nand plums" . self::CLEAR . "\rleave at dawn.");

    $this->assertSame("Figs\nand plums[2J\nleave at dawn.", $markup->bodyText());
  }

  public function testRefusedValueReopensTheEditorOnTheFilteredDraft(): void {
    $field = (new Field('courier', 'Courier'))->validate(static fn(mixed $value): string => 'Coast Runs do not deliver here.');
    $field->open();
    $field->draft('Figs' . self::CLEAR);
    $field->capture(Key::char('x'));

    // The refusal leaves the field open on what was offered, so what is drawn
    // beside the reason must be the filtered draft rather than the raw offer.
    $this->assertStringNotContainsString("\033", $field->render($this->plain()));
  }

  public function testTypingAnUnboundControlKeyInsertsNothing(): void {
    $textarea = new Textarea('Figs');
    $textarea->handle(Key::char("\007"));

    $this->assertSame('Figs', $textarea->buffer());

    $textarea->handle(Key::char('!'));
    $this->assertSame('Figs!', $textarea->buffer());
  }

  public function testValueFromAnEnvironmentVariableIsFiltered(): void {
    $this->putEnv('CRATE_COURIER', 'Valley' . self::CLEAR . ' Runs');

    $answers = (new Tui($this->form(), env_prefix: 'CRATE_'))->collect();

    $this->assertSame('Valley[2J Runs', $answers->value('courier'));
  }

  public function testValueFromSuppliedPayloadIsFiltered(): void {
    $answers = (new Tui($this->form()))->collect(json_encode(['courier' => 'Valley' . self::CLEAR . ' Runs'], JSON_THROW_ON_ERROR));

    $this->assertSame('Valley[2J Runs', $answers->value('courier'));
  }

  public function testValueFromDiscoverySourceIsFiltered(): void {
    vfsStream::setup('orchard', NULL, ['.env' => 'COURIER=Valley' . self::CLEAR . ' Runs']);

    $form = Form::create('Orchard')->panel('delivery', 'Delivery', static function (PanelBuilder $panel): void {
      $panel->text('courier', 'Courier')->default('')->discover(new Dotenv('COURIER'));
    });

    $answers = (new Tui($form))->collect('', vfsStream::url('orchard'), TRUE);

    $this->assertSame('Valley[2J Runs', $answers->value('courier'));
  }

  public function testValueFromDetectorOfItsOwnIsFiltered(): void {
    $form = Form::create('Orchard')->panel('delivery', 'Delivery', static function (PanelBuilder $panel): void {
      $panel->text('courier', 'Courier')->default('')->discover(static fn(Context $context): string => 'Valley' . self::CLEAR . ' Runs');
    });

    $answers = (new Tui($form))->collect('', 'orchard', TRUE);

    $this->assertSame('Valley[2J Runs', $answers->value('courier'));
  }

  public function testComputedDefaultIsFiltered(): void {
    $form = Form::create('Orchard')->panel('delivery', 'Delivery', static function (PanelBuilder $panel): void {
      $panel->text('courier', 'Courier')->default(static fn(Context $context): string => 'Valley' . self::CLEAR . ' Runs');
    });

    $this->assertSame('Valley[2J Runs', (new Tui($form))->collect()->value('courier'));
  }

  public function testAnExternalEditorsSaveIsFiltered(): void {
    $saved = "Figs\tand plums" . self::CLEAR . "\r\nleave at dawn.\n";

    $editor = new class($saved) extends ExternalEditor {

      /**
       * Construct an editor that saves a fixed buffer.
       *
       * @param string $saved
       *   The buffer the editor writes back.
       */
      public function __construct(protected string $saved) {
      }

      /**
       * {@inheritdoc}
       */
      public function command(): string {
        return 'true';
      }

      /**
       * {@inheritdoc}
       */
      protected function spawn(string $command, string $file): int {
        file_put_contents($file, $this->saved);

        return 0;
      }

    };

    $this->assertSame("Figs\tand plums[2J\nleave at dawn.", $editor->edit('Figs'));
  }

  #[DataProvider('dataProviderTheOutputPrimitiveWritesFilteredText')]
  public function testTheOutputPrimitiveWritesFilteredText(\Closure $write, string $visible): void {
    $terminal = new BufferedTerminal();

    $write(new Output($terminal, $this->theme(color: FALSE)), self::CLEAR);

    $this->assertStringNotContainsString("\033", $terminal->output());
    $this->assertStringContainsString($visible, $terminal->output());
  }

  /**
   * Data provider for testTheOutputPrimitiveWritesFilteredText().
   *
   * @return \Iterator<string, array{\Closure, string}>
   *   What writes the text, and a fragment of what has to survive the filter.
   */
  public static function dataProviderTheOutputPrimitiveWritesFilteredText(): \Iterator {
    yield 'a box body' => [static fn(Output $out, string $b): Output => $out->box('Figs' . $b), 'Figs'];
    yield 'a box body as a list' => [static fn(Output $out, string $b): Output => $out->box(['Figs' . $b, 'Plums']), 'Plums'];
    yield 'a box title' => [static fn(Output $out, string $b): Output => $out->box('Plums', 'Figs' . $b), 'Figs'];
    yield 'a card title' => [static fn(Output $out, string $b): Output => $out->card('Figs' . $b, 'Plums'), 'Figs'];
    yield 'a card grid cell' => [static fn(Output $out, string $b): Output => $out->card('Crates', '', ['Produce'], [['Figs' . $b]]), 'Figs'];
    yield 'a grid header cell' => [static fn(Output $out, string $b): Output => $out->table(['Figs' . $b], [['12']]), 'Figs'];
    yield 'a grid body cell' => [static fn(Output $out, string $b): Output => $out->table(['Produce'], [['Figs' . $b]]), 'Figs'];
    yield 'a paragraph' => [static fn(Output $out, string $b): Output => $out->text('Figs' . $b), 'Figs'];
    yield 'a banner logo' => [static fn(Output $out, string $b): Output => $out->banner('Figs' . $b), 'Figs'];
    yield 'a banner version' => [static fn(Output $out, string $b): Output => $out->banner('Orchard', '1.2' . $b), '1.2'];
    yield 'a status message' => [static fn(Output $out, string $b): Output => $out->status(Status::Success, 'Figs' . $b), 'Figs'];
    yield 'a definition label' => [static fn(Output $out, string $b): Output => $out->definitions(['Figs' . $b => '12']), 'Figs'];
    yield 'a definition value' => [static fn(Output $out, string $b): Output => $out->definitions(['Crates' => 'Figs' . $b]), 'Figs'];
  }

  public function testTheProgressPrimitiveWritesFilteredText(): void {
    $terminal = new BufferedTerminal();

    (new Progress($terminal, $this->theme(color: FALSE), FALSE, NULL, 'Packing' . self::CLEAR . ' crates'))->run(static fn(Progress $progress): bool => TRUE);

    $this->assertSame("Packing[2J crates\n", $terminal->output());
  }

  public function testTheProgressPrimitiveFiltersTheLabelReportedMidRun(): void {
    $terminal = new BufferedTerminal();

    (new Progress($terminal, $this->theme(color: FALSE), TRUE, 2, 'Packing crates'))->run(static function (Progress $progress): bool {
      $progress->advance('Figs' . self::CLEAR);

      return TRUE;
    });

    $this->assertStringNotContainsString(self::CLEAR, $terminal->output());
    $this->assertStringContainsString('Figs[2J', $terminal->output());
  }

  /**
   * A borderless, unstyled theme, so an assertion reads the content alone.
   *
   * @return \DrevOps\Tui\Theme\DefaultTheme
   *   The theme.
   */
  protected function plain(): DefaultTheme {
    return new DefaultTheme(80, ['color' => FALSE]);
  }

  /**
   * A one-question form to collect a value into.
   *
   * @return \DrevOps\Tui\Builder\Form
   *   The form.
   */
  protected function form(): Form {
    return Form::create('Orchard')->panel('delivery', 'Delivery', static function (PanelBuilder $panel): void {
      $panel->text('courier', 'Courier')->default('');
    });
  }

}
