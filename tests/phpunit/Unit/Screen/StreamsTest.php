<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Screen;

use DrevOps\PhpTui\Screen\Streams;
use DrevOps\PhpTui\Tests\Traits\OpensSocketPairsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the registry of streams a session wakes on.
 */
#[CoversClass(Streams::class)]
#[Group('screen')]
final class StreamsTest extends TestCase {

  use OpensSocketPairsTrait;

  public function testWatchedStreamsAreKeyedByResourceId(): void {
    [$pears, $pears_child] = $this->socketPair();
    [$plums, $plums_child] = $this->socketPair();

    $streams = (new Streams())->watch($pears, $this->ignore(...))->watch($plums, $this->ignore(...));

    $this->assertSame([get_resource_id($pears) => $pears, get_resource_id($plums) => $plums], $streams->open());

    $streams->unwatch($pears);

    $this->assertSame([get_resource_id($plums) => $plums], $streams->open());

    $this->close($pears, $pears_child, $plums, $plums_child);
  }

  public function testWatchingAgainReplacesTheCallback(): void {
    [$pipe, $child] = $this->socketPair();
    $calls = [];

    $streams = (new Streams())
      ->watch($pipe, static function () use (&$calls): void {
        $calls[] = 'first';
      })
      ->watch($pipe, static function () use (&$calls): void {
        $calls[] = 'second';
      });

    $streams->dispatch([$pipe]);

    $this->assertSame(['second'], $calls);
    $this->assertCount(1, $streams->open());

    $this->close($pipe, $child);
  }

  public function testDispatchRunsTheCallbackWithTheStream(): void {
    [$pipe, $child] = $this->socketPair();
    fwrite($child, 'Apples sorted');
    $read = [];

    $streams = (new Streams())->watch($pipe, static function ($stream) use (&$read): void {
      $read[] = fread($stream, 64);
    });

    $streams->dispatch([$pipe]);

    $this->assertSame(['Apples sorted'], $read);
    $this->assertSame([get_resource_id($pipe) => $pipe], $streams->open());

    $this->close($pipe, $child);
  }

  public function testDispatchSkipsStreamsThatAreNotWatched(): void {
    [$pipe, $child] = $this->socketPair();

    $streams = new Streams();
    $streams->dispatch([$pipe]);

    $this->assertSame([], $streams->open());

    $this->close($pipe, $child);
  }

  public function testCallbackCanUnwatchStreamLaterInTheSamePass(): void {
    [$pears, $pears_child] = $this->socketPair();
    [$plums, $plums_child] = $this->socketPair();
    $calls = [];
    $streams = new Streams();

    $streams
      ->watch($pears, static function () use (&$calls, $streams, $plums): void {
        $calls[] = 'pears';
        $streams->unwatch($plums);
      })
      ->watch($plums, static function () use (&$calls): void {
        $calls[] = 'plums';
      });

    $streams->dispatch([$pears, $plums]);

    $this->assertSame(['pears'], $calls);
    $this->assertSame([get_resource_id($pears) => $pears], $streams->open());

    $this->close($pears, $pears_child, $plums, $plums_child);
  }

  public function testStreamAtItsEndIsUnwatchedAfterItsCallback(): void {
    [$pipe, $child] = $this->socketPair();
    fwrite($child, 'Last crate');
    fclose($child);
    $read = [];

    $streams = (new Streams())->watch($pipe, static function ($stream) use (&$read): void {
      $read[] = stream_get_contents($stream);
    });

    $streams->dispatch([$pipe]);

    $this->assertSame(['Last crate'], $read);
    $this->assertSame([], $streams->open());

    fclose($pipe);
  }

  public function testStreamClosedByItsCallbackIsUnwatched(): void {
    [$pipe, $child] = $this->socketPair();

    $streams = (new Streams())->watch($pipe, static function ($stream): void {
      fclose($stream);
    });

    $streams->dispatch([$pipe]);

    $this->assertSame([], $streams->open());

    fclose($child);
  }

  public function testStreamClosedElsewhereIsDropped(): void {
    [$pipe, $child] = $this->socketPair();

    $streams = (new Streams())->watch($pipe, $this->ignore(...));
    fclose($pipe);

    $this->assertSame([], $streams->open());

    fclose($child);
  }

  public function testClosedStreamCanBeUnwatched(): void {
    [$pipe, $child] = $this->socketPair();

    $streams = (new Streams())->watch($pipe, $this->ignore(...));
    fclose($pipe);
    $streams->unwatch($pipe);

    $this->assertSame([], $streams->open());

    fclose($child);
  }

  #[DataProvider('dataProviderWatchRefusesWhatIsNotAnOpenStream')]
  public function testWatchRefusesWhatIsNotAnOpenStream(string $kind): void {
    $value = match ($kind) {
      'closed' => $this->closedStream(),
      'process' => proc_open([PHP_BINARY, '-r', ''], [], $pipes),
      default => 'basket',
    };

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('A watched stream must be an open stream resource.');

    try {
      (new Streams())->watch($value, $this->ignore(...));
    }
    finally {
      if (is_resource($value)) {
        proc_close($value);
      }
    }
  }

  public static function dataProviderWatchRefusesWhatIsNotAnOpenStream(): \Iterator {
    yield 'a string' => ['string'];
    yield 'a closed stream' => ['closed'];
    yield 'a process rather than a stream' => ['process'];
  }

  public function testUnwatchRefusesWhatIsNotResource(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('An unwatched stream must be a stream resource.');

    (new Streams())->unwatch('basket');
  }

  /**
   * A callback that reads nothing.
   *
   * @param resource $stream
   *   The stream with output.
   */
  protected function ignore($stream): void {
  }

  /**
   * A stream that has already been closed.
   *
   * @return resource
   *   The closed stream.
   */
  protected function closedStream() {
    [$pipe, $child] = $this->socketPair();
    fclose($pipe);
    fclose($child);

    return $pipe;
  }

  /**
   * Close every stream given.
   *
   * @param resource ...$streams
   *   The streams.
   */
  protected function close(...$streams): void {
    foreach ($streams as $stream) {
      fclose($stream);
    }
  }

}
