<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

/**
 * A declared block tree, read as a flat list.
 *
 * A panel knows what it holds and which panels hang from it, and nothing more:
 * flattening the two into the order a form is declared in is a question about
 * the whole tree rather than about any one panel. It lives here so the headless
 * collection and the machine-readable descriptions read one order rather than
 * each walking their own.
 *
 * The order is declaration order: a panel's own rows first, then everything
 * beneath it, panel by panel.
 *
 * @package DrevOps\Tui\Block
 */
final class Tree {

  /**
   * Every field a panel and the panels beneath it hold.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to walk.
   *
   * @return list<\DrevOps\Tui\Block\Field>
   *   The fields, in declaration order.
   */
  public static function fields(Panel $panel): array {
    $fields = $panel->fields();

    foreach ($panel->children() as $child) {
      $fields = [...$fields, ...self::fields($child)];
    }

    return $fields;
  }

  /**
   * Every panel beneath a panel, the panel itself included.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to walk.
   *
   * @return list<\DrevOps\Tui\Block\Panel>
   *   The panels, in declaration order, outermost first.
   */
  public static function panels(Panel $panel): array {
    $panels = [$panel];

    foreach ($panel->children() as $child) {
      $panels = [...$panels, ...self::panels($child)];
    }

    return $panels;
  }

  /**
   * The ids of every row a panel and the panels beneath it hold.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to walk.
   *
   * @return list<string>
   *   The ids, in declaration order.
   */
  public static function ids(Panel $panel): array {
    $ids = $panel->ids();

    foreach ($panel->children() as $child) {
      $ids = [...$ids, ...self::ids($child)];
    }

    return $ids;
  }

}
