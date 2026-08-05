<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Traits;

use DrevOps\Tui\Block\Option;
use DrevOps\Tui\Block\OptionKind;

/**
 * Provides a choice-list fixture mixing every option kind.
 */
trait MixedOptionsTrait {

  /**
   * A list mixing selectable options with a heading, separator and disabled.
   *
   * @return list<\DrevOps\Tui\Block\Option>
   *   The option rows: Apple, a heading, Banana, a separator, a disabled
   *   Cherry and Date.
   */
  protected function mixedOptions(): array {
    return [
      new Option('a', 'Apple'),
      new Option('', 'Fruits', '', OptionKind::Heading),
      new Option('b', 'Banana'),
      new Option('', '', '', OptionKind::Separator),
      new Option('c', 'Cherry', '', OptionKind::Option, TRUE, 'out of stock'),
      new Option('d', 'Date'),
    ];
  }

}
