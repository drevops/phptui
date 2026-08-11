<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen\Layout;

use DrevOps\Tui\Block\Element\ChromeElementsInterface;
use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Capability\ScrollCapableTrait;
use DrevOps\Tui\Screen\Furniture;
use DrevOps\Tui\Screen\Region;
use DrevOps\Tui\Screen\Sizing;

/**
 * The sizing arithmetic every layout inherits, and the conventional names.
 *
 * A subclass declares an axis and its regions and inherits every line of this,
 * which is what makes writing a layout a matter of saying where things go
 * rather than working out how big they are.
 *
 * Sizing belongs here rather than on a region because a region cannot answer
 * how big it is - its siblings' fixed cells come off the top before the
 * remainder is divided, and only the layout sees them all.
 *
 * @package DrevOps\Tui\Screen\Layout
 */
abstract class AbstractLayout implements LayoutInterface {

  use ScrollCapableTrait;

  /**
   * The region a trail conventionally goes in.
   */
  protected const string HEADER = 'header';

  /**
   * The region a form conventionally goes in.
   */
  protected const string CONTENT = 'content';

  /**
   * The region the keys on offer conventionally go in.
   */
  protected const string FOOTER = 'footer';

  /**
   * The regions, keyed by name, in declaration order.
   *
   * @var array<string,\DrevOps\Tui\Screen\Region>
   */
  protected array $regions = [];

  /**
   * Construct a layout.
   *
   * @param \DrevOps\Tui\Screen\Axis $axis
   *   The direction its regions run.
   */
  public function __construct(protected Axis $axis) {
  }

  /**
   * {@inheritdoc}
   */
  public function axis(): Axis {
    return $this->axis;
  }

  /**
   * {@inheritdoc}
   */
  public function names(): array {
    return array_keys($this->regions);
  }

  /**
   * {@inheritdoc}
   */
  public function in(string $name): Region {
    if (!isset($this->regions[$name])) {
      throw new \InvalidArgumentException(sprintf('Unknown region "%s". This layout declares: %s.', $name, implode(', ', $this->names())));
    }

    return $this->regions[$name];
  }

  /**
   * {@inheritdoc}
   *
   * The conventional names, which is what makes them conventional: a layout
   * declaring a header gets the trail in it, one declaring none shows no trail.
   * The form itself is the exception - it has to be drawn somewhere, so a
   * layout that names no content region draws it in whichever it declared
   * first. Override to send a piece somewhere else, or to answer NULL and keep
   * it off the screen entirely.
   */
  public function furnishes(Furniture $piece): ?string {
    $conventional = match ($piece) {
      Furniture::Trail => self::HEADER,
      Furniture::Body => self::CONTENT,
      Furniture::Keys => self::FOOTER,
    };

    if (isset($this->regions[$conventional])) {
      return $conventional;
    }

    return $piece === Furniture::Body ? ($this->names()[0] ?? NULL) : NULL;
  }

  /**
   * {@inheritdoc}
   *
   * Sized lines come off the top, what remains is divided by the declared
   * shares, and any cell left over by the rounding goes to the last line taking
   * a share - so the sizes always add up to what was available. Regions sharing
   * a line take the same cells of the axis, because they sit beside each other
   * rather than one after the other.
   */
  public function arrange(int $available, array $measured = []): array {
    if ($this->regions === []) {
      return [];
    }

    $lines = $this->lines();
    $sizes = [];
    $shares = [];
    $taken = 0;

    foreach ($lines as $index => $line) {
      $share = $this->shared($line);

      if ($share > 0) {
        $shares[$index] = $share;
        $sizes[$index] = 0;

        continue;
      }

      $sizes[$index] = $this->demanded($line, $measured);
      $taken += $sizes[$index];
    }

    // The cells telling one line from the next belong to no region, so they are
    // spent before anything is apportioned - and only where there is a line to
    // tell apart, since a region holding nothing draws nothing to separate.
    $room = max(0, $available - $this->separator() * max(0, count($shares) + count(array_filter($sizes)) - 1));

    // A terminal too small for the sized lines alone is a real state, not an
    // error: they are trimmed in declaration order so the sizes still add up.
    // The trim is handed the whole of the space, because how many lines come
    // out of it is what says how many cells were owed to telling them apart.
    if ($taken > $room) {
      return $this->spread($lines, $this->trim($sizes, $available));
    }

    if ($shares === []) {
      return $this->spread($lines, $sizes);
    }

    $remainder = $room - $taken;
    $total = array_sum($shares);
    $spent = 0;

    foreach ($shares as $index => $share) {
      $sizes[$index] = intdiv($remainder * $share, $total);
      $spent += $sizes[$index];
    }

    $sizes[array_key_last($shares)] += $remainder - $spent;

    return $this->spread($lines, $sizes);
  }

  /**
   * {@inheritdoc}
   */
  public function natural(array $measured = []): int {
    $sizes = array_map(fn(array $line): int => $this->demanded($line, $measured), $this->lines());
    $total = $this->axis === Axis::Rows ? $this->separator() * max(0, count(array_filter($sizes)) - 1) : 0;

    foreach ($sizes as $held) {
      $total = $this->axis === Axis::Rows ? $total + $held : max($total, $held);
    }

    return $total;
  }

  /**
   * {@inheritdoc}
   *
   * One region to a line, which is what stacking regions means. An arrangement
   * that draws several of them side by side says so.
   */
  public function lines(): array {
    return array_map(static fn(string $name): array => [$name], $this->names());
  }

  /**
   * {@inheritdoc}
   *
   * A line holding one region gives it every cell across it.
   */
  public function share(int $available, int $count, ChromeElementsInterface $chrome): int {
    return $available;
  }

  /**
   * Declare a region.
   *
   * @param string $name
   *   The name a block addresses it by.
   *
   * @return \DrevOps\Tui\Screen\Region
   *   The region, for declaring its size, flow and scrolling.
   */
  protected function region(string $name): Region {
    if (isset($this->regions[$name])) {
      throw new \InvalidArgumentException(sprintf('Region "%s" is already declared on this layout.', $name));
    }

    return $this->regions[$name] = new Region($name);
  }

  /**
   * The cells telling one line of the axis from the next.
   *
   * @return int
   *   The cells; none runs the lines against each other, which is what a stack
   *   of regions does.
   */
  protected function separator(): int {
    return 0;
  }

  /**
   * The share a line takes of whatever the sized lines leave behind.
   *
   * @param list<string> $line
   *   The regions drawn on it.
   *
   * @return int
   *   The share, or 0 when every region on the line states a size instead.
   */
  protected function shared(array $line): int {
    $share = 0;

    foreach ($line as $name) {
      $region = $this->regions[$name];
      $share = $region->sizing() === Sizing::Flex ? max($share, $region->size()) : $share;
    }

    return $share;
  }

  /**
   * The cells a line asks for outright.
   *
   * @param list<string> $line
   *   The regions drawn on it.
   * @param array<string,int> $measured
   *   The cells each region's contents come to, keyed by name.
   *
   * @return int
   *   The cells: what the hungriest region on the line asks for, since they
   *   sit beside each other and the line is as deep as the deepest of them.
   */
  protected function demanded(array $line, array $measured): int {
    $held = 0;

    foreach ($line as $name) {
      $held = max($held, $this->regions[$name]->sizing() === Sizing::Fixed ? $this->regions[$name]->size() : ($measured[$name] ?? 0));
    }

    return $held;
  }

  /**
   * Give every region on a line the cells its line was granted.
   *
   * @param list<list<string>> $lines
   *   The regions drawn on each line.
   * @param array<int,int> $sizes
   *   The cells each line was granted, keyed by its place in them.
   *
   * @return array<string,int>
   *   The cells each region gets, keyed by name, in declaration order. Every
   *   line that draws something but the last of them carries the cell telling
   *   it from the next, so the air between two lines is a region's own last
   *   cell rather than a row belonging to nobody.
   */
  protected function spread(array $lines, array $sizes): array {
    $drawn = array_filter($sizes, static fn(int $size): bool => $size > 0);
    $last = array_key_last($drawn);
    $spread = [];

    foreach ($lines as $index => $line) {
      $size = $sizes[$index] ?? 0;
      $separated = $size > 0 && $index !== $last;

      foreach ($line as $name) {
        $spread[$name] = $size + ($separated ? $this->separator() : 0);
      }
    }

    return $spread;
  }

  /**
   * Cut sizes back to what is actually available, in declaration order.
   *
   * @param array<int,int> $sizes
   *   The sizes asked for.
   * @param int $available
   *   The cells there are, before anything is spent telling lines apart.
   *
   * @return array<int,int>
   *   The sizes granted.
   */
  protected function trim(array $sizes, int $available): array {
    $left = max(0, $available);
    $drawn = FALSE;

    foreach ($sizes as $index => $size) {
      // The cell telling one line from the next is owed only once a line has
      // been granted something to be told apart from, so the last cells go to
      // a line rather than to the air above one there is no room to draw.
      $room = max(0, $drawn ? $left - $this->separator() : $left);
      $sizes[$index] = min($size, $room);

      if ($sizes[$index] > 0) {
        $left = $room - $sizes[$index];
        $drawn = TRUE;
      }
    }

    return $sizes;
  }

}
