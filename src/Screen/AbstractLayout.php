<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

/**
 * An arrangement of named regions along one axis.
 *
 * A layout declares regions and works out how big each one is. It never names a
 * block: a layout carrying content opinions is a layout exactly one form can
 * use, and reuse is the whole reason it is a class of its own.
 *
 * Sizing belongs here rather than on a region because a region cannot answer
 * how big it is - its siblings' fixed cells come off the top before the
 * remainder is divided, and only the layout sees them all.
 *
 * @package DrevOps\Tui\Screen
 */
abstract class AbstractLayout {

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
  public function __construct(
    protected Axis $axis,
  ) {
  }

  /**
   * The direction this layout's regions run.
   *
   * @return \DrevOps\Tui\Screen\Axis
   *   The axis.
   */
  public function axis(): Axis {
    return $this->axis;
  }

  /**
   * The names of the regions this layout declares, in declaration order.
   *
   * @return list<string>
   *   The names.
   */
  public function names(): array {
    return array_keys($this->regions);
  }

  /**
   * The region of a given name.
   *
   * @param string $name
   *   The region name.
   *
   * @return \DrevOps\Tui\Screen\Region
   *   The region.
   */
  public function in(string $name): Region {
    if (!isset($this->regions[$name])) {
      throw new \InvalidArgumentException(sprintf('Unknown region "%s". This layout declares: %s.', $name, implode(', ', $this->names())));
    }

    return $this->regions[$name];
  }

  /**
   * Work out how much of the axis each region gets.
   *
   * Fixed regions come off the top, what remains is divided by the declared
   * shares, and any cell left over by the rounding goes to the last region
   * taking a share - so the sizes always add up to what was available.
   *
   * @param int $available
   *   The cells to divide.
   *
   * @return array<string,int>
   *   The cells each region gets, keyed by name.
   */
  public function arrange(int $available): array {
    if ($this->regions === []) {
      return [];
    }

    $sizes = [];
    $shares = [];
    $taken = 0;

    foreach ($this->regions as $name => $region) {
      $fixed = $region->fixedSize();

      if ($fixed === NULL) {
        $shares[$name] = (int) $region->flexShare();
        $sizes[$name] = 0;

        continue;
      }

      $sizes[$name] = $fixed;
      $taken += $fixed;
    }

    // A terminal too small for the fixed regions alone is a real state, not an
    // error: they are trimmed in declaration order so the sizes still add up.
    if ($taken > $available) {
      return $this->trim($sizes, $available);
    }

    if ($shares === []) {
      return $sizes;
    }

    $remainder = $available - $taken;
    $total = array_sum($shares);
    $spent = 0;

    foreach ($shares as $name => $share) {
      $sizes[$name] = intdiv($remainder * $share, $total);
      $spent += $sizes[$name];
    }

    $sizes[array_key_last($shares)] += $remainder - $spent;

    return $sizes;
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
   * Cut sizes back to what is actually available, in declaration order.
   *
   * @param array<string,int> $sizes
   *   The sizes asked for.
   * @param int $available
   *   The cells there are.
   *
   * @return array<string,int>
   *   The sizes granted.
   */
  protected function trim(array $sizes, int $available): array {
    $left = max(0, $available);

    foreach ($sizes as $name => $size) {
      $sizes[$name] = min($size, $left);
      $left -= $sizes[$name];
    }

    return $sizes;
  }

}
