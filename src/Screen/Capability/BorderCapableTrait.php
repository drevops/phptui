<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen\Capability;

use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\BorderSide;

/**
 * The declaration behind {@see BorderCapableInterface}.
 *
 * @package DrevOps\Tui\Screen\Capability
 */
trait BorderCapableTrait {

  /**
   * Whether edges are drawn; NULL before any declaration.
   */
  protected ?bool $bordered = NULL;

  /**
   * The style the edges are drawn in; NULL uses the theme's own.
   */
  protected ?Border $borderStyle = NULL;

  /**
   * The text written into the top edge.
   */
  protected string $borderTitle = '';

  /**
   * The edges that are drawn, combined from {@see BorderSide}.
   */
  protected int $borderSides = BorderSide::ALL;

  /**
   * {@inheritdoc}
   */
  public function border(int $sides = BorderSide::ALL, ?Border $style = NULL, string $title = ''): static {
    // Naming no side and naming a style of None both mean no border at all,
    // not a border with no edges.
    $this->bordered = $sides !== BorderSide::NONE && $style !== Border::None;
    $this->borderSides = $sides;
    $this->borderStyle = $style;
    $this->borderTitle = $title;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isBordered(): bool {
    return $this->bordered ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function borderStyle(): ?Border {
    return $this->borderStyle;
  }

  /**
   * {@inheritdoc}
   */
  public function borderTitle(): string {
    return $this->borderTitle;
  }

  /**
   * {@inheritdoc}
   */
  public function borderSides(): int {
    return $this->borderSides;
  }

}
