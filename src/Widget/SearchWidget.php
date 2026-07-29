<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\SelectionBounds;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Widget\Capability\FilterCapableInterface;
use DrevOps\Tui\Widget\Capability\FilterCapableTrait;
use DrevOps\Tui\Widget\Capability\OptionsCapableInterface;
use DrevOps\Tui\Widget\Capability\OptionsCapableTrait;
use DrevOps\Tui\Widget\Capability\PagingCapableInterface;
use DrevOps\Tui\Widget\Capability\PagingCapableTrait;
use DrevOps\Tui\Widget\Capability\PlaceholderCapableInterface;
use DrevOps\Tui\Widget\Capability\PlaceholderCapableTrait;
use DrevOps\Tui\Widget\Capability\QueryOptionsCapableInterface;
use DrevOps\Tui\Widget\Capability\QueryOptionsCapableTrait;
use DrevOps\Tui\Widget\Capability\SearchCapableInterface;
use DrevOps\Tui\Widget\Capability\SearchCapableTrait;
use DrevOps\Tui\Widget\Capability\SelectionBoundedTrait;
use DrevOps\Tui\Widget\Capability\SelectionCapableInterface;
use DrevOps\Tui\Widget\Capability\SelectionCapableTrait;

/**
 * A fuzzy type-to-filter choice list under a query line.
 *
 * A single-choice radio list or a multiple-choice checkbox list: printable
 * characters narrow the list, ranked by fuzzy relevance, and the matched
 * characters are highlighted.
 *
 * @package DrevOps\Tui\Widget
 */
class SearchWidget extends AbstractWidget implements
  OptionsCapableInterface,
  SelectionCapableInterface,
  FilterCapableInterface,
  SearchCapableInterface,
  QueryOptionsCapableInterface,
  PagingCapableInterface,
  PlaceholderCapableInterface {

  use OptionsCapableTrait;
  use SelectionCapableTrait;
  use SelectionBoundedTrait;
  use FilterCapableTrait;
  use SearchCapableTrait {
    queryLine as protected filterLine;
  }
  use QueryOptionsCapableTrait;
  use PagingCapableTrait;
  use PlaceholderCapableTrait;

  /**
   * Construct a search widget.
   *
   * @param array<int|string,\DrevOps\Tui\Model\Option|string> $options
   *   Option rows in display order - a list of options or the value => label
   *   shorthand map.
   * @param string|list<string> $default
   *   The initially highlighted value (single) or selected values (multiple).
   * @param bool $multiple
   *   Whether several options are collected as a list.
   * @param int|null $page_size
   *   The number of option rows shown at once before the list pages; NULL uses
   *   the default.
   * @param \DrevOps\Tui\Model\SelectionBounds|null $selection_bounds
   *   The minimum/maximum selection counts enforced on accept, or NULL for no
   *   count limit.
   */
  public function __construct(array $options, string|array $default = '', bool $multiple = FALSE, ?int $page_size = NULL, ?SelectionBounds $selection_bounds = NULL) {
    $this->initChoice($options, $default, $multiple);
    $this->pageSize = $this->resolvePageSize($page_size);
    $this->selectionBounds = $selection_bounds;
  }

  /**
   * The field type this widget binds its keys under.
   *
   * @return \DrevOps\Tui\Model\FieldType
   *   The search field type.
   */
  protected function choiceType(): FieldType {
    return FieldType::Search;
  }

  /**
   * {@inheritdoc}
   *
   * Space is part of the query in single mode, so it cannot double as a select
   * key there; multiple mode binds Space to toggle the highlighted option.
   */
  protected function handleSingleMode(Key $key): void {
    if ($this->keys()->matches($key, Action::InsertSpace)) {
      $this->filter .= ' ';
      $this->resetFilterCursor();

      return;
    }

    if ($this->handleFilterKey($key)) {
      return;
    }

    $this->handleSingleChoiceKey($key);
  }

  /**
   * {@inheritdoc}
   */
  public function query(): string {
    return $this->filter;
  }

  /**
   * {@inheritdoc}
   */
  protected function adoptQueryRows(array $rows): void {
    $this->options = $rows;
    $this->resetFilterCursor();
  }

  /**
   * {@inheritdoc}
   */
  protected function renderBody(ThemeInterface $theme): string {
    return $this->queryLine($theme) . "\n" . ($this->queryStateLine($theme) ?? $this->withSelectionHint($theme, $this->renderChoiceList($theme)));
  }

  /**
   * {@inheritdoc}
   */
  public function queryLine(ThemeInterface $theme): string {
    return $this->filterLine($theme) . $this->placeholderGhost($theme, $this->filter);
  }

}
