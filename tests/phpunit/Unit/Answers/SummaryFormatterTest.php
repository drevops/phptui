<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Answers;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Answers\SummaryFormatter;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Render\Ansi;
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
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
        $p->text('machine', 'Machine')->derive(new Derive('{{name}}'));
      })
      ->panel('drupal', 'Drupal', function (PanelBuilder $p): void {
        $p->text('profile', 'Profile');
        $p->panel('adv', 'Advanced', function (PanelBuilder $sp): void {
          $sp->confirm('debug', 'Debug');
        });
      })
      ->panel('empty', 'Empty', function (PanelBuilder $p): void {
        $p->text('gone', 'Gone')->when(new Condition('name', eq: 'never'));
      })
      ->build();
    $answers = Answers::forForm(
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
      ->panel('p', 'P', function (PanelBuilder $p): void {
        $p->select('mods', 'Mods')->multiple();
      })
      ->build();
    $answers = Answers::forForm($form, ['mods' => ['a', 'b']], ['mods' => Provenance::Edited]);

    $summary = (new SummaryFormatter())->format($answers);

    $this->assertStringContainsString('Mods: a, b', $summary);
  }

  public function testMasksPasswordValues(): void {
    $form = Form::create('T')
      ->panel('p', 'P', function (PanelBuilder $p): void {
        $p->password('token', 'Token');
        $p->password('unset', 'Unset');
      })
      ->build();
    $answers = Answers::forForm($form, ['token' => 's3cret-long', 'unset' => ''], ['token' => Provenance::Edited, 'unset' => Provenance::Default]);

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
      ->panel('p', 'See [Orchard](https://example.com/orchard)', function (PanelBuilder $p): void {
        $p->text('name', 'Order at [Basket](https://example.com/basket)');
      })
      ->build();
    $answers = Answers::forForm($form, ['name' => 'Weekly'], ['name' => Provenance::Edited]);

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
