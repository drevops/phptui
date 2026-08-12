<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Fixtures\Theme;

use DrevOps\PhpTui\Theme\DefaultTheme;

/**
 * Test fixture: a theme declaring a custom "accent" display option.
 *
 * The option is read back through a method named for the option rather than
 * for the hue, so declaring one does not collide with the palette a theme
 * paints from.
 *
 * @package DrevOps\PhpTui\Tests\Fixtures\Theme
 */
class AccentOptionTheme extends DefaultTheme {

  /**
   * Construct at a fixed width, taking options only.
   *
   * @param array<string,mixed> $options
   *   The theme options.
   */
  public function __construct(array $options = []) {
    parent::__construct(40, $options);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function optionSchema(): array {
    return ['accent' => ['cool', 'warm', 'mono']] + parent::optionSchema();
  }

  /**
   * The value of the custom "accent" option.
   *
   * @return string
   *   The accent, or "cool" when unset.
   */
  public function accentOption(): string {
    return $this->option('accent', 'cool');
  }

}
