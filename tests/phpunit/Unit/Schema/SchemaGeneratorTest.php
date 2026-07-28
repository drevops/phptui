<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Schema;

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Model\Weekday;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Schema\SchemaGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the schema generator.
 */
#[CoversClass(SchemaGenerator::class)]
#[Group('schema')]
final class SchemaGeneratorTest extends TestCase {

  public function testGenerate(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $profile = $p->select('profile', 'Profile')->description('The profile')->default('standard')->required();
        $profile->option('standard', 'Standard', 'Std')->option('minimal', 'Minimal');
        $p->text('theme')->derive(new Derive('{{profile}}'))->when(new Condition('profile', eq: 'standard'));
        $p->number('port', 'Port')->min(1)->max(65535)->step(5);
        $p->calendar('release', 'Release date')->minDate('2000-01-01')->maxDate('2030-12-31')->weekStart(Weekday::Sunday);
      })
      ->build();

    $expected = [
      'prompts' => [
        [
          'id' => 'profile',
          'type' => 'select',
          'label' => 'Profile',
          'description' => 'The profile',
          'options' => [
            ['value' => 'standard', 'label' => 'Standard', 'description' => 'Std'],
            ['value' => 'minimal', 'label' => 'Minimal', 'description' => ''],
          ],
          'default' => 'standard',
          'required' => TRUE,
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
          'derive' => NULL,
          'discover' => NULL,
          'depends_on' => [],
        ],
        [
          'id' => 'theme',
          'type' => 'text',
          'label' => 'theme',
          'description' => '',
          'options' => [],
          'default' => '',
          'required' => FALSE,
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
          'derive' => ['template' => '{{profile}}'],
          'discover' => NULL,
          'depends_on' => ['profile'],
        ],
        [
          'id' => 'port',
          'type' => 'number',
          'label' => 'Port',
          'description' => '',
          'options' => [],
          'default' => 0,
          'required' => FALSE,
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
          'derive' => NULL,
          'discover' => NULL,
          'depends_on' => [],
        ],
        [
          'id' => 'release',
          'type' => 'calendar',
          'label' => 'Release date',
          'description' => '',
          'options' => [],
          'default' => '',
          'required' => FALSE,
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
          'derive' => NULL,
          'discover' => NULL,
          'depends_on' => [],
        ],
      ],
    ];

    $this->assertSame($expected, (new SchemaGenerator($form))->generate());
  }

  public function testExcludesNonSelectableOptions(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->select('profile', 'Profile')
          ->heading('Recommended')
          ->option('standard', 'Standard')
          ->separator()
          ->option('demo', 'Demo', disabled: TRUE, disabled_reason: 'nope');
      })
      ->build();

    $expected = [
      'prompts' => [
        [
          'id' => 'profile',
          'type' => 'select',
          'label' => 'Profile',
          'description' => '',
          'options' => [
            ['value' => 'standard', 'label' => 'Standard', 'description' => ''],
          ],
          'default' => '',
          'required' => FALSE,
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
          'derive' => NULL,
          'discover' => NULL,
          'depends_on' => [],
        ],
      ],
    ];

    $this->assertSame($expected, (new SchemaGenerator($form))->generate());
  }

  public function testSelectionBounds(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->select('tags', 'Tags')->multiple()->minSelections(2)->maxSelections(5)->option('a')->option('b');
      })
      ->build();

    $json = (string) json_encode((new SchemaGenerator($form))->generate());

    $this->assertStringContainsString('"min_selections":2', $json);
    $this->assertStringContainsString('"max_selections":5', $json);
  }

  public function testDependsOnCollectsNestedFieldRefs(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('a');
        $p->text('b');
        $p->text('c')->when(Condition::all(new Condition('a', eq: 'x'), new Condition('b', eq: 'y')));
      })
      ->build();

    $json = (string) json_encode((new SchemaGenerator($form))->generate());

    $this->assertStringContainsString('"depends_on":["a","b"]', $json);
  }

  public function testToggleDescribesBothValues(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->toggle('visibility', 'Visibility')->options(['public' => 'Public', 'private' => 'Private'])->default('public');
      })
      ->build();

    $json = (string) json_encode((new SchemaGenerator($form))->generate());

    $this->assertStringContainsString('"type":"toggle"', $json);
    $this->assertStringContainsString('"value":"public"', $json);
    $this->assertStringContainsString('"value":"private"', $json);
  }

  public function testDescribesReorderField(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->reorder('ranking', 'Ranking')->options(['a' => 'A', 'b' => 'B', 'c' => 'C'])->default(['c']);
      })
      ->build();

    $json = (string) json_encode((new SchemaGenerator($form))->generate());

    $this->assertStringContainsString('"type":"reorder"', $json);
    // The partial default is completed to a full ranking in the schema.
    $this->assertStringContainsString('"default":["c","a","b"]', $json);
    $this->assertStringContainsString('"value":"a"', $json);
  }

  public function testExcludesPresentationalNote(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->note('intro', 'Intro')->description('Welcome.');
        $p->text('name', 'Name');
      })
      ->build();

    $schema = (new SchemaGenerator($form))->generate();

    // A note collects no answer, so it is not a prompt in the machine schema.
    $prompts = $schema['prompts'];
    $this->assertIsArray($prompts);
    $ids = array_column($prompts, 'id');
    $this->assertSame(['name'], $ids);
  }

  public function testResolvesClosureDefault(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('name', 'Name')->default(fn (Context $context): string => 'computed');
      })
      ->build();

    $prompts = (new SchemaGenerator($form))->generate()['prompts'];
    $this->assertIsArray($prompts);
    $first = $prompts[0];
    $this->assertIsArray($first);
    $this->assertSame('computed', $first['default']);
  }

  public function testClosureDefaultUsesProvidedContext(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('version', 'Version')->default(fn (Context $context): string => $context->version);
      })
      ->build();

    $prompts = (new SchemaGenerator($form, new Context(version: '9.9.9')))->generate()['prompts'];
    $this->assertIsArray($prompts);
    $first = $prompts[0];
    $this->assertIsArray($first);
    $this->assertSame('9.9.9', $first['default']);
  }

  public function testDeclaredSchemaDefaultStandsInForClosure(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('name', 'Name')->default(fn (Context $context): string => 'live')->schemaDefault('static');
      })
      ->build();

    $prompts = (new SchemaGenerator($form))->generate()['prompts'];
    $this->assertIsArray($prompts);
    $first = $prompts[0];
    $this->assertIsArray($first);
    $this->assertSame('static', $first['default']);
  }

  public function testUnresolvableClosureDefaultIsNull(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('name', 'Name')->default(fn (Context $context): string => throw new \RuntimeException('needs answers'));
      })
      ->build();

    $prompts = (new SchemaGenerator($form))->generate()['prompts'];
    $this->assertIsArray($prompts);
    $first = $prompts[0];
    $this->assertIsArray($first);
    $this->assertNull($first['default']);
  }

  public function testRoundTripsThroughJson(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->confirm('x')->default(TRUE);
      })
      ->build();

    $schema = (new SchemaGenerator($form))->generate();
    $decoded = json_decode((string) json_encode($schema), TRUE);

    $this->assertSame($schema, $decoded);
  }

}
