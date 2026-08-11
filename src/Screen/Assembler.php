<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

use DrevOps\Tui\Block\Actions;
use DrevOps\Tui\Block\Breadcrumb;
use DrevOps\Tui\Block\Buttons;
use DrevOps\Tui\Block\Legend;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\FormException;
use DrevOps\Tui\Screen\Layout\LayoutManager;
use DrevOps\Tui\Theme\BorderSide;
use DrevOps\Tui\Translation\Translator;

/**
 * Puts the standard furniture into a screen.
 *
 * A layout declares regions and names no block, because one carrying content
 * opinions is a layout exactly one form can use. Something still has to decide
 * that a breadcrumb belongs in the header, and it cannot be the layout without
 * costing it every reuse - nor a separate object bolted on, since that would
 * have to assume a header exists and a two-column layout has none.
 *
 * So it is assembled here, alongside the form's other defaults, against the
 * region each piece belongs in - which the layout states, rather than being
 * guessed at from the names it happens to have used.
 *
 * Everything it places goes into the screen's own regions. One declaration
 * outlives the session driving it, so a session that wrote its furniture into
 * the panel tree would hand the next one a form nobody declared.
 *
 * @package DrevOps\Tui\Screen
 */
final class Assembler {

  /**
   * Build a screen around a panel.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel filling the body region.
   * @param string $layout
   *   The layout the screen is arranged by.
   *
   * @return \DrevOps\Tui\Screen\Screen
   *   The screen.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the layout keeps no place for the form itself.
   */
  public function assemble(Panel $panel, string $layout = 'default'): Screen {
    $arranged = LayoutManager::create($layout);
    $screen = (new Screen())->layout($arranged);

    $trail = $arranged->furnishes(Furniture::Trail);

    // A layout keeping no place for a piece simply shows none of it, rather
    // than being refused for not being the default.
    if ($trail !== NULL) {
      $screen->in($trail)->add(new Breadcrumb($panel->title()));
    }

    $body = $arranged->furnishes(Furniture::Body);

    if ($body === NULL) {
      throw new FormException(sprintf('Layout "%s" keeps no place for the form itself, so there is nowhere to draw it.', $arranged::class));
    }

    $region = $screen->in($body)->add($panel->enter());
    $declared = $panel->currentButtons();

    // Beside the panel rather than among its rows: they end the form rather
    // than the panel the cursor happens to be in, so going into a nested one
    // leaves them behind exactly as it leaves that panel's siblings behind.
    if ($declared->show) {
      $region->add($this->actions($declared));
    }

    $keys = $arranged->furnishes(Furniture::Keys);

    if ($keys !== NULL) {
      $screen->in($keys)->add($this->legend($panel));
    }

    return $screen;
  }

  /**
   * The buttons that end a form, or close a panel drawn over it.
   *
   * @param \DrevOps\Tui\Block\Buttons $declared
   *   The pair as the panel they close declares it.
   *
   * @return \DrevOps\Tui\Block\Actions
   *   The actions.
   */
  public function actions(Buttons $declared = new Buttons()): Actions {
    // A rule above and below rather than a box: the buttons are a compartment
    // of the frame, and sides are what says so.
    return (new Actions())
      ->border(BorderSide::TOP | BorderSide::BOTTOM)
      ->action(Ending::Submit->value, Translator::t($declared->submitLabel))
      ->action(Ending::Cancel->value, Translator::t($declared->cancelLabel));
  }

  /**
   * The keys a panel offers before anything in it is open.
   *
   * Read out of the panel's own bindings rather than written out beside them,
   * so a retuned key changes the line that advertises it too.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel filling the body region.
   *
   * @return \DrevOps\Tui\Block\Legend
   *   The legend.
   */
  protected function legend(Panel $panel): Legend {
    return (new Legend())->advertise($panel->bindings(), ...$panel->hints());
  }

}
