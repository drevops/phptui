<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field;

use DrevOps\Tui\Block\FieldType;
use DrevOps\Tui\Block\Option;
use DrevOps\Tui\Block\OptionType;
use DrevOps\Tui\Block\SelectionBounds;
use DrevOps\Tui\Field\Capability\FilterCapableInterface;
use DrevOps\Tui\Field\Capability\FilterCapableTrait;
use DrevOps\Tui\Field\Capability\OptionsCapableInterface;
use DrevOps\Tui\Field\Capability\OptionsCapableTrait;
use DrevOps\Tui\Field\Capability\PagingCapableInterface;
use DrevOps\Tui\Field\Capability\PagingCapableTrait;
use DrevOps\Tui\Field\Capability\SelectionBoundedTrait;
use DrevOps\Tui\Field\Capability\SelectionCapableInterface;
use DrevOps\Tui\Field\Capability\SelectionCapableTrait;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Utils\Strings;

/**
 * A single-choice or multiple-choice list of options.
 *
 * A single-choice radio list, or a multiple-choice checkbox list with
 * type-to-filter and select-all/none.
 *
 * @package DrevOps\Tui\Field
 */
class Select extends AbstractField implements OptionsCapableInterface, SelectionCapableInterface, FilterCapableInterface, PagingCapableInterface {

  use OptionsCapableTrait;
  use SelectionCapableTrait;
  use SelectionBoundedTrait;
  use FilterCapableTrait;
  use PagingCapableTrait;

  /**
   * Construct a select field.
   *
   * @param array<int|string,\DrevOps\Tui\Block\Option|string> $options
   *   Option rows in display order - a list of options or the value => label
   *   shorthand map.
   * @param string|list<string> $default
   *   The initially highlighted value (single) or selected values (multiple).
   * @param bool $multiple
   *   Whether several options are collected as a list.
   * @param int|null $page_size
   *   The number of option rows shown at once before the list pages; NULL uses
   *   the default.
   * @param \DrevOps\Tui\Block\SelectionBounds|null $selection_bounds
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
   * @return \DrevOps\Tui\Block\FieldType
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
   * @return list<\DrevOps\Tui\Block\Option>
   *   The matching option rows.
   */
  protected function filterOptions(string $needle): array {
    $lower = Strings::lower($needle);

    return array_values(array_filter($this->options, static fn(Option $option): bool => $option->kind === OptionType::Option && str_contains(Strings::lower($option->label), $lower)));
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
    return $this->renderChoiceList($theme);
  }

}
