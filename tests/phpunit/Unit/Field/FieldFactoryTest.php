<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Field;

use DrevOps\Tui\Handler\HandlerRegistry;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Model\DateBounds;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\NumberBounds;
use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Model\OptionKind;
use DrevOps\Tui\Model\Template as TemplateModel;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Field\Calendar;
use DrevOps\Tui\Field\Confirm;
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
use DrevOps\Tui\Field\Textarea;
use DrevOps\Tui\Field\Text;
use DrevOps\Tui\Field\Toggle;
use DrevOps\Tui\Field\FieldFactory;
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
   * Tests the field each field type builds an editor from.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field to build an editor for.
   * @param mixed $current
   *   The value the field opens on.
   * @param class-string $expected
   *   The field the factory builds.
   */
  #[DataProvider('dataProviderCreatesByType')]
  public function testCreatesByType(Field $field, mixed $current, string $expected): void {
    $this->assertInstanceOf($expected, (new FieldFactory())->create($field, $current));
  }

  /**
   * Data provider for testCreatesByType().
   *
   * @return \Iterator<string, array{\DrevOps\Tui\Model\Field, mixed, class-string}>
   *   The field, the value it opens on and the field class it builds.
   */
  public static function dataProviderCreatesByType(): \Iterator {
    yield 'text' => [self::field(FieldType::Text), 'x', Text::class];
    yield 'confirm' => [self::field(FieldType::Confirm), TRUE, Confirm::class];
    yield 'toggle' => [self::fieldWithOptions(FieldType::Toggle), 'a', Toggle::class];
    yield 'select' => [self::fieldWithOptions(FieldType::Select), 'a', Select::class];
    yield 'multiple select' => [self::multiFieldWithOptions(FieldType::Select), ['a'], Select::class];
    yield 'suggest' => [self::fieldWithOptions(FieldType::Suggest), 'a', Suggest::class];
    yield 'number' => [self::field(FieldType::Number), 42, Number::class];
    yield 'rating' => [self::ratingField(), 3, Rating::class];
    yield 'calendar' => [self::field(FieldType::Calendar), '2026-07-15', Calendar::class];
    yield 'textarea' => [self::field(FieldType::Textarea), 'x', Textarea::class];
    yield 'password' => [self::field(FieldType::Password), 'x', Password::class];
    yield 'search' => [self::fieldWithOptions(FieldType::Search), 'a', Search::class];
    yield 'multiple search' => [self::multiFieldWithOptions(FieldType::Search), ['a'], Search::class];
    yield 'reorder' => [self::fieldWithOptions(FieldType::Reorder), ['a'], Reorder::class];
    yield 'file picker' => [self::field(FieldType::FilePicker), '/nonexistent', FilePicker::class];
    yield 'pause' => [self::field(FieldType::Pause), TRUE, Pause::class];
    yield 'template' => [self::templateField(), 'one-two', Template::class];
  }

  #[DataProvider('dataProviderSeedsValueFromCurrent')]
  public function testSeedsValueFromCurrent(Field $field, mixed $current, mixed $expected): void {
    $this->assertSame($expected, (new FieldFactory())->create($field, $current)->value());
  }

  /**
   * Data provider for testSeedsValueFromCurrent().
   *
   * @return \Iterator<string, array{\DrevOps\Tui\Model\Field, mixed, mixed}>
   *   The field, the value it is handed and the value the field opens on.
   */
  public static function dataProviderSeedsValueFromCurrent(): \Iterator {
    yield 'text' => [self::field(FieldType::Text), 'Acme', 'Acme'];
    yield 'number from an integer' => [self::field(FieldType::Number), 8080, 8080];
    yield 'number from a non-numeric' => [self::field(FieldType::Number), 'oops', 0];
    yield 'rating from a non-numeric' => [self::ratingField(), 'oops', 1];
    yield 'template from the assembled value' => [self::templateField(), 'one-two', 'one-two'];
    yield 'template from a non-string' => [self::templateField(), 42, '-'];
    // The seed order flows through: the given value first, the remaining
    // option appended to complete the ranking.
    yield 'reorder completes the ranking' => [self::fieldWithOptions(FieldType::Reorder), ['b'], ['b', 'a']];
    yield 'multiple from a non-list' => [self::multiFieldWithOptions(FieldType::Select), 'notalist', []];
    // A multiple choice field seeds from the list value, proving the multiple
    // flag reaches the select and search fields.
    yield 'multiple select' => [self::multiFieldWithOptions(FieldType::Select), ['a', 'b'], ['a', 'b']];
    yield 'multiple search' => [self::multiFieldWithOptions(FieldType::Search), ['a'], ['a']];
  }

  public function testFilePickerSeedsValueFromCurrent(): void {
    // Kept out of the seeding provider: the picker reads a directory, and a
    // virtual one lists nothing without depending on what the host happens to
    // have on disk.
    vfsStream::setup('crates');
    $start = vfsStream::url('crates');

    // A current path outside the start is ignored and the empty directory
    // lists nothing, so the value is empty.
    $single = new Field('f', 'F', '', FieldType::FilePicker, '', pickerStart: $start);
    $this->assertSame('', (new FieldFactory())->create($single, 'x')->value());

    // The multiple picker yields a list seeded from the current value, proving
    // the multiple flag is threaded through.
    $multi = new Field('g', 'G', '', FieldType::FilePicker, [], pickerStart: $start, multiple: TRUE);
    $this->assertSame(['/a', '/b'], (new FieldFactory())->create($multi, ['/a', '/b'])->value());
  }

  public function testDateWithNonStringCurrentOpensOnToday(): void {
    // Kept out of the seeding provider: a static provider would fix "today" at
    // collection time. Bracketing the call keeps a midnight rollover between
    // the two reads from failing the run.
    $before = (new \DateTimeImmutable('today'))->format('Y-m-d');
    $field = (new FieldFactory())->create(self::field(FieldType::Calendar), 42);
    $after = (new \DateTimeImmutable('today'))->format('Y-m-d');

    $this->assertContains($field->value(), [$before, $after]);
  }

  public function testNoteHasNoEditorField(): void {
    // A note is presentational: the theme renders it and the cursor skips
    // it, so asking the factory to build an editor for one is a mistake.
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Note fields are presentational and have no editor field.');

    (new FieldFactory())->create(self::field(FieldType::Note), NULL);
  }

  public function testPasswordFlagsPassedThrough(): void {
    $field = new Field('f', 'F', '', FieldType::Password, '', revealable: TRUE, confirm: TRUE);

    $field = (new FieldFactory())->create($field, 'secret');

    // Revealable shows through the reveal hint the field contributes.
    $labels = array_map(static fn(Hint $hint): string => $hint->label, $field->hints());
    $this->assertContains('reveal', $labels);

    // Confirm shows through the two-step flow: the first Enter does not accept.
    $field->handle(Key::named(KeyName::Enter));
    $this->assertFalse($field->isComplete());
  }

  public function testNumberBoundsPassedThrough(): void {
    $field = new Field('f', 'F', '', FieldType::Number, 0, bounds: new NumberBounds(0, 10));

    $field = (new FieldFactory())->create($field, 5);

    // Bounds show through the adjust hint the field contributes and stepping.
    $labels = array_map(static fn(Hint $hint): string => $hint->label, $field->hints());
    $this->assertContains('adjust', $labels);
    $field->handle(Key::named(KeyName::Up));
    $this->assertSame(6, $field->value());
  }

  public function testRatingScaleAndCaptionsPassedThrough(): void {
    $field = (new FieldFactory())->create(self::ratingField(), 3);

    $this->assertStringContainsString('●●●○○ 3/5 Fair', Ansi::strip($field->view(new DefaultTheme())));
  }

  public function testDateBoundsPassedThrough(): void {
    $field = new Field('f', 'F', '', FieldType::Calendar, '', dateBounds: new DateBounds(new \DateTimeImmutable('2026-07-10'), new \DateTimeImmutable('2026-07-20')));

    $field = (new FieldFactory())->create($field, '2026-07-01');

    // The seed is clamped into the field's declared range.
    $this->assertSame('2026-07-10', $field->value());
  }

  public function testCreatingProgressFieldThrows(): void {
    // A progress row runs its work on activation; it has no editor to build.
    $field = new Field('p', 'P', '', FieldType::Progress, NULL);

    $this->expectException(\LogicException::class);

    (new FieldFactory())->create($field, NULL);
  }

  #[DataProvider('dataProviderTextareaExternalEditorHandoff')]
  public function testTextareaExternalEditorHandoff(bool $opted_in, bool $available, bool $expected): void {
    $field = new Field('f', 'F', '', FieldType::Textarea, '', externalEditor: $opted_in);

    $field = (new FieldFactory(externalEditorAvailable: $available))->create($field, 'x');
    $this->assertInstanceOf(Textarea::class, $field);

    $field->handle(Key::char("\x05"));
    $this->assertSame($expected, $field->wantsExternalEdit());
  }

  /**
   * Data provider for testTextareaExternalEditorHandoff().
   *
   * @return \Iterator<string, array{bool, bool, bool}>
   *   Whether the field opted in, whether an editor is available, and whether
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
    $field = (new FieldFactory(KeyMapManager::create('vim')))->create(self::fieldWithOptions(FieldType::Select), 'a');

    $field->handle(Key::char('j'));

    $this->assertSame('b', $field->value());
  }

  public function testPageSizePassedThrough(): void {
    $options = ['a' => new Option('a', 'A'), 'b' => new Option('b', 'B'), 'c' => new Option('c', 'C')];
    $field = new Field('f', 'F', '', FieldType::Select, '', $options, pageSize: 2);

    $view = (new FieldFactory())->create($field, 'a')->view(new DefaultTheme());

    // A page size of 2 over three options hides the last one and shows the
    // "more below" indicator, proving the field's page size reached the field.
    $this->assertStringContainsString('▼', $view);
    $this->assertStringNotContainsString('C', Ansi::strip($view));
  }

  public function testSuggestReceivesSelectableValuesOnly(): void {
    $field = new Field('tz', 'TZ', '', FieldType::Suggest, '', [
      new Option('utc', 'UTC'),
      new Option('gmt', 'GMT', '', OptionKind::Option, TRUE),
      new Option('', '', '', OptionKind::Separator),
    ]);

    $field = (new FieldFactory())->create($field, '');
    $view = $field->view(new DefaultTheme());

    $this->assertStringContainsString('utc', $view);
    $this->assertStringNotContainsString('gmt', $view);
  }

  public function testPerOptionDescriptionReachesChoiceField(): void {
    $field = new Field('f', 'F', '', FieldType::Select, 'a', [
      new Option('a', 'Apple', 'Crisp and sweet.'),
      new Option('b', 'Banana', 'Rich in potassium.'),
    ]);

    $view = Ansi::strip((new FieldFactory())->create($field, 'a')->view(new DefaultTheme()));

    $this->assertStringContainsString('Crisp and sweet.', $view);
  }

  public function testPerOptionDescriptionReachesSuggest(): void {
    $field = new Field('f', 'F', '', FieldType::Suggest, '', [new Option('apple', 'Apple', 'Crisp and sweet.')]);

    $field = (new FieldFactory())->create($field, '');
    $field->handle(Key::named(KeyName::Down));

    $this->assertStringContainsString('Crisp and sweet.', Ansi::strip($field->view(new DefaultTheme())));
  }

  public function testTextCompletionStaticListReachesField(): void {
    $field = new Field('name', 'Name', '', FieldType::Text, '', completion: ['acme-site']);

    $view = (new FieldFactory())->create($field, 'ac')->view(new DefaultTheme());

    // The matching candidate's remaining suffix shows as dimmed ghost-text.
    $this->assertStringContainsString('me-site', $view);
  }

  public function testSuggestGhostFlagReachesField(): void {
    $options = ['Apple' => 'Apple', 'Apricot' => 'Apricot'];

    $off = new Field('fruit', 'Fruit', '', FieldType::Suggest, '', $options);
    $this->assertStringNotContainsString("\033[90m", (new FieldFactory())->create($off, 'ap')->view(new DefaultTheme()));

    $on = new Field('fruit', 'Fruit', '', FieldType::Suggest, '', $options, ghost: TRUE);
    $view = (new FieldFactory())->create($on, 'ap')->view(new DefaultTheme());

    // The opted-in field previews the leading candidate's remaining suffix.
    $this->assertStringContainsString('ple', $view);
    $this->assertStringContainsString("\033[90m", $view);
  }

  public function testTextCompletionClosureReceivesAnswers(): void {
    $seen = [];
    $field = new Field('repo', 'Repo', '', FieldType::Text, '', completion: function (array $answers) use (&$seen): array {
      $seen = $answers;

      return ['acme-site'];
    });

    $view = (new FieldFactory())->create($field, 'ac', ['owner' => 'acme'])->view(new DefaultTheme());

    // The closure is handed the answers collected so far and its result reaches
    // the field as ghost-text.
    $this->assertSame(['owner' => 'acme'], $seen);
    $this->assertStringContainsString('me-site', $view);
  }

  public function testTextCompletionCoercesInvalidResult(): void {
    // A mistyped source degrades to no completion rather than erroring: a list
    // with non-strings is filtered, and a non-list result is ignored.
    $items = new Field('a', 'A', '', FieldType::Text, '', completion: fn (array $answers): array => [123, NULL]);
    $this->assertStringNotContainsString("\033[90m", (new FieldFactory())->create($items, 'ac')->view(new DefaultTheme()));

    $scalar = new Field('b', 'B', '', FieldType::Text, '', completion: fn (array $answers): string => 'oops');
    $this->assertStringNotContainsString("\033[90m", (new FieldFactory())->create($scalar, 'ac')->view(new DefaultTheme()));
  }

  #[DataProvider('dataProviderPlaceholderReachesEveryCapableField')]
  public function testPlaceholderReachesEveryCapableField(FieldType $type): void {
    $field = new Field('f', 'F', '', $type, '', placeholder: 'E.g. Golden Beetroot');

    $view = Ansi::strip((new FieldFactory())->create($field, '')->view(new DefaultTheme()));

    $this->assertStringContainsString('E.g. Golden Beetroot', $view);
  }

  public static function dataProviderPlaceholderReachesEveryCapableField(): \Iterator {
    foreach (FieldType::cases() as $type) {
      if ($type->supportsPlaceholder()) {
        yield $type->value => [$type];
      }
    }
  }

  public function testFieldWithoutPlaceholderGhostsNothing(): void {
    $field = new Field('f', 'F', '', FieldType::Text, '');

    $this->assertStringNotContainsString("\033[90m", (new FieldFactory())->create($field, '')->view(new DefaultTheme()));
  }

  public function testDeclaredValidatorBlocksAcceptUntilItPasses(): void {
    $field = new Field('name', 'Name', '', FieldType::Text, '', validate: static fn (mixed $value): ?string => is_string($value) && $value !== '' ? NULL : 'A name is required.');

    $field = (new FieldFactory())->create($field, '');

    $field->handle(Key::named(KeyName::Enter));
    $this->assertFalse($field->isComplete());
    $this->assertSame('A name is required.', $field->error());

    $field->handle(Key::char('x'));
    $field->handle(Key::named(KeyName::Enter));
    $this->assertTrue($field->isComplete());
    $this->assertNull($field->error());
  }

  public function testRequiredBlocksAcceptWithNoDeclaredValidator(): void {
    $field = new Field('name', 'Produce name', '', FieldType::Text, '', required: TRUE);

    $field = (new FieldFactory())->create($field, '');

    $field->handle(Key::named(KeyName::Enter));
    $this->assertFalse($field->isComplete());
    $this->assertSame('Produce name is required.', $field->error());

    $field->handle(Key::char('P'));
    $field->handle(Key::named(KeyName::Enter));
    $this->assertTrue($field->isComplete());
    $this->assertNull($field->error());
  }

  public function testRequiredMessageOverrideReachesField(): void {
    $field = new Field('plot', 'Garden plot name', '', FieldType::Text, '', required: TRUE, requiredMessage: 'The garden plot name is required.');

    $field = (new FieldFactory())->create($field, '');
    $field->handle(Key::named(KeyName::Enter));

    $this->assertSame('The garden plot name is required.', $field->error());
  }

  public function testRequiredRunsBeforeTheDeclaredValidator(): void {
    $field = new Field('name', 'Produce name', '', FieldType::Text, '', required: TRUE, validate: static fn (mixed $value): ?string => $value === 'Pear' ? NULL : 'Only pears keep.');

    $field = (new FieldFactory())->create($field, '');

    // The empty value never reaches the declared validator...
    $field->handle(Key::named(KeyName::Enter));
    $this->assertSame('Produce name is required.', $field->error());

    // ...which still governs a non-empty one.
    $field->handle(Key::char('F'));
    $field->handle(Key::named(KeyName::Enter));
    $this->assertFalse($field->isComplete());
    $this->assertSame('Only pears keep.', $field->error());
  }

  public function testRequiredGuardsMultipleSelection(): void {
    $field = new Field('crates', 'Crates', '', FieldType::Select, [], ['a' => 'Apples', 'b' => 'Beans'], required: TRUE, multiple: TRUE);

    $field = (new FieldFactory())->create($field, []);

    $field->handle(Key::named(KeyName::Enter));
    $this->assertFalse($field->isComplete());
    $this->assertSame('Crates is required.', $field->error());

    $field->handle(Key::named(KeyName::Space));
    $field->handle(Key::named(KeyName::Enter));
    $this->assertTrue($field->isComplete());
    $this->assertSame(['a'], $field->value());
  }

  public function testDeclaredTransformAppliesOnAccept(): void {
    $field = new Field('variety', 'Variety', '', FieldType::Text, '', transform: static fn (mixed $value): mixed => is_string($value) ? strtolower(trim($value)) : $value);

    $field = (new FieldFactory())->create($field, ' Golden ');
    $field->handle(Key::named(KeyName::Enter));

    $this->assertSame('golden', $field->value());
  }

  public function testHandlerBehaviourReachesField(): void {
    $handlers = new HandlerRegistry(['DrevOps\Tui\Tests\Fixtures\Handler']);
    $field = new Field('machine_name', 'Machine name', '', FieldType::Text, '');

    $field = (new FieldFactory(handlers: $handlers))->create($field, '');

    // The registry's static validate() blocks the empty value...
    $field->handle(Key::named(KeyName::Enter));
    $this->assertFalse($field->isComplete());
    $this->assertSame('A machine name is required.', $field->error());

    // ...and its static transform() lowercases the accepted one.
    $field->handle(Key::char('A'));
    $field->handle(Key::named(KeyName::Enter));
    $this->assertTrue($field->isComplete());
    $this->assertSame('a', $field->value());
  }

  public function testDeclaredClosuresWinOverHandlerBehaviour(): void {
    $handlers = new HandlerRegistry(['DrevOps\Tui\Tests\Fixtures\Handler']);
    $field = new Field('machine_name', 'Machine name', '', FieldType::Text, '', validate: static fn (mixed $value): ?string => NULL, transform: static fn (mixed $value): mixed => is_string($value) ? strtoupper($value) : $value);

    $field = (new FieldFactory(handlers: $handlers))->create($field, 'a');
    $field->handle(Key::named(KeyName::Enter));

    // The declared closures replace the handler's: the accept is not blocked
    // and the value uppercases rather than lowercasing.
    $this->assertTrue($field->isComplete());
    $this->assertSame('A', $field->value());
  }

  /**
   * A field of the given type.
   *
   * @param \DrevOps\Tui\Model\FieldType $type
   *   The field type.
   */
  protected static function field(FieldType $type): Field {
    return new Field('f', 'F', '', $type, '');
  }

  /**
   * A template field with a two-slot shape.
   *
   * @return \DrevOps\Tui\Model\Field
   *   The field.
   */
  protected static function templateField(): Field {
    return new Field('f', 'F', '', FieldType::Template, '', template: new TemplateModel('{{a}}-{{b}}'));
  }

  /**
   * A rating field over a one-to-five scale with one captioned point.
   *
   * @return \DrevOps\Tui\Model\Field
   *   The field.
   */
  protected static function ratingField(): Field {
    return new Field('f', 'F', '', FieldType::Rating, 1, bounds: new NumberBounds(1, 5), ratingCaptions: [3 => 'Fair']);
  }

  /**
   * A choice field of the given type with two options.
   *
   * @param \DrevOps\Tui\Model\FieldType $type
   *   The field type.
   */
  protected static function fieldWithOptions(FieldType $type): Field {
    return new Field('f', 'F', '', $type, '', ['a' => new Option('a', 'A'), 'b' => new Option('b', 'B')]);
  }

  /**
   * A multiple-choice field of the given type with two options.
   *
   * @param \DrevOps\Tui\Model\FieldType $type
   *   The field type.
   */
  protected static function multiFieldWithOptions(FieldType $type): Field {
    return new Field('f', 'F', '', $type, [], ['a' => new Option('a', 'A'), 'b' => new Option('b', 'B')], multiple: TRUE);
  }

}
