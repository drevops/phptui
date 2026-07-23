<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Model\OptionKind;
use DrevOps\Tui\Model\SelectionBounds;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Utils\Strings;
use DrevOps\Tui\Widget\Capability\FilterCapableInterface;
use DrevOps\Tui\Widget\Capability\FilterCapableTrait;
use DrevOps\Tui\Widget\Capability\OptionsCapableInterface;
use DrevOps\Tui\Widget\Capability\OptionsCapableTrait;
use DrevOps\Tui\Widget\Capability\PagingCapableInterface;
use DrevOps\Tui\Widget\Capability\PagingCapableTrait;
use DrevOps\Tui\Widget\Capability\SelectionBoundedTrait;
use DrevOps\Tui\Widget\Capability\SelectionCapableInterface;
use DrevOps\Tui\Widget\Capability\SelectionCapableTrait;

/**
 * A single-choice or multiple-choice list of options.
 *
 * A single-choice radio list, or a multiple-choice checkbox list with
 * type-to-filter and select-all/none.
 *
 * @package DrevOps\Tui\Widget
 */
class SelectWidget extends AbstractWidget implements OptionsCapableInterface, SelectionCapableInterface, FilterCapableInterface, PagingCapableInterface {

  use OptionsCapableTrait;
  use SelectionCapableTrait;
  use SelectionBoundedTrait;
  use FilterCapableTrait;
  use PagingCapableTrait;

  /**
   * Construct a select widget.
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
   *   The select field type.
   */
  protected function choiceType(): FieldType {
    return FieldType::Select;
  }

  /**
   * Filter the options by case-insensitive substring over the labels.
   *
   * @param string $needle
   *   The query.
   *
   * @return list<\DrevOps\Tui\Model\Option>
   *   The matching option rows.
   */
  protected function filterOptions(string $needle): array {
    $lower = Strings::lower($needle);

    return array_values(array_filter($this->options, static fn(Option $option): bool => $option->kind === OptionKind::Option && str_contains(Strings::lower($option->label), $lower)));
  }

  /**
   * The matched-character positions: a plain choice list highlights none.
   *
   * @param string $label
   *   The option label.
   *
   * @return list<int>
   *   The matched indices (always empty).
   */
  protected function matchPositions(string $label): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function renderBody(ThemeInterface $theme): string {
    return $this->withSelectionHint($theme, $this->renderChoiceList($theme));
  }

}
