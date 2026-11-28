<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Screen;

use DrevOps\PhpTui\Block\Field;
use DrevOps\PhpTui\Block\Markup;
use DrevOps\PhpTui\Block\Panel;
use DrevOps\PhpTui\Input\Key;
use DrevOps\PhpTui\Input\KeyName;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Screen\Layout\PanelLayout;
use DrevOps\PhpTui\Screen\ScreenController;
use DrevOps\PhpTui\Terminal\Ansi;
use DrevOps\PhpTui\Terminal\Terminal;
use DrevOps\PhpTui\Terminal\TerminalControl;
use DrevOps\PhpTui\Testing\BufferedTerminal;
use DrevOps\PhpTui\Testing\KeyEncoder;
use DrevOps\PhpTui\Testing\Resize;
use DrevOps\PhpTui\Testing\ScreenTester;
use DrevOps\PhpTui\Testing\Tick;
use DrevOps\PhpTui\Theme\DefaultTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests a live screen: redrawn on a timer and on a resize, not only on a key.
 */
#[CoversClass(ScreenController::class)]
#[Group('screen')]
final class LiveScreenTest extends TestCase {

  public function testFrameChangesWithNoKeypress(): void {
    $scale = new Markup('scale', 'Crates weighed: 0');
    $tester = $this->tester($this->panel($scale))->live(static function (int $count) use ($scale): void {
      $scale->body('Crates weighed: ' . $count);
    });

    $tester->run(new Tick(), new Tick());

    $this->assertCount(3, $tester->frames());
    $this->assertStringContainsString('Crates weighed: 0', $tester->frame(0));
    $this->assertStringContainsString('Crates weighed: 1', $tester->frame(1));
    $this->assertStringContainsString('Crates weighed: 2', $tester->frame(2));
  }

  public function testTickIsCountedFromOne(): void {
    $counts = [];
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')))->live(static function (int $count) use (&$counts): void {
      $counts[] = $count;
    });

    $tester->run(new Tick(), new Tick(), new Tick());

    $this->assertSame([1, 2, 3], $counts);
  }

  public function testTickThatChangesNothingDrawsNothing(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')))->live(static function (int $count): void {
    });

    $tester->run(new Tick(), new Tick());

    $this->assertCount(1, $tester->frames());
  }

  public function testKeyIsDrawnEvenWhenItChangesNothing(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')))->live(static function (int $count): void {
    });

    $tester->run(new Tick(), Key::named(KeyName::Up));

    $this->assertCount(2, $tester->frames());
  }

  public function testKeysStillReachTheFormBetweenTicks(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')))->live(static function (int $count): void {
    });

    $answers = $tester->run(new Tick(), Key::named(KeyName::Enter), 'Coast', new Tick(), Key::named(KeyName::Enter));

    $this->assertSame('Coast', $answers->value('courier'));
  }

  public function testInterruptAbortsLiveSession(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')))->live(static function (int $count): void {
    });

    $this->expectException(InterruptException::class);

    $tester->run(new Tick(), Key::named(KeyName::Interrupt));
  }

  public function testSessionThatIsNotLiveSkipsTicks(): void {
    $courier = new Field('courier', 'Courier');
    $weight = new Field('weight', 'Basket weight');
    $tester = $this->tester($this->panel($courier, $weight));

    $tester->run(new Tick(), Key::named(KeyName::Down), new Tick());

    $this->assertCount(2, $tester->frames());
    $this->assertTrue($weight->isFocused());
  }

  #[DataProvider('dataProviderResizeRedrawsAtTheNewSize')]
  public function testResizeRedrawsAtTheNewSize(int $columns, int $rows, int $width, int $height): void {
    $tester = $this->tester($this->panel(...$this->crates(20)))->live(static function (int $count): void {
    });

    $tester->run(new Resize($columns, $rows));

    $this->assertCount(2, $tester->frames());
    $this->assertSame([60, 14], $this->measure($tester->frame(0)));
    $this->assertSame([$width, $height], $this->measure($tester->frame(1)));
  }

  /**
   * Data provider for testResizeRedrawsAtTheNewSize().
   *
   * @return \Iterator<string, array{int, int, int, int}>
   *   The new terminal columns and rows, and the widest line and the line
   *   count of the frame drawn after the resize.
   */
  public static function dataProviderResizeRedrawsAtTheNewSize(): \Iterator {
    yield 'narrower' => [40, 14, 40, 14];
    yield 'shorter' => [60, 8, 60, 8];
    yield 'narrower and shorter' => [40, 8, 40, 8];
  }

  public function testResizeToTheSameSizeRedrawsNothing(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')))->live(static function (int $count): void {
    });

    $tester->run(new Resize(60, 14));

    $this->assertCount(1, $tester->frames());
  }

  public function testResizeKeepsTheWidthOfThemeWithNothingToRebuildIt(): void {
    $controller = (new ScreenController($this->panel(...$this->crates(20)), new DefaultTheme(60, ['color' => FALSE])))->live(static function (int $count): void {
    });
    $terminal = new BufferedTerminal([new Resize(40, 8)], 14, 60);

    $controller->run($terminal);

    $frames = $this->frames($terminal);

    [$width, $height] = $this->measure($frames[0]);

    $this->assertCount(2, $frames);
    $this->assertSame(14, $height);
    // The height follows the terminal; the width stays the theme's own.
    $this->assertSame([$width, 8], $this->measure($frames[1]));
    $this->assertGreaterThan(40, $width);
  }

  public function testResizeRebuildsTheThemeForTheNewWidth(): void {
    $widths = [];
    $controller = (new ScreenController($this->panel(new Field('courier', 'Courier')), new DefaultTheme(60, ['color' => FALSE])))
      ->live(static function (int $count): void {
      })
      ->relayout(static function (int $columns) use (&$widths): DefaultTheme {
        $widths[] = $columns;

        return new DefaultTheme($columns, ['color' => FALSE]);
      });

    $controller->run(new BufferedTerminal([new Resize(40, 14), new Tick(), new Resize(50, 14)], 14, 60));

    $this->assertSame([40, 50], $widths);
  }

  public function testResizeIsLaidOutWhenKeyIsAlreadyWaiting(): void {
    $ticks = [];
    $widths = [];
    $courier = new Field('courier', 'Courier');
    $weight = new Field('weight', 'Basket weight');
    $controller = (new ScreenController($this->panel($courier, $weight), new DefaultTheme(60, ['color' => FALSE])))
      ->live(static function (int $count) use (&$ticks): void {
        $ticks[] = $count;
      })
      ->relayout(static function (int $columns) use (&$widths): DefaultTheme {
        $widths[] = $columns;

        return new DefaultTheme($columns, ['color' => FALSE]);
      });

    // A key is ready on every wait, and the size changed before the first.
    $terminal = new class(KeyEncoder::encode(Key::named(KeyName::Down))) extends Terminal {

      /**
       * Whether the size changed since remeasure() last ran.
       */
      protected bool $resized = TRUE;

      /**
       * Construct a terminal holding one key.
       *
       * @param string $key
       *   The bytes of the key it delivers.
       */
      public function __construct(protected string $key) {
        parent::__construct(fopen('php://memory', 'r+'), fopen('php://memory', 'r+'));
      }

      /**
       * {@inheritdoc}
       */
      #[\Override]
      public function setup(?string $background = NULL): void {
      }

      /**
       * {@inheritdoc}
       */
      #[\Override]
      public function restore(): void {
      }

      /**
       * {@inheritdoc}
       */
      #[\Override]
      public function wait(int $microseconds): bool {
        return TRUE;
      }

      /**
       * {@inheritdoc}
       */
      #[\Override]
      public function read(int $bytes = 32): string {
        $key = $this->key;
        $this->key = '';

        return $key;
      }

      /**
       * {@inheritdoc}
       */
      #[\Override]
      public function remeasure(): bool {
        $resized = $this->resized;
        $this->resized = FALSE;

        return $resized;
      }

      /**
       * {@inheritdoc}
       */
      #[\Override]
      public function rows(): int {
        return 14;
      }

      /**
       * {@inheritdoc}
       */
      #[\Override]
      public function columns(): int {
        return 40;
      }

    };

    $controller->run($terminal);

    $this->assertSame([40], $widths);
    $this->assertSame([], $ticks);
    $this->assertTrue($weight->isFocused());
  }

  public function testIntervalBelowOneMillisecondIsRefused(): void {
    $controller = new ScreenController($this->panel(new Field('courier', 'Courier')), new DefaultTheme(60, ['color' => FALSE]));

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The tick interval must be at least 1 millisecond.');

    $controller->live(static function (int $count): void {
    }, 0);
  }

  public function testIntervalOfOneMillisecondIsAccepted(): void {
    $this->assertSame(1, ScreenController::validInterval(1));
  }

  /**
   * The widest line and the line count of a frame.
   *
   * @param string $frame
   *   The frame, stripped of its escape sequences.
   *
   * @return array{int, int}
   *   The columns and the rows.
   */
  protected function measure(string $frame): array {
    $lines = explode("\n", $frame);

    return [Ansi::blockWidth($lines), count($lines)];
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
   * A tester over a panel, sized so a frame reads the same everywhere.
   */
  protected function tester(Panel $panel): ScreenTester {
    return (new ScreenTester($panel))->rows(14)->cols(60);
  }

  /**
   * More rows than any frame here has room for.
   *
   * @return list<\DrevOps\PhpTui\Block\Field>
   *   The fields.
   */
  protected function crates(int $count): array {
    $fields = [];

    for ($crate = 1; $crate <= $count; $crate++) {
      $fields[] = (new Field('crate_' . $crate, 'Crate ' . $crate))->default('Apples');
    }

    return $fields;
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
