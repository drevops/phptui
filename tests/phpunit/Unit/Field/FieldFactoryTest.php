<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Field;

use DrevOps\Tui\Block\DateBounds;
use DrevOps\Tui\Block\Field as BlockField;
use DrevOps\Tui\Block\FieldType;
use DrevOps\Tui\Block\NumberBounds;
use DrevOps\Tui\Block\Template as TemplateModel;
use DrevOps\Tui\Field\Calendar;
use DrevOps\Tui\Field\Capability\QueryOptionsCapableInterface;
use DrevOps\Tui\Field\Confirm;
use DrevOps\Tui\Field\FieldFactory;
use DrevOps\Tui\Field\FilePicker;
use DrevOps\Tui\Field\Number;
use DrevOps\Tui\Field\Password;
use DrevOps\Tui\Field\Pause;
use DrevOps\Tui\Field\Rating;
use DrevOps\Tui\Field\Reorder;
use DrevOps\Tui\Field\Search;
use DrevOps\Tui\Field\Select;
use DrevOps\Tui\Field\Suggest;
use DrevOps\Tui\Field\Template;
use DrevOps\Tui\Field\Text;
use DrevOps\Tui\Field\Textarea;
use DrevOps\Tui\Field\Toggle;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Terminal\Ansi;
use DrevOps\Tui\Theme\DefaultTheme;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the field factory.
 */
#[CoversClass(FieldFactory::class)]
#[Group('field')]
final class FieldFactoryTest extends TestCase {

  /**
   * Tests the value each kind of block opens its field on.
   *
   * @param \DrevOps\Tui\Block\Field $block
   *   The block to open.
   * @param mixed $current
   *   The value it is handed.
   * @param mixed $expected
   *   The value the field opens on.
   */
  #[DataProvider('dataProviderSeedsValueFromCurrent')]
  public function testSeedsValueFromCurrent(BlockField $block, mixed $current, mixed $expected): void {
    $this->assertSame($expected, (new FieldFactory())->open($block, $current)->value());
  }

  /**
   * Data provider for testSeedsValueFromCurrent().
   *
   * @return \Iterator<string, array{\DrevOps\Tui\Block\Field, mixed, mixed}>
   *   The block, the value it is handed and the value the field opens on.
   */
  public static function dataProviderSeedsValueFromCurrent(): \Iterator {
    yield 'text' => [new BlockField('f', 'F'), 'Acme', 'Acme'];
    yield 'number from an integer' => [new BlockField('f', 'F', FieldType::Number), 8080, 8080];
    yield 'number from a non-numeric' => [new BlockField('f', 'F', FieldType::Number), 'oops', 0];
    yield 'rating from a non-numeric' => [self::ratingBlock(), 'oops', 1];
    yield 'template from the assembled value' => [self::templateBlock(), 'one-two', 'one-two'];
    yield 'template from a non-string' => [self::templateBlock(), 42, '-'];
    // The seed order flows through: the given value first, the remaining
    // option appended to complete the ranking.
    yield 'reorder completes the ranking' => [self::blockWithOptions(FieldType::Reorder), ['b'], ['b', 'a']];
    yield 'multiple from a non-list' => [self::blockWithOptions(FieldType::Select)->multiple(), 'notalist', []];
    // A multiple choice block opens on the list value, proving the multiple
    // flag reaches the select and search fields.
    yield 'multiple select' => [self::blockWithOptions(FieldType::Select)->multiple(), ['a', 'b'], ['a', 'b']];
    yield 'multiple search' => [self::blockWithOptions(FieldType::Search)->multiple(), ['a'], ['a']];
  }

  public function testFilePickerSeedsValueFromCurrent(): void {
    // Kept out of the seeding provider: the picker reads a directory, and a
    // virtual one lists nothing without depending on what the host happens to
    // have on disk.
    vfsStream::setup('crates');
    $start = vfsStream::url('crates');

    // A current path outside the start is ignored and the empty directory
    // lists nothing, so the value is empty.
    $single = (new BlockField('f', 'F', FieldType::FilePicker))->startIn($start);
    $this->assertSame('', (new FieldFactory())->open($single, 'x')->value());

    // The multiple picker yields a list seeded from the current value, proving
    // the multiple flag is threaded through.
    $multi = (new BlockField('g', 'G', FieldType::FilePicker))->startIn($start)->multiple();
    $this->assertSame(['/a', '/b'], (new FieldFactory())->open($multi, ['/a', '/b'])->value());
  }

  public function testDateWithNonStringCurrentOpensOnToday(): void {
    // Kept out of the seeding provider: a static provider would fix "today" at
    // collection time. Bracketing the call keeps a midnight rollover between
    // the two reads from failing the run.
    $before = (new \DateTimeImmutable('today'))->format('Y-m-d');
    $field = (new FieldFactory())->open(new BlockField('f', 'F', FieldType::Calendar), 42);
    $after = (new \DateTimeImmutable('today'))->format('Y-m-d');

    $this->assertContains($field->value(), [$before, $after]);
  }

  public function testPasswordFlagsPassedThrough(): void {
    $block = (new BlockField('f', 'F', FieldType::Password))->revealable()->confirmation();

    $field = (new FieldFactory())->open($block, 'secret');

    // Revealable shows through the reveal hint the field contributes.
    $labels = array_map(static fn(Hint $hint): string => $hint->label, $field->hints());
    $this->assertContains('reveal', $labels);

    // Confirm shows through the two-step flow: the first Enter does not accept.
    $field->handle(Key::named(KeyName::Enter));
    $this->assertFalse($field->isComplete());
  }

  public function testNumberBoundsPassedThrough(): void {
    $block = (new BlockField('f', 'F', FieldType::Number))->bounds(new NumberBounds(0, 10));

    $field = (new FieldFactory())->open($block, 5);

    // Bounds show through the adjust hint the field contributes and stepping.
    $labels = array_map(static fn(Hint $hint): string => $hint->label, $field->hints());
    $this->assertContains('adjust', $labels);
    $field->handle(Key::named(KeyName::Up));
    $this->assertSame(6, $field->value());
  }

  public function testRatingScaleAndCaptionsPassedThrough(): void {
    $field = (new FieldFactory())->open(self::ratingBlock(), 3);

    $this->assertStringContainsString('●●●○○ 3/5 Fair', Ansi::strip($field->view(new DefaultTheme())));
  }

  public function testDateBoundsPassedThrough(): void {
    $block = (new BlockField('f', 'F', FieldType::Calendar))->dates(new DateBounds(new \DateTimeImmutable('2026-07-10'), new \DateTimeImmutable('2026-07-20')));

    $field = (new FieldFactory())->open($block, '2026-07-01');

    // The seed is clamped into the block's declared range.
    $this->assertSame('2026-07-10', $field->value());
  }

  /**
   * Tests when a textarea offers the handoff to the reader's own editor.
   *
   * @param bool $opted_in
   *   Whether the block opted in.
   * @param bool $available
   *   Whether an editor is launchable here.
   * @param bool $expected
   *   Whether the handoff is offered.
   */
  #[DataProvider('dataProviderTextareaExternalEditorHandoff')]
  public function testTextareaExternalEditorHandoff(bool $opted_in, bool $available, bool $expected): void {
    $block = (new BlockField('f', 'F', FieldType::Textarea))->externalEditor($opted_in);

    $field = (new FieldFactory(externalEditorAvailable: $available))->open($block, 'x');
    $this->assertInstanceOf(Textarea::class, $field);

    $field->handle(Key::char("\x05"));
    $this->assertSame($expected, $field->wantsExternalEdit());
  }

  /**
   * Data provider for testTextareaExternalEditorHandoff().
   *
   * @return \Iterator<string, array{bool, bool, bool}>
   *   Whether the block opted in, whether an editor is available, and whether
   *   the handoff is offered.
   */
  public static function dataProviderTextareaExternalEditorHandoff(): \Iterator {
    yield 'opted in and available' => [TRUE, TRUE, TRUE];
    yield 'opted in but unavailable' => [TRUE, FALSE, FALSE];
    yield 'available but not opted in' => [FALSE, TRUE, FALSE];
  }

  public function testInjectsScopedKeymapIntoField(): void {
    // The vim preset binds j to move-down in the select scope, so the injected
    // field responds to j where a default-preset field would not.
    $field = (new FieldFactory(KeyMapManager::create('vim')))->open(self::blockWithOptions(FieldType::Select), 'a');

    $field->handle(Key::char('j'));

    $this->assertSame('b', $field->value());
  }

  public function testSuggestReceivesSelectableValuesOnly(): void {
    $block = (new BlockField('tz', 'TZ', FieldType::Suggest))
      ->option('utc', 'UTC')
      ->option('gmt', 'GMT', '', TRUE)
      ->separator();

    $view = (new FieldFactory())->open($block, '')->view(new DefaultTheme());

    $this->assertStringContainsString('utc', $view);
    $this->assertStringNotContainsString('gmt', $view);
  }

  public function testPerOptionDescriptionReachesChoiceField(): void {
    $block = (new BlockField('f', 'F', FieldType::Select))
      ->option('a', 'Apple', 'Crisp and sweet.')
      ->option('b', 'Banana', 'Rich in potassium.');

    $view = Ansi::strip((new FieldFactory())->open($block, 'a')->view(new DefaultTheme()));

    $this->assertStringContainsString('Crisp and sweet.', $view);
  }

  public function testPerOptionDescriptionReachesSuggest(): void {
    $block = (new BlockField('f', 'F', FieldType::Suggest))->option('apple', 'Apple', 'Crisp and sweet.');

    $field = (new FieldFactory())->open($block, '');
    $field->handle(Key::named(KeyName::Down));

    $this->assertStringContainsString('Crisp and sweet.', Ansi::strip($field->view(new DefaultTheme())));
  }

  public function testTextCompletionStaticListReachesField(): void {
    $block = (new BlockField('name', 'Name'))->complete(['acme-site']);

    $view = (new FieldFactory())->open($block, 'ac')->view(new DefaultTheme());

    // The matching candidate's remaining suffix shows as dimmed ghost-text.
    $this->assertStringContainsString('me-site', $view);
  }

  public function testSuggestGhostFlagReachesField(): void {
    $off = (new BlockField('fruit', 'Fruit', FieldType::Suggest))->option('Apple', 'Apple')->option('Apricot', 'Apricot');
    $this->assertStringNotContainsString("\033[90m", (new FieldFactory())->open($off, 'ap')->view(new DefaultTheme()));

    $on = (new BlockField('fruit', 'Fruit', FieldType::Suggest))->option('Apple', 'Apple')->option('Apricot', 'Apricot')->ghost();
    $view = (new FieldFactory())->open($on, 'ap')->view(new DefaultTheme());

    // The opted-in field previews the leading candidate's remaining suffix.
    $this->assertStringContainsString('ple', $view);
    $this->assertStringContainsString("\033[90m", $view);
  }

  public function testTextCompletionCoercesInvalidResult(): void {
    // A mistyped source degrades to no completion rather than erroring: a list
    // with non-strings is filtered, and a non-list result is ignored.
    $items = (new BlockField('a', 'A'))->complete(static fn(array $answers): array => [123, NULL]);
    $this->assertStringNotContainsString("\033[90m", (new FieldFactory())->open($items, 'ac')->view(new DefaultTheme()));

    $scalar = (new BlockField('b', 'B'))->complete(static fn(array $answers): string => 'oops');
    $this->assertStringNotContainsString("\033[90m", (new FieldFactory())->open($scalar, 'ac')->view(new DefaultTheme()));
  }

  /**
   * Tests that a declared placeholder ghosts every field with a buffer.
   *
   * @param \DrevOps\Tui\Block\FieldType $type
   *   The kind.
   */
  #[DataProvider('dataProviderPlaceholderReachesEveryCapableField')]
  public function testPlaceholderReachesEveryCapableField(FieldType $type): void {
    $block = (new BlockField('f', 'F', $type))->placeholder('E.g. Golden Beetroot');

    $view = Ansi::strip((new FieldFactory())->open($block, '')->view(new DefaultTheme()));

    $this->assertStringContainsString('E.g. Golden Beetroot', $view);
  }

  /**
   * Data provider for testPlaceholderReachesEveryCapableField().
   *
   * @return \Iterator<string, array{\DrevOps\Tui\Block\FieldType}>
   *   The kinds that draw a buffer to ghost.
   */
  public static function dataProviderPlaceholderReachesEveryCapableField(): \Iterator {
    foreach (FieldType::cases() as $type) {
      if ($type->supportsPlaceholder()) {
        yield $type->value => [$type];
      }
    }
  }

  public function testFieldWithoutPlaceholderGhostsNothing(): void {
    $this->assertStringNotContainsString("\033[90m", (new FieldFactory())->open(new BlockField('f', 'F'), '')->view(new DefaultTheme()));
  }

  /**
   * Tests the field a block's kind opens onto.
   *
   * @param \DrevOps\Tui\Block\Field $block
   *   The block to open.
   * @param mixed $current
   *   The value the block holds.
   * @param class-string $expected
   *   The field the factory builds.
   */
  #[DataProvider('dataProviderOpensBlockByKind')]
  public function testOpensBlockByKind(BlockField $block, mixed $current, string $expected): void {
    $this->assertInstanceOf($expected, (new FieldFactory())->open($block, $current));
  }

  /**
   * Data provider for testOpensBlockByKind().
   *
   * @return \Iterator<string, array{\DrevOps\Tui\Block\Field, mixed, class-string}>
   *   The block, the value it opens on and the field class it builds.
   */
  public static function dataProviderOpensBlockByKind(): \Iterator {
    yield 'text' => [new BlockField('f', 'F'), 'x', Text::class];
    yield 'confirm' => [new BlockField('f', 'F', FieldType::Confirm), TRUE, Confirm::class];
    yield 'toggle' => [self::blockWithOptions(FieldType::Toggle), 'a', Toggle::class];
    yield 'select' => [self::blockWithOptions(FieldType::Select), 'a', Select::class];
    yield 'multiple select' => [self::blockWithOptions(FieldType::Select)->multiple(), ['a'], Select::class];
    yield 'search' => [self::blockWithOptions(FieldType::Search), 'a', Search::class];
    yield 'suggest' => [self::blockWithOptions(FieldType::Suggest), 'a', Suggest::class];
    yield 'reorder' => [self::blockWithOptions(FieldType::Reorder), ['a', 'b'], Reorder::class];
    yield 'file picker' => [new BlockField('f', 'F', FieldType::FilePicker), '', FilePicker::class];
    yield 'number' => [new BlockField('f', 'F', FieldType::Number), 42, Number::class];
    yield 'rating' => [(new BlockField('f', 'F', FieldType::Rating))->bounds(new NumberBounds(1, 5)), 3, Rating::class];
    yield 'calendar' => [new BlockField('f', 'F', FieldType::Calendar), '2026-07-15', Calendar::class];
    yield 'textarea' => [new BlockField('f', 'F', FieldType::Textarea), 'x', Textarea::class];
    yield 'password' => [new BlockField('f', 'F', FieldType::Password), 'x', Password::class];
    yield 'pause' => [new BlockField('f', 'F', FieldType::Pause), NULL, Pause::class];
    yield 'template' => [(new BlockField('f', 'F', FieldType::Template))->pattern(new TemplateModel('{{a}}-{{b}}')), '', Template::class];
  }

  public function testOpeningTheBlockCarriesItsDeclarationOntoTheField(): void {
    $block = (new BlockField('f', 'F', FieldType::Select))
      ->multiple()
      ->option('a', 'A')
      ->option('b', 'B')
      ->paginate(1)
      ->placeholder('Pick some produce');

    $field = (new FieldFactory())->open($block, ['b']);

    $this->assertSame(['b'], $field->value());
    $this->assertEquals(KeyMapManager::create()->forField(FieldType::Select, TRUE), $field->keys());

    // One page of one row, so only the row the cursor is on is drawn.
    $view = Ansi::strip($field->view(new DefaultTheme(40, ['color' => FALSE])));
    $this->assertStringContainsString('A', $view);
    $this->assertStringNotContainsString('B', $view);
  }

  public function testOpeningTheBlockWiresNoValidatorBecauseTheBlockRefuses(): void {
    $block = (new BlockField('f', 'F'))
      ->required()
      ->validate(static fn(mixed $value): string => 'Never acceptable.');

    $field = (new FieldFactory())->open($block, '');
    $field->handle(Key::named(KeyName::Enter));

    // What a block will not take is the block's own to refuse, so the field it
    // opened onto offers the value rather than measuring it a second time.
    $this->assertTrue($field->isComplete());
    $this->assertNull($field->error());
  }

  public function testOpeningTheBlockDrivenByQuerySourceLeavesTheListToIt(): void {
    $block = (new BlockField('f', 'F', FieldType::Search))->query(static fn(): array => ['a' => 'A']);

    $field = (new FieldFactory())->open($block, '');

    $this->assertInstanceOf(QueryOptionsCapableInterface::class, $field);
    $this->assertTrue($field->isQueryDriven());
  }

  public function testOpeningTheRatingBlockWithNoScaleSaysSo(): void {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('is a rating field carrying no closed scale');

    (new FieldFactory())->open(new BlockField('f', 'F', FieldType::Rating), 1);
  }

  public function testOpeningTheTemplateBlockWithNoShapeSaysSo(): void {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('is a template field carrying no template');

    (new FieldFactory())->open(new BlockField('f', 'F', FieldType::Template), '');
  }

  public function testOpeningTheTextBlockResolvesCompletionAgainstTheAnswers(): void {
    $block = (new BlockField('f', 'F'))->complete(static fn(array $answers): array => [(string) ($answers['courier'] ?? '')]);

    $field = (new FieldFactory())->open($block, 'Val', ['courier' => 'Valley Runs']);
    $field->handle(Key::named(KeyName::Tab));

    $this->assertSame('Valley Runs', $field->value());
  }

  /**
   * A block of the given kind with two options.
   *
   * @param \DrevOps\Tui\Block\FieldType $type
   *   The kind.
   *
   * @return \DrevOps\Tui\Block\Field
   *   The block.
   */
  protected static function blockWithOptions(FieldType $type): BlockField {
    return (new BlockField('f', 'F', $type))->option('a', 'A')->option('b', 'B');
  }

  /**
   * A template block with a two-slot shape.
   *
   * @return \DrevOps\Tui\Block\Field
   *   The block.
   */
  protected static function templateBlock(): BlockField {
    return (new BlockField('f', 'F', FieldType::Template))->pattern(new TemplateModel('{{a}}-{{b}}'));
  }

  /**
   * A rating block over a one-to-five scale with one captioned point.
   *
   * @return \DrevOps\Tui\Block\Field
   *   The block.
   */
  protected static function ratingBlock(): BlockField {
    return (new BlockField('f', 'F', FieldType::Rating))->bounds(new NumberBounds(1, 5))->captions([3 => 'Fair']);
  }

}
