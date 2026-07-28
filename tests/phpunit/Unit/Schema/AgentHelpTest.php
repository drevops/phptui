<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Schema;

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Schema\AgentHelp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the agent help generator.
 */
#[CoversClass(AgentHelp::class)]
#[Group('schema')]
final class AgentHelpTest extends TestCase {

  public function testGenerate(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('name', 'Site name')->required();
        $p->confirm('agree', 'Agree');
      })
      ->build();

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

  public function testSelectOptions(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->select('fruit', 'Fruit')->default('banana')->options([
          'apple' => 'Apple',
          'banana' => 'Banana',
          'cherry' => 'Cherry',
        ]);
      })
      ->build();

    $help = (new AgentHelp($form))->generate();

    $this->assertMatchesRegularExpression('/"enum":\s*\[\s*"apple",\s*"banana",\s*"cherry"\s*\]/', $help);
    $this->assertStringContainsString('"default": "banana"', $help);
  }

  public function testMultipleSelectIsAnArrayOfOptions(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->select('veg', 'Vegetables')->multiple()->options([
          'carrot' => 'Carrot',
          'tomato' => 'Tomato',
        ]);
      })
      ->build();

    $help = (new AgentHelp($form))->generate();

    $this->assertStringContainsString('"type": "array"', $help);
    $this->assertMatchesRegularExpression('/"items":\s*\{\s*"enum":\s*\[\s*"carrot",\s*"tomato"\s*\]\s*\}/', $help);
  }

  public function testMultipleSelectionBounds(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->select('veg', 'Vegetables')->multiple()->minSelections(2)->maxSelections(4)->options([
          'carrot' => 'Carrot',
          'tomato' => 'Tomato',
          'potato' => 'Potato',
        ]);
      })
      ->build();

    $help = (new AgentHelp($form))->generate();

    $this->assertStringContainsString('"type": "array"', $help);
    $this->assertStringContainsString('"minItems": 2', $help);
    $this->assertStringContainsString('"maxItems": 4', $help);
  }

  public function testNumberBounds(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->number('port', 'HTTP port')->min(1)->max(65535)->step(5);
      })
      ->build();

    $help = (new AgentHelp($form))->generate();

    $this->assertStringContainsString('"type": "integer"', $help);
    $this->assertStringContainsString('"minimum": 1', $help);
    $this->assertStringContainsString('"maximum": 65535', $help);
    // The step is a keyboard increment, not a value constraint: the schema
    // must accept every in-range integer the collection accepts.
    $this->assertStringNotContainsString('multipleOf', $help);
  }

  public function testCalendarFormat(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->calendar('due', 'Due date');
      })
      ->build();

    $help = (new AgentHelp($form))->generate();

    $this->assertStringContainsString('"format": "date"', $help);
  }

  public function testTemplatePattern(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->template('crate', 'Crate label')->pattern('{{orchard}}-{{grade}}');
      })
      ->build();

    $help = (new AgentHelp($form))->generate();

    // The answer is the assembled string, described by the expression it must
    // match rather than by the pattern's own slot syntax.
    $this->assertStringContainsString('"type": "string"', $help);
    $this->assertStringContainsString('"pattern": "^(.*?)-(.*?)$"', $help);
    $this->assertStringNotContainsString('{{orchard}}', $help);
  }

  public function testFieldDescription(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('name', 'Site name')->description('The public name');
      })
      ->build();

    $help = (new AgentHelp($form))->generate();

    $this->assertStringContainsString('"description": "The public name"', $help);
  }

  public function testNoEnvPrefixOmitsEnv(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('x', 'X');
      })
      ->build();

    $help = (new AgentHelp($form))->generate();

    $this->assertStringNotContainsString('"env"', $help);
  }

  public function testResolvesClosureDefault(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('name', 'Name')->default(fn (Context $context): string => 'computed');
      })
      ->build();

    $help = (new AgentHelp($form))->generate();

    $this->assertStringContainsString('"default": "computed"', $help);
  }

  public function testClosureDefaultUsesProvidedContext(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('version', 'Version')->default(fn (Context $context): string => $context->version);
      })
      ->build();

    $help = (new AgentHelp($form, '', new Context(version: '7.7.7')))->generate();

    $this->assertStringContainsString('"default": "7.7.7"', $help);
  }

  public function testDeclaredSchemaDefaultStandsInForClosure(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('name', 'Name')->default(fn (Context $context): string => 'live')->schemaDefault('static');
      })
      ->build();

    $help = (new AgentHelp($form))->generate();

    $this->assertStringContainsString('"default": "static"', $help);
    $this->assertStringNotContainsString('live', $help);
  }

  public function testUnresolvableClosureDefaultOmitsDefault(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('name', 'Name')->default(fn (Context $context): string => throw new \RuntimeException('needs answers'));
      })
      ->build();

    $help = (new AgentHelp($form))->generate();

    // The unresolvable closure emits no `default` key; `"default"` still occurs
    // in the x-precedence list, so match the key form with its colon.
    $this->assertStringContainsString('"name"', $help);
    $this->assertStringNotContainsString('"default":', $help);
  }

  public function testPauseIsSkipped(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('name', 'Name')->required();
        $p->pause('ready', 'Review');
      })
      ->build();

    $help = (new AgentHelp($form, 'APP_'))->generate();

    $this->assertStringContainsString('"name"', $help);
    $this->assertStringNotContainsString('ready', $help);
  }

  public function testNoteIsSkipped(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('name', 'Name')->required();
        $p->note('intro', 'Intro')->description('Welcome.');
      })
      ->build();

    $help = (new AgentHelp($form, 'APP_'))->generate();

    // A note carries no answer, so an agent is not asked to provide one.
    $this->assertStringContainsString('"name"', $help);
    $this->assertStringNotContainsString('intro', $help);
  }

}
