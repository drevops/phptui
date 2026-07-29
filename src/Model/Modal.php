<?php

declare(strict_types=1);

namespace DrevOps\Tui\Model;

/**
 * The presentation config that makes a panel open as a centered modal dialog.
 *
 * A panel carrying a Modal is not drilled into like an ordinary sub-panel: it
 * opens as a bordered box centered over the dimmed parent, collects its fields
 * (or just shows its description text) and is dismissed through its own
 * submit/cancel {@see Buttons}. Submit keeps the edits; cancel restores the
 * answers as they were when the dialog opened.
 *
 * @package DrevOps\Tui\Model
 */
final readonly class Modal {

  /**
   * Construct a modal config.
   *
   * @param \DrevOps\Tui\Model\Buttons $buttons
   *   The dialog's submit/cancel buttons.
   *
   * @throws \DrevOps\Tui\Model\FormException
   *   When the buttons are hidden.
   */
  public function __construct(
    public Buttons $buttons = new Buttons(),
  ) {
    // The buttons are a modal's only on-screen way out, so hiding them would
    // strand the dialog; the constructor rejects that rather than ignoring it.
    if (!$this->buttons->show) {
      throw new FormException('A modal dialog must show its buttons.');
    }
  }

}
