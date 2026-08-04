<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Schema;

use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Tree;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Schema\AgentHelp;
use DrevOps\Tui\Screen\Layout\PanelLayout;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the agent help generator.
 */
#[CoversClass(AgentHelp::class)]
#[CoversClass(Tree::class)]
#[Group('schema')]
final class AgentHelpTest extends TestCase {

  public function testGenerate(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('name', 'Site name')->required();
        $p->confirm('agree', 'Agree');
      })
      ->root();

    $help = (new AgentHelp($form, 'APP_'))->generate();

    $this->assertNotNull(json_decode($help), 'output is valid JSON');
    $this->assertStringContainsString('"$schema": "https://json-schema.org/draft/2020-12/schema"', $help);
    $this->assertStringContainsString('"type": "object"', $help);
    $this->assertStringContainsString('"title": "Site name"', $help);
    $this->assertStringContainsString('"env": "APP_NAME"', $help);
    $this->assertStringContainsString('"type": "boolean"', $help);
    $this->assertStringContainsString('"env": "APP_AGREE"', $help);
    $this->assertMatchesRegularExpression('/"required":\s*\[\s*"name"\s*\]/', $help);
    $this->assertMatchesRegularExpression('/"x-precedence":\s*\[\s*"provided",\s*"environment",\s*"discovered",\s*"derived",\s*"default"\s*\]/', $help);
  }

  #[DataProvider('dataProviderDescribesFieldShape')]
  public function testDescribesFieldShape(\Closure $declare, array $contains, array $absent, array $matches): void {
    $form = Form::create('T')->panel('p', 'p', $declare)->root();

    $this->assertHelp((new AgentHelp($form))->generate(), $contains, $absent, $matches);
  }

  /**
   * Data provider for testDescribesFieldShape().
   *
   * @return \Iterator<string, array{\Closure, string[], string[], string[]}>
   *   A panel declaration, then the fragments the help must carry, the ones it
   *   must not, and the patterns it must match.
   */
  public static function dataProviderDescribesFieldShape(): \Iterator {
    yield 'select is an enum' => [
      static function (PanelBuilder $p): void {
        $p->select('fruit', 'Fruit')->default('banana')->options([
          'apple' => 'Apple',
          'banana' => 'Banana',
          'cherry' => 'Cherry',
        ]);
      },
      ['"default": "banana"'],
      [],
      ['/"enum":\s*\[\s*"apple",\s*"banana",\s*"cherry"\s*\]/'],
    ];

    yield 'multiple select is an array of options' => [
      static function (PanelBuilder $p): void {
        $p->select('veg', 'Vegetables')->multiple()->options(['carrot' => 'Carrot', 'tomato' => 'Tomato']);
      },
      ['"type": "array"'],
      [],
      ['/"items":\s*\{\s*"enum":\s*\[\s*"carrot",\s*"tomato"\s*\]\s*\}/'],
    ];

    yield 'selection bounds are item bounds' => [
      static function (PanelBuilder $p): void {
        $p->select('veg', 'Vegetables')->multiple()->minSelections(2)->maxSelections(4)->options([
          'carrot' => 'Carrot',
          'tomato' => 'Tomato',
          'potato' => 'Potato',
        ]);
      },
      ['"type": "array"', '"minItems": 2', '"maxItems": 4'],
      [],
      [],
    ];

    // The step is a keyboard increment, not a value constraint: the schema
    // must accept every in-range integer the collection accepts.
    yield 'number bounds without the step' => [
      static function (PanelBuilder $p): void {
        $p->number('port', 'HTTP port')->min(1)->max(65535)->step(5);
      },
      ['"type": "integer"', '"minimum": 1', '"maximum": 65535'],
      ['multipleOf'],
      [],
    ];

    // The scale travels as an integer range, so an agent answers with a point.
    // Captions name what the points mean; they are not a closed value set, so
    // they never narrow the answer to an enum.
    yield 'rating is an integer range' => [
      static function (PanelBuilder $p): void {
        $p->rating('taste', 'Taste')->min(1)->max(5)->captions([1 => 'Poor', 5 => 'Excellent']);
      },
      ['"type": "integer"', '"minimum": 1', '"maximum": 5'],
      ['enum'],
      [],
    ];

    yield 'calendar is a date' => [
      static function (PanelBuilder $p): void {
        $p->calendar('due', 'Due date');
      },
      ['"format": "date"'],
      [],
      [],
    ];

    // The answer is the assembled string, described by the expression it must
    // match rather than by the pattern's own slot syntax.
    yield 'template is a matched string' => [
      static function (PanelBuilder $p): void {
        $p->template('crate', 'Crate label')->pattern('{{orchard}}-{{grade}}');
      },
      ['"type": "string"', '"pattern": "^(.*?)-(.*?)$"'],
      ['{{orchard}}'],
      [],
    ];

    yield 'description travels' => [
      static function (PanelBuilder $p): void {
        $p->text('name', 'Site name')->description('The public name');
      },
      ['"description": "The public name"'],
      [],
      [],
    ];

    // Each text keeps its own key, so an agent reads the same three guidance
    // texts a human does rather than one merged description.
    yield 'help and placeholder travel as extension keys' => [
      static function (PanelBuilder $p): void {
        $p->text('name', 'Site name')
          ->description('The public name')
          ->help('Type a few letters to filter.')
          ->placeholder('E.g. Golden Beetroot');
      },
      ['"description": "The public name"', '"x-help": "Type a few letters to filter."', '"x-placeholder": "E.g. Golden Beetroot"'],
      [],
      [],
    ];

    yield 'undeclared help and placeholder are omitted' => [
      static function (PanelBuilder $p): void {
        $p->text('name', 'Site name');
      },
      [],
      ['"x-help"', '"x-placeholder"'],
      [],
    ];
  }

  #[DataProvider('dataProviderAdvertisesEnvironmentVariables')]
  public function testAdvertisesEnvironmentVariables(\Closure $declare, string $prefix, array $contains, array $absent, array $matches): void {
    $form = Form::create('T')->panel('p', 'p', $declare)->root();

    $this->assertHelp((new AgentHelp($form, $prefix))->generate(), $contains, $absent, $matches);
  }

  /**
   * Data provider for testAdvertisesEnvironmentVariables().
   *
   * @return \Iterator<string, array{\Closure, string, string[], string[], string[]}>
   *   A panel declaration and the prefix in force, then the fragments the help
   *   must carry, the ones it must not, and the patterns it must match.
   */
  public static function dataProviderAdvertisesEnvironmentVariables(): \Iterator {
    yield 'no prefix advertises nothing' => [
      static function (PanelBuilder $p): void {
        $p->text('x', 'X');
      },
      '',
      [],
      ['"env"'],
      [],
    ];

    yield 'declared name replaces the mechanical one' => [
      static function (PanelBuilder $p): void {
        $p->text('crate_size', 'Crate size')->env('LEGACY_CRATE');
      },
      'APP_',
      ['"env": "LEGACY_CRATE"'],
      ['APP_CRATE_SIZE'],
      [],
    ];

    // The named field advertises itself; its unnamed neighbour has no
    // namespaced variable to offer, so it stays absent.
    yield 'declared name is advertised without a prefix' => [
      static function (PanelBuilder $p): void {
        $p->text('crate_size', 'Crate size')->env('LEGACY_CRATE');
        $p->text('grade', 'Grade');
      },
      '',
      ['"env": "LEGACY_CRATE"'],
      ['"env": "GRADE"'],
      [],
    ];

    yield 'aliases keep their declaration order' => [
      static function (PanelBuilder $p): void {
        $p->text('crate_size', 'Crate size')->envAliases(['OLD_CRATE', 'OLDER_CRATE']);
      },
      'APP_',
      ['"env": "APP_CRATE_SIZE"'],
      [],
      ['/"x-env-aliases":\s*\[\s*"OLD_CRATE",\s*"OLDER_CRATE"\s*\]/'],
    ];

    // The bare mechanical name stays hidden, but the alias answers the field
    // either way, so withholding it would advertise less than is honoured.
    yield 'aliases show without an advertisable canonical name' => [
      static function (PanelBuilder $p): void {
        $p->text('crate_size', 'Crate size')->envAliases(['OLD_CRATE']);
      },
      '',
      [],
      ['"env":'],
      ['/"x-env-aliases":\s*\[\s*"OLD_CRATE"\s*\]/'],
    ];

    yield 'no aliases omits the annotation' => [
      static function (PanelBuilder $p): void {
        $p->text('crate_size', 'Crate size');
      },
      'APP_',
      [],
      ['x-env-aliases'],
      [],
    ];
  }

  #[DataProvider('dataProviderResolvesDefault')]
  public function testResolvesDefault(\Closure $declare, Context $context, array $contains, array $absent): void {
    $form = Form::create('T')->panel('p', 'p', $declare)->root();

    $this->assertHelp((new AgentHelp($form, '', $context))->generate(), $contains, $absent, []);
  }

  /**
   * Data provider for testResolvesDefault().
   *
   * @return \Iterator<string, array{\Closure, \DrevOps\Tui\Handler\Context, string[], string[]}>
   *   A panel declaration and the context it resolves against, then the
   *   fragments the help must carry and the ones it must not.
   */
  public static function dataProviderResolvesDefault(): \Iterator {
    yield 'closure resolved' => [
      static function (PanelBuilder $p): void {
        $p->text('name', 'Name')->default(fn (Context $context): string => 'computed');
      },
      new Context(),
      ['"default": "computed"'],
      [],
    ];

    yield 'closure reads the provided context' => [
      static function (PanelBuilder $p): void {
        $p->text('version', 'Version')->default(fn (Context $context): string => $context->version);
      },
      new Context(version: '7.7.7'),
      ['"default": "7.7.7"'],
      [],
    ];

    yield 'declared schema default stands in' => [
      static function (PanelBuilder $p): void {
        $p->text('name', 'Name')->default(fn (Context $context): string => 'live')->schemaDefault('static');
      },
      new Context(),
      ['"default": "static"'],
      ['live'],
    ];

    // The unresolvable closure emits no `default` key; `"default"` still occurs
    // in the x-precedence list, so match the key form with its colon.
    yield 'unresolvable closure omits the key' => [
      static function (PanelBuilder $p): void {
        $p->text('name', 'Name')->default(fn (Context $context): string => throw new \RuntimeException('needs answers'));
      },
      new Context(),
      ['"name"'],
      ['"default":'],
    ];
  }

  #[DataProvider('dataProviderSkipsNonAnsweringField')]
  public function testSkipsNonAnsweringField(\Closure $declare, string $absent): void {
    $form = Form::create('T')->panel('p', 'p', $declare)->root();

    // A field that carries no answer is not one an agent is asked to provide.
    $this->assertHelp((new AgentHelp($form, 'APP_'))->generate(), ['"name"'], [$absent], []);
  }

  /**
   * Data provider for testSkipsNonAnsweringField().
   *
   * @return \Iterator<string, array{\Closure, string}>
   *   A panel declaration and the id of the field the help must leave out.
   */
  public static function dataProviderSkipsNonAnsweringField(): \Iterator {
    yield 'pause' => [
      static function (PanelBuilder $p): void {
        $p->text('name', 'Name')->required();
        $p->pause('ready', 'Review');
      },
      'ready',
    ];

    yield 'note' => [
      static function (PanelBuilder $p): void {
        $p->text('name', 'Name')->required();
        $p->note('intro', 'Intro')->body('Welcome.');
      },
      'intro',
    ];
  }

  public function testCarriesTheWholeRuleGatingTheQuestion(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->confirm('organic', 'Organic only?');
        $p->text('courier', 'Courier');

        $p->panel('certification', 'Certification', function (PanelBuilder $sp): void {
          $sp->when(new Condition('organic', eq: TRUE));
          $sp->text('certifier', 'Certifier');
        });
      })
      ->root();

    $help = (new AgentHelp($form))->generate();

    // The section's rule reaches the question it holds, and a question outside
    // every section carries no rule at all.
    $this->assertMatchesRegularExpression('/"certifier":\s*\{[^}]*"x-asked-when":\s*\{\s*"field":\s*"organic",\s*"eq":\s*true\s*\}/', $help);
    $this->assertDoesNotMatchRegularExpression('/"courier":\s*\{[^}]*"x-asked-when"/', $help);
  }

  public function testGatedRequiredQuestionStaysOutOfTheRequiredList(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->confirm('organic', 'Organic only?');
        $p->text('name', 'Order name')->required();

        $p->panel('certification', 'Certification', function (PanelBuilder $sp): void {
          $sp->when(new Condition('organic', eq: TRUE));
          $sp->text('certifier', 'Certifier')->required();
        });
      })
      ->root();

    $help = (new AgentHelp($form))->generate();

    // Only the question every run asks is asserted at the root, so a payload
    // answering "organic": false and nothing else still satisfies the schema.
    $this->assertMatchesRegularExpression('/"required":\s*\[\s*"name"\s*\]/', $help);

    // What the gated question owes travels with it instead.
    $this->assertMatchesRegularExpression('/"certifier":[\s\S]*?"x-required-when-asked":\s*true/', $help);
    $this->assertDoesNotMatchRegularExpression('/"name":\s*\{[^}]*"x-required-when-asked"/', $help);
  }

  public function testRuleNobodyCanReadStillKeepsTheQuestionOutOfRequired(): void {
    // A rule the block decides for itself cannot be published, and treating it
    // as no rule would assert an answer the form may never ask for.
    $panel = (new Panel('p', 'p'))->layout(new PanelLayout());
    $panel->in('content')->add((new Field('certifier', 'Certifier'))->required()->when(static fn(array $answers): bool => FALSE));

    $help = (new AgentHelp($panel))->generate();

    $this->assertStringNotContainsString('"required"', $help);
    $this->assertStringContainsString('"x-required-when-asked": true', $help);
    $this->assertStringNotContainsString('"x-asked-when"', $help);
  }

  /**
   * Assert what the generated help does and does not say.
   *
   * @param string $help
   *   The generated help.
   * @param string[] $contains
   *   Fragments the help must carry.
   * @param string[] $absent
   *   Fragments the help must not carry.
   * @param string[] $matches
   *   Patterns the help must match, for fragments whose whitespace varies.
   */
  protected function assertHelp(string $help, array $contains, array $absent, array $matches): void {
    foreach ($contains as $fragment) {
      $this->assertStringContainsString($fragment, $help);
    }

    foreach ($absent as $fragment) {
      $this->assertStringNotContainsString($fragment, $help);
    }

    foreach ($matches as $pattern) {
      $this->assertMatchesRegularExpression($pattern, $help);
    }
  }

}
