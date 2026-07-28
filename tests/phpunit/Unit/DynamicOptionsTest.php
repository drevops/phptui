<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit;

use DrevOps\Tui\Builder\FieldBuilder;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Engine\Engine;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\FormException;
use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Render\PanelController;
use DrevOps\Tui\Schema\AgentHelp;
use DrevOps\Tui\Schema\OptionsResolver;
use DrevOps\Tui\Schema\SchemaGenerator;
use DrevOps\Tui\Schema\SchemaValidator;
use DrevOps\Tui\Testing\TuiTester;
use DrevOps\Tui\Tui;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests options resolved from the answers collected so far.
 */
#[CoversClass(FieldBuilder::class)]
#[CoversClass(Field::class)]
#[CoversClass(FieldType::class)]
#[CoversClass(Option::class)]
#[CoversClass(Engine::class)]
#[CoversClass(OptionsResolver::class)]
#[CoversClass(PanelController::class)]
#[CoversClass(SchemaGenerator::class)]
#[CoversClass(SchemaValidator::class)]
#[CoversClass(AgentHelp::class)]
#[CoversClass(Tui::class)]
#[Group('tui')]
final class DynamicOptionsTest extends TestCase {

  /**
   * The produce each category holds, keyed by value.
   *
   * @var array<string,array<string,string>>
   */
  protected const array CATALOG = [
    'fruit' => ['apple' => 'Apple', 'banana' => 'Banana', 'cherry' => 'Cherry'],
    'vegetable' => ['carrot' => 'Carrot', 'potato' => 'Potato', 'tomato' => 'Tomato'],
  ];

  /**
   * The contexts the scripted resolver was called with, in order.
   *
   * @var list<\DrevOps\Tui\Handler\Context>
   */
  protected array $contexts = [];

  public function testResolvesTheOptionsOfTheAnsweredCategory(): void {
    $answers = (new Tui($this->form()))->collect('{"category":"vegetable","item":"carrot"}');

    $this->assertSame('carrot', $answers->value('item'));
    $this->assertSame('vegetable', $this->lastContext()->answers['category']);
  }

  public function testRejectsValueTheResolvedSetDoesNotHold(): void {
    // Apple is a real option - just not one this category resolves to.
    $this->expectException(EngineException::class);
    $this->expectExceptionMessageMatches('/not one of: carrot, potato, tomato/');

    (new Tui($this->form()))->collect('{"category":"vegetable","item":"apple"}');
  }

  public function testRejectsValueWhenTheSetResolvesToNothing(): void {
    // With no list to offer, naming the value is all that can honestly be said.
    $this->expectException(EngineException::class);
    $this->expectExceptionMessageMatches('/"apple" was not found/');

    (new Tui($this->form(static fn(): array => [])))->collect('{"category":"fruit","item":"apple"}');
  }

  public function testResolverReadsTheWholeRunContext(): void {
    (new Tui($this->form()))->collect('{"category":"fruit"}', 'orchard', TRUE, '2.0');

    $context = $this->lastContext();
    $this->assertSame('orchard', $context->directory);
    $this->assertTrue($context->update);
    $this->assertSame('2.0', $context->version);
  }

  public function testResolverReadsDerivedAnswers(): void {
    $form = Form::create('Order')->panel('order', 'New order', function (PanelBuilder $p): void {
      $p->text('label', 'Order label')->default('Vegetable');
      $p->text('category', 'Category')->derive(new Derive('{{label}}', transform: 'lower'));
      $p->select('item', 'Item')->options($this->resolver());
    });

    // The derived category settles before the options resolve, so the resolver
    // sees the computed value rather than the empty one it started as.
    $answers = (new Tui($form))->collect('{"item":"carrot"}');

    $this->assertSame('carrot', $answers->value('item'));
  }

  public function testDefaultOutsideTheResolvedSetIsDropped(): void {
    $form = $this->form(NULL, static fn(FieldBuilder $field): FieldBuilder => $field->default('apple'));

    $answers = (new Tui($form))->collect('{"category":"vegetable"}');

    $this->assertSame('', $answers->value('item'));
  }

  public function testDefaultInsideTheResolvedSetStands(): void {
    $form = $this->form(NULL, static fn(FieldBuilder $field): FieldBuilder => $field->default('apple'));

    $answers = (new Tui($form))->collect('{"category":"fruit"}');

    $this->assertSame('apple', $answers->value('item'));
  }

  public function testMultipleKeepsOnlyTheValuesTheSetStillHolds(): void {
    $form = Form::create('Order')->panel('order', 'New order', function (PanelBuilder $p): void {
      $p->select('category', 'Category')->options(['fruit' => 'Fruit', 'vegetable' => 'Vegetable'])->default('fruit');
      $p->select('basket', 'Basket')->multiple()->default(['apple', 'carrot', 'cherry'])->options($this->resolver());
    });

    $answers = (new Tui($form))->collect('{"category":"fruit"}');

    $this->assertSame(['apple', 'cherry'], $answers->value('basket'));
  }

  public function testRankingIsCompletedToTheResolvedOptions(): void {
    $form = Form::create('Order')->panel('order', 'New order', function (PanelBuilder $p): void {
      $p->select('category', 'Category')->options(['fruit' => 'Fruit', 'vegetable' => 'Vegetable'])->default('vegetable');
      $p->reorder('ranking', 'Ranking')->options($this->resolver());
    });

    // A reorder is always a full permutation, so the resolved set becomes the
    // ranking even though the field was declared without one.
    $answers = (new Tui($form))->collect('{}');

    $this->assertSame(['carrot', 'potato', 'tomato'], $answers->value('ranking'));
  }

  public function testToggleFallsBackToTheFirstResolvedOption(): void {
    $form = Form::create('Order')->panel('order', 'New order', function (PanelBuilder $p): void {
      $p->select('category', 'Category')->options(['fruit' => 'Fruit', 'vegetable' => 'Vegetable'])->default('vegetable');
      $p->toggle('item', 'Item')->options($this->resolver());
    });

    // A toggle is always in one of its states, and it has none until the
    // resolver hands it some.
    $answers = (new Tui($form))->collect('{}');

    $this->assertSame('carrot', $answers->value('item'));
  }

  public function testSuggestKeepsAValueTheResolvedHintsDoNotHold(): void {
    $form = Form::create('Order')->panel('order', 'New order', function (PanelBuilder $p): void {
      $p->select('category', 'Category')->options(['fruit' => 'Fruit', 'vegetable' => 'Vegetable'])->default('fruit');
      $p->suggest('item', 'Item')->options($this->resolver());
    });

    // A suggest field's options are hints, never a closed set, so a value
    // outside them is the user's own rather than a mistake.
    $answers = (new Tui($form))->collect('{"item":"Quince"}');

    $this->assertSame('Quince', $answers->value('item'));
  }

  public function testResolverReturningSomethingElseDegradesToNoOptions(): void {
    $form = $this->form(static fn(): mixed => 'not a map');

    $this->expectException(EngineException::class);
    $this->expectExceptionMessageMatches('/"apple" was not found/');

    (new Tui($form))->collect('{"category":"fruit","item":"apple"}');
  }

  public function testThrowingResolverBecomesAnEngineError(): void {
    $form = $this->form(static function (): array {
      throw new \RuntimeException('The pantry is unreachable.');
    });

    $this->expectException(EngineException::class);
    $this->expectExceptionMessageMatches('/Could not load options for field "item": The pantry is unreachable\./');

    (new Tui($form))->collect('{"category":"fruit"}');
  }

  public function testInteractiveListNarrowsToTheChosenCategory(): void {
    $tester = $this->tester($this->form());
    // Open the category, step to Vegetable, take it, then open the item.
    $tester->run($this->enter(), $this->enter(), $this->down(), $this->enter(), $this->down(), $this->enter());

    $display = $tester->display();
    $this->assertStringContainsString('Carrot', $display);
    $this->assertStringNotContainsString('Apple', $display);
  }

  public function testInteractiveSelectionIsDroppedOnceTheSetNarrows(): void {
    $tester = $this->tester($this->form());
    // Take Apple under Fruit, then go back and switch the category: the choice
    // the new category does not offer is not left standing in the answers.
    $answers = $tester->run(
      $this->enter(),
      $this->down(),
      $this->enter(),
      $this->enter(),
      $this->up(),
      $this->enter(),
      $this->down(),
      $this->enter(),
    );

    $this->assertSame('vegetable', $answers->value('category'));
    $this->assertSame('', $answers->value('item'));
  }

  public function testCallbackAskingForNothingStillLoadsOnce(): void {
    $calls = 0;
    $form = Form::create('Order')->panel('order', 'New order', function (PanelBuilder $p) use (&$calls): void {
      $p->select('category', 'Category')->options(['fruit' => 'Fruit', 'vegetable' => 'Vegetable'])->default('fruit');
      $p->select('item', 'Item')->options(function () use (&$calls): array {
        $calls++;

        return ['apple' => 'Apple'];
      });
    });

    // The callback asks for no context, so it is the loader it has always been:
    // resolved once when the panel opens, and left alone when an answer it
    // cannot read changes under it.
    $tester = $this->tester($form);
    $tester->run($this->enter(), $this->enter(), $this->down(), $this->enter(), $this->down(), $this->enter());

    $this->assertSame(1, $calls);
    $this->assertStringContainsString('Apple', $tester->display());
  }

  public function testResolverIsNotCalledAgainWhileTheAnswersStand(): void {
    $tester = $this->tester($this->form());
    // Moving the cursor and opening an editor changes no answer, so the options
    // in front of the user are still the ones already resolved.
    $tester->run($this->down(), $this->down(), $this->up(), $this->enter());

    $this->assertCount(1, $this->contexts);
  }

  public function testValidatorChecksMembershipAgainstTheResolvedSet(): void {
    $tui = new Tui($this->form());

    $this->assertSame([], $tui->validate(['category' => 'vegetable', 'item' => 'carrot']));
    $this->assertSame(['Question "item": value "apple" is not one of: carrot, potato, tomato.'], $tui->validate(['category' => 'vegetable', 'item' => 'apple']));
  }

  public function testValidatorCarriesTheRunContextToTheResolver(): void {
    // The resolver sees what it would see during collection, so one reading the
    // directory or the update flag does not judge a value against a set that
    // only a bare context produces.
    (new Tui($this->form()))->validate(['category' => 'vegetable', 'item' => 'carrot'], new Context('orchard', [], TRUE, '2.0'));

    $context = $this->lastContext();
    $this->assertSame('orchard', $context->directory);
    $this->assertTrue($context->update);
    $this->assertSame('2.0', $context->version);
    $this->assertSame('vegetable', $context->answers['category']);
  }

  public function testResolverIsCalledAgainForAnotherRunContext(): void {
    $tui = new Tui($this->form());

    // Same answers, another directory: a resolver reading the context has a
    // different question to answer, so the first run's options are not reused.
    $tui->collect('{"category":"fruit"}', 'orchard');
    $tui->collect('{"category":"fruit"}', 'market');

    $this->assertSame(['orchard', 'market'], array_map(static fn(Context $context): string => $context->directory, $this->contexts));
  }

  public function testFailedResolutionLeavesNoOptionsForSchemaSurfaces(): void {
    // The first context resolves; the second cannot. Its description must not
    // fall back to the set the first one produced.
    $form = $this->form(static function (Context $context): array {
      if (($context->answers['category'] ?? '') === 'vegetable') {
        throw new \RuntimeException('The pantry is unreachable.');
      }

      return self::CATALOG['fruit'];
    });

    $tui = new Tui($form);
    $this->assertSame(['apple', 'banana', 'cherry'], $this->schemaOptions($tui->schema(new Context(answers: ['category' => 'fruit'])), 'item'));
    $this->assertSame([], $this->schemaOptions($tui->schema(new Context(answers: ['category' => 'vegetable'])), 'item'));
  }

  public function testSchemaResolvesTheOptionsOfTheGivenContext(): void {
    $schema = (new Tui($this->form()))->schema(new Context(answers: ['category' => 'vegetable']));

    $this->assertSame(['carrot', 'potato', 'tomato'], $this->schemaOptions($schema, 'item'));
    $this->assertTrue($this->schemaFlag($schema, 'item', 'options_dynamic'));
    $this->assertFalse($this->schemaFlag($schema, 'category', 'options_dynamic'));
  }

  public function testSchemaFlagsOptionsThatFollowTheQuery(): void {
    $form = Form::create('Order')->panel('order', 'New order', function (PanelBuilder $p): void {
      $p->search('veg', 'Vegetable')->optionsFrom(static fn(string $query): array => []);
    });

    $this->assertTrue($this->schemaFlag((new Tui($form))->schema(), 'veg', 'options_dynamic'));
  }

  public function testAgentHelpEnumeratesTheResolvedValues(): void {
    $help = (new Tui($this->form()))->agentHelp(new Context(answers: ['category' => 'vegetable']));

    $this->assertStringContainsString('carrot', $help);
    $this->assertStringNotContainsString('apple', $help);
  }

  public function testReconcilingLeavesAValueThatIsNotAChoiceAlone(): void {
    $field = new Field('name', 'Order name', '', FieldType::Text, 'Pear');

    $this->assertSame('Pear', $field->reconcileValue('Pear'));
  }

  #[DataProvider('dataProviderRejectedDeclarationFailsWhenTheFormIsBuilt')]
  public function testRejectedDeclarationFailsWhenTheFormIsBuilt(\Closure $declare, string $message): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessageMatches($message);

    Form::create('Order')->panel('order', 'New order', $declare)->build();
  }

  /**
   * Declarations an options resolver cannot be combined with.
   *
   * @return \Iterator<string,array{\Closure,string}>
   *   The panel declaration and the expected message pattern, per case.
   */
  public static function dataProviderRejectedDeclarationFailsWhenTheFormIsBuilt(): \Iterator {
    $resolver = static fn(Context $context): array => [];

    yield 'a resolver on a type with no option list' => [
      static function (PanelBuilder $p) use ($resolver): void {
        $p->text('item', 'Item')->options($resolver);
      },
      '/of type "text" shows no options/',
    ];

    yield 'a fixed list on a type with no option list' => [
      static function (PanelBuilder $p): void {
        $p->number('quantity', 'Quantity')->options(['1' => 'One']);
      },
      '/of type "number" shows no options/',
    ];

    yield 'a loader on a type with no option list' => [
      static function (PanelBuilder $p): void {
        $p->text('item', 'Item')->options(static fn(): array => ['apple' => 'Apple']);
      },
      '/of type "text" shows no options/',
    ];

    yield 'alongside static options' => [
      static function (PanelBuilder $p) use ($resolver): void {
        $p->select('item', 'Item')->options(['apple' => 'Apple'])->options($resolver);
      },
      '/declare only one/',
    ];

    yield 'alongside an option loader' => [
      static function (PanelBuilder $p) use ($resolver): void {
        $p->select('item', 'Item')->options(static fn(): array => ['apple' => 'Apple'])->options($resolver);
      },
      '/declare only one/',
    ];

    yield 'alongside a query source' => [
      static function (PanelBuilder $p) use ($resolver): void {
        $p->search('item', 'Item')->optionsFrom(static fn(string $query): array => [])->options($resolver);
      },
      '/declare only one/',
    ];
  }

  /**
   * A single-panel order form whose item options follow the chosen category.
   *
   * @param \Closure|null $answer
   *   The `fn (Context $context): mixed` the resolver answers with; NULL reads
   *   the catalog of the answered category.
   * @param \Closure|null $declare
   *   A `fn (FieldBuilder): FieldBuilder` adding further declarations.
   *
   * @return \DrevOps\Tui\Builder\Form
   *   The form.
   */
  protected function form(?\Closure $answer = NULL, ?\Closure $declare = NULL): Form {
    return Form::create('Order')->panel('order', 'New order', function (PanelBuilder $p) use ($answer, $declare): void {
      $p->select('category', 'Category')->options(['fruit' => 'Fruit', 'vegetable' => 'Vegetable'])->default('fruit');
      $field = $p->select('item', 'Item')->options($this->resolver($answer));

      if ($declare instanceof \Closure) {
        $declare($field);
      }
    });
  }

  /**
   * An options resolver that records the context before it answers.
   *
   * @param \Closure|null $answer
   *   The `fn (Context $context): mixed` to answer with; NULL reads the catalog
   *   of the answered category.
   *
   * @return \Closure
   *   The recording resolver.
   */
  protected function resolver(?\Closure $answer = NULL): \Closure {
    return function (Context $context) use ($answer): mixed {
      $this->contexts[] = $context;

      if ($answer instanceof \Closure) {
        return $answer($context);
      }

      $category = $context->answers['category'] ?? '';

      return is_string($category) ? (self::CATALOG[$category] ?? []) : [];
    };
  }

  /**
   * The option values a generated schema advertises for a prompt.
   *
   * @param array<string,mixed> $schema
   *   The generated schema.
   * @param string $id
   *   The prompt id.
   *
   * @return list<string>
   *   The advertised option values, in order.
   */
  protected function schemaOptions(array $schema, string $id): array {
    $options = $this->schemaFor($schema, $id)['options'] ?? NULL;

    if (!is_array($options)) {
      $this->fail(sprintf('The prompt "%s" carries no options.', $id));
    }

    $values = [];

    foreach ($options as $option) {
      $value = is_array($option) ? ($option['value'] ?? NULL) : NULL;

      if (!is_string($value)) {
        $this->fail(sprintf('The prompt "%s" carries an option with no value.', $id));
      }

      $values[] = $value;
    }

    return $values;
  }

  /**
   * A boolean a generated schema carries for a prompt.
   *
   * @param array<string,mixed> $schema
   *   The generated schema.
   * @param string $id
   *   The prompt id.
   * @param string $key
   *   The key of the boolean.
   *
   * @return bool
   *   The advertised value.
   */
  protected function schemaFlag(array $schema, string $id, string $key): bool {
    $flag = $this->schemaFor($schema, $id)[$key] ?? NULL;

    if (!is_bool($flag)) {
      $this->fail(sprintf('The prompt "%s" carries no "%s" flag.', $id, $key));
    }

    return $flag;
  }

  /**
   * The prompt entry a generated schema carries for a field.
   *
   * @param array<string,mixed> $schema
   *   The generated schema.
   * @param string $id
   *   The prompt id.
   *
   * @return array<array-key,mixed>
   *   The prompt entry.
   */
  protected function schemaFor(array $schema, string $id): array {
    $prompts = $schema['prompts'] ?? NULL;

    if (!is_array($prompts)) {
      $this->fail('The schema carries no prompts.');
    }

    foreach ($prompts as $prompt) {
      if (is_array($prompt) && ($prompt['id'] ?? NULL) === $id) {
        return $prompt;
      }
    }

    $this->fail(sprintf('The schema carries no prompt "%s".', $id));
  }

  /**
   * The context of the resolver's most recent call.
   *
   * @return \DrevOps\Tui\Handler\Context
   *   The context.
   */
  protected function lastContext(): Context {
    $this->assertNotEmpty($this->contexts);

    return $this->contexts[count($this->contexts) - 1];
  }

  /**
   * A tester rendering the form deterministically at a workable height.
   *
   * @param \DrevOps\Tui\Builder\Form $form
   *   The form.
   *
   * @return \DrevOps\Tui\Testing\TuiTester
   *   The tester.
   */
  protected function tester(Form $form): TuiTester {
    return (new TuiTester($form))->rows(16);
  }

  /**
   * The Enter key, as its own read.
   *
   * @return \DrevOps\Tui\Input\Key
   *   The key.
   */
  protected function enter(): Key {
    return Key::named(KeyName::Enter);
  }

  /**
   * The Down key, as its own read.
   *
   * @return \DrevOps\Tui\Input\Key
   *   The key.
   */
  protected function down(): Key {
    return Key::named(KeyName::Down);
  }

  /**
   * The Up key, as its own read.
   *
   * @return \DrevOps\Tui\Input\Key
   *   The key.
   */
  protected function up(): Key {
    return Key::named(KeyName::Up);
  }

}
