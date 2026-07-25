<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Widget\Capability\OptionsCapableInterface;
use DrevOps\Tui\Widget\Capability\PagingCapableInterface;
use DrevOps\Tui\Widget\Capability\PagingCapableTrait;

/**
 * A ranking list: pick up the highlighted item and move it to reorder.
 *
 * Up and Down move the highlight; Space picks the highlighted item up and,
 * while it is held, Up and Down carry it through the list; Space (or Enter)
 * drops it. The widget returns every item in its final order - a permutation
 * of the options, never a subset.
 *
 * @package DrevOps\Tui\Widget
 */
class ReorderWidget extends AbstractWidget implements OptionsCapableInterface, PagingCapableInterface {

  use PagingCapableTrait;

  /**
   * The items in their current arrangement.
   *
   * @var list<\DrevOps\Tui\Model\Option>
   */
  protected array $items;

  /**
   * The highlighted position in the arrangement.
   */
  protected int $cursor = 0;

  /**
   * Whether the highlighted item is held and moves with the cursor.
   */
  protected bool $grabbed = FALSE;

  /**
   * Construct a reorder widget.
   *
   * @param array<int|string,\DrevOps\Tui\Model\Option|string> $options
   *   The items to rank, in display order - a list of options or the
   *   value => label shorthand map.
   * @param list<string> $default
   *   The initial order; values it omits are appended in declared order and
   *   unknown values are ignored, so the arrangement is always a full ranking.
   * @param int|null $page_size
   *   The number of rows shown at once before the list pages; NULL uses the
   *   default.
   */
  public function __construct(array $options, array $default = [], ?int $page_size = NULL) {
    $this->pageSize = $this->resolvePageSize($page_size);

    $by_value = [];
    foreach (Option::list($options) as $row) {
      $by_value[$row->value] = $row;
    }

    $order = Field::canonicalOrder(array_keys($by_value), $default);
    $this->items = array_map(static fn(string $value): Option => $by_value[$value], $order);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function keyScope(): Scope {
    return Scope::field(FieldType::Reorder);
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Key $key): void {
    $keys = $this->keys();

    if ($this->handleCancel($key)) {
      return;
    }

    if ($keys->matches($key, Action::Accept)) {
      // A held item drops on Accept, mirroring Space; nothing is committed
      // while an item is held, so Enter never accepts mid-move.
      if ($this->grabbed) {
        $this->grabbed = FALSE;
      }
      else {
        $this->accept($this->liveValue());
      }

      return;
    }

    if ($keys->matches($key, Action::Grab)) {
      $this->grabbed = !$this->grabbed;

      return;
    }

    if ($keys->matches($key, Action::MoveUp)) {
      $this->move(-1);

      return;
    }

    if ($keys->matches($key, Action::MoveDown)) {
      $this->move(1);
    }
  }

  /**
   * Move the cursor, carrying the held item when one is grabbed.
   *
   * @param int $dir
   *   The direction: -1 up, +1 down.
   */
  protected function move(int $dir): void {
    $target = $this->cursor + $dir;

    if ($target < 0 || $target >= count($this->items)) {
      return;
    }

    if ($this->grabbed) {
      $items = $this->items;
      [$items[$this->cursor], $items[$target]] = [$items[$target], $items[$this->cursor]];
      $this->items = array_values($items);
    }

    $this->cursor = $target;
  }

  /**
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    return array_map(static fn(Option $option): string => $option->value, $this->items);
  }

  /**
   * The rows currently shown: the full arrangement, in its current order.
   *
   * @return list<\DrevOps\Tui\Model\Option>
   *   The visible rows.
   */
  public function visible(): array {
    return $this->items;
  }

  /**
   * {@inheritdoc}
   */
  protected function renderBody(ThemeInterface $theme): string {
    $visible = $this->visible();
    $viewport = $this->pageViewport(count($visible), $this->cursor);

    $rows = [];

    foreach (array_slice($visible, $viewport->offset, $this->pageSize) as $slot => $option) {
      $rows[] = $this->renderOptionRow($theme, $option, $viewport->offset + $slot === $this->cursor);
    }

    return implode("\n", $this->wrapScrolled($theme, $rows, $viewport));
  }

  /**
   * The description of the highlighted item, empty for a non-selectable row.
   *
   * @return string
   *   The highlighted item's description.
   */
  #[\Override]
  protected function highlightedDescription(): string {
    if ($this->items === []) {
      return '';
    }

    $current = $this->items[$this->cursor];

    return $current->selectable() ? $current->description : '';
  }

  /**
   * Render one row: the marker cell and the (possibly held) item's label.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\Tui\Model\Option $option
   *   The item row.
   * @param bool $current
   *   Whether the row holds the cursor.
   *
   * @return string
   *   The rendered row.
   */
  public function renderOptionRow(ThemeInterface $theme, Option $option, bool $current): string {
    return $this->marker($theme, $current) . ' ' . $this->highlightLabel($theme, $option->label, $current);
  }

  /**
   * The two-column marker cell for a row.
   *
   * A held item shows the up-down glyphs, the plain cursor shows the marker,
   * and every other row is blank - all two columns wide so the labels align.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param bool $current
   *   Whether the row holds the cursor.
   *
   * @return string
   *   The two-column marker cell.
   */
  protected function marker(ThemeInterface $theme, bool $current): string {
    if ($current && $this->grabbed) {
      return $theme->arrowUp() . $theme->arrowDown();
    }

    return $theme->marker($current) . ' ';
  }

  /**
   * {@inheritdoc}
   *
   * A held item flips the labels to "reorder"/"drop" and cannot be accepted
   * mid-move, so the accept hint is dropped until it lands; otherwise "move"
   * and "grab" lead the base accept/cancel fragments.
   */
  #[\Override]
  public function hints(): array {
    if ($this->grabbed) {
      return [
        new Hint('reorder', Action::MoveUp, Action::MoveDown),
        new Hint('drop', Action::Grab),
        new Hint('cancel', Action::Cancel),
      ];
    }

    return [
      new Hint('move', Action::MoveUp, Action::MoveDown),
      new Hint('grab', Action::Grab),
      ...parent::hints(),
    ];
  }

}
