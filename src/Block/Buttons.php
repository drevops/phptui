<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

/**
 * The submit/cancel action pair that closes a form or a modal.
 *
 * Shared chrome: the outermost panel carries one for the row that ends the
 * form, and a panel drawn over what is behind it carries one for the row that
 * closes the dialog. The labels are configurable; a dialog always shows its
 * pair, because it is the only way out of one, while a form may hide it.
 *
 * @package DrevOps\Tui\Block
 */
final readonly class Buttons {

  /**
   * Construct a button pair.
   *
   * @param bool $show
   *   Whether the pair is shown.
   * @param string $submitLabel
   *   The submit (accept) button label.
   * @param string $cancelLabel
   *   The cancel (dismiss) button label.
   */
  public function __construct(
    public bool $show = TRUE,
    public string $submitLabel = 'Submit',
    public string $cancelLabel = 'Cancel',
  ) {
  }

}
