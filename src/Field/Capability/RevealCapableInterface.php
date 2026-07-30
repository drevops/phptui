<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field\Capability;

/**
 * A field that can toggle the display of concealed content.
 *
 * Revealing only changes what is drawn - a masked value, hidden entries -
 * never the collected value itself.
 *
 * @package DrevOps\Tui\Field\Capability
 */
interface RevealCapableInterface {

  /**
   * Toggle (or cycle) the reveal state of the concealed content.
   */
  public function toggleReveal(): void;

}
