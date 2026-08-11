<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Block;

use DrevOps\Tui\Block\DateBounds;
use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\FieldType;
use DrevOps\Tui\Block\FilePickerConstraints;
use DrevOps\Tui\Block\FilePickerMode;
use DrevOps\Tui\Block\Mode;
use DrevOps\Tui\Block\NumberBounds;
use DrevOps\Tui\Block\Option;
use DrevOps\Tui\Block\OptionType;
use DrevOps\Tui\Block\RenderMode;
use DrevOps\Tui\Block\SelectionBounds;
use DrevOps\Tui\Block\Template;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Discovery\PathExists;
use DrevOps\Tui\Field\FieldInterface;
use DrevOps\Tui\Field\Select;
use DrevOps\Tui\Field\Text;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Terminal\Ansi;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\DefaultTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the field: the only block that collects, and the only one with modes.
 */
#[CoversClass(Field::class)]
#[Group('block')]
final class FieldBlockTest extends TestCase {

  public function testFieldStartsInViewModeShowingWhatWasAccepted(): void {
    $field = (new Field('courier', 'Courier'))->default('Valley Runs');

    $this->assertSame(Mode::View, $field->mode());
    $this->assertSame('  Courier  Valley Runs', $field->render($this->theme()));

    // The selector column is drawn whether or not the cursor is on the row, so
    // a row never shifts sideways as the cursor arrives on it.
    $this->assertSame('❯ Courier  Valley Runs', $field->focus()->render($this->theme()));
  }

  public function testOpeningFieldSwitchesItToEditMode(): void {
    $field = new Field('courier', 'Courier');

    $this->assertSame(Mode::Edit, $field->open()->mode());
    $this->assertSame(Mode::View, $field->close()->mode());
  }

  public function testTheLabelStaysPutAcrossBothModes(): void {
    $field = (new Field('basket', 'Basket', FieldType::Select))->entry('apple', 'Apple');

    $this->assertStringStartsWith('  Basket', $field->render($this->theme()));
    $this->assertStringStartsWith('  Basket', $field->open()->render($this->theme()));
  }

  public function testEditModeOpensOntoTheEntriesItWasGiven(): void {
    $field = (new Field('basket', 'Basket', FieldType::Select))->entry('apple', 'Apple')->entry('carrot', 'Carrot');

    $rendered = $field->open()->render($this->theme());

    $this->assertStringContainsString('Apple', $rendered);
    $this->assertStringContainsString('Carrot', $rendered);
  }

  public function testOpeningFieldHandsTheValueRegionToTheEditorItsKindOpensOnto(): void {
    $courier = (new Field('courier', 'Courier'))->default('Valley Runs');
    $basket = (new Field('basket', 'Basket', FieldType::Select))->entry('apple', 'Apple');

    $this->assertNotInstanceOf(FieldInterface::class, $courier->editor());

    $editor = $courier->open()->editor();

    $this->assertInstanceOf(Text::class, $editor);
    $this->assertInstanceOf(Select::class, $basket->open()->editor());

    // The editor starts from the answer the field holds, so opening a field
    // shows what is there rather than an empty one.
    $this->assertSame('Valley Runs', $editor->value());

    $this->assertNotInstanceOf(FieldInterface::class, $courier->close()->editor());
  }

  public function testOnlyWhatWasAcceptedReachesTheResult(): void {
    $field = new Field('courier', 'Courier');

    $this->assertNull($field->value());

    $this->assertTrue($field->accept('Valley Runs'));
    $this->assertSame('Valley Runs', $field->value());
  }

  public function testDraftIsDiscardedUnlessItIsAccepted(): void {
    $field = (new Field('courier', 'Courier'))->default('Valley Runs');

    $field->open()->draft('Coast Runs');
    $this->assertSame('Valley Runs', $field->value());

    $field->close();
    $this->assertSame('Valley Runs', $field->value());
  }

  public function testAcceptingDraftMakesItTheValue(): void {
    $field = (new Field('courier', 'Courier'))->default('Valley Runs');

    $field->open()->draft('Coast Runs');

    $this->assertTrue($field->accept());
    $this->assertSame('Coast Runs', $field->value());
    $this->assertSame(Mode::View, $field->mode());
  }

  public function testTypingIntoAnOpenFieldIsWhatFillsTheValueRegion(): void {
    $field = (new Field('courier', 'Courier'))->open();

    foreach (str_split('Coast') as $char) {
      $field->capture(Key::char($char));
    }

    $this->assertStringContainsString('Coast', $field->render($this->theme()));

    $field->capture(Key::named(KeyName::Enter));

    $this->assertSame('Coast', $field->value());
    $this->assertSame(Mode::View, $field->mode());
  }

  public function testSpaceTogglesAnEntryOfAnOpenMultipleSelect(): void {
    $field = (new Field('basket', 'Basket contents', FieldType::Select))
      ->multiple()
      ->entry('apple', 'Apple')
      ->entry('carrot', 'Carrot')
      ->open();

    // Space belongs to the kind rather than to whatever sends the key: the
    // field binds what its editor binds, so nothing above it knows of a toggle.
    $field->capture(Key::named(KeyName::Space));
    $field->capture(Key::named(KeyName::Down));
    $field->capture(Key::named(KeyName::Space));
    $field->capture(Key::named(KeyName::Enter));

    $this->assertSame(['apple', 'carrot'], $field->value());
  }

  public function testConfirmFieldAnswersTheKeysItsOwnKindBinds(): void {
    $field = (new Field('organic', 'Organic only?', FieldType::Confirm))->open();

    $field->capture(Key::char('y'));
    $field->capture(Key::named(KeyName::Enter));

    $this->assertTrue($field->value());
  }

  public function testCancellingAnOpenFieldLeavesTheAnswerWhereItWas(): void {
    $field = (new Field('courier', 'Courier'))->default('Valley Runs')->open();

    $field->capture(Key::char('X'));
    $field->capture(Key::named(KeyName::Escape));

    $this->assertSame('Valley Runs', $field->value());
    $this->assertSame(Mode::View, $field->mode());
  }

  public function testTheEditorOffersAndTheFieldRefuses(): void {
    $field = (new Field('courier', 'Courier'))
      ->validate(static fn(mixed $value): ?string => $value === 'Coast' ? 'Coast Runs do not deliver here.' : NULL)
      ->open();

    foreach (str_split('Coast') as $char) {
      $field->capture(Key::char($char));
    }

    $field->capture(Key::named(KeyName::Enter));

    // What a field will not take is the field's to refuse rather than the
    // editor's, so a refused value leaves the field open on what was offered.
    $this->assertSame(Mode::Edit, $field->mode());
    $this->assertSame('Coast Runs do not deliver here.', $field->refusal());
    $this->assertStringContainsString('Coast Runs do not deliver here.', $field->render($this->theme()));
    $this->assertNull($field->value());

    // The editor starts again from what was offered, so correcting it carries
    // on from what is on screen rather than from nothing.
    $field->capture(Key::char('X'));
    $field->capture(Key::named(KeyName::Enter));

    $this->assertSame('CoastX', $field->value());
  }

  public function testSettledFieldHasNoEditorToTakeTheKey(): void {
    $this->assertFalse((new Field('courier', 'Courier'))->capture(Key::char('x')));
  }

  public function testConstraintSaysWhatIsAcceptableBeforeYouAct(): void {
    $field = (new Field('weight', 'Weight'))->constrain('a number between 200 and 9000');

    $this->assertSame('a number between 200 and 9000', $field->constraint());
    $this->assertNull($field->refusal());
  }

  public function testRefusedValueIsExplainedAndDoesNotBecomeTheValue(): void {
    $field = (new Field('weight', 'Weight'))
      ->default(1200)
      ->validate(static fn(mixed $value): ?string => is_int($value) && $value >= 200 ? NULL : 'Enter at least 200.');

    $this->assertFalse($field->accept(10));
    $this->assertSame('Enter at least 200.', $field->refusal());
    $this->assertSame(1200, $field->value());
  }

  public function testRefusalClearsAsSoonAsValueIsAcceptable(): void {
    $field = (new Field('weight', 'Weight'))
      ->validate(static fn(mixed $value): ?string => is_int($value) && $value >= 200 ? NULL : 'Enter at least 200.');

    $field->accept(10);
    $this->assertNotNull($field->refusal());

    $this->assertTrue($field->accept(400));
    $this->assertNull($field->refusal());
  }

  public function testFieldIsAskedForUnlessItsConditionSaysOtherwise(): void {
    $field = new Field('organic', 'Organic only?');

    $this->assertTrue($field->isActive());
    $this->assertFalse($field->when(static fn(): bool => FALSE)->isActive());
  }

  public function testHelpIsNeverDrawnInTheRowThatOffersIt(): void {
    $field = (new Field('basket', 'Basket'))->help('Every crate is weighed at the packing bench.');

    $this->assertSame('Every crate is weighed at the packing bench.', $field->helpText());
    $this->assertStringNotContainsString('packing bench', $field->render($this->theme()));
  }

  public function testFieldCollectsTextUnlessItIsToldOtherwise(): void {
    $this->assertSame(FieldType::Text, (new Field('courier', 'Courier'))->type());
    $this->assertSame(FieldType::Number, (new Field('weight', 'Weight', FieldType::Number))->type());
    $this->assertSame('weight', (new Field('weight', 'Weight'))->id());
    $this->assertSame('Weight', (new Field('weight', 'Weight'))->label());
  }

  #[DataProvider('dataProviderDeclarationReadsBackAsItWasWritten')]
  public function testDeclarationReadsBackAsItWasWritten(\Closure $declare, \Closure $read, mixed $expected): void {
    $field = $declare(new Field('basket', 'Basket contents', FieldType::Select));

    $this->assertEquals($expected, $read($field));
  }

  public static function dataProviderDeclarationReadsBackAsItWasWritten(): \Iterator {
    $derive = new Derive('{{courier}}-run', 'machine');
    $discover = new PathExists('composer.json');
    $picker = new FilePickerConstraints(FilePickerMode::File, ['csv'], 2048);
    $template = new Template('{{orchard}}-{{fruit}}');

    yield 'description' => [
      static fn(Field $field): Field => $field->description('Pick the produce.'),
      static fn(Field $field): string => $field->descriptionText(),
      'Pick the produce.',
    ];

    yield 'help' => [
      static fn(Field $field): Field => $field->help('Crates are weighed at the bench.'),
      static fn(Field $field): string => $field->helpText(),
      'Crates are weighed at the bench.',
    ];

    yield 'placeholder' => [
      static fn(Field $field): Field => $field->placeholder('E.g. Golden Beetroot'),
      static fn(Field $field): string => $field->placeholderText(),
      'E.g. Golden Beetroot',
    ];

    yield 'completion' => [
      static fn(Field $field): Field => $field->complete(['Apple', 'Apricot']),
      static fn(Field $field): array|\Closure => $field->completion(),
      ['Apple', 'Apricot'],
    ];

    yield 'ghost' => [
      static fn(Field $field): Field => $field->ghost(),
      static fn(Field $field): bool => $field->hasGhost(),
      TRUE,
    ];

    yield 'page size' => [
      static fn(Field $field): Field => $field->paginate(8),
      static fn(Field $field): ?int => $field->pageSize(),
      8,
    ];

    yield 'multiple' => [
      static fn(Field $field): Field => $field->multiple(),
      static fn(Field $field): bool => $field->isMultiple(),
      TRUE,
    ];

    yield 'required' => [
      static fn(Field $field): Field => $field->required(),
      static fn(Field $field): bool => $field->isRequired(),
      TRUE,
    ];

    yield 'revealable' => [
      static fn(Field $field): Field => $field->revealable(),
      static fn(Field $field): bool => $field->isRevealable(),
      TRUE,
    ];

    yield 'confirmation' => [
      static fn(Field $field): Field => $field->confirmation(),
      static fn(Field $field): bool => $field->hasConfirmation(),
      TRUE,
    ];

    yield 'external editor' => [
      static fn(Field $field): Field => $field->externalEditor(),
      static fn(Field $field): bool => $field->hasExternalEditor(),
      TRUE,
    ];

    yield 'standalone' => [
      static fn(Field $field): Field => $field->standalone(),
      static fn(Field $field): RenderMode => $field->renderMode(),
      RenderMode::Standalone,
    ];

    yield 'inline' => [
      static fn(Field $field): Field => $field->standalone(FALSE),
      static fn(Field $field): RenderMode => $field->renderMode(),
      RenderMode::Inline,
    ];

    yield 'derive' => [
      static fn(Field $field): Field => $field->derive($derive),
      static fn(Field $field): ?Derive => $field->derivation(),
      $derive,
    ];

    yield 'discover' => [
      static fn(Field $field): Field => $field->discover($discover),
      static fn(Field $field): mixed => $field->discovery(),
      $discover,
    ];

    yield 'environment name' => [
      static fn(Field $field): Field => $field->env('ORCHARD_BASKET'),
      static fn(Field $field): string => $field->envName(),
      'ORCHARD_BASKET',
    ];

    yield 'environment aliases' => [
      static fn(Field $field): Field => $field->envAliases(['BASKET', 'CRATE']),
      static fn(Field $field): array => $field->aliases(),
      ['BASKET', 'CRATE'],
    ];

    yield 'picker constraints' => [
      static fn(Field $field): Field => $field->picker($picker),
      static fn(Field $field): FilePickerConstraints => $field->pickerConstraints(),
      $picker,
    ];

    yield 'picker start' => [
      static fn(Field $field): Field => $field->startIn('/orchard'),
      static fn(Field $field): string => $field->pickerStart(),
      '/orchard',
    ];

    yield 'picker hidden entries' => [
      static fn(Field $field): Field => $field->showHidden(),
      static fn(Field $field): bool => $field->showsHidden(),
      TRUE,
    ];

    yield 'template' => [
      static fn(Field $field): Field => $field->pattern($template),
      static fn(Field $field): ?Template => $field->template(),
      $template,
    ];

    yield 'rating captions' => [
      static fn(Field $field): Field => $field->captions([1 => 'Unripe', 5 => 'Ripe']),
      static fn(Field $field): array => $field->ratingCaptions(),
      [1 => 'Unripe', 5 => 'Ripe'],
    ];

    yield 'query minimum length' => [
      static fn(Field $field): Field => $field->query(static fn(): array => [])->minQuery(3),
      static fn(Field $field): int => $field->queryMinLength(),
      3,
    ];

    yield 'schema default' => [
      static fn(Field $field): Field => $field->schemaDefault('apple'),
      static fn(Field $field): mixed => $field->schemaDefaultValue(),
      'apple',
    ];
  }

  public function testDeclaredNullDefaultIsStillDeclaredInMachineOutput(): void {
    $field = new Field('basket', 'Basket contents');

    $this->assertFalse($field->hasSchemaDefault());
    $this->assertTrue($field->schemaDefault(NULL)->hasSchemaDefault());
    $this->assertNull($field->schemaDefaultValue());
  }

  #[DataProvider('dataProviderEachBoundReachesTheValueItMeasures')]
  public function testEachBoundReachesTheValueItMeasures(Field $field, mixed $value, ?string $violation): void {
    $this->assertSame($violation, $field->boundsViolation($value));
  }

  public static function dataProviderEachBoundReachesTheValueItMeasures(): \Iterator {
    $numbers = static fn(): Field => (new Field('weight', 'Weight', FieldType::Number))->bounds(new NumberBounds(200, 9000));
    $dates = static fn(): Field => (new Field('harvest', 'Harvest date', FieldType::Calendar))
      ->dates(new DateBounds(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31')));
    $counts = static fn(): Field => (new Field('basket', 'Basket contents', FieldType::Select))->multiple()->selections(new SelectionBounds(2, 3));

    yield 'number in range' => [$numbers(), 1200, NULL];
    yield 'number below' => [$numbers(), 10, 'between 200 and 9000'];
    yield 'number above' => [$numbers(), 90000, 'between 200 and 9000'];
    yield 'date in range' => [$dates(), '2026-07-15', NULL];
    yield 'date outside' => [$dates(), '2025-07-15', 'between 2026-01-01 and 2026-12-31'];
    yield 'count in range' => [$counts(), ['apple', 'carrot'], NULL];
    yield 'count below' => [$counts(), ['apple'], 'between 2 and 3 items'];
    yield 'unbounded' => [new Field('courier', 'Courier'), 'Valley Runs', NULL];
  }

  public function testBoundsAreReadBackAsTheObjectsTheyWereDeclaredWith(): void {
    $numbers = new NumberBounds(200, 9000);
    $dates = new DateBounds(new \DateTimeImmutable('2026-01-01'));
    $counts = new SelectionBounds(2, 3);

    $field = (new Field('basket', 'Basket contents'))->bounds($numbers)->dates($dates)->selections($counts);

    $this->assertSame($numbers, $field->numberBounds());
    $this->assertSame($dates, $field->dateBounds());
    $this->assertSame($counts, $field->selectionBounds());
  }

  public function testValueOutsideDeclaredBoundIsRefusedWithTheRangeItMissed(): void {
    $field = (new Field('weight', 'Weight', FieldType::Number))->default(1200)->bounds(new NumberBounds(200, 9000));

    $this->assertFalse($field->accept(10));
    $this->assertSame('Enter a number between 200 and 9000.', $field->refusal());
    $this->assertSame(1200, $field->value());

    $this->assertTrue($field->accept(400));
  }

  #[DataProvider('dataProviderLimitIsRefusedInTheVoiceOfWhatItGoverns')]
  public function testLimitIsRefusedInTheVoiceOfWhatItGoverns(Field $field, mixed $value, string $says): void {
    $this->assertFalse($field->accept($value));
    $this->assertSame($says, $field->refusal());
  }

  public static function dataProviderLimitIsRefusedInTheVoiceOfWhatItGoverns(): \Iterator {
    yield 'a number is entered' => [
      (new Field('weight', 'Weight', FieldType::Number))->bounds(new NumberBounds(200, 9000)),
      10,
      'Enter a number between 200 and 9000.',
    ];

    yield 'a date is what it must be' => [
      (new Field('harvest', 'Harvest date', FieldType::Calendar))->dates(new DateBounds(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'))),
      '2025-07-15',
      'must be between 2026-01-01 and 2026-12-31.',
    ];

    yield 'a count is selected' => [
      (new Field('basket', 'Basket contents', FieldType::Select))->multiple()->selections(new SelectionBounds(2, 3)),
      ['apple'],
      'Select between 2 and 3 items.',
    ];
  }

  #[DataProvider('dataProviderRequiredFieldRefusesAnEmptyAnswer')]
  public function testRequiredFieldRefusesAnEmptyAnswer(mixed $value, bool $accepted): void {
    $field = (new Field('basket', 'Basket contents'))->required();

    $this->assertSame($accepted, $field->accept($value));
  }

  public static function dataProviderRequiredFieldRefusesAnEmptyAnswer(): \Iterator {
    yield 'empty string' => ['', FALSE];
    yield 'empty list' => [[], FALSE];
    yield 'nothing' => [NULL, FALSE];
    // Strictly compared, so a decision against and a count of none are answers
    // rather than omissions.
    yield 'false' => [FALSE, TRUE];
    yield 'zero' => [0, TRUE];
    yield 'a value' => ['apple', TRUE];
  }

  public function testMissingRequiredAnswerIsExplainedByTheLabelUnlessItSaysOtherwise(): void {
    $derived = (new Field('basket', 'Basket contents'))->required();
    $declared = (new Field('basket', 'Basket contents'))->required(TRUE, 'Pick at least one crate.');

    $this->assertSame('Basket contents is required.', $derived->requiredViolation(''));
    $this->assertSame('Pick at least one crate.', $declared->requiredViolation(''));
    $this->assertNull((new Field('basket', 'Basket contents'))->requiredViolation(''));
  }

  public function testFieldHandsBackWhatItWasDeclaredToRefuseAndNormalizeWith(): void {
    $validate = static fn(mixed $value): ?string => NULL;
    $transform = static fn(mixed $value): mixed => $value;
    $field = (new Field('courier', 'Courier'))->required(TRUE, 'Name the courier.')->validate($validate)->transform($transform);

    $this->assertSame('Name the courier.', $field->requiredMessage());
    $this->assertSame($validate, $field->validator());
    $this->assertSame($transform, $field->transformer());

    $bare = new Field('courier', 'Courier');

    $this->assertSame('', $bare->requiredMessage());
    $this->assertNotInstanceOf(\Closure::class, $bare->validator());
    $this->assertNotInstanceOf(\Closure::class, $bare->transformer());
  }

  public function testAcceptedValueIsNormalizedBeforeItIsHeld(): void {
    $field = (new Field('courier', 'Courier'))->transform(static fn(mixed $value): mixed => is_string($value) ? trim($value) : $value);

    $this->assertTrue($field->accept('  Valley Runs  '));
    $this->assertSame('Valley Runs', $field->value());
  }

  public function testRefusedValueIsNeverNormalized(): void {
    $field = (new Field('courier', 'Courier'))
      ->transform(static fn(mixed $value): string => 'transformed')
      ->validate(static fn(mixed $value): string => 'Enter a courier.');

    $this->assertFalse($field->accept('Valley Runs'));
    $this->assertNull($field->value());
  }

  #[DataProvider('dataProviderAnswerIsListWhenTheFieldCollectsOne')]
  public function testAnswerIsListWhenTheFieldCollectsOne(Field $field, bool $list, bool $choice): void {
    $this->assertSame($list, $field->collectsList());
    $this->assertSame($choice, $field->isMultiChoice());
  }

  public static function dataProviderAnswerIsListWhenTheFieldCollectsOne(): \Iterator {
    yield 'one value' => [new Field('courier', 'Courier'), FALSE, FALSE];
    yield 'several picks' => [(new Field('basket', 'Basket', FieldType::Select))->multiple(), TRUE, TRUE];
    yield 'several paths' => [(new Field('crates', 'Crates', FieldType::FilePicker))->multiple(), TRUE, FALSE];
    yield 'a ranking' => [new Field('order', 'Order', FieldType::Reorder), TRUE, TRUE];
  }

  public function testEntriesAreDrawnInTheOrderTheyWereDeclared(): void {
    $field = (new Field('basket', 'Basket contents', FieldType::Select))
      ->heading('Fruit')
      ->entry('apple', 'Apple')
      ->separator()
      ->heading('Vegetables')
      ->entry('carrot', 'Carrot');

    $kinds = array_map(static fn(object $entry): OptionType => $entry->kind, $field->entries());

    $expected = [OptionType::Heading, OptionType::Option, OptionType::Separator, OptionType::Heading, OptionType::Option];
    $this->assertSame($expected, $kinds);
    $this->assertSame(['apple', 'carrot'], $field->selectableValues());
  }

  public function testEntryValueStaysTheStringItWasDeclaredAs(): void {
    // Rows are held as a list carrying their own value, so a numeric-looking
    // value is never coerced the way an array key would be.
    $field = (new Field('grade', 'Grade', FieldType::Select))->entry('0', 'Unripe')->entry('1', 'Ripe');

    $this->assertSame(['0', '1'], $field->selectableValues());
    $this->assertSame('Unripe', $field->entryOf('0')?->label);
    $this->assertTrue($field->accept('0'));
    $this->assertSame('0', $field->value());
  }

  public function testDeclaringValueTwiceReplacesTheEntryWhereItAlreadySits(): void {
    $field = (new Field('basket', 'Basket contents', FieldType::Select))
      ->entry('apple', 'Apple')
      ->entry('carrot', 'Carrot')
      ->entry('apple', 'Golden Apple');

    $this->assertCount(2, $field->entries());
    $this->assertSame('Golden Apple', $field->entryOf('apple')?->label);
    $this->assertSame(['apple', 'carrot'], $field->selectableValues());
  }

  public function testEntryWithNoLabelDrawsItsOwnValue(): void {
    $field = (new Field('basket', 'Basket contents', FieldType::Select))->entry('apple');

    $this->assertSame('apple', $field->entryOf('apple')?->label);
    $this->assertNotInstanceOf(Option::class, $field->entryOf('carrot'));
  }

  #[DataProvider('dataProviderValueOutsideTheEntriesIsRefused')]
  public function testValueOutsideTheEntriesIsRefused(Field $field, mixed $value, ?string $error): void {
    $this->assertSame($error, $field->entryViolation($value));
  }

  public static function dataProviderValueOutsideTheEntriesIsRefused(): \Iterator {
    $basket = static fn(): Field => (new Field('basket', 'Basket contents', FieldType::Select))
      ->entry('apple', 'Apple')
      ->entry('carrot', 'Carrot', disabled: TRUE, disabled_reason: 'Out of season.')
      ->entry('turnip', 'Turnip', disabled: TRUE);

    yield 'a listed value' => [$basket(), 'apple', NULL];
    yield 'an unlisted value' => [$basket(), 'plum', 'value "plum" is not one of: apple'];
    yield 'a disabled value' => [$basket(), 'carrot', 'option "carrot" is disabled: Out of season.'];
    yield 'a disabled value with no reason' => [$basket(), 'turnip', 'option "turnip" is disabled'];
    yield 'no entries at all' => [new Field('basket', 'Basket', FieldType::Select), 'plum', NULL];
    yield 'nothing to constrain' => [new Field('courier', 'Courier'), 'Valley Runs', NULL];

    yield 'one value where a list is owed' => [
      (new Field('basket', 'Basket', FieldType::Select))->multiple()->entry('apple', 'Apple'),
      'apple',
      'value must be a list',
    ];

    yield 'a ranking that misses an entry' => [
      (new Field('order', 'Order', FieldType::Reorder))->entry('apple')->entry('carrot'),
      ['apple'],
      'must rank every option exactly once (apple, carrot)',
    ];

    yield 'a full ranking' => [
      (new Field('order', 'Order', FieldType::Reorder))->entry('apple')->entry('carrot'),
      ['apple', 'carrot'],
      NULL,
    ];

    yield 'a list of listed values' => [
      (new Field('basket', 'Basket', FieldType::Select))->multiple()->entry('apple')->entry('carrot'),
      ['apple', 'carrot'],
      NULL,
    ];

    yield 'a list carrying an unlisted value' => [
      (new Field('basket', 'Basket', FieldType::Select))->multiple()->entry('apple')->entry('carrot'),
      ['apple', 'plum'],
      'value "plum" is not one of: apple, carrot',
    ];
  }

  public function testValueIsRefusedWhenTheQueryResolvedToNothingThatCarriesIt(): void {
    // Entries that follow a query constrain the value to whatever they resolved
    // to, so resolving to nothing means the value does not exist.
    $field = (new Field('basket', 'Basket contents', FieldType::Search))->query(static fn(): array => []);

    $this->assertSame('value "plum" was not found', $field->entryViolation('plum'));
  }

  #[DataProvider('dataProviderEntriesAreUnsettledWhileSomethingOwesThem')]
  public function testEntriesAreUnsettledWhileSomethingOwesThem(Field $field, bool $settled, bool $dynamic): void {
    $this->assertSame($settled, $field->hasSettledEntries());
    $this->assertSame($dynamic, $field->hasDynamicEntries());
  }

  public static function dataProviderEntriesAreUnsettledWhileSomethingOwesThem(): \Iterator {
    $field = static fn(): Field => new Field('basket', 'Basket contents', FieldType::Select);

    yield 'declared' => [$field()->entry('apple', 'Apple'), TRUE, FALSE];
    yield 'loaded once' => [$field()->load(static fn(): array => []), FALSE, FALSE];
    yield 'resolved from the answers' => [$field()->resolve(static fn(array $answers): array => []), FALSE, TRUE];
    yield 'resolved from a query' => [$field()->query(static fn(): array => []), FALSE, TRUE];
  }

  public function testWhatOwesTheEntriesIsReadBackAsItWasDeclared(): void {
    $loader = static fn(): array => ['apple' => 'Apple'];
    $resolver = static fn(array $answers): array => ['carrot' => 'Carrot'];
    $source = static fn(string $query, array $answers): array => ['plum' => 'Plum'];

    $field = (new Field('basket', 'Basket contents', FieldType::Search))->load($loader)->resolve($resolver)->query($source);

    $this->assertSame($loader, $field->loader());
    $this->assertSame($resolver, $field->resolver());
    $this->assertSame($source, $field->source());
  }

  public function testSettlingTheEntriesRetiresTheLoaderThatOwedThem(): void {
    $field = (new Field('basket', 'Basket contents', FieldType::Select))->load(static fn(): array => ['apple' => 'Apple']);

    $field->settle(['apple' => 'Apple', 'carrot' => 'Carrot']);

    $this->assertSame(['apple', 'carrot'], $field->selectableValues());
    $this->assertNotInstanceOf(\Closure::class, $field->loader());
    $this->assertTrue($field->hasSettledEntries());
  }

  public function testEntriesThatAreNotMapOfLabelsSettleToNone(): void {
    $field = (new Field('basket', 'Basket contents', FieldType::Select))->entry('apple', 'Apple');

    $this->assertSame([], $field->settle('not a map')->entries());
  }

  public function testValueThatDoesNotFitTheDeclaredShapeIsRefused(): void {
    $field = (new Field('crate', 'Crate code', FieldType::Template))->pattern(new Template('{{orchard}}-{{fruit}}'));

    $this->assertNull($field->templateViolation('valley-apple'));
    $this->assertNull($field->templateViolation(''));
    $this->assertNull((new Field('courier', 'Courier'))->templateViolation('anything'));

    $this->assertFalse($field->accept('valley'));
    $this->assertStringContainsString('does not match the template', (string) $field->refusal());
  }

  public function testShapeIsMeasuredAndNormalizedWhole(): void {
    $seen = NULL;
    $field = (new Field('crate', 'Crate code', FieldType::Template))
      ->pattern(new Template('{{orchard}}-{{fruit}}'))
      ->validate(static function (mixed $value) use (&$seen): ?string {
        $seen = $value;

        return $value === 'valley-apple' ? NULL : 'Unknown crate.';
      })
      ->transform(static fn(mixed $value): string => is_string($value) ? strtoupper($value) : '');

    // The shape is filled in slot by slot and refused or normalized whole, so
    // neither ever sees a part of it.
    $this->assertFalse($field->accept('valley-pear'));
    $this->assertSame('valley-pear', $seen);
    $this->assertSame('Unknown crate.', $field->refusal());

    $this->assertTrue($field->accept('valley-apple'));
    $this->assertSame('VALLEY-APPLE', $field->value());
  }

  public function testPathOutsideTheDeclaredLimitsIsRefused(): void {
    $field = (new Field('manifest', 'Manifest', FieldType::FilePicker))
      ->picker(new FilePickerConstraints(FilePickerMode::File, ['csv']));

    $this->assertNull($field->pickerViolation(''));
    $this->assertSame('an existing file', $field->pickerViolation('/orchard/missing.csv'));
    // Limits left on a field that browses nothing govern nothing.
    $this->assertNull((new Field('courier', 'Courier'))->picker($field->pickerConstraints())->pickerViolation('/orchard/missing.csv'));

    $this->assertFalse($field->accept('/orchard/missing.csv'));
    $this->assertSame('Choose an existing file.', $field->refusal());
  }

  #[DataProvider('dataProviderDeclarationThatCouldNotBeHonouredIsRefused')]
  public function testDeclarationThatCouldNotBeHonouredIsRefused(\Closure $declare, string $says): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($says);

    $declare(new Field('basket', 'Basket contents', FieldType::Select));
  }

  public static function dataProviderDeclarationThatCouldNotBeHonouredIsRefused(): \Iterator {
    yield 'an unportable name' => [
      static fn(Field $field): Field => $field->env('ORCHARD-BASKET'),
      'which is not portable',
    ];

    yield 'an unportable alias' => [
      static fn(Field $field): Field => $field->envAliases(['9BASKET']),
      'which is not portable',
    ];

    yield 'an alias repeating the name' => [
      static fn(Field $field): Field => $field->env('BASKET')->envAliases(['BASKET']),
      'declares the environment variable "BASKET" twice',
    ];

    yield 'an alias repeating an alias' => [
      static fn(Field $field): Field => $field->envAliases(['CRATE', 'CRATE']),
      'declares the environment variable "CRATE" twice',
    ];

    yield 'a caption off the scale' => [
      static fn(Field $field): Field => $field->bounds(new NumberBounds(1, 5))->captions([9 => 'Ripe']),
      'captions the point 9, which is outside its scale of between 1 and 5',
    ];

    yield 'a page of no rows' => [
      static fn(Field $field): Field => $field->paginate(0),
      'a page shows at least one',
    ];

    yield 'a query floor of no characters' => [
      static fn(Field $field): Field => $field->minQuery(0),
      'it must be at least one character',
    ];
  }

  public function testEditModeDrawsGroupedDisabledAndDividedEntries(): void {
    $field = (new Field('basket', 'Basket contents', FieldType::Select))
      ->heading('Fruit')
      ->entry('apple', 'Apple')
      ->separator()
      ->entry('carrot', 'Carrot', disabled: TRUE, disabled_reason: 'Out of season.')
      ->default('apple');

    $lines = explode("\n", $field->open()->render($this->theme()));

    $this->assertStringContainsString('Fruit', $lines[0]);
    $this->assertStringContainsString('Apple', $lines[1]);
    $this->assertStringContainsString('─', $lines[2]);
    $this->assertStringContainsString('Carrot', $lines[3]);
    $this->assertStringContainsString('Out of season.', $lines[3]);
  }

  public function testDescriptionIsDrawnWhetherOrNotTheFieldIsOpen(): void {
    // What is being asked is worth saying before the row is opened: a reader
    // decides what to answer without opening anything.
    $field = (new Field('basket', 'Basket contents'))->description('Pick the produce.')->default('apple');

    $this->assertStringContainsString('Pick the produce.', $field->render($this->theme()));
    $this->assertStringContainsString('Pick the produce.', $field->open()->render($this->theme()));
  }

  public function testDescriptionUnderSettledRowLinesUpWithTheAnswer(): void {
    $field = (new Field('basket', 'Basket contents'))->description('Pick the produce.')->default('apple');

    // The explanation steps in to the column the answer starts in, so the row
    // above it reads as the thing it explains.
    $this->assertSame(['  Basket contents  apple', '                   Pick the produce.'], explode("\n", $field->render($this->theme())));
  }

  public function testChosenEntryIsMarkedAcrossOneValueAndSeveral(): void {
    $one = (new Field('basket', 'Basket contents', FieldType::Select))->entry('apple', 'Apple')->entry('carrot', 'Carrot')->default('carrot');
    $several = (new Field('basket', 'Basket contents', FieldType::Select))
      ->multiple()
      ->entry('apple', 'Apple')
      ->entry('carrot', 'Carrot')
      ->entry('plum', 'Plum')
      ->default(['apple', 'carrot']);

    // A single choice is a radio list and several is a checkbox list, which is
    // the editor's shape rather than the row's: the field hands the region over
    // and the kind decides what fills it.
    $this->assertSame(['○ Apple', '● Carrot'], $this->entryLines($one));
    $this->assertSame(['❯ ◼ Apple', '◼ Carrot', '◻ Plum'], $this->entryLines($several));
  }

  public function testSeveralAnswersReadAsOneLineWhileTheFieldIsSettled(): void {
    $field = (new Field('basket', 'Basket contents', FieldType::Select))
      ->multiple()
      ->entry('apple', 'Apple')
      ->entry('carrot', 'Carrot')
      ->default(['apple', 'carrot']);

    $this->assertSame('  Basket contents  apple, carrot', $field->render($this->theme()));
  }

  public function testRowSaysItsEntriesAreStillComingRatherThanReadingAsEmpty(): void {
    $field = (new Field('basket', 'Basket contents', FieldType::Select))->load(static fn(): array => ['apple' => 'Apple']);

    // Nothing has asked the loader yet, which is what a reader meets while a
    // panel that fetches its own rows is being opened.
    $this->assertSame('  Basket contents  Loading…', $field->render($this->theme()));

    $this->assertSame('  Basket contents', $field->settle(['apple' => 'Apple'])->render($this->theme()));
  }

  public function testBadgeSitsAtTheEdgeOfTheFrameRatherThanBesideTheAnswer(): void {
    $field = (new Field('courier', 'Courier'))->default('Valley Runs')->badge('edited');

    $row = $field->render(new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None]));

    // The badge belongs in a column of its own, so it is set against the width
    // the theme lays the frame out to rather than against the answer's length.
    $this->assertStringStartsWith('  Courier  Valley Runs', $row);
    $this->assertStringEndsWith(' edited ', $row);
    $this->assertSame(40, Ansi::width($row));
  }

  public function testOpenFieldSaysWhatItAcceptsUntilSomethingIsRefused(): void {
    $field = (new Field('weight', 'Weight', FieldType::Number))
      ->constrain('a weight between 200 and 9000')
      ->bounds(new NumberBounds(200, 9000))
      ->open();

    $this->assertStringContainsString('a weight between 200 and 9000', $field->render($this->theme()));

    // The two share one line and never appear together, so the refusal is what
    // is drawn from the moment there is one.
    $this->assertFalse($field->accept(10));

    $rendered = $field->render($this->theme());
    $this->assertStringContainsString('Enter a number between 200 and 9000.', $rendered);
    $this->assertStringNotContainsString('a weight between 200 and 9000', $rendered);
  }

  /**
   * The entry rows an open field draws, with the label prefix taken off.
   *
   * @param \DrevOps\Tui\Block\Field $field
   *   The field.
   *
   * @return list<string>
   *   The rows.
   */
  protected function entryLines(Field $field): array {
    $lines = explode("\n", $field->open()->render($this->theme()));

    return array_map(static fn(string $line): string => trim(str_replace('Basket contents', '', $line)), $lines);
  }

  /**
   * A theme with colour off, so the assertions read as plain strings.
   */
  protected function theme(): DefaultTheme {
    return new DefaultTheme(80, ['color' => FALSE]);
  }

}
