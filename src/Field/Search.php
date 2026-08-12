<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Field;

use DrevOps\PhpTui\Block\FieldType;
use DrevOps\PhpTui\Block\SelectionBounds;
use DrevOps\PhpTui\Field\Capability\FilterCapableInterface;
use DrevOps\PhpTui\Field\Capability\FilterCapableTrait;
use DrevOps\PhpTui\Field\Capability\OptionsCapableInterface;
use DrevOps\PhpTui\Field\Capability\OptionsCapableTrait;
use DrevOps\PhpTui\Field\Capability\PagingCapableInterface;
use DrevOps\PhpTui\Field\Capability\PagingCapableTrait;
use DrevOps\PhpTui\Field\Capability\PlaceholderCapableInterface;
use DrevOps\PhpTui\Field\Capability\PlaceholderCapableTrait;
use DrevOps\PhpTui\Field\Capability\QueryOptionsCapableInterface;
use DrevOps\PhpTui\Field\Capability\QueryOptionsCapableTrait;
use DrevOps\PhpTui\Field\Capability\SearchCapableInterface;
use DrevOps\PhpTui\Field\Capability\SearchCapableTrait;
use DrevOps\PhpTui\Field\Capability\SelectionBoundedTrait;
use DrevOps\PhpTui\Field\Capability\SelectionCapableInterface;
use DrevOps\PhpTui\Field\Capability\SelectionCapableTrait;
use DrevOps\PhpTui\Input\Action;
use DrevOps\PhpTui\Input\Key;
use DrevOps\PhpTui\Theme\ThemeInterface;

/**
 * A fuzzy type-to-filter choice list under a query line.
 *
 * A single-choice radio list or a multiple-choice checkbox list: printable
 * characters narrow the list, ranked by fuzzy relevance, and the matched
 * characters are highlighted.
 *
 * @package DrevOps\PhpTui\Field
 */
class Search extends AbstractField implements
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
   * Construct a search field.
   *
   * @param array<int|string,\DrevOps\PhpTui\Block\Option|string> $options
   *   Option rows in display order - a list of options or the value => label
   *   shorthand map.
   * @param string|list<string> $default
   *   The initially highlighted value (single) or selected values (multiple).
   * @param bool $multiple
   *   Whether several options are collected as a list.
   * @param int|null $page_size
   *   The number of option rows shown at once before the list pages; NULL uses
   *   the default.
   * @param \DrevOps\PhpTui\Block\SelectionBounds|null $selection_bounds
   *   The minimum/maximum selection counts enforced on accept, or NULL for no
   *   count limit.
   */
  public function __construct(array $options, string|array $default = '', bool $multiple = FALSE, ?int $page_size = NULL, ?SelectionBounds $selection_bounds = NULL) {
    $this->initChoice($options, $default, $multiple);
    $this->pageSize = $this->resolvePageSize($page_size);
    $this->selectionBounds = $selection_bounds;
  }

  /**
   * The field type this field binds its keys under.
   *
   * @return \DrevOps\PhpTui\Block\FieldType
   *   The search field type.
   */
  protected function choiceType(): FieldType {
    return FieldType::Search;
  }

  /**
   * {@inheritdoc}
   *
   * Space is part of the query in single mode, so it cannot also select;
   * multiple mode binds Space to toggle the highlighted option.
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
    return $this->queryLine($theme) . "\n" . ($this->queryStateLine($theme) ?? $this->renderChoiceList($theme));
  }

  /**
   * {@inheritdoc}
   *
   * While the query-state line replaces the list, the selection-count hint is
   * suppressed.
   */
  #[\Override]
  protected function renderConstraint(ThemeInterface $theme): string {
    return $this->queryStateLine($theme) === NULL ? $this->selectionHint($theme) : '';
  }

  /**
   * {@inheritdoc}
   */
  public function queryLine(ThemeInterface $theme): string {
    return $this->filterLine($theme) . $this->placeholderGhost($theme, $this->filter);
  }

}
