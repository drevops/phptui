<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Model;

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Model\FormException;
use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Model\Panel;
use DrevOps\Tui\Model\SelectionBounds;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the immutable models built by the fluent form builder.
 */
#[CoversClass(FormDefinition::class)]
#[CoversClass(Panel::class)]
#[CoversClass(Field::class)]
#[CoversClass(Option::class)]
#[Group('model')]
final class BuiltModelTest extends TestCase {

  public function testBuildsNestedForm(): void {
    $form = Form::create('Demo', 'Acme')
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name')->default('Acme')->required();
        $p->text('email');
      })
      ->panel('drupal', 'Drupal', function (PanelBuilder $p): void {
        $p->select('profile')->option('standard', 'Standard');
        $p->panel('advanced', 'Advanced', function (PanelBuilder $sp): void {
          $sp->confirm('theme_debug');
        });
      })
      ->build();

    $this->assertSame('Demo', $form->title);
    $this->assertSame('Acme', $form->subject);
    $this->assertCount(2, $form->panels);

    $general = $form->panels[0];
    $this->assertSame('general', $general->id);
    $this->assertCount(2, $general->fields);

    $name = $general->fields[0];
    $this->assertSame(FieldType::Text, $name->type);
    $this->assertSame('Acme', $name->default);
    $this->assertTrue($name->required);

    $drupal = $form->panels[1];
    $profile = $drupal->fields[0];
    $this->assertSame(FieldType::Select, $profile->type);
    $standard = $profile->option('standard');
    $this->assertInstanceOf(Option::class, $standard);
    $this->assertSame('Standard', $standard->label);
    $this->assertNotInstanceOf(Option::class, $profile->option('missing'));

    $this->assertCount(1, $drupal->panels);
    $this->assertSame('advanced', $drupal->panels[0]->id);

    // field() resolves nested fields across sub-panels.
    $this->assertSame('theme_debug', $form->field('theme_debug')?->id);
    $this->assertNotInstanceOf(Field::class, $form->field('nope'));
    $this->assertCount(4, $form->fields());
  }

  public function testPanelItemCount(): void {
    $panel = new Panel('p', 'P', '', [new Field('a', 'A', '', FieldType::Text, '')], [new Panel('s', 'S', '')]);

    $this->assertSame(2, $panel->itemCount());
    $this->assertSame(0, (new Panel('empty', 'E', ''))->itemCount());
  }

  public function testTypeDefaults(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->select('ms')->multiple();
        $p->confirm('cb');
        $p->text('tx');
      })
      ->build();

    $this->assertSame([], $form->field('ms')?->default);
    $this->assertFalse($form->field('cb')?->default);
    $this->assertSame('', $form->field('tx')?->default);
  }

  public function testSelectionBoundsOnNonMultipleFieldThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Field "s" declares selection limits but does not collect several values.');

    new Field('s', 'S', '', FieldType::Text, '', selectionBounds: new SelectionBounds(2, 3));
  }

  public function testFormDefaults(): void {
    $form = Form::create('T')->build();

    $this->assertSame('', $form->subject);
    $this->assertSame('', $form->envPrefix);
    $this->assertSame([], $form->fixups);
    // Form chrome defaults (the global TUI runtime lives on the Tui facade).
    $this->assertSame('', $form->banner);
    $this->assertTrue($form->buttons->show);
    $this->assertSame('Submit', $form->buttons->submitLabel);
    $this->assertSame('Cancel', $form->buttons->cancelLabel);
  }

}
