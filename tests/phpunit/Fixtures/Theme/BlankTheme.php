<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Fixtures\Theme;

use DrevOps\PhpTui\Input\KeyName;
use DrevOps\PhpTui\Theme\ThemeInterface;

/**
 * Test fixture: a theme by type that answers for no element at all.
 *
 * It carries the two theme-wide methods and stops there, so it is a theme
 * every type check accepts and nothing on a screen can be drawn with.
 *
 * @package DrevOps\PhpTui\Tests\Fixtures\Theme
 */
class BlankTheme implements ThemeInterface {

  /**
   * Construct a theme.
   *
   * @param int $width
   *   The columns available for the content it lays out.
   * @param array<string,mixed> $options
   *   Display options keyed by name.
   */
  public function __construct(protected int $width = 0, protected array $options = []) {
  }

  /**
   * {@inheritdoc}
   */
  public function contentWidth(): int {
    return $this->width;
  }

  /**
   * {@inheritdoc}
   */
  public function keyGlyph(KeyName|string $key): string {
    return $key instanceof KeyName ? $key->name : $key;
  }

}
