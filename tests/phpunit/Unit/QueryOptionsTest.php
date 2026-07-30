<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit;

use DrevOps\Tui\Builder\FieldBuilder;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Engine\Engine;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\FormException;
use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Render\PanelController;
use DrevOps\Tui\Testing\TuiTester;
use DrevOps\Tui\Tui;
use DrevOps\Tui\Field\Capability\QueryOptionsCapableTrait;
use DrevOps\Tui\Field\Search;
use DrevOps\Tui\Field\Suggest;
use DrevOps\Tui\Field\FieldFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests options sourced from the live query as the user types.
 */
#[CoversClass(FieldBuilder::class)]
#[CoversClass(Field::class)]
#[CoversClass(FieldType::class)]
#[CoversClass(Option::class)]
#[CoversClass(Engine::class)]
#[CoversClass(PanelController::class)]
#[CoversClass(FieldFactory::class)]
#[CoversClass(Search::class)]
#[CoversClass(Suggest::class)]
#[CoversTrait(QueryOptionsCapableTrait::class)]
#[Group('tui')]
final class QueryOptionsTest extends TestCase {

  /**
   * The produce the scripted source answers from, keyed by value.
   *
   * @var array<string,string>
   */
  protected const array PRODUCE = [
    'carrot' => 'Carrot',
    'potato' => 'Potato',
    'onion' => 'Onion',
    'pepper' => 'Pepper',
  ];

  /**
   * The queries the scripted source was asked, in order.
   *
   * @var list<string>
   */
  protected array $queries = [];

  public function testResolvesTheEmptyQueryWhenTheEditorOpens(): void {
    $tester = $this->tester($this->form());
    $tester->run($this->enter(), $this->enter());

    $this->assertSame([''], $this->queries);
    $this->assertStringContainsString('Carrot', $tester->display());
  }

  public function testTypingResolvesTheTypedQuery(): void {
    $tester = $this->tester($this->form());
    $tester->run($this->enter(), $this->enter(), 'pot');

    $this->assertSame(['', 'pot'], $this->queries);
    $this->assertStringContainsString('Potato', $tester->display());
  }

  public function testTypingBurstCostsTheSourceOneCall(): void {
    // The three characters arrive in one read, so they settle into one query
    // rather than one per character.
    $this->tester($this->form())->run($this->enter(), $this->enter(), 'pot');

    $this->assertSame(['', 'pot'], $this->queries);
  }

  public function testCharactersArrivingSeparatelyEachResolve(): void {
    $this->tester($this->form())->run($this->enter(), $this->enter(), 'p', 'o', 't');

    $this->assertSame(['', 'p', 'po', 'pot'], $this->queries);
  }

  public function testRepeatedQueryIsServedFromTheCache(): void {
    // Type "po", step back to "p", then forward to "po" again: both of the
    // retyped queries were answered already, so neither reaches the source.
    $this->tester($this->form())->run($this->enter(), $this->enter(), 'p', 'o', Key::named(KeyName::Backspace), 'o');

    $this->assertSame(['', 'p', 'po'], $this->queries);
  }

  public function testTheLoadingIndicatorIsPaintedBeforeTheResultArrives(): void {
    $tester = $this->tester($this->form());
    $tester->run($this->enter(), $this->enter());

    // The frame painted while the source was called carries the themed loading
    // ellipsis; the resolved list follows it.
    $display = $tester->display();
    $this->assertStringContainsString('…', $display);
    $this->assertStringContainsString('Carrot', $display);
  }

  public function testTheResolvedRowsAreNotFilteredAgainstTheQuery(): void {
    // The source answers "root" with a label the query does not appear in - the
    // remote did the matching, so a local filter must not drop it.
    $form = $this->form(static fn(string $query): array => $query === 'root' ? ['carrot' => 'Carrot'] : []);

    $tester = $this->tester($form);
    $tester->run($this->enter(), $this->enter(), 'root');

    $this->assertStringContainsString('Carrot', $tester->display());
  }

  public function testThrowingSourceReportsAndKeepsTheSessionAlive(): void {
    $form = $this->form(static function (string $query): array {
      if ($query !== '') {
        throw new \RuntimeException('The pantry is unreachable.');
      }

      return self::PRODUCE;
    });

    $tester = $this->tester($form);
    // After the failure the field is still editable, so a backspace returns to
    // the cached empty query and its rows.
    $answers = $tester->run($this->enter(), $this->enter(), 'p', Key::named(KeyName::Backspace), $this->enter());

    $display = $tester->display();
    $this->assertStringContainsString('Could not load options.', $display);
    $this->assertSame('carrot', $answers->value('veg'));
  }

  public function testFailedQueryIsNotRetriedWhileItStillReads(): void {
    $calls = 0;
    $form = $this->form(function (string $query) use (&$calls): array {
      $calls++;

      throw new \RuntimeException('The pantry is unreachable.');
    });

    // Two further reads that do not change the query must not call again.
    $this->tester($form)->run($this->enter(), $this->enter(), Key::named(KeyName::Down), Key::named(KeyName::Down));

    $this->assertSame(1, $calls);
  }

  public function testQueryBelowTheMinimumLengthIsNeverSent(): void {
    $form = $this->form(NULL, static fn(FieldBuilder $field): FieldBuilder => $field->minQuery(3));

    $tester = $this->tester($form);
    $tester->run($this->enter(), $this->enter(), 'po');

    $this->assertSame([], $this->queries);
    $this->assertStringContainsString('Type 3 characters to search.', $tester->display());
  }

  public function testTheMinimumLengthReleasesOnceTheQueryIsLongEnough(): void {
    $form = $this->form(NULL, static fn(FieldBuilder $field): FieldBuilder => $field->minQuery(3));

    $tester = $this->tester($form);
    $tester->run($this->enter(), $this->enter(), 'pot');

    $this->assertSame(['pot'], $this->queries);
    $this->assertStringContainsString('Potato', $tester->display());
  }

  public function testSuggestFieldIsDrivenByItsSource(): void {
    $form = Form::create('Order')->panel('order', 'New order', function (PanelBuilder $p): void {
      $p->suggest('fruit', 'Fruit')->optionsFrom($this->source(static fn(string $query): array => $query === 'ap' ? ['Apple' => 'Apple', 'Apricot' => 'Apricot'] : []));
    });

    $tester = $this->tester($form);
    $answers = $tester->run($this->enter(), $this->enter(), 'ap', Key::named(KeyName::Down), $this->enter());

    $this->assertSame(['', 'ap'], $this->queries);
    $this->assertStringContainsString('Apricot', $tester->display());
    $this->assertSame('Apple', $answers->value('fruit'));
  }

  public function testSelectionSurvivesLaterQueryThatOmitsIt(): void {
    $form = Form::create('Order')->panel('order', 'New order', function (PanelBuilder $p): void {
      $p->search('basket', 'Basket')->multiple()->optionsFrom($this->source(static fn(string $query): array => $query === 'pot' ? ['potato' => 'Potato'] : ['carrot' => 'Carrot']));
    });

    // Select Carrot under the empty query, then search for something that does
    // not include it: the earlier choice is still the user's.
    $answers = $this->tester($form)->run($this->enter(), $this->enter(), Key::named(KeyName::Space), 'pot', Key::named(KeyName::Space), $this->enter());

    $this->assertSame(['potato', 'carrot'], $answers->value('basket'));
  }

  public function testHeadlessLooksTheSuppliedValueUpAsItsQuery(): void {
    $answers = (new Tui($this->form()))->collect('{"veg":"potato"}');

    $this->assertSame('potato', $answers->value('veg'));
    $this->assertSame(['potato'], $this->queries);
  }

  public function testHeadlessRejectsValueNoQueryProduces(): void {
    $this->expectException(EngineException::class);
    $this->expectExceptionMessageMatches('/"turnip" was not found/');

    (new Tui($this->form()))->collect('{"veg":"turnip"}');
  }

  public function testHeadlessRejectsValueTheQueryAnswersWithout(): void {
    // The lookup returns rows, just not the one asked for, so the message can
    // name what was allowed.
    $this->expectException(EngineException::class);
    $this->expectExceptionMessageMatches('/not one of: carrot/');

    (new Tui($this->form(static fn(string $query): array => ['carrot' => 'Carrot'])))->collect('{"veg":"turnip"}');
  }

  public function testHeadlessLooksUpEachValueOfMultipleField(): void {
    $form = Form::create('Order')->panel('order', 'New order', function (PanelBuilder $p): void {
      $p->search('basket', 'Basket')->multiple()->optionsFrom($this->source());
    });

    $answers = (new Tui($form))->collect('{"basket":["carrot","onion"]}');

    $this->assertSame(['carrot', 'onion'], $answers->value('basket'));
    $this->assertSame(['carrot', 'onion'], $this->queries);
  }

  public function testHeadlessTurnsSourceFailureIntoEngineError(): void {
    $form = $this->form(static function (string $query): array {
      throw new \RuntimeException('The pantry is unreachable.');
    });

    $this->expectException(EngineException::class);
    $this->expectExceptionMessageMatches('/Could not load options for field "veg": The pantry is unreachable\./');

    (new Tui($form))->collect('{"veg":"potato"}');
  }

  public function testHeadlessLeavesConditionHiddenFieldUnqueried(): void {
    // The vegetable field is hidden unless the order is a box, so it carries no
    // answer to check and must cost the source nothing.
    $form = Form::create('Order')->panel('order', 'New order', function (PanelBuilder $p): void {
      $p->confirm('box', 'Box order?');
      $p->search('veg', 'Vegetable')->optionsFrom($this->source())->when(new Condition('box', eq: TRUE));
    });

    $answers = (new Tui($form))->collect('{"box":false,"veg":"carrot"}');

    $this->assertSame([], $this->queries);
    $this->assertNull($answers->value('veg'));
  }

  public function testHeadlessLeavesAnUnansweredFieldUnqueried(): void {
    (new Tui($this->form()))->collect('{}');

    $this->assertSame([], $this->queries);
  }

  public function testHeadlessDoesNotQuerySuggestFieldHints(): void {
    $form = Form::create('Order')->panel('order', 'New order', function (PanelBuilder $p): void {
      $p->suggest('fruit', 'Fruit')->optionsFrom($this->source());
    });

    $answers = (new Tui($form))->collect('{"fruit":"Quince"}');

    $this->assertSame('Quince', $answers->value('fruit'));
    $this->assertSame([], $this->queries);
  }

  public function testSourceReturningSomethingElseDegradesToNoOptions(): void {
    $form = $this->form(static fn(string $query): mixed => 'not a map');

    $tester = $this->tester($form);
    $tester->run($this->enter(), $this->enter());

    $this->assertStringNotContainsString('Carrot', $tester->display());
  }

  public function testFieldWithNoSourceNeverAsksForQuery(): void {
    $field = new Search(['carrot' => 'Carrot']);
    $field->handle(Key::char('c'));

    $this->assertFalse($field->isQueryDriven());
    $this->assertNull($field->pendingQuery());
  }

  public function testTheOldestCachedQueryIsDroppedOnceTheCacheIsFull(): void {
    $field = new Search([]);
    $field->driveByQuery();

    // Each character makes the query one longer, so typing fills the cache with
    // one more distinct query than it holds.
    $queries = [];
    for ($i = 0; $i <= Search::QUERY_CACHE_SIZE; $i++) {
      $field->handle(Key::char('a'));
      $query = $field->pendingQuery();
      $this->assertIsString($query);
      $queries[] = $query;
      $field->applyQuery($query, []);
    }

    // Stepping back lands on cached queries until the very first one, which the
    // newest insert evicted and which therefore has to be asked for again.
    for ($i = count($queries) - 1; $i > 0; $i--) {
      $field->handle(Key::named(KeyName::Backspace));
      $expected = $i === 1 ? $queries[0] : NULL;
      $this->assertSame($expected, $field->pendingQuery());
    }
  }

  #[DataProvider('dataProviderRejectedDeclarationFailsWhenTheFormIsBuilt')]
  public function testRejectedDeclarationFailsWhenTheFormIsBuilt(\Closure $declare, string $message): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessageMatches($message);

    Form::create('Order')->panel('order', 'New order', $declare)->build();
  }

  /**
   * Declarations a query source cannot be combined with.
   *
   * @return \Iterator<string,array{\Closure,string}>
   *   The panel declaration and the expected message pattern, per case.
   */
  public static function dataProviderRejectedDeclarationFailsWhenTheFormIsBuilt(): \Iterator {
    $source = static fn(string $query): array => [];

    yield 'unsupported type' => [
      static function (PanelBuilder $p) use ($source): void {
        $p->select('veg', 'Vegetable')->optionsFrom($source);
      },
      '/only search and suggest fields show one/',
    ];

    yield 'alongside static options' => [
      static function (PanelBuilder $p) use ($source): void {
        $p->search('veg', 'Vegetable')->options(['carrot' => 'Carrot'])->optionsFrom($source);
      },
      '/declare only one/',
    ];

    yield 'alongside an option loader' => [
      static function (PanelBuilder $p) use ($source): void {
        $p->search('veg', 'Vegetable')->options(static fn(): array => ['carrot' => 'Carrot'])->optionsFrom($source);
      },
      '/declare only one/',
    ];

    yield 'a minimum query with no source' => [
      static function (PanelBuilder $p): void {
        $p->search('veg', 'Vegetable')->options(['carrot' => 'Carrot'])->minQuery(2);
      },
      '/no query source to apply it to/',
    ];

    yield 'a minimum query below one character' => [
      static function (PanelBuilder $p) use ($source): void {
        $p->search('veg', 'Vegetable')->optionsFrom($source)->minQuery(0);
      },
      '/at least one character/',
    ];
  }

  /**
   * A single-panel order form whose vegetable options come from a query.
   *
   * @param \Closure|null $answer
   *   The `fn (string $query): mixed` the source answers with; NULL matches the
   *   produce list by substring, as a remote backend would.
   * @param \Closure|null $declare
   *   A `fn (FieldBuilder): FieldBuilder` adding further declarations.
   *
   * @return \DrevOps\Tui\Builder\Form
   *   The form.
   */
  protected function form(?\Closure $answer = NULL, ?\Closure $declare = NULL): Form {
    return Form::create('Order')->panel('order', 'New order', function (PanelBuilder $p) use ($answer, $declare): void {
      $field = $p->search('veg', 'Vegetable')->optionsFrom($this->source($answer));

      if ($declare instanceof \Closure) {
        $declare($field);
      }
    });
  }

  /**
   * A query source that records what it was asked before it answers.
   *
   * @param \Closure|null $answer
   *   The `fn (string $query): mixed` to answer with; NULL matches the produce
   *   list by substring.
   *
   * @return \Closure
   *   The recording source.
   */
  protected function source(?\Closure $answer = NULL): \Closure {
    return function (string $query) use ($answer): mixed {
      $this->queries[] = $query;

      if ($answer instanceof \Closure) {
        return $answer($query);
      }

      return array_filter(self::PRODUCE, static fn(string $value): bool => $query === '' || str_contains($value, $query), ARRAY_FILTER_USE_KEY);
    };
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

}
