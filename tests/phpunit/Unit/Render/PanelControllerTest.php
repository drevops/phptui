<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Render;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Handler\HandlerRegistry;
use DrevOps\Tui\Model\Fixup;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\ExternalEditor;
use DrevOps\Tui\Render\PanelController;
use DrevOps\Tui\Render\Terminal;
use DrevOps\Tui\Render\TerminalControl;
use DrevOps\Tui\Testing\BufferedTerminal;
use DrevOps\Tui\Testing\KeyEncoder;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\DosTheme;
use DrevOps\Tui\Theme\HAlign;
use DrevOps\Tui\Theme\Spacing;
use DrevOps\Tui\Theme\VAlign;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the interactive panel controller.
 */
#[CoversClass(PanelController::class)]
#[Group('tui')]
final class PanelControllerTest extends TestCase {

  public function testDrillIntoPanelAndBack(): void {
    $controller = $this->controller();

    $controller->handle(Key::named(KeyName::Enter));
    $this->assertSame('General', $controller->currentPanel()->title);

    $controller->handle(Key::named(KeyName::Escape));
    $this->assertSame('Demo', $controller->currentPanel()->title);
  }

  public function testNavigateCursorClamps(): void {
    $controller = $this->controller();
    $this->assertSame(0, $controller->cursor());

    $controller->handle(Key::named(KeyName::Down));
    $this->assertSame(1, $controller->cursor());

    // The root holds 2 panels plus the Submit and Cancel buttons (4 items).
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Down));
    $this->assertSame(3, $controller->cursor());

    $controller->handle(Key::named(KeyName::Up));
    $this->assertSame(2, $controller->cursor());
  }

  public function testSubmitButton(): void {
    $controller = $this->controller();

    // Move past the 2 panels to Submit (index 2), then activate it.
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Enter));

    $this->assertTrue($controller->isDone());
    $this->assertFalse($controller->isCancelled());
  }

  public function testCancelButton(): void {
    $controller = $this->controller();

    // Move to Cancel (index 3), then activate it.
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Enter));

    $this->assertTrue($controller->isDone());
    $this->assertTrue($controller->isCancelled());
  }

  public function testButtonsRenderByDefault(): void {
    $controller = $this->controller();

    // Submit and Cancel render inline on one row.
    $this->assertStringContainsString('[ Submit ]  [ Cancel ]', Ansi::strip($controller->frame(12)));

    // Select a button and re-render (covers the button cursor-line branch).
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Down));
    $this->assertStringContainsString('[ Submit ]', Ansi::strip($controller->frame(12)));
  }

  public function testButtonsOptOut(): void {
    $builder = Form::create('Demo')
      ->buttons(FALSE)
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('a', 'A');
      });
    $controller = new PanelController($builder->build(), new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]), ['a' => 'x']);

    $this->assertStringNotContainsString('Submit', Ansi::strip($controller->frame(12)));

    // With buttons off, the single field is the only item: Down clamps at 0.
    $controller->handle(Key::named(KeyName::Down));
    $this->assertSame(0, $controller->cursor());
  }

  public function testButtonsOnlyOnRoot(): void {
    $controller = $this->controller();

    // The root panel shows the buttons.
    $this->assertStringContainsString('[ Submit ]', Ansi::strip($controller->frame(12)));

    // Drilling into a sub-panel hides them.
    $controller->handle(Key::named(KeyName::Enter));
    $sub = Ansi::strip($controller->frame(12));
    $this->assertStringNotContainsString('Submit', $sub);
    $this->assertStringNotContainsString('Cancel', $sub);

    // Popping back to the root shows them again.
    $controller->handle(Key::named(KeyName::Escape));
    $this->assertStringContainsString('[ Submit ]', Ansi::strip($controller->frame(12)));
  }

  public function testInlineEditExpandsWidgetInsideThePanel(): void {
    $controller = $this->controller();
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertTrue($controller->isEditing());

    $frame = Ansi::strip($controller->frame(12));

    // The editor expands in place: the breadcrumb and the sibling row stay
    // visible, the field's view (its caret input) shows under the label, and
    // the footer switches to the widget's hints - no full-screen editor header.
    $this->assertStringContainsString('General', $frame);
    $this->assertStringContainsString('❯ Name', $frame);
    $this->assertStringContainsString('Acme', $frame);
    $this->assertStringContainsString('Advanced', $frame);
    $this->assertStringContainsString('accept', $frame);
    $this->assertStringNotContainsString('────', $frame);
  }

  public function testInlineEditRendersChoiceListInThePanel(): void {
    $builder = Form::create('Demo')
      ->buttons(FALSE)
      ->panel('main', 'Main', function (PanelBuilder $p): void {
        $p->select('env', 'Env')->default('dev')->options(['dev' => 'Development', 'prod' => 'Production']);
        $p->text('note', 'Note');
      });
    $controller = new PanelController($builder->build(), new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]), ['env' => 'dev', 'note' => 'n']);

    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));

    $frame = Ansi::strip($controller->frame(12));

    // A multi-line widget view renders inline too: the select's own radio list
    // shows in the panel, with the sibling field still visible below it.
    $this->assertStringContainsString('Development', $frame);
    $this->assertStringContainsString('Production', $frame);
    $this->assertStringContainsString('Note', $frame);
  }

  public function testInlineEditKeepsTheFieldDescription(): void {
    $builder = Form::create('Demo')
      ->buttons(FALSE)
      ->panel('main', 'Main', function (PanelBuilder $p): void {
        $p->confirm('cdn', 'Serve via CDN?')->description('Cache assets at the edge.');
      });
    $controller = new PanelController($builder->build(), new DefaultTheme(50, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]), ['cdn' => TRUE]);

    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));

    // The field's help text stays visible while its editor is open in the row.
    $this->assertStringContainsString('Cache assets at the edge.', Ansi::strip($controller->frame(12)));
  }

  public function testStandaloneEditTakesTheFullScreen(): void {
    $builder = Form::create('Demo')
      ->buttons(FALSE)
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name', 'Name')->standalone();
        $p->text('other', 'Other');
      });
    $controller = new PanelController($builder->build(), new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]), ['name' => 'Acme', 'other' => 'x']);

    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertTrue($controller->isEditing());

    $frame = Ansi::strip($controller->frame(12));

    // A standalone field opens full-screen: the underlined label header and the
    // widget's hints, with none of the other panel rows around it.
    $this->assertStringContainsString("Name\n────", $frame);
    $this->assertStringContainsString('accept', $frame);
    $this->assertStringContainsString('esc cancel', $frame);
    $this->assertStringNotContainsString('Other', $frame);
  }

  public function testButtonsNavigateWithLeftRight(): void {
    $controller = $this->controller();

    // Past the two sub-panels to Submit (index 2).
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Down));
    $this->assertSame(2, $controller->cursor());

    // Right moves to Cancel, Left moves back to Submit.
    $controller->handle(Key::named(KeyName::Right));
    $this->assertSame(3, $controller->cursor());
    $controller->handle(Key::named(KeyName::Left));
    $this->assertSame(2, $controller->cursor());

    // Left on the first button clamps.
    $controller->handle(Key::named(KeyName::Left));
    $this->assertSame(2, $controller->cursor());
  }

  public function testLeftRightIgnoredOffButtons(): void {
    $controller = $this->controller();

    // On a normal item, Left/Right do nothing.
    $controller->handle(Key::named(KeyName::Right));
    $this->assertSame(0, $controller->cursor());
    $controller->handle(Key::named(KeyName::Left));
    $this->assertSame(0, $controller->cursor());
  }

  public function testEditFieldReturnsWithValue(): void {
    $controller = $this->controller();
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertTrue($controller->isEditing());

    $controller->handle(Key::char('!'));
    $controller->handle(Key::named(KeyName::Enter));

    $this->assertFalse($controller->isEditing());
    $this->assertSame('Acme!', $controller->answers()->value('name'));
    $this->assertSame(Provenance::Edited, $controller->answers()->provenanceOf('name'));
  }

  public function testEditCancelKeepsValue(): void {
    $controller = $this->controller();
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertTrue($controller->isEditing());

    $controller->handle(Key::named(KeyName::Escape));

    $this->assertFalse($controller->isEditing());
    $this->assertSame('Acme', $controller->answers()->value('name'));
  }

  public function testDrillIntoSubPanel(): void {
    $controller = $this->controller();
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Down));
    $this->assertSame(1, $controller->cursor());

    $controller->handle(Key::named(KeyName::Enter));

    $this->assertSame('Advanced', $controller->currentPanel()->title);
  }

  public function testMouseWheelScrollsWithoutMovingCursor(): void {
    $controller = $this->controller();
    $before = $controller->cursor();

    $controller->handle(Key::named(KeyName::MouseWheelDown));

    $this->assertSame($before, $controller->cursor());
    $this->assertFalse($controller->isEditing());
    $this->assertStringContainsString('Demo', $controller->frame(4));
  }

  public function testMouseWheelUpScrollsBackWithoutMovingCursor(): void {
    $controller = $this->controller();

    $controller->handle(Key::named(KeyName::MouseWheelDown));
    $controller->handle(Key::named(KeyName::MouseWheelUp));

    $this->assertSame(0, $controller->cursor());
    $this->assertStringContainsString('Demo', $controller->frame(4));
  }

  public function testHubShowsPanelValueSummary(): void {
    $controller = $this->controller();

    // At the root hub (before drilling in), each sub-panel shows a one-line
    // summary of its field values - here the "General" panel's name.
    $this->assertStringContainsString('Acme', Ansi::strip($controller->frame(20)));
  }

  public function testFrameShowsSelectionAndValue(): void {
    $controller = $this->controller();
    $controller->handle(Key::named(KeyName::Enter));

    $frame = Ansi::strip($controller->frame(12));

    $this->assertStringContainsString('General', $frame);
    $this->assertStringContainsString('❯ Name', $frame);
    $this->assertStringContainsString('Acme', $frame);
  }

  public function testEditingFrameShowsWidget(): void {
    $controller = $this->controller();
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));

    $frame = $controller->frame(12);

    $this->assertStringContainsString('Name', $frame);
    $this->assertStringContainsString('Acme', $frame);
  }

  public function testNoteRendersAsCardTheCursorSkips(): void {
    $builder = Form::create('Demo')
      ->buttons(FALSE)
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
        $p->note('mid', 'Middle')->description('Between fields.');
        $p->confirm('agree', 'Agree');
      });
    $theme = new DefaultTheme(60, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]);
    $controller = new PanelController($builder->build(), $theme, ['name' => 'Acme', 'agree' => FALSE]);

    // Drill in: the cursor lands on the first navigable field.
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertSame(0, $controller->cursor());

    $frame = Ansi::strip($controller->frame(16));
    // The note card renders its title and body inline...
    $this->assertStringContainsString('Middle', $frame);
    $this->assertStringContainsString('Between fields.', $frame);
    // ...but the selection marker is on the field, never the note.
    $this->assertStringContainsString('❯ Name', $frame);

    // Down moves from the first field straight to the field after the note.
    $controller->handle(Key::named(KeyName::Down));
    $this->assertSame(1, $controller->cursor());

    $frame = Ansi::strip($controller->frame(16));
    $this->assertStringContainsString('❯ Agree', $frame);
    $this->assertStringNotContainsString('❯ Middle', $frame);
  }

  public function testNoteInterpolatesCurrentAnswersAndUpdates(): void {
    $builder = Form::create('Demo')
      ->buttons(FALSE)
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
        $p->note('echo', 'Echo')->description('Hello {{name}}.');
      });
    $theme = new DefaultTheme(60, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]);
    $controller = new PanelController($builder->build(), $theme, ['name' => '']);

    $controller->handle(Key::named(KeyName::Enter));

    // Edit the name; the note re-interpolates the new answer once it settles.
    $controller->handle(Key::named(KeyName::Enter));
    foreach (str_split('Plum') as $char) {
      $controller->handle(Key::char($char));
    }
    $controller->handle(Key::named(KeyName::Enter));

    $this->assertStringContainsString('Hello Plum.', Ansi::strip($controller->frame(16)));
  }

  public function testBorderedNoteRendersBox(): void {
    $builder = Form::create('Demo')
      ->buttons(FALSE)
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->note('boxed', 'Boxed')->description('In a box.')->border();
        $p->text('name', 'Name');
      });
    // The frame itself is borderless, so any box glyphs come from the note; an
    // opt-in note border falls back to the single-line box here.
    $theme = new DefaultTheme(60, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]);
    $controller = new PanelController($builder->build(), $theme, ['name' => 'Acme']);

    $controller->handle(Key::named(KeyName::Enter));
    $frame = Ansi::strip($controller->frame(16));

    $this->assertStringContainsString('Boxed', $frame);
    $this->assertStringContainsString('┌', $frame);
    $this->assertStringContainsString('┐', $frame);
    $this->assertStringContainsString('└', $frame);
    $this->assertStringContainsString('┘', $frame);
  }

  public function testNotesOnlySubPanelNavigatesSafely(): void {
    $builder = Form::create('Demo')
      ->buttons(FALSE)
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
        $p->panel('info', 'Info', function (PanelBuilder $sp): void {
          $sp->note('a', 'First note')->description('Alpha.');
          $sp->note('b', 'Second note')->description('Beta.');
        });
      });
    $theme = new DefaultTheme(60, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]);
    $controller = new PanelController($builder->build(), $theme, ['name' => 'Acme']);

    // Drill into General, move to the Info sub-panel, then drill into it.
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertSame('Info', $controller->currentPanel()->title);

    // The sub-panel has no navigable items: the cursor stays put and pressing
    // Enter is inert rather than opening an editor for a note.
    $this->assertSame(0, $controller->cursor());
    $controller->handle(Key::named(KeyName::Down));
    $this->assertSame(0, $controller->cursor());
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertFalse($controller->isEditing());

    $frame = Ansi::strip($controller->frame(16));
    $this->assertStringContainsString('First note', $frame);
    $this->assertStringContainsString('Second note', $frame);

    // Back out returns to the parent panel.
    $controller->handle(Key::named(KeyName::Escape));
    $this->assertSame('General', $controller->currentPanel()->title);
  }

  public function testModalButtonSelectionSkipsNoteInTheOffset(): void {
    $form = Form::create('Demo')
      ->panel('main', 'Main', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
      })
      ->panel('edit', 'Quick edit', function (PanelBuilder $m): void {
        $m->modal('Apply', 'Discard');
        $m->note('hint', 'Heads up')->description('Pick a nickname.');
        $m->text('nick', 'Nickname');
      })
      ->build();
    // Colour on so the selected button carries the cursor styling.
    $theme = new DefaultTheme(50, ['color' => TRUE, 'border' => Border::None, 'spacing' => Spacing::Normal]);
    $controller = new PanelController($form, $theme, ['name' => 'Acme', 'nick' => '']);

    // Open the modal, then move onto the Apply button. The note before the
    // field must not count toward the button offset, or Apply stays unlit.
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Down));

    $this->assertStringContainsString($theme->cursor('[ Apply ]'), $controller->frame(20));
  }

  public function testQuit(): void {
    $controller = $this->controller();
    $this->assertFalse($controller->isDone());

    $controller->handle(Key::char('q'));

    $this->assertTrue($controller->isDone());
  }

  public function testHubFooterShowsQuitAndHelp(): void {
    $controller = $this->controller();

    // The hub footer is complete: it surfaces quit and the help toggle, not
    // just the move/select/back subset.
    $footer = Ansi::strip($controller->frame(12));
    $this->assertStringContainsString('q quit', $footer);
    $this->assertStringContainsString('? help', $footer);
  }

  public function testHelpOverlayTogglesAndCloses(): void {
    $controller = $this->controller();

    // '?' opens the overlay; the frame becomes the help screen listing the hub
    // and each widget type the form uses (here Text and Confirm).
    $controller->handle(Key::char('?'));
    $this->assertTrue($controller->isShowingHelp());

    $help = Ansi::strip($controller->frame(12));
    $this->assertStringContainsString('Keyboard help', $help);
    $this->assertStringContainsString('Navigation', $help);
    $this->assertStringContainsString('Text', $help);
    $this->assertStringContainsString('Confirm', $help);
    $this->assertStringContainsString('? close', $help);

    // Any key dismisses it, and that key does nothing else (the cursor stays).
    $controller->handle(Key::named(KeyName::Down));
    $this->assertFalse($controller->isShowingHelp());
    $this->assertSame(0, $controller->cursor());
    $this->assertStringContainsString('Demo', Ansi::strip($controller->frame(12)));
  }

  public function testFooterHiddenWhenTurnedOff(): void {
    $builder = Form::create('Demo')
      ->buttons(FALSE)
      ->panel('p', 'p', function (PanelBuilder $p): void {
        $p->text('a', 'A');
      });
    $controller = new PanelController($builder->build(), new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]), ['a' => 'x'], footer: FALSE);

    // The hub footer is gone.
    $this->assertStringNotContainsString('quit', Ansi::strip($controller->frame(12)));

    // And so is the editor's hint line (drill into the panel, then the field).
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertTrue($controller->isEditing());
    $this->assertStringNotContainsString('accept', Ansi::strip($controller->frame(12)));
  }

  public function testRunSubmitsThroughTheInputPipe(): void {
    $controller = $this->controller();
    $terminal = new BufferedTerminal([
      KeyEncoder::encode(Key::named(KeyName::Down)),
      KeyEncoder::encode(Key::named(KeyName::Down)),
      KeyEncoder::encode(Key::named(KeyName::Enter)),
    ]);

    $answers = $controller->run($terminal);

    $this->assertInstanceOf(Answers::class, $answers);
    $this->assertTrue($controller->isDone());
    $this->assertFalse($controller->isCancelled());
    // The loop rendered the hub before submitting.
    $this->assertStringContainsString('Demo', Ansi::strip($terminal->output()));
  }

  public function testRunPaintsTheThemeBackground(): void {
    $config = Form::create('Demo')
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
      })
      ->build();
    $keys = [KeyEncoder::encode(Key::named(KeyName::Enter))];

    // The dos theme washes the screen blue: run() hands its background to the
    // terminal, which fills every rendered frame with it.
    $dos = new PanelController($config, new DosTheme(40), ['name' => 'Acme']);
    $painted = new BufferedTerminal($keys);
    $dos->run($painted);
    $this->assertSame('44', $painted->paintedBackground);
    $this->assertStringContainsString("\033[44m", $painted->output());

    // A theme with no background leaves the terminal's own surface untouched.
    $plain = new PanelController($config, new DefaultTheme(40), ['name' => 'Acme']);
    $blank = new BufferedTerminal($keys);
    $plain->run($blank);
    $this->assertNull($blank->paintedBackground);
    $this->assertStringNotContainsString("\033[44m", $blank->output());
  }

  public function testRunStopsWhenInputIsExhausted(): void {
    $controller = $this->controller();
    // A single navigation key that does not finish the form; input then ends.
    $terminal = new BufferedTerminal([KeyEncoder::encode(Key::named(KeyName::Down))]);

    $controller->run($terminal);

    // The EOF break ends the loop without the form being submitted.
    $this->assertFalse($controller->isDone());
    $this->assertSame(1, $controller->cursor());
  }

  public function testRunInterruptStopsBeforeHandlingMoreKeys(): void {
    $controller = $this->controller();
    // Ctrl-C arrives first; the Down queued after it must never be handled.
    $terminal = new BufferedTerminal([KeyEncoder::encode(Key::named(KeyName::Interrupt)), KeyEncoder::encode(Key::named(KeyName::Down))]);

    $controller->run($terminal);

    $this->assertTrue($controller->isInterrupted());
    // An interrupt is neither a quit nor a cancel-button finish.
    $this->assertFalse($controller->isDone());
    $this->assertFalse($controller->isCancelled());
    // The loop broke on the interrupt, so the trailing Down never
    // moved the cursor.
    $this->assertSame(0, $controller->cursor());
  }

  public function testRunInterruptClearsEvenWhenClearOnExitOff(): void {
    $config = Form::create('Demo')
      ->buttons(FALSE)
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
      })
      ->build();
    $theme = new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]);

    // An interrupt renders the frame once (one clear) and then forces a second
    // clear at teardown despite clearOnExit being off.
    $interrupted = new PanelController($config, $theme, ['name' => 'Acme'], clearOnExit: FALSE);
    $terminal = new BufferedTerminal([KeyEncoder::encode(Key::named(KeyName::Interrupt))]);
    $interrupted->run($terminal);
    $this->assertTrue($interrupted->isInterrupted());
    $this->assertSame(2, substr_count($terminal->output(), TerminalControl::clear()));

    // Exhausting the input renders the same single frame but adds no teardown
    // clear - so the interrupt's extra clear is what wipes the screen.
    $exhausted = new PanelController($config, $theme, ['name' => 'Acme'], clearOnExit: FALSE);
    $quiet = new BufferedTerminal([]);
    $exhausted->run($quiet);
    $this->assertFalse($exhausted->isInterrupted());
    $this->assertSame(1, substr_count($quiet->output(), TerminalControl::clear()));
  }

  public function testRunInterruptAtBannerAbortsBeforeTheForm(): void {
    $config = Form::create('Demo')
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
      })
      ->build();
    $controller = new PanelController($config, new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]), ['name' => 'Acme'], banner: 'WELCOME', version: '2.0');
    // Ctrl-C at the "press any key" banner aborts instead of entering the form.
    $terminal = new BufferedTerminal([KeyEncoder::encode(Key::named(KeyName::Interrupt))]);

    $controller->run($terminal);

    $this->assertTrue($controller->isInterrupted());
    $this->assertStringContainsString('Press any key to continue', Ansi::strip($terminal->output()));
    // The loop was skipped entirely, so the form body never rendered.
    $this->assertStringNotContainsString('Name', Ansi::strip($terminal->output()));
  }

  public function testRunRendersBannerThenTheForm(): void {
    $builder = Form::create('Demo')
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
      });
    $controller = new PanelController($builder->build(), new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]), ['name' => 'Acme'], banner: 'WELCOME', version: '2.0');
    // The first key dismisses the banner; input then ends.
    $terminal = new BufferedTerminal([KeyEncoder::encode(Key::named(KeyName::Enter))]);

    $controller->run($terminal);

    $this->assertStringContainsString('Press any key to continue', Ansi::strip($terminal->output()));
    // After the banner is dismissed, the loop renders the form body itself.
    $this->assertStringContainsString('General', Ansi::strip($terminal->output()));
  }

  public function testTextareaExternalEditCommitsCapturedValue(): void {
    $controller = $this->textareaController($this->fixedEditor('FROM EDITOR'));

    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertTrue($controller->isEditing());

    $controller->handle(Key::char("\x05"));

    $this->assertFalse($controller->isEditing());
    $this->assertSame('FROM EDITOR', $controller->answers()->value('notes'));
    $this->assertSame(Provenance::Edited, $controller->answers()->provenanceOf('notes'));
  }

  public function testTextareaExternalEditAbortKeepsEditing(): void {
    $controller = $this->textareaController($this->fixedEditor(NULL));

    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::char("\x05"));

    // A NULL capture (aborted edit) leaves the field open, value intact.
    $this->assertTrue($controller->isEditing());
    $this->assertSame('seeded', $controller->answers()->value('notes'));
  }

  public function testTextareaEditorHintShownWhenAvailable(): void {
    $controller = $this->textareaController($this->fixedEditor(NULL));

    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));

    $this->assertStringContainsString('ctrl-e editor', Ansi::strip($controller->frame(12)));
  }

  public function testTextareaEditorHintHiddenAndHandoffInertWhenUnavailable(): void {
    $controller = $this->textareaController($this->unavailableEditor());

    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertStringNotContainsString('ctrl-e editor', Ansi::strip($controller->frame(12)));

    // With no editor available the trigger is inert - editing continues.
    $controller->handle(Key::char("\x05"));
    $this->assertTrue($controller->isEditing());
    $this->assertSame('seeded', $controller->answers()->value('notes'));
  }

  /**
   * A single-panel controller whose textarea opts into the editor handoff.
   *
   * @param \DrevOps\Tui\Render\ExternalEditor $editor
   *   The external-editor service to inject.
   */
  protected function textareaController(ExternalEditor $editor): PanelController {
    $builder = Form::create('Demo')
      ->buttons(FALSE)
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->textarea('notes', 'Notes')->externalEditor();
      });

    return new PanelController($builder->build(), new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]), ['notes' => 'seeded'], external_editor: $editor);
  }

  /**
   * An available editor stub returning a fixed capture.
   *
   * @param string|null $result
   *   The value edit() returns (NULL simulates an aborted edit).
   */
  protected function fixedEditor(?string $result): ExternalEditor {
    return new class($result) extends ExternalEditor {

      public function __construct(protected ?string $result) {
      }

      #[\Override]
      public function isAvailable(): bool {
        return TRUE;
      }

      #[\Override]
      public function edit(string $initial, ?Terminal $terminal = NULL): ?string {
        return $this->result;
      }

    };
  }

  /**
   * An editor stub reporting no editor is available.
   */
  protected function unavailableEditor(): ExternalEditor {
    return new class extends ExternalEditor {

      #[\Override]
      public function isAvailable(): bool {
        return FALSE;
      }

      #[\Override]
      public function edit(string $initial, ?Terminal $terminal = NULL): ?string {
        throw new \RuntimeException('the editor must not launch when unavailable');
      }

    };
  }

  public function testModalOpensCenteredOverTheBackdrop(): void {
    $controller = $this->modalController();

    // Move to the modal item and open it.
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Enter));

    $this->assertTrue($controller->currentPanel()->isModal());
    $this->assertSame('Quick edit', $controller->currentPanel()->title);

    $frame = Ansi::strip($controller->frame(14));

    // The dialog box shows its title, description, field and its own configured
    // buttons; the parent panel shows through around it (the backdrop).
    $this->assertStringContainsString('Quick edit', $frame);
    $this->assertStringContainsString('Adjust the nickname.', $frame);
    $this->assertStringContainsString('Nickname', $frame);
    $this->assertStringContainsString('[ Apply ]', $frame);
    $this->assertStringContainsString('[ Discard ]', $frame);
    $this->assertStringContainsString('Main', $frame);
  }

  public function testModalSubmitKeepsEditsAndReturnsToParent(): void {
    $controller = $this->modalController();

    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Enter));

    // Edit the dialog's field, then activate its Submit button.
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::char('!'));
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Enter));

    $this->assertFalse($controller->currentPanel()->isModal());
    $this->assertFalse($controller->isDone());
    $this->assertSame('ace!', $controller->answers()->value('nick'));
    // The cursor is restored to the item that opened the dialog.
    $this->assertSame(1, $controller->cursor());
  }

  public function testModalCancelButtonRestoresTheAnswers(): void {
    $controller = $this->modalController();

    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Enter));

    // Edit the field, then activate the Cancel button.
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::char('X'));
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Enter));

    $this->assertFalse($controller->currentPanel()->isModal());
    $this->assertSame('ace', $controller->answers()->value('nick'));
  }

  public function testModalEscapeRestoresTheAnswers(): void {
    $controller = $this->modalController();

    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Enter));

    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::char('Z'));
    $controller->handle(Key::named(KeyName::Enter));

    // Escape dismisses the dialog like Cancel: edits are discarded.
    $controller->handle(Key::named(KeyName::Escape));

    $this->assertFalse($controller->currentPanel()->isModal());
    $this->assertSame('ace', $controller->answers()->value('nick'));
    $this->assertSame(1, $controller->cursor());
  }

  public function testModalQuitDismissesInsteadOfEndingTheForm(): void {
    $controller = $this->modalController();

    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertTrue($controller->currentPanel()->isModal());

    // A modal is blocking: quit closes it rather than finishing the form.
    $controller->handle(Key::char('q'));

    $this->assertFalse($controller->isDone());
    $this->assertFalse($controller->currentPanel()->isModal());
  }

  public function testModalButtonsNavigateWithLeftRight(): void {
    $controller = $this->modalController();

    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Enter));

    // Past the field to Submit (index 1), then Left/Right between buttons.
    $controller->handle(Key::named(KeyName::Down));
    $this->assertSame(1, $controller->cursor());
    $controller->handle(Key::named(KeyName::Right));
    $this->assertSame(2, $controller->cursor());
    $controller->handle(Key::named(KeyName::Left));
    $this->assertSame(1, $controller->cursor());
  }

  public function testModalEditsFieldInlineInsideTheDialog(): void {
    $controller = $this->modalController();

    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertTrue($controller->isEditing());

    // Type a character so the live editor value differs from the stored one -
    // the dialog must show the live value, proving the editor renders in place.
    $controller->handle(Key::char('Z'));

    $frame = Ansi::strip($controller->frame(14));

    $this->assertStringContainsString('Nickname', $frame);
    $this->assertStringContainsString('aceZ', $frame);
    // The editor renders inside the dialog box, which still frames it.
    $this->assertStringContainsString('[ Apply ]', $frame);
  }

  public function testModalKeepsButtonsVisibleWhenTallerThanTheScreen(): void {
    $builder = Form::create('Demo')
      ->buttons(FALSE)
      ->panel('main', 'Main', function (PanelBuilder $p): void {
        $p->text('a', 'Alpha');
        $p->panel('big', 'Big dialog', function (PanelBuilder $m): void {
          $m->modal('Save', 'Discard')->description('Many fields.');
          for ($i = 1; $i <= 12; $i++) {
            $m->text('f' . $i, 'Field ' . $i);
          }
        });
      });
    $values = ['a' => 'x'];
    for ($i = 1; $i <= 12; $i++) {
      $values['f' . $i] = 'v' . $i;
    }
    $controller = new PanelController($builder->build(), new DefaultTheme(50, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]), $values);

    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Enter));

    // A dialog with more content than the viewport scrolls its body under a
    // pinned button footer, so both exits stay reachable rather than clipping
    // off the bottom.
    $frame = Ansi::strip($controller->frame(12));
    $this->assertStringContainsString('[ Save ]', $frame);
    $this->assertStringContainsString('[ Discard ]', $frame);

    // The very short viewport falls back to truncating the body, still pinning
    // the buttons.
    $squeezed = Ansi::strip($controller->frame(8));
    $this->assertStringContainsString('[ Save ]', $squeezed);
  }

  /**
   * A controller over a form whose second top-level panel is a modal dialog.
   */
  protected function modalController(): PanelController {
    $builder = Form::create('Demo')
      ->buttons(FALSE)
      ->panel('main', 'Main', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
      })
      ->panel('edit', 'Quick edit', function (PanelBuilder $m): void {
        $m->modal('Apply', 'Discard')->description('Adjust the nickname.');
        $m->text('nick', 'Nickname');
      });

    return new PanelController($builder->build(), new DefaultTheme(50, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]), ['name' => 'Acme', 'nick' => 'ace']);
  }

  public function testRunFullscreenFillsTheTerminalExactly(): void {
    $controller = $this->fullscreenController(['fullscreen' => TRUE]);
    $terminal = new BufferedTerminal([], 14, 40);

    $controller->run($terminal);

    // The stretched frame is exactly the terminal height, footer pinned last.
    $lines = explode("\n", Ansi::strip($terminal->output()));
    $this->assertCount(14, $lines);
    $this->assertStringContainsString('quit', $lines[13]);
  }

  public function testRunFullscreenCentersTheBodyBlock(): void {
    $controller = $this->fullscreenController(['fullscreen' => TRUE, 'halign' => HAlign::Center]);
    $terminal = new BufferedTerminal([], 14, 40);

    $controller->run($terminal);

    // The widest block row is the 24-column button bar, so the block indents
    // (40 - 24) / 2 = 8 columns as one unit.
    $this->assertStringContainsString(str_repeat(' ', 8) . '> General', Ansi::strip($terminal->output()));
  }

  public function testRunFullscreenBottomAlignsTheBodyBlock(): void {
    $controller = $this->fullscreenController(['fullscreen' => TRUE, 'valign' => VAlign::Bottom]);
    $terminal = new BufferedTerminal([], 14, 40);

    $controller->run($terminal);

    $lines = explode("\n", Ansi::strip($terminal->output()));

    // The body window spans rows 1-11: the block sinks to its bottom.
    $this->assertSame('', $lines[1]);
    $this->assertStringContainsString('> General', $lines[8]);
    $this->assertStringContainsString('[ Submit ]', $lines[11]);
  }

  public function testRunFullscreenPositionsTheCappedFrame(): void {
    $controller = $this->fullscreenController(['fullscreen' => TRUE, 'max_width' => 30, 'halign' => HAlign::Center, 'border' => Border::Line], 60);
    $terminal = new BufferedTerminal([], 12, 60);

    $controller->run($terminal);

    $lines = explode("\n", Ansi::strip($terminal->output()));

    // The capped 30-column box floats centered in the 60-column terminal.
    $this->assertCount(12, $lines);
    $this->assertSame(str_repeat(' ', 15) . '+' . str_repeat('-', 28) . '+', rtrim($lines[0]));
  }

  public function testRunFullscreenTooSmallGuardSwallowsAllButQuit(): void {
    $controller = $this->fullscreenController(['fullscreen' => TRUE]);
    // Six rows are below the ten-row minimum; Down must be swallowed by the
    // guard screen, then quit ends the loop.
    $terminal = new BufferedTerminal([KeyEncoder::encode(Key::named(KeyName::Down)), 'q'], 6, 40);

    $controller->run($terminal);

    $output = Ansi::strip($terminal->output());
    $this->assertStringContainsString('Terminal too small.', $output);
    $this->assertStringContainsString('Need at least 24 x 10 - have 40 x 6.', $output);
    $this->assertTrue($controller->isDone());
    $this->assertSame(0, $controller->cursor());
  }

  public function testRunFullscreenTooSmallGuardStillInterrupts(): void {
    $controller = $this->fullscreenController(['fullscreen' => TRUE]);
    $terminal = new BufferedTerminal([KeyEncoder::encode(Key::named(KeyName::Interrupt))], 6, 40);

    $controller->run($terminal);

    $this->assertTrue($controller->isInterrupted());
    $this->assertFalse($controller->isDone());
  }

  public function testRunFullscreenMinWidthIsMeasuredFromContent(): void {
    $builder = Form::create('Demo')
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('window', 'Preferred delivery window of the season');
      });
    $controller = new PanelController($builder->build(), new DefaultTheme(30, ['color' => FALSE, 'unicode' => FALSE, 'fullscreen' => TRUE, 'border' => Border::None, 'spacing' => Spacing::Normal]), ['window' => 'Morning']);

    // Thirty columns cannot fit the measured 50-column field row.
    $terminal = new BufferedTerminal([], 24, 30);
    $controller->run($terminal);

    $this->assertStringContainsString('Terminal too small.', Ansi::strip($terminal->output()));
  }

  public function testRunFullscreenExplicitMinWidthOverridesTheMeasure(): void {
    $builder = Form::create('Demo')
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('window', 'Preferred delivery window of the season');
      });
    $controller = new PanelController($builder->build(), new DefaultTheme(30, ['color' => FALSE, 'unicode' => FALSE, 'fullscreen' => TRUE, 'min_width' => 10, 'border' => Border::None, 'spacing' => Spacing::Normal]), ['window' => 'Morning']);

    $terminal = new BufferedTerminal([], 24, 30);
    $controller->run($terminal);

    $output = Ansi::strip($terminal->output());
    $this->assertStringNotContainsString('Terminal too small.', $output);
    $this->assertStringContainsString('General', $output);
  }

  public function testRunOutsideFullscreenIgnoresTheMinimums(): void {
    // The same six-row terminal renders the plain frame when not fullscreen.
    $controller = $this->fullscreenController([]);
    $terminal = new BufferedTerminal([], 6, 40);

    $controller->run($terminal);

    $output = Ansi::strip($terminal->output());
    $this->assertStringNotContainsString('Terminal too small.', $output);
    $this->assertStringContainsString('General', $output);
  }

  public function testRunFullscreenTooSmallQuitDismissesAnOpenModal(): void {
    $builder = Form::create('Demo')
      ->panel('main', 'Main', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
      })
      ->panel('edit', 'Quick edit', function (PanelBuilder $m): void {
        $m->modal('Apply', 'Discard');
        $m->text('nick', 'Nickname');
      });
    $controller = new PanelController($builder->build(), new DefaultTheme(40, ['color' => FALSE, 'unicode' => FALSE, 'fullscreen' => TRUE, 'border' => Border::None, 'spacing' => Spacing::Normal]), ['name' => 'Acme', 'nick' => 'ace']);

    // Open the modal, then run on a terminal below the minimum height.
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertTrue($controller->currentPanel()->isModal());

    $controller->run(new BufferedTerminal(['q'], 6, 40));

    // Quit on the guard screen dismissed the dialog, not the whole form.
    $this->assertFalse($controller->isDone());
    $this->assertFalse($controller->currentPanel()->isModal());
  }

  public function testModalBodyUsesTheFullScreenBudget(): void {
    $builder = Form::create('Demo')
      ->panel('main', 'Main', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
      })
      ->panel('edit', 'Quick edit', function (PanelBuilder $m): void {
        $m->modal('Apply', 'Discard');
        $m->text('one', 'First');
        $m->text('two', 'Second');
        $m->text('three', 'Third');
        $m->text('four', 'Fourth');
      });
    $controller = new PanelController($builder->build(), new DefaultTheme(50, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]));

    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Enter));

    // The screen rows bound the dialog, so its four fields fit a 14-row
    // screen; a body-viewport bound would deduct the frame chrome a second
    // time and slice the last field away.
    $this->assertStringContainsString('Fourth', Ansi::strip($controller->frame(14)));
  }

  public function testRunFullscreenMeasuredMinWidthIsCappedByMaxWidth(): void {
    $builder = Form::create('Demo')
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('window', 'Preferred delivery window of the season');
      });
    $controller = new PanelController($builder->build(), new DefaultTheme(40, ['color' => FALSE, 'unicode' => FALSE, 'fullscreen' => TRUE, 'max_width' => 30, 'border' => Border::None, 'spacing' => Spacing::Normal]), ['window' => 'Morning']);

    // The content measures ~50 columns, but the 30-column cap is the
    // consumer's word that clipping is acceptable: a 40-column terminal must
    // render the capped frame, not dead-end on an unsatisfiable notice.
    $terminal = new BufferedTerminal([], 24, 40);
    $controller->run($terminal);

    $output = Ansi::strip($terminal->output());
    $this->assertStringNotContainsString('Terminal too small.', $output);
    $this->assertStringContainsString('General', $output);
  }

  public function testRunFullscreenMinHeightIsCappedByMaxHeight(): void {
    // max_height 8 lowers the default 10-row minimum: a 9-row terminal is
    // enough for the 8-row frame, so no notice shows.
    $controller = $this->fullscreenController(['fullscreen' => TRUE, 'max_height' => 8]);
    $terminal = new BufferedTerminal([], 9, 40);

    $controller->run($terminal);

    $output = Ansi::strip($terminal->output());
    $this->assertStringNotContainsString('Terminal too small.', $output);
    $this->assertCount(9, explode("\n", $output));
  }

  public function testGridArrowsMoveSpatially(): void {
    $controller = $this->gridController();

    // layout(1, 2): A alone on row one, B and C beside each other below.
    // Right on a one-column row stays put.
    $controller->handle(Key::named(KeyName::Right));
    $this->assertSame(0, $controller->cursor());

    // Down lands on the nearest column of the next row (B), Right walks to C.
    $controller->handle(Key::named(KeyName::Down));
    $this->assertSame(1, $controller->cursor());
    $controller->handle(Key::named(KeyName::Right));
    $this->assertSame(2, $controller->cursor());

    // The row edge clamps; Up from C lands back on A (its nearest column).
    $controller->handle(Key::named(KeyName::Right));
    $this->assertSame(2, $controller->cursor());
    $controller->handle(Key::named(KeyName::Up));
    $this->assertSame(0, $controller->cursor());

    // Down, Left: back to B; Left clamps at the row's first column.
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Left));
    $this->assertSame(1, $controller->cursor());
    $controller->handle(Key::named(KeyName::Left));
    $this->assertSame(1, $controller->cursor());
  }

  public function testGridDownFromTheLastRowReachesTheButtons(): void {
    $controller = $this->gridController();

    // A -> B -> buttons: Down from the last grid row jumps to Submit, and Up
    // returns to the last panel.
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Down));
    $this->assertSame(3, $controller->cursor());

    $controller->handle(Key::named(KeyName::Enter));
    $this->assertTrue($controller->isDone());
    $this->assertFalse($controller->isCancelled());
  }

  public function testGridUpFromTheFirstRowReachesTheFieldsAbove(): void {
    $builder = Form::create('Demo')
      ->panel('mixed', 'Mixed', function (PanelBuilder $p): void {
        $p->layout(2);
        $p->text('note', 'Note');
        $p->panel('a', 'A', function (PanelBuilder $sp): void {
          $sp->text('one', 'One');
        });
        $p->panel('b', 'B', function (PanelBuilder $sp): void {
          $sp->text('two', 'Two');
        });
      });
    $controller = new PanelController($builder->build(), new DefaultTheme(40, ['color' => FALSE, 'unicode' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]));

    // Drill into the mixed panel: the field sits above the grid.
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertSame('Mixed', $controller->currentPanel()->title);

    // Down enters the grid, Up climbs back out onto the field.
    $controller->handle(Key::named(KeyName::Down));
    $this->assertSame(1, $controller->cursor());
    $controller->handle(Key::named(KeyName::Up));
    $this->assertSame(0, $controller->cursor());
  }

  public function testGridEnterDrillsIntoTheSelectedPanel(): void {
    $controller = $this->gridController();

    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Right));
    $controller->handle(Key::named(KeyName::Enter));

    $this->assertSame('C', $controller->currentPanel()->title);
  }

  public function testGridFrameRendersPanelsSideBySide(): void {
    $controller = $this->gridController();

    $lines = explode("\n", Ansi::strip($controller->frame(20)));

    // B and C share a line; A has its own row above them.
    $side_by_side = array_values(array_filter($lines, static fn(string $line): bool => str_contains($line, 'B >') && str_contains($line, 'C >')));
    $this->assertNotSame([], $side_by_side);
    $this->assertStringContainsString('> A', Ansi::strip($controller->frame(20)));

    // The spatial hint advertises all four arrows.
    $this->assertStringContainsString('^/v/</> move', Ansi::strip($controller->frame(20)));
  }

  /**
   * A controller over a layout(1, 2) grid of three panels.
   *
   * @return \DrevOps\Tui\Render\PanelController
   *   The controller.
   */
  protected function gridController(): PanelController {
    $builder = Form::create('Demo')
      ->layout(1, 2)
      ->panel('a', 'A', function (PanelBuilder $p): void {
        $p->text('one', 'One');
      })
      ->panel('b', 'B', function (PanelBuilder $p): void {
        $p->text('two', 'Two');
      })
      ->panel('c', 'C', function (PanelBuilder $p): void {
        $p->text('three', 'Three');
      });

    return new PanelController($builder->build(), new DefaultTheme(40, ['color' => FALSE, 'unicode' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]));
  }

  /**
   * A controller over a one-panel form with configurable layout options.
   *
   * @param array<string,mixed> $options
   *   Theme options merged over colourless ASCII defaults.
   * @param int $width
   *   The theme width (the terminal width in fullscreen).
   *
   * @return \DrevOps\Tui\Render\PanelController
   *   The controller.
   */
  protected function fullscreenController(array $options, int $width = 40): PanelController {
    $builder = Form::create('Demo')
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
      });

    return new PanelController($builder->build(), new DefaultTheme($width, ['color' => FALSE, 'unicode' => FALSE] + $options + ['border' => Border::None, 'spacing' => Spacing::Normal]), ['name' => 'Acme']);
  }

  /**
   * A controller over a two-panel form seeded with answers.
   */
  public function testEditEnforcesDeclaredValidatorAndTransform(): void {
    $form = Form::create('Demo')
      ->panel('stall', 'Stall', function (PanelBuilder $p): void {
        $p->text('name', 'Name')
          ->validate(static fn (mixed $value): ?string => is_string($value) && $value !== '' ? NULL : 'A name is required.')
          ->transform(static fn (mixed $value): mixed => is_string($value) ? strtolower($value) : $value);
      })
      ->build();

    $controller = new PanelController($form, new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]), values: ['name' => '']);
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertTrue($controller->isEditing());

    // An invalid value is rejected: the editor stays open showing the error.
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertTrue($controller->isEditing());
    $this->assertStringContainsString('A name is required.', $controller->frame(24));

    // A valid value is accepted and stored transformed.
    $controller->handle(Key::char('B'));
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertFalse($controller->isEditing());
    $this->assertSame('b', $controller->answers()->value('name'));
  }

  public function testEditEnforcesHandlerBehaviour(): void {
    $form = Form::create('Demo')
      ->panel('stall', 'Stall', function (PanelBuilder $p): void {
        $p->text('machine_name', 'Machine name');
      })
      ->build();

    $controller = new PanelController($form, new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]), values: ['machine_name' => 'Seed'], handlers: new HandlerRegistry(['DrevOps\Tui\Tests\Fixtures\Handler']));
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::char('X'));
    $controller->handle(Key::named(KeyName::Enter));

    // The handler's static transform() lowercased the accepted value.
    $this->assertFalse($controller->isEditing());
    $this->assertSame('seedx', $controller->answers()->value('machine_name'));
  }

  public function testConditionalFieldFollowsAnswers(): void {
    $form = Form::create('Demo')
      ->panel('packing', 'Packing', function (PanelBuilder $p): void {
        $p->confirm('extra', 'Extra');
        $p->text('notes', 'Notes')->default('mixed')->when(new Condition('extra'));
      })
      ->build();

    $controller = new PanelController($form, new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]), ['extra' => FALSE, 'notes' => 'mixed']);
    $controller->handle(Key::named(KeyName::Enter));

    // The condition fails, so the field neither renders nor answers.
    $this->assertStringNotContainsString('Notes', Ansi::strip($controller->frame(12)));
    $this->assertFalse($controller->answers()->has('notes'));

    // Flip the gate on: the field appears carrying its settled value.
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::char('y'));
    $controller->handle(Key::named(KeyName::Enter));

    $this->assertStringContainsString('Notes', Ansi::strip($controller->frame(12)));
    $this->assertSame('mixed', $controller->answers()->value('notes'));

    // Flip it back: the field hides again and contributes no answer.
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::char('n'));
    $controller->handle(Key::named(KeyName::Enter));

    $this->assertStringNotContainsString('Notes', Ansi::strip($controller->frame(12)));
    $this->assertFalse($controller->answers()->has('notes'));
  }

  public function testCursorClampsWhenFieldHides(): void {
    $form = Form::create('Demo')
      ->panel('p', 'P', function (PanelBuilder $p): void {
        $p->text('gated', 'Gated')->default('g')->when(new Condition('extra'));
        $p->confirm('extra', 'Extra');
      })
      ->build();

    $controller = new PanelController($form, new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]), ['gated' => 'g', 'extra' => TRUE]);
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::named(KeyName::Down));
    $this->assertSame(1, $controller->cursor());

    // Hiding the first field shrinks the list; the cursor clamps onto it.
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::char('n'));
    $controller->handle(Key::named(KeyName::Enter));

    $this->assertSame(0, $controller->cursor());
    $this->assertSame(['extra'], array_keys($controller->answers()->values));
  }

  public function testEditReSettlesDerivedChain(): void {
    $controller = $this->derivedController();
    $controller->handle(Key::named(KeyName::Enter));

    // The construction settle computed the rule over the seeded source.
    $this->assertSame('red_apple', $controller->answers()->value('slug'));

    // Editing the source re-derives the target, keeping its derived badge.
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::char('x'));
    $controller->handle(Key::named(KeyName::Enter));

    $this->assertSame('Red Applex', $controller->answers()->value('name'));
    $this->assertSame('red_applex', $controller->answers()->value('slug'));
    $this->assertSame(Provenance::Derived, $controller->answers()->provenanceOf('slug'));
  }

  public function testEditDerivedFieldPinsOverride(): void {
    $controller = $this->derivedController();
    $controller->handle(Key::named(KeyName::Enter));

    // Editing the derived field itself pins the rule as an override.
    $controller->handle(Key::named(KeyName::Down));
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::char('z'));
    $controller->handle(Key::named(KeyName::Enter));

    $this->assertSame('red_applez', $controller->answers()->value('slug'));
    $this->assertSame(Provenance::Override, $controller->answers()->provenanceOf('slug'));

    // The pinned value survives edits to the source it derived from.
    $controller->handle(Key::named(KeyName::Up));
    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::char('x'));
    $controller->handle(Key::named(KeyName::Enter));

    $this->assertSame('Red Applex', $controller->answers()->value('name'));
    $this->assertSame('red_applez', $controller->answers()->value('slug'));
  }

  public function testEditAppliesFixups(): void {
    $form = Form::create('Demo')
      ->fixup(new Fixup(set: 'note', to: 'boxed', when: new Condition('tag', eq: 'go')))
      ->panel('p', 'P', function (PanelBuilder $p): void {
        $p->text('tag', 'Tag');
        $p->text('note', 'Note');
      })
      ->build();

    $controller = new PanelController($form, new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]), ['tag' => '', 'note' => '']);
    $controller->handle(Key::named(KeyName::Enter));

    $controller->handle(Key::named(KeyName::Enter));
    $controller->handle(Key::char('g'));
    $controller->handle(Key::char('o'));
    $controller->handle(Key::named(KeyName::Enter));

    // The guard matches the accepted edit, so the fix-up set its target on
    // the same settle.
    $this->assertSame('boxed', $controller->answers()->value('note'));
  }

  /**
   * Build a controller over a name field and a slug derived from it.
   */
  protected function derivedController(): PanelController {
    $form = Form::create('Demo')
      ->panel('naming', 'Naming', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
        $p->text('slug', 'Slug')->derive(new Derive('{{name}}', 'machine'));
      })
      ->build();

    return new PanelController($form, new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]), ['name' => 'Red Apple'], ['slug' => Provenance::Derived]);
  }

  protected function controller(): PanelController {
    $builder = Form::create('Demo')
      ->panel('general', 'General', function (PanelBuilder $p): void {
        $p->text('name', 'Name');
        $p->panel('adv', 'Advanced', function (PanelBuilder $sp): void {
          $sp->confirm('debug', 'Debug');
        });
      })
      ->panel('drupal', 'Drupal', function (PanelBuilder $p): void {
        $p->text('profile', 'Profile');
      });
    $theme = new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]);

    return new PanelController($builder->build(), $theme, ['name' => 'Acme', 'debug' => FALSE, 'profile' => 'standard']);
  }

}
