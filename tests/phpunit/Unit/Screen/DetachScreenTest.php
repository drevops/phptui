<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Screen;

use DrevOps\PhpTui\Block\Field;
use DrevOps\PhpTui\Block\FieldType;
use DrevOps\PhpTui\Block\Markup;
use DrevOps\PhpTui\Block\Panel;
use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\Input\Key;
use DrevOps\PhpTui\Input\KeyName;
use DrevOps\PhpTui\Screen\ExternalEditor;
use DrevOps\PhpTui\Screen\Layout\PanelLayout;
use DrevOps\PhpTui\Screen\ScreenController;
use DrevOps\PhpTui\Screen\Streams;
use DrevOps\PhpTui\Terminal\Ansi;
use DrevOps\PhpTui\Terminal\Terminal;
use DrevOps\PhpTui\Terminal\TerminalControl;
use DrevOps\PhpTui\Testing\Background;
use DrevOps\PhpTui\Testing\BufferedTerminal;
use DrevOps\PhpTui\Testing\KeyEncoder;
use DrevOps\PhpTui\Testing\Resize;
use DrevOps\PhpTui\Testing\Tick;
use DrevOps\PhpTui\Tests\Traits\OpensSocketPairsTrait;
use DrevOps\PhpTui\Theme\DefaultTheme;
use DrevOps\PhpTui\Theme\DosTheme;
use DrevOps\PhpTui\Theme\Sgr;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests a session that hands the terminal to the shell and takes it back.
 */
#[CoversClass(ScreenController::class)]
#[Group('screen')]
final class DetachScreenTest extends TestCase {

  use OpensSocketPairsTrait;

  public function testSuspendContinuedInTheForegroundRedrawsAtOnce(): void {
    $scale = new Markup('scale', 'Crates weighed: 0');
    $controller = $this->controller($this->panel($scale))->live(static function (int $count) use ($scale): void {
      $scale->body('Crates weighed: ' . $count);
    });
    $terminal = new BufferedTerminal([$this->suspendKey(), new Tick()], 14, 60);

    $controller->run($terminal);

    $frames = $this->frames($terminal);
    $this->assertSame(1, $terminal->suspensions);
    $this->assertCount(3, $frames);
    $this->assertSame($frames[0], $frames[1]);
    $this->assertStringContainsString('Crates weighed: 1', $frames[2]);
  }

  public function testSessionInTheBackgroundKeepsTickingAndDrawsNothing(): void {
    $scale = new Markup('scale', 'Crates weighed: 0');
    $controller = $this->controller($this->panel($scale))->live(static function (int $count) use ($scale): void {
      $scale->body('Crates weighed: ' . $count);
    });
    $terminal = new BufferedTerminal([$this->suspendKey(), new Background(), new Tick(), new Tick(), KeyEncoder::encode(Key::named(KeyName::Up))], 14, 60);

    $controller->run($terminal);

    $frames = $this->frames($terminal);
    $this->assertCount(3, $frames);
    $this->assertStringContainsString('Crates weighed: 0', $frames[0]);
    // Re-attached after 2 ticks in the background, so the first frame back
    // shows the count as it stands and none of the counts in between.
    $this->assertStringContainsString('Crates weighed: 2', $frames[1]);
    $this->assertStringNotContainsString('Crates weighed: 1', implode("\n", $frames));
  }

  public function testWatchedStreamIsServedInTheBackground(): void {
    [$pipe, $child] = $this->socketPair();
    $terminal = new BufferedTerminal([$this->suspendKey(), new Background(), new Tick(), new Tick()], 14, 60);
    $calls = [];
    $streams = (new Streams())->watch($pipe, static function ($stream) use (&$calls, $terminal): void {
      $calls[] = [fread($stream, 64), $terminal->reclaim()];
    });
    $controller = $this->controller($this->panel(new Field('courier', 'Courier')))->streams($streams)->live(static function (int $count) use (&$calls, $child): void {
      $calls[] = 'tick ' . $count;

      if ($count === 1) {
        fwrite($child, 'Figs sorted');
      }
    });

    $controller->run($terminal);

    // The stream that wakes the detached session runs its callback there, and
    // the tick waits for its interval instead of running on the wake.
    $this->assertSame(['tick 1', ['Figs sorted', FALSE], 'tick 2'], $calls);
    $this->assertCount(2, $this->frames($terminal));

    fclose($pipe);
    fclose($child);
  }

  public function testScriptThatEndsInTheBackgroundReattachesBeforeTheSessionEnds(): void {
    $controller = $this->controller($this->panel(new Field('courier', 'Courier')));
    $terminal = new BufferedTerminal([$this->suspendKey(), new Background()], 14, 60);

    $controller->run($terminal);

    $this->assertCount(2, $this->frames($terminal));
  }

  public function testResizeWhileDetachedIsLaidOutOnReattach(): void {
    $widths = [];
    $controller = $this->controller($this->panel(new Field('courier', 'Courier')))->relayout(static function (int $columns) use (&$widths): DefaultTheme {
      $widths[] = $columns;

      return new DefaultTheme($columns, ['color' => FALSE]);
    });
    $terminal = new BufferedTerminal([$this->suspendKey(), new Background(), new Resize(40, 10)], 14, 60);

    $controller->run($terminal);

    $frames = $this->frames($terminal);
    $this->assertSame([40], $widths);
    $this->assertLessThanOrEqual(40, Ansi::blockWidth(explode("\n", $frames[1])));
    $this->assertLessThanOrEqual(10, count(explode("\n", $frames[1])));
  }

  public function testKeysBehindTheSuspendKeyAreDropped(): void {
    $controller = $this->controller($this->panel(new Field('courier', 'Courier')));
    $terminal = new BufferedTerminal(["\r", $this->suspendKey() . 'Kale', 'Fig', "\r"], 14, 60);

    $answers = $controller->run($terminal);

    $this->assertSame(1, $terminal->suspensions);
    $this->assertSame('Fig', $answers->value('courier'));
  }

  public function testSuspendKeyDoesNothingWithoutJobControl(): void {
    $controller = $this->controller($this->panel(new Field('courier', 'Courier')));
    $terminal = new BufferedTerminal([$this->suspendKey(), new Background(), new Tick()], 14, 60);
    $terminal->suspendable = FALSE;

    $controller->run($terminal);

    $this->assertSame(0, $terminal->suspensions);
    $this->assertCount(2, $this->frames($terminal));
  }

  public function testSessionThatEndsDetachedLeavesTheShellScreenAlone(): void {
    $controller = $this->controller($this->panel(new Field('courier', 'Courier')))->live(static function (int $count): never {
      throw new \RuntimeException('The scale jammed.');
    });
    $terminal = new BufferedTerminal([$this->suspendKey(), new Background(), new Tick()], 14, 60);

    try {
      $controller->run($terminal);
      $this->fail('The tick should have thrown.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('The scale jammed.', $exception->getMessage());
    }

    $this->assertFalse(str_ends_with($terminal->output(), TerminalControl::clear()));

    // The next session starts attached, however the last one ended.
    $next = new BufferedTerminal([], 14, 60);
    $controller->run($next);
    $this->assertCount(1, $this->frames($next));
  }

  public function testQueryTypedBeforeTheSuspendKeyWaitsForTheReattach(): void {
    $terminal = new BufferedTerminal(["\r", "\r", 'pot' . $this->suspendKey(), new Background(), new Tick(), KeyEncoder::encode(Key::named(KeyName::Down))], 16, 60);
    $attached = [];
    $form = Form::create('Order')->panel('New order', 'order', static function (PanelBuilder $p) use ($terminal, &$attached): void {
      $p->search('Vegetable', 'veg')->optionsFrom(static function (string $query) use ($terminal, &$attached): array {
        $attached[$query] = $terminal->reclaim();

        return ['potato' => 'Potato'];
      });
    });
    $controller = $this->controller($form->root());

    $controller->run($terminal);

    $this->assertSame(['' => TRUE, 'pot' => TRUE], $attached);
  }

  public function testReattachThatFailsStillRestoresTheTerminal(): void {
    $controller = $this->controller($this->panel(new Field('courier', 'Courier')))->relayout(static function (int $columns): DefaultTheme {
      throw new \RuntimeException('The bench would not fit.');
    });
    $terminal = new BufferedTerminal([$this->suspendKey(), new Background(), new Resize(40, 10)], 14, 60);

    try {
      $controller->run($terminal);
      $this->fail('The relayout should have thrown.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('The bench would not fit.', $exception->getMessage());
    }

    $this->assertTrue(str_ends_with($terminal->output(), TerminalControl::clear()));
  }

  public function testReattachKeepsTheBackgroundWash(): void {
    $controller = new ScreenController($this->panel(new Field('courier', 'Courier')), new DosTheme(60, ['color' => TRUE]));
    $terminal = new BufferedTerminal([$this->suspendKey()], 14, 60);

    $controller->run($terminal);

    $this->assertSame(1, $terminal->suspensions);
    $this->assertSame(Sgr::of(Sgr::OnBlue), $terminal->paintedBackground);
  }

  public function testExternalEditorDetachesAndReattachesTheSession(): void {
    $editor = new class() extends ExternalEditor {

      /**
       * The terminal each edit was given.
       *
       * @var list<\DrevOps\PhpTui\Terminal\Terminal|null>
       */
      public array $terminals = [];

      /**
       * {@inheritdoc}
       */
      #[\Override]
      public function isAvailable(): bool {
        return TRUE;
      }

      /**
       * {@inheritdoc}
       */
      #[\Override]
      public function edit(string $initial, ?Terminal $terminal = NULL): string {
        $this->terminals[] = $terminal;

        return $initial . ' at the bench';
      }

    };
    $notes = (new Field('notes', 'Packing notes', FieldType::Textarea))->default('Weighed')->externalEditor();
    $controller = new ScreenController($this->panel($notes), new DosTheme(60, ['color' => TRUE]), external_editor: $editor);
    $terminal = new BufferedTerminal(["\r", "\x05", "\t"], 14, 60);

    $answers = $controller->run($terminal);

    // The session takes the terminal away itself, so the editor is given none
    // to restore, and the wash is set up again once it returns.
    $this->assertSame([NULL], $editor->terminals);
    $this->assertSame('Weighed at the bench', $answers->value('notes'));
    $this->assertSame(Sgr::of(Sgr::OnBlue), $terminal->paintedBackground);
  }

  public function testExternalEditorThatThrowsStillReattaches(): void {
    $editor = new class() extends ExternalEditor {

      /**
       * {@inheritdoc}
       */
      #[\Override]
      public function isAvailable(): bool {
        return TRUE;
      }

      /**
       * {@inheritdoc}
       */
      #[\Override]
      public function edit(string $initial, ?Terminal $terminal = NULL): string {
        throw new \RuntimeException('The editor would not start.');
      }

    };
    $notes = (new Field('notes', 'Packing notes', FieldType::Textarea))->default('Weighed')->externalEditor();
    $controller = new ScreenController($this->panel($notes), new DefaultTheme(60, ['color' => FALSE]), external_editor: $editor);
    $terminal = new BufferedTerminal(["\r", "\x05"], 14, 60);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The editor would not start.');

    try {
      $controller->run($terminal);
    }
    finally {
      // Attached again, so the session's own teardown clears the screen.
      $this->assertTrue(str_ends_with($terminal->output(), TerminalControl::clear()));
    }
  }

  /**
   * The bytes of the suspend key.
   */
  protected function suspendKey(): string {
    return KeyEncoder::encode(Key::named(KeyName::Suspend));
  }

  /**
   * The frames a terminal was drawn, stripped of their escape sequences.
   *
   * @param \DrevOps\PhpTui\Testing\BufferedTerminal $terminal
   *   The terminal.
   *
   * @return list<string>
   *   The frames.
   */
  protected function frames(BufferedTerminal $terminal): array {
    /** @var non-empty-string $clear */
    $clear = TerminalControl::clear();

    return array_values(array_map(Ansi::strip(...), array_filter(explode($clear, $terminal->output()), static fn(string $frame): bool => $frame !== '')));
  }

  /**
   * A controller over a panel, drawn without colour.
   */
  protected function controller(Panel $panel): ScreenController {
    return new ScreenController($panel, new DefaultTheme(60, ['color' => FALSE]));
  }

  /**
   * A panel holding the given blocks.
   */
  protected function panel(Field|Markup ...$blocks): Panel {
    $panel = (new Panel('market', 'Market day'))->layout(new PanelLayout());

    foreach ($blocks as $block) {
      $panel->in('content')->add($block);
    }

    return $panel;
  }

}
