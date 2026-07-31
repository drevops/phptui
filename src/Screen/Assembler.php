<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

use DrevOps\Tui\Block\Actions;
use DrevOps\Tui\Block\Breadcrumb;
use DrevOps\Tui\Block\Legend;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Screen\Layout\LayoutManager;

/**
 * Puts the standard furniture into a screen.
 *
 * A layout declares regions and names no block, because one carrying content
 * opinions is a layout exactly one form can use. Something still has to decide
 * that a breadcrumb belongs in the header, and it cannot be the layout without
 * costing it every reuse - nor a separate object bolted on, since that would
 * have to assume a header exists and a two-column layout has none.
 *
 * So it is assembled here, alongside the form's other defaults.
 *
 * @package DrevOps\Tui\Screen
 */
final class Assembler {

  /**
   * Build a screen around a panel.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel filling the content region.
   * @param string $layout
   *   The layout the screen is arranged by.
   *
   * @return \DrevOps\Tui\Screen\Screen
   *   The screen.
   */
  public function assemble(Panel $panel, string $layout = 'default'): Screen {
    $arranged = LayoutManager::create($layout);
    $screen = (new Screen())->layout($arranged);
    $names = $arranged->names();

    // Furniture goes only where the named layout keeps a place for it: a
    // layout with no header simply shows no trail, rather than being refused
    // for not being the default.
    if (in_array('header', $names, TRUE)) {
      $screen->in('header')->add(new Breadcrumb($panel->title()));
    }

    $screen->in(in_array('content', $names, TRUE) ? 'content' : $names[0])->add($panel->enter());

    if (in_array('footer', $names, TRUE)) {
      $screen->in('footer')->add($this->legend($panel));
    }

    return $screen;
  }

  /**
   * The keys a panel offers before anything in it is open.
   *
   * Read out of the panel's own bindings rather than written out beside them,
   * so a retuned key changes the line that advertises it too.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel filling the content region.
   *
   * @return \DrevOps\Tui\Block\Legend
   *   The legend.
   */
  protected function legend(Panel $panel): Legend {
    return (new Legend())->advertise($panel->bindings(), ...$panel->hints());
  }

  /**
   * The buttons that end a form.
   *
   * @return \DrevOps\Tui\Block\Actions
   *   The actions.
   */
  public function actions(): Actions {
    return (new Actions())->action('submit', 'Submit')->action('cancel', 'Cancel');
  }

}
