<?php

declare(strict_types=1);

namespace DrevOps\Tui\Model;

/**
 * A panel: an ordered group of fields and nested sub-panels.
 *
 * The definition is immutable except for its preload hook, which is resolved
 * once, on first open, and then cleared - so `$preload` is the only mutable
 * state.
 *
 * @package DrevOps\Tui\Model
 */
final class Panel {

  /**
   * Construct a panel.
   *
   * @param string $id
   *   The unique panel id.
   * @param string $title
   *   The panel title.
   * @param string $description
   *   The panel description.
   * @param \DrevOps\Tui\Model\Field[] $fields
   *   Ordered fields in this panel.
   * @param \DrevOps\Tui\Model\Panel[] $panels
   *   Ordered nested sub-panels.
   * @param \DrevOps\Tui\Model\Modal|null $modal
   *   The modal presentation config when this panel opens as a centered dialog
   *   over the parent, or NULL for an ordinary drill-in panel.
   * @param list<int> $layout
   *   The sub-panel grid: one entry per visual row naming how many sub-panels
   *   sit side by side in it, consumed in declaration order. Empty renders the
   *   sub-panels as today's row list.
   * @param \Closure|null $preload
   *   An `fn(): void` run once before the panel first opens, or NULL for none.
   *   Resolved in the same pass as the fields' option loaders - the panel reads
   *   as loading until it returns - then cleared so it runs only once.
   */
  public function __construct(
    public readonly string $id,
    public readonly string $title,
    public readonly string $description,
    public readonly array $fields = [],
    public readonly array $panels = [],
    public readonly ?Modal $modal = NULL,
    public readonly array $layout = [],
    public ?\Closure $preload = NULL,
  ) {
  }

  /**
   * The number of navigable items: fields plus sub-panels.
   *
   * @return int
   *   The item count.
   */
  public function itemCount(): int {
    return count($this->fields) + count($this->panels);
  }

  /**
   * Whether this panel opens as a modal dialog rather than a drill-in panel.
   *
   * @return bool
   *   TRUE when the panel carries a modal config.
   */
  public function isModal(): bool {
    return $this->modal instanceof Modal;
  }

}
