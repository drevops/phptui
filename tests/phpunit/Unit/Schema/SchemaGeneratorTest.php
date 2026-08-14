<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Schema;

use DrevOps\PhpTui\Block\Tree;
use DrevOps\PhpTui\Block\Weekday;
use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\Condition\Condition;
use DrevOps\PhpTui\Derive\Derive;
use DrevOps\PhpTui\Handler\Context;
use DrevOps\PhpTui\Schema\SchemaGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the schema generator.
 */
#[CoversClass(SchemaGenerator::class)]
#[CoversClass(Tree::class)]
#[Group('schema')]
final class SchemaGeneratorTest extends TestCase {

  public function testGenerate(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $profile = $p->select('Profile', 'profile')->description('The profile')->default('standard')->required();
        $profile->option('standard', 'Standard', 'Std')->option('minimal', 'Minimal');
        $p->text('theme')->help('Leave empty to follow the profile.')->placeholder('E.g. Golden Beetroot')->derive(new Derive('{{profile}}'))->when(new Condition('profile', eq: 'standard'));
        $p->number('Port', 'port')->min(1)->max(65535)->step(5);
        $p->calendar('Release date', 'release')->minDate('2000-01-01')->maxDate('2030-12-31')->weekStart(Weekday::Sunday);
      })
      ->root();

    // Spelled out in full rather than through prompt(): this is the one place
    // that documents the complete shape of a generated prompt.
    $expected = [
      'prompts' => [
        [
          'id' => 'profile',
          'type' => 'select',
          'label' => 'Profile',
          'description' => 'The profile',
          'help' => '',
          'placeholder' => '',
          'options' => [
            ['value' => 'standard', 'label' => 'Standard', 'description' => 'Std'],
            ['value' => 'minimal', 'label' => 'Minimal', 'description' => ''],
          ],
          'options_dynamic' => FALSE,
          'default' => 'standard',
          'required' => TRUE,
          'env' => NULL,
          'env_aliases' => [],
          'min' => NULL,
          'max' => NULL,
          'step' => NULL,
          'min_selections' => NULL,
          'max_selections' => NULL,
          'min_date' => NULL,
          'max_date' => NULL,
          'week_start' => NULL,
          'template' => NULL,
          'placeholders' => [],
          'when' => NULL,
          'asked_when' => NULL,
          'derive' => NULL,
          'discover' => NULL,
          'depends_on' => [],
        ],
        [
          'id' => 'theme',
          'type' => 'text',
          'label' => 'theme',
          'description' => '',
          'help' => 'Leave empty to follow the profile.',
          'placeholder' => 'E.g. Golden Beetroot',
          'options' => [],
          'options_dynamic' => FALSE,
          'default' => '',
          'required' => FALSE,
          'env' => NULL,
          'env_aliases' => [],
          'min' => NULL,
          'max' => NULL,
          'step' => NULL,
          'min_selections' => NULL,
          'max_selections' => NULL,
          'min_date' => NULL,
          'max_date' => NULL,
          'week_start' => NULL,
          'template' => NULL,
          'placeholders' => [],
          'when' => ['field' => 'profile', 'eq' => 'standard'],
          'asked_when' => ['field' => 'profile', 'eq' => 'standard'],
          'derive' => ['template' => '{{profile}}'],
          'discover' => NULL,
          'depends_on' => ['profile'],
        ],
        [
          'id' => 'port',
          'type' => 'number',
          'label' => 'Port',
          'description' => '',
          'help' => '',
          'placeholder' => '',
          'options' => [],
          'options_dynamic' => FALSE,
          'default' => 0,
          'required' => FALSE,
          'env' => NULL,
          'env_aliases' => [],
          'min' => 1,
          'max' => 65535,
          'step' => 5,
          'min_selections' => NULL,
          'max_selections' => NULL,
          'min_date' => NULL,
          'max_date' => NULL,
          'week_start' => NULL,
          'template' => NULL,
          'placeholders' => [],
          'when' => NULL,
          'asked_when' => NULL,
          'derive' => NULL,
          'discover' => NULL,
          'depends_on' => [],
        ],
        [
          'id' => 'release',
          'type' => 'calendar',
          'label' => 'Release date',
          'description' => '',
          'help' => '',
          'placeholder' => '',
          'options' => [],
          'options_dynamic' => FALSE,
          'default' => '',
          'required' => FALSE,
          'env' => NULL,
          'env_aliases' => [],
          'min' => NULL,
          'max' => NULL,
          'step' => NULL,
          'min_selections' => NULL,
          'max_selections' => NULL,
          'min_date' => '2000-01-01',
          'max_date' => '2030-12-31',
          'week_start' => Weekday::Sunday->value,
          'template' => NULL,
          'placeholders' => [],
          'when' => NULL,
          'asked_when' => NULL,
          'derive' => NULL,
          'discover' => NULL,
          'depends_on' => [],
        ],
      ],
    ];

    $this->assertSame($expected, (new SchemaGenerator($form))->generate());
  }

  public function testDescribesTemplateShape(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->template('Crate label', 'crate')->pattern('{{orchard}}-{{grade}}')->default('valley-a');
      })
      ->root();

    // The pattern travels as declared, with its slots named in shape order, so
    // external tooling can drive the field rather than guess at its shape.
    $expected = [
      'prompts' => [
        self::prompt([
          'id' => 'crate',
          'type' => 'template',
          'label' => 'Crate label',
          'default' => 'valley-a',
          'template' => '{{orchard}}-{{grade}}',
          'placeholders' => ['orchard', 'grade'],
        ]),
      ],
    ];

    $this->assertSame($expected, (new SchemaGenerator($form))->generate());
  }

  public function testExcludesNonSelectableOptions(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->select('Profile', 'profile')
          ->heading('Recommended')
          ->option('standard', 'Standard')
          ->separator()
          ->option('demo', 'Demo', disabled: TRUE, disabled_reason: 'nope');
      })
      ->root();

    $expected = [
      'prompts' => [
        self::prompt([
          'id' => 'profile',
          'type' => 'select',
          'label' => 'Profile',
          'options' => [
            ['value' => 'standard', 'label' => 'Standard', 'description' => ''],
          ],
        ]),
      ],
    ];

    $this->assertSame($expected, (new SchemaGenerator($form))->generate());
  }

  /**
   * Tests the rule published for a prompt, and the answers it names.
   *
   * @param string $id
   *   The prompt to read.
   * @param array<string,mixed>|null $expected_when
   *   The prompt's own rule.
   * @param array<string,mixed>|null $expected_asked
   *   The whole rule deciding whether it is asked.
   * @param list<string> $expected_depends
   *   The answers that rule reads.
   */
  #[DataProvider('dataProviderCarriesTheSectionsRule')]
  public function testCarriesTheSectionsRule(string $id, ?array $expected_when, ?array $expected_asked, array $expected_depends): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->confirm('Organic only?', 'organic');
        $p->text('Courier', 'courier');

        $p->panel('Certification', 'certification', function (PanelBuilder $sp): void {
          $sp->when(new Condition('organic', eq: TRUE));
          $sp->text('Certifier', 'certifier');
          $sp->text('Expiry', 'expiry')->when(new Condition('certifier', eq: 'Soil Board'));
        });
      })
      ->root();

    $prompts = (new SchemaGenerator($form))->generate()['prompts'];
    $this->assertIsArray($prompts);

    $found = NULL;

    foreach ($prompts as $prompt) {
      $this->assertIsArray($prompt);

      if ($prompt['id'] === $id) {
        $found = $prompt;
      }
    }

    $this->assertIsArray($found);
    $this->assertSame($expected_when, $found['when']);
    $this->assertSame($expected_asked, $found['asked_when']);
    $this->assertSame($expected_depends, $found['depends_on']);
  }

  /**
   * Data provider for testCarriesTheSectionsRule().
   *
   * @return \Iterator<string, array{string, array<string,mixed>|null, array<string,mixed>|null, list<string>}>
   *   The prompt id, its own rule, the whole rule and the answers it reads.
   */
  public static function dataProviderCarriesTheSectionsRule(): \Iterator {
    yield 'a prompt nothing gates' => ['courier', NULL, NULL, []];

    yield 'a prompt only its section gates' => [
      'certifier',
      NULL,
      ['field' => 'organic', 'eq' => TRUE],
      ['organic'],
    ];

    // Both rules have to hold, so they travel as one condition an agent can
    // evaluate with whatever already evaluates `when`.
    yield 'a prompt its section and its own rule gate' => [
      'expiry',
      ['field' => 'certifier', 'eq' => 'Soil Board'],
      ['all' => [['field' => 'organic', 'eq' => TRUE], ['field' => 'certifier', 'eq' => 'Soil Board']]],
      ['organic', 'certifier'],
    ];
  }

  #[DataProvider('dataProviderDescribesFieldInJson')]
  public function testDescribesFieldInJson(\Closure $declare, array $fragments): void {
    $form = Form::create('T')->panel('p', 'p', $declare)->root();

    $json = (string) json_encode((new SchemaGenerator($form))->generate());

    foreach ($fragments as $fragment) {
      $this->assertStringContainsString($fragment, $json);
    }
  }

  /**
   * Data provider for testDescribesFieldInJson().
   *
   * @return \Iterator<string, array{\Closure, string[]}>
   *   A panel declaration and the JSON fragments its schema must carry.
   */
  public static function dataProviderDescribesFieldInJson(): \Iterator {
    yield 'selection bounds' => [
      static function (PanelBuilder $p): void {
        $p->select('Tags', 'tags')->multiple()->minSelections(2)->maxSelections(5)->option('a')->option('b');
      },
      ['"min_selections":2', '"max_selections":5'],
    ];

    yield 'nested condition field refs' => [
      static function (PanelBuilder $p): void {
        $p->text('a');
        $p->text('b');
        $p->text('c')->when(Condition::all(new Condition('a', eq: 'x'), new Condition('b', eq: 'y')));
      },
      ['"depends_on":["a","b"]'],
    ];

    yield 'toggle carries both values' => [
      static function (PanelBuilder $p): void {
        $p->toggle('Visibility', 'visibility')->options(['public' => 'Public', 'private' => 'Private'])->default('public');
      },
      ['"type":"toggle"', '"value":"public"', '"value":"private"'],
    ];

    // The points are the steps, so a rating never advertises an increment.
    yield 'rating carries its scale' => [
      static function (PanelBuilder $p): void {
        $p->rating('Taste', 'taste')->min(0)->max(10);
      },
      ['"type":"rating"', '"min":0', '"max":10', '"step":null'],
    ];

    // The partial default is completed to a full ranking in the schema.
    yield 'reorder completes its ranking' => [
      static function (PanelBuilder $p): void {
        $p->reorder('Ranking', 'ranking')->options(['a' => 'A', 'b' => 'B', 'c' => 'C'])->default(['c']);
      },
      ['"type":"reorder"', '"default":["c","a","b"]', '"value":"a"'],
    ];
  }

  #[DataProvider('dataProviderResolvesDefault')]
  public function testResolvesDefault(\Closure $declare, Context $context, mixed $expected): void {
    $form = Form::create('T')->panel('p', 'p', $declare)->root();

    $prompts = (new SchemaGenerator($form, $context))->generate()['prompts'];
    $this->assertIsArray($prompts);
    $first = $prompts[0];
    $this->assertIsArray($first);
    $this->assertSame($expected, $first['default']);
  }

  /**
   * Data provider for testResolvesDefault().
   *
   * @return \Iterator<string, array{\Closure, \DrevOps\PhpTui\Handler\Context, mixed}>
   *   A panel declaration, the context it resolves against and the default the
   *   schema advertises.
   */
  public static function dataProviderResolvesDefault(): \Iterator {
    yield 'closure resolved' => [
      static function (PanelBuilder $p): void {
        $p->text('Name', 'name')->default(fn (Context $context): string => 'computed');
      },
      new Context(),
      'computed',
    ];

    yield 'closure reads the provided context' => [
      static function (PanelBuilder $p): void {
        $p->text('Version', 'version')->default(fn (Context $context): string => $context->version);
      },
      new Context(version: '9.9.9'),
      '9.9.9',
    ];

    yield 'declared schema default stands in' => [
      static function (PanelBuilder $p): void {
        $p->text('Name', 'name')->default(fn (Context $context): string => 'live')->schemaDefault('static');
      },
      new Context(),
      'static',
    ];

    // Unlike the agent help, which omits the key entirely, the machine schema
    // keeps every key and advertises the unresolvable default as null.
    yield 'unresolvable closure is null' => [
      static function (PanelBuilder $p): void {
        $p->text('Name', 'name')->default(fn (Context $context): string => throw new \RuntimeException('needs answers'));
      },
      new Context(),
      NULL,
    ];
  }

  #[DataProvider('dataProviderDescribesEnvironmentVariables')]
  public function testDescribesEnvironmentVariables(\Closure $declare, string $prefix, int $index, ?string $env, array $aliases): void {
    $form = Form::create('T')->panel('p', 'p', $declare)->root();

    $prompts = (new SchemaGenerator($form, new Context(), $prefix))->generate()['prompts'];
    $this->assertIsArray($prompts);

    $prompt = $prompts[$index];
    $this->assertIsArray($prompt);
    $this->assertSame($env, $prompt['env']);
    $this->assertSame($aliases, $prompt['env_aliases']);
  }

  /**
   * Data provider for testDescribesEnvironmentVariables().
   *
   * @return \Iterator<string, array{\Closure, string, int, string|null, string[]}>
   *   A panel declaration, the prefix in force, the prompt to read, and the
   *   variable and aliases that prompt advertises.
   */
  public static function dataProviderDescribesEnvironmentVariables(): \Iterator {
    $named = static function (PanelBuilder $p): void {
      $p->text('Crate size', 'crate_size');
      $p->text('Grade', 'grade')->env('LEGACY_GRADE')->envAliases(['OLD_GRADE']);
    };

    yield 'mechanical name takes the prefix' => [$named, 'APP_', 0, 'APP_CRATE_SIZE', []];
    yield 'declared name is advertised as given' => [$named, 'APP_', 1, 'LEGACY_GRADE', ['OLD_GRADE']];

    // Without a prefix the mechanical name is not a real variable, so nothing
    // is advertised for it.
    yield 'bare mechanical name is not advertised' => [
      static function (PanelBuilder $p): void {
        $p->text('Crate size', 'crate_size');
      },
      '',
      0,
      NULL,
      [],
    ];
  }

  public function testExcludesPresentationalNote(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->note('Intro', 'intro')->body('Welcome.');
        $p->text('Name', 'name');
      })
      ->root();

    $schema = (new SchemaGenerator($form))->generate();

    // A note collects no answer, so it is not a prompt in the machine schema.
    $prompts = $schema['prompts'];
    $this->assertIsArray($prompts);
    $ids = array_column($prompts, 'id');
    $this->assertSame(['name'], $ids);
  }

  public function testRoundTripsThroughJson(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->confirm('x')->default(TRUE);
      })
      ->root();

    $schema = (new SchemaGenerator($form))->generate();
    $decoded = json_decode((string) json_encode($schema), TRUE);

    $this->assertSame($schema, $decoded);
  }

  /**
   * A generated prompt, with only the given keys differing from the defaults.
   *
   * The generator emits every key on every prompt, so an expectation that
   * spells them all out buries the handful that carry the point.
   *
   * @param array<string,mixed> $overrides
   *   The keys this prompt declares.
   *
   * @return array<string,mixed>
   *   The prompt, in the generator's own key order.
   */
  protected static function prompt(array $overrides): array {
    return array_merge([
      'id' => '',
      'type' => '',
      'label' => '',
      'description' => '',
      'help' => '',
      'placeholder' => '',
      'options' => [],
      'options_dynamic' => FALSE,
      'default' => '',
      'required' => FALSE,
      'env' => NULL,
      'env_aliases' => [],
      'min' => NULL,
      'max' => NULL,
      'step' => NULL,
      'min_selections' => NULL,
      'max_selections' => NULL,
      'min_date' => NULL,
      'max_date' => NULL,
      'week_start' => NULL,
      'template' => NULL,
      'placeholders' => [],
      'when' => NULL,
      'asked_when' => NULL,
      'derive' => NULL,
      'discover' => NULL,
      'depends_on' => [],
    ], $overrides);
  }

}
