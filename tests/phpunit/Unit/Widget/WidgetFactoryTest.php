<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Widget;

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
use DrevOps\Tui\Model\Template;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\CalendarWidget;
use DrevOps\Tui\Widget\ConfirmWidget;
use DrevOps\Tui\Widget\FilePickerWidget;
use DrevOps\Tui\Widget\NumberWidget;
use DrevOps\Tui\Widget\PasswordWidget;
use DrevOps\Tui\Widget\PauseWidget;
use DrevOps\Tui\Widget\RatingWidget;
use DrevOps\Tui\Widget\ReorderWidget;
use DrevOps\Tui\Widget\SearchWidget;
use DrevOps\Tui\Widget\SelectWidget;
use DrevOps\Tui\Widget\SuggestWidget;
use DrevOps\Tui\Widget\TemplateWidget;
use DrevOps\Tui\Widget\TextareaWidget;
use DrevOps\Tui\Widget\TextWidget;
use DrevOps\Tui\Widget\ToggleWidget;
use DrevOps\Tui\Widget\WidgetFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the widget factory.
 */
#[CoversClass(WidgetFactory::class)]
#[Group('widget')]
final class WidgetFactoryTest extends TestCase {

  #[DataProvider('dataProviderCreatesByType')]
  public function testCreatesByType(Field $field, mixed $current, string $expected): void {
    $this->assertInstanceOf($expected, (new WidgetFactory())->create($field, $current));
  }

  /**
   * Data provider for testCreatesByType().
   *
   * @return \Iterator<string, array{\DrevOps\Tui\Model\Field, mixed, class-string}>
   *   The field, the value it opens on and the widget class it builds.
   */
  public static function dataProviderCreatesByType(): \Iterator {
    yield 'text' => [self::field(FieldType::Text), 'x', TextWidget::class];
    yield 'confirm' => [self::field(FieldType::Confirm), TRUE, ConfirmWidget::class];
    yield 'toggle' => [self::fieldWithOptions(FieldType::Toggle), 'a', ToggleWidget::class];
    yield 'select' => [self::fieldWithOptions(FieldType::Select), 'a', SelectWidget::class];
    yield 'multiple select' => [self::multiFieldWithOptions(FieldType::Select), ['a'], SelectWidget::class];
    yield 'suggest' => [self::fieldWithOptions(FieldType::Suggest), 'a', SuggestWidget::class];
    yield 'number' => [self::field(FieldType::Number), 42, NumberWidget::class];
    yield 'rating' => [self::ratingField(), 3, RatingWidget::class];
    yield 'calendar' => [self::field(FieldType::Calendar), '2026-07-15', CalendarWidget::class];
    yield 'textarea' => [self::field(FieldType::Textarea), 'x', TextareaWidget::class];
    yield 'password' => [self::field(FieldType::Password), 'x', PasswordWidget::class];
    yield 'search' => [self::fieldWithOptions(FieldType::Search), 'a', SearchWidget::class];
    yield 'multiple search' => [self::multiFieldWithOptions(FieldType::Search), ['a'], SearchWidget::class];
    yield 'reorder' => [self::fieldWithOptions(FieldType::Reorder), ['a'], ReorderWidget::class];
    yield 'file picker' => [self::field(FieldType::FilePicker), '/nonexistent', FilePickerWidget::class];
    yield 'pause' => [self::field(FieldType::Pause), TRUE, PauseWidget::class];
    yield 'template' => [self::templateField(), 'one-two', TemplateWidget::class];
  }

  #[DataProvider('dataProviderSeedsValueFromCurrent')]
  public function testSeedsValueFromCurrent(Field $field, mixed $current, mixed $expected): void {
    $this->assertSame($expected, (new WidgetFactory())->create($field, $current)->value());
  }

  /**
   * Data provider for testSeedsValueFromCurrent().
   *
   * @return \Iterator<string, array{\DrevOps\Tui\Model\Field, mixed, mixed}>
   *   The field, the value it is handed and the value the widget opens on.
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
    // flag reaches the select and search widgets.
    yield 'multiple select' => [self::multiFieldWithOptions(FieldType::Select), ['a', 'b'], ['a', 'b']];
    yield 'multiple search' => [self::multiFieldWithOptions(FieldType::Search), ['a'], ['a']];
    // A current path outside the start is ignored and the missing directory
    // lists nothing, so a single picker opens empty.
    yield 'single picker outside its start' => [
      new Field('f', 'F', '', FieldType::FilePicker, '', pickerStart: '/nonexistent'),
      'x',
      '',
    ];

    yield 'multiple picker keeps its paths' => [
      new Field('g', 'G', '', FieldType::FilePicker, [], pickerStart: '/nonexistent', multiple: TRUE),
      ['/a', '/b'],
      ['/a', '/b'],
    ];
  }

  public function testDateWithNonStringCurrentOpensOnToday(): void {
    // Kept out of the seeding provider: a static provider would fix "today" at
    // collection time, so a run spanning midnight would fail.
    $widget = (new WidgetFactory())->create(self::field(FieldType::Calendar), 42);

    $this->assertSame((new \DateTimeImmutable('today'))->format('Y-m-d'), $widget->value());
  }

  public function testNoteHasNoEditorWidget(): void {
    // A note is presentational: the theme renders it and the cursor skips
    // it, so asking the factory to build an editor for one is a mistake.
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Note fields are presentational and have no editor widget.');

    (new WidgetFactory())->create(self::field(FieldType::Note), NULL);
  }

  public function testPasswordFlagsPassedThrough(): void {
    $field = new Field('f', 'F', '', FieldType::Password, '', revealable: TRUE, confirm: TRUE);

    $widget = (new WidgetFactory())->create($field, 'secret');

    // Revealable shows through the reveal hint the widget contributes.
    $labels = array_map(static fn(Hint $hint): string => $hint->label, $widget->hints());
    $this->assertContains('reveal', $labels);

    // Confirm shows through the two-step flow: the first Enter does not accept.
    $widget->handle(Key::named(KeyName::Enter));
    $this->assertFalse($widget->isComplete());
  }

  public function testNumberBoundsPassedThrough(): void {
    $field = new Field('f', 'F', '', FieldType::Number, 0, bounds: new NumberBounds(0, 10));

    $widget = (new WidgetFactory())->create($field, 5);

    // Bounds show through the adjust hint the widget contributes and stepping.
    $labels = array_map(static fn(Hint $hint): string => $hint->label, $widget->hints());
    $this->assertContains('adjust', $labels);
    $widget->handle(Key::named(KeyName::Up));
    $this->assertSame(6, $widget->value());
  }

  public function testRatingScaleAndCaptionsPassedThrough(): void {
    $widget = (new WidgetFactory())->create(self::ratingField(), 3);

    $this->assertStringContainsString('●●●○○ 3/5 Fair', Ansi::strip($widget->view(new DefaultTheme())));
  }

  public function testDateBoundsPassedThrough(): void {
    $field = new Field('f', 'F', '', FieldType::Calendar, '', dateBounds: new DateBounds(new \DateTimeImmutable('2026-07-10'), new \DateTimeImmutable('2026-07-20')));

    $widget = (new WidgetFactory())->create($field, '2026-07-01');

    // The seed is clamped into the field's declared range.
    $this->assertSame('2026-07-10', $widget->value());
  }

  public function testCreatingProgressWidgetThrows(): void {
    // A progress row runs its work on activation; it has no editor to build.
    $field = new Field('p', 'P', '', FieldType::Progress, NULL);

    $this->expectException(\LogicException::class);

    (new WidgetFactory())->create($field, NULL);
  }

  #[DataProvider('dataProviderTextareaExternalEditorHandoff')]
  public function testTextareaExternalEditorHandoff(bool $opted_in, bool $available, bool $expected): void {
    $field = new Field('f', 'F', '', FieldType::Textarea, '', externalEditor: $opted_in);

    $widget = (new WidgetFactory(externalEditorAvailable: $available))->create($field, 'x');
    $this->assertInstanceOf(TextareaWidget::class, $widget);

    $widget->handle(Key::char("\x05"));
    $this->assertSame($expected, $widget->wantsExternalEdit());
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

  public function testInjectsScopedKeymapIntoWidget(): void {
    // The vim preset binds j to move-down in the select scope, so the injected
    // widget responds to j where a default-preset widget would not.
    $widget = (new WidgetFactory(KeyMapManager::create('vim')))->create(self::fieldWithOptions(FieldType::Select), 'a');

    $widget->handle(Key::char('j'));

    $this->assertSame('b', $widget->value());
  }

  public function testPageSizePassedThrough(): void {
    $options = ['a' => new Option('a', 'A'), 'b' => new Option('b', 'B'), 'c' => new Option('c', 'C')];
    $field = new Field('f', 'F', '', FieldType::Select, '', $options, pageSize: 2);

    $view = (new WidgetFactory())->create($field, 'a')->view(new DefaultTheme());

    // A page size of 2 over three options hides the last one and shows the
    // "more below" indicator, proving the field's page size reached the widget.
    $this->assertStringContainsString('▼', $view);
    $this->assertStringNotContainsString('C', Ansi::strip($view));
  }

  public function testSuggestReceivesSelectableValuesOnly(): void {
    $field = new Field('tz', 'TZ', '', FieldType::Suggest, '', [
      new Option('utc', 'UTC'),
      new Option('gmt', 'GMT', '', OptionKind::Option, TRUE),
      new Option('', '', '', OptionKind::Separator),
    ]);

    $widget = (new WidgetFactory())->create($field, '');
    $view = $widget->view(new DefaultTheme());

    $this->assertStringContainsString('utc', $view);
    $this->assertStringNotContainsString('gmt', $view);
  }

  public function testPerOptionDescriptionReachesChoiceWidget(): void {
    $field = new Field('f', 'F', '', FieldType::Select, 'a', [
      new Option('a', 'Apple', 'Crisp and sweet.'),
      new Option('b', 'Banana', 'Rich in potassium.'),
    ]);

    $view = Ansi::strip((new WidgetFactory())->create($field, 'a')->view(new DefaultTheme()));

    $this->assertStringContainsString('Crisp and sweet.', $view);
  }

  public function testPerOptionDescriptionReachesSuggest(): void {
    $field = new Field('f', 'F', '', FieldType::Suggest, '', [new Option('apple', 'Apple', 'Crisp and sweet.')]);

    $widget = (new WidgetFactory())->create($field, '');
    $widget->handle(Key::named(KeyName::Down));

    $this->assertStringContainsString('Crisp and sweet.', Ansi::strip($widget->view(new DefaultTheme())));
  }

  public function testTextCompletionStaticListReachesWidget(): void {
    $field = new Field('name', 'Name', '', FieldType::Text, '', completion: ['acme-site']);

    $view = (new WidgetFactory())->create($field, 'ac')->view(new DefaultTheme());

    // The matching candidate's remaining suffix shows as dimmed ghost-text.
    $this->assertStringContainsString('me-site', $view);
  }

  public function testSuggestGhostFlagReachesWidget(): void {
    $options = ['Apple' => 'Apple', 'Apricot' => 'Apricot'];

    $off = new Field('fruit', 'Fruit', '', FieldType::Suggest, '', $options);
    $this->assertStringNotContainsString("\033[90m", (new WidgetFactory())->create($off, 'ap')->view(new DefaultTheme()));

    $on = new Field('fruit', 'Fruit', '', FieldType::Suggest, '', $options, ghost: TRUE);
    $view = (new WidgetFactory())->create($on, 'ap')->view(new DefaultTheme());

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

    $view = (new WidgetFactory())->create($field, 'ac', ['owner' => 'acme'])->view(new DefaultTheme());

    // The closure is handed the answers collected so far and its result reaches
    // the widget as ghost-text.
    $this->assertSame(['owner' => 'acme'], $seen);
    $this->assertStringContainsString('me-site', $view);
  }

  public function testTextCompletionCoercesInvalidResult(): void {
    // A mistyped source degrades to no completion rather than erroring: a list
    // with non-strings is filtered, and a non-list result is ignored.
    $items = new Field('a', 'A', '', FieldType::Text, '', completion: fn (array $answers): array => [123, NULL]);
    $this->assertStringNotContainsString("\033[90m", (new WidgetFactory())->create($items, 'ac')->view(new DefaultTheme()));

    $scalar = new Field('b', 'B', '', FieldType::Text, '', completion: fn (array $answers): string => 'oops');
    $this->assertStringNotContainsString("\033[90m", (new WidgetFactory())->create($scalar, 'ac')->view(new DefaultTheme()));
  }

  #[DataProvider('dataProviderPlaceholderReachesEveryCapableWidget')]
  public function testPlaceholderReachesEveryCapableWidget(FieldType $type): void {
    $field = new Field('f', 'F', '', $type, '', placeholder: 'E.g. Golden Beetroot');

    $view = Ansi::strip((new WidgetFactory())->create($field, '')->view(new DefaultTheme()));

    $this->assertStringContainsString('E.g. Golden Beetroot', $view);
  }

  public static function dataProviderPlaceholderReachesEveryCapableWidget(): \Iterator {
    foreach (FieldType::cases() as $type) {
      if ($type->supportsPlaceholder()) {
        yield $type->value => [$type];
      }
    }
  }

  public function testFieldWithoutPlaceholderGhostsNothing(): void {
    $field = new Field('f', 'F', '', FieldType::Text, '');

    $this->assertStringNotContainsString("\033[90m", (new WidgetFactory())->create($field, '')->view(new DefaultTheme()));
  }

  public function testDeclaredValidatorBlocksAcceptUntilItPasses(): void {
    $field = new Field('name', 'Name', '', FieldType::Text, '', validate: static fn (mixed $value): ?string => is_string($value) && $value !== '' ? NULL : 'A name is required.');

    $widget = (new WidgetFactory())->create($field, '');

    $widget->handle(Key::named(KeyName::Enter));
    $this->assertFalse($widget->isComplete());
    $this->assertSame('A name is required.', $widget->error());

    $widget->handle(Key::char('x'));
    $widget->handle(Key::named(KeyName::Enter));
    $this->assertTrue($widget->isComplete());
    $this->assertNull($widget->error());
  }

  public function testRequiredBlocksAcceptWithNoDeclaredValidator(): void {
    $field = new Field('name', 'Produce name', '', FieldType::Text, '', required: TRUE);

    $widget = (new WidgetFactory())->create($field, '');

    $widget->handle(Key::named(KeyName::Enter));
    $this->assertFalse($widget->isComplete());
    $this->assertSame('Produce name is required.', $widget->error());

    $widget->handle(Key::char('P'));
    $widget->handle(Key::named(KeyName::Enter));
    $this->assertTrue($widget->isComplete());
    $this->assertNull($widget->error());
  }

  public function testRequiredMessageOverrideReachesWidget(): void {
    $field = new Field('plot', 'Garden plot name', '', FieldType::Text, '', required: TRUE, requiredMessage: 'The garden plot name is required.');

    $widget = (new WidgetFactory())->create($field, '');
    $widget->handle(Key::named(KeyName::Enter));

    $this->assertSame('The garden plot name is required.', $widget->error());
  }

  public function testRequiredRunsBeforeTheDeclaredValidator(): void {
    $field = new Field('name', 'Produce name', '', FieldType::Text, '', required: TRUE, validate: static fn (mixed $value): ?string => $value === 'Pear' ? NULL : 'Only pears keep.');

    $widget = (new WidgetFactory())->create($field, '');

    // The empty value never reaches the declared validator...
    $widget->handle(Key::named(KeyName::Enter));
    $this->assertSame('Produce name is required.', $widget->error());

    // ...which still governs a non-empty one.
    $widget->handle(Key::char('F'));
    $widget->handle(Key::named(KeyName::Enter));
    $this->assertFalse($widget->isComplete());
    $this->assertSame('Only pears keep.', $widget->error());
  }

  public function testRequiredGuardsMultipleSelection(): void {
    $field = new Field('crates', 'Crates', '', FieldType::Select, [], ['a' => 'Apples', 'b' => 'Beans'], required: TRUE, multiple: TRUE);

    $widget = (new WidgetFactory())->create($field, []);

    $widget->handle(Key::named(KeyName::Enter));
    $this->assertFalse($widget->isComplete());
    $this->assertSame('Crates is required.', $widget->error());

    $widget->handle(Key::named(KeyName::Space));
    $widget->handle(Key::named(KeyName::Enter));
    $this->assertTrue($widget->isComplete());
    $this->assertSame(['a'], $widget->value());
  }

  public function testDeclaredTransformAppliesOnAccept(): void {
    $field = new Field('variety', 'Variety', '', FieldType::Text, '', transform: static fn (mixed $value): mixed => is_string($value) ? strtolower(trim($value)) : $value);

    $widget = (new WidgetFactory())->create($field, ' Golden ');
    $widget->handle(Key::named(KeyName::Enter));

    $this->assertSame('golden', $widget->value());
  }

  public function testHandlerBehaviourReachesWidget(): void {
    $handlers = new HandlerRegistry(['DrevOps\Tui\Tests\Fixtures\Handler']);
    $field = new Field('machine_name', 'Machine name', '', FieldType::Text, '');

    $widget = (new WidgetFactory(handlers: $handlers))->create($field, '');

    // The registry's static validate() blocks the empty value...
    $widget->handle(Key::named(KeyName::Enter));
    $this->assertFalse($widget->isComplete());
    $this->assertSame('A machine name is required.', $widget->error());

    // ...and its static transform() lowercases the accepted one.
    $widget->handle(Key::char('A'));
    $widget->handle(Key::named(KeyName::Enter));
    $this->assertTrue($widget->isComplete());
    $this->assertSame('a', $widget->value());
  }

  public function testDeclaredClosuresWinOverHandlerBehaviour(): void {
    $handlers = new HandlerRegistry(['DrevOps\Tui\Tests\Fixtures\Handler']);
    $field = new Field('machine_name', 'Machine name', '', FieldType::Text, '', validate: static fn (mixed $value): ?string => NULL, transform: static fn (mixed $value): mixed => is_string($value) ? strtoupper($value) : $value);

    $widget = (new WidgetFactory(handlers: $handlers))->create($field, 'a');
    $widget->handle(Key::named(KeyName::Enter));

    // The declared closures replace the handler's: the accept is not blocked
    // and the value uppercases rather than lowercasing.
    $this->assertTrue($widget->isComplete());
    $this->assertSame('A', $widget->value());
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
    return new Field('f', 'F', '', FieldType::Template, '', template: new Template('{{a}}-{{b}}'));
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
