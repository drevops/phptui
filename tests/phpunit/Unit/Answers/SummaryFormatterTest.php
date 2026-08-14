<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Answers;

use DrevOps\PhpTui\Answers\Answers;
use DrevOps\PhpTui\Answers\Provenance;
use DrevOps\PhpTui\Answers\SummaryFormatter;
use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\Condition\Condition;
use DrevOps\PhpTui\Derive\Derive;
use DrevOps\PhpTui\Terminal\Ansi;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the answers summary formatter.
 */
#[CoversClass(SummaryFormatter::class)]
#[Group('answers')]
final class SummaryFormatterTest extends TestCase {

  public function testFormatsGroupedByPanel(): void {
    $form = Form::create('T')
      ->panel('General', 'general', function (PanelBuilder $p): void {
        $p->text('Name', 'name');
        $p->text('Machine', 'machine')->derive(new Derive('{{name}}'));
      })
      ->panel('Drupal', 'drupal', function (PanelBuilder $p): void {
        $p->text('Profile', 'profile');
        $p->panel('Advanced', 'adv', function (PanelBuilder $sp): void {
          $sp->confirm('Debug', 'debug');
        });
      })
      ->panel('Empty', 'empty', function (PanelBuilder $p): void {
        $p->text('Gone', 'gone')->when(new Condition('name', eq: 'never'));
      })
      ->root();
    $answers = Answers::forTree(
      $form,
      ['name' => 'Acme', 'machine' => 'acme', 'profile' => 'standard', 'debug' => TRUE],
      ['name' => Provenance::Edited, 'machine' => Provenance::Derived, 'profile' => Provenance::Default, 'debug' => Provenance::Edited],
    );

    $summary = (new SummaryFormatter())->format($answers);

    $this->assertStringContainsString('General', $summary);
    $this->assertStringContainsString('Name: Acme (edited)', $summary);
    $this->assertStringContainsString('Machine: acme (derived)', $summary);
    $this->assertStringContainsString('Drupal', $summary);
    $this->assertStringContainsString('Profile: standard', $summary);
    $this->assertStringContainsString('Advanced', $summary);
    $this->assertStringContainsString('Debug: yes (edited)', $summary);
    // Sub-panel content indents below its parent.
    $this->assertStringContainsString("Drupal\n  Profile: standard\n  Advanced\n    Debug: yes (edited)", $summary);
    // Defaults carry no badge.
    $this->assertStringNotContainsString('(default)', $summary);
    // A panel with no active answers is omitted.
    $this->assertStringNotContainsString('Empty', $summary);
    $this->assertStringNotContainsString('Gone', $summary);
  }

  public function testFormatsListValues(): void {
    $form = Form::create('T')
      ->panel('P', 'p', function (PanelBuilder $p): void {
        $p->select('Mods', 'mods')->multiple();
      })
      ->root();
    $answers = Answers::forTree($form, ['mods' => ['a', 'b']], ['mods' => Provenance::Edited]);

    $summary = (new SummaryFormatter())->format($answers);

    $this->assertStringContainsString('Mods: a, b', $summary);
  }

  public function testMasksPasswordValues(): void {
    $form = Form::create('T')
      ->panel('P', 'p', function (PanelBuilder $p): void {
        $p->password('Token', 'token');
        $p->password('Unset', 'unset');
      })
      ->root();
    $answers = Answers::forTree($form, ['token' => 's3cret-long', 'unset' => ''], ['token' => Provenance::Edited, 'unset' => Provenance::Default]);

    $summary = (new SummaryFormatter())->format($answers);

    $this->assertStringNotContainsString('s3cret-long', $summary);
    // The mask has a fixed length so it does not leak the value's length.
    $this->assertStringContainsString('Token: ********', $summary);
    $this->assertStringContainsString('Unset: ', $summary);
  }

  public function testBareAnswersFormatEmpty(): void {
    // An answer set assembled without a configuration carries no snapshots.
    $this->assertSame('', (new SummaryFormatter())->format(new Answers(['name' => 'Acme'], ['name' => Provenance::Edited])));
  }

  public function testResolvesLinkedLabels(): void {
    $form = Form::create('T')
      ->panel('See [Orchard](https://example.com/orchard)', 'p', function (PanelBuilder $p): void {
        $p->text('Order at [Basket](https://example.com/basket)', 'name');
      })
      ->root();
    $answers = Answers::forTree($form, ['name' => 'Weekly'], ['name' => Provenance::Edited]);

    // Without hyperlink support the label and heading degrade to text (url).
    $plain = (new SummaryFormatter())->format($answers);
    $this->assertStringContainsString('See Orchard (https://example.com/orchard)', $plain);
    $this->assertStringContainsString('Order at Basket (https://example.com/basket): Weekly', $plain);

    // With it on, the same labels carry the OSC 8 escape.
    $linked = (new SummaryFormatter(TRUE))->format($answers);
    $this->assertStringContainsString(Ansi::link('Orchard', 'https://example.com/orchard'), $linked);
    $this->assertStringContainsString(Ansi::link('Basket', 'https://example.com/basket'), $linked);
  }

}
