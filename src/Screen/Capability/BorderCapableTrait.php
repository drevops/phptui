<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen\Capability;

use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\Side;

/**
 * The declaration behind {@see BorderCapableInterface}.
 *
 * @package DrevOps\Tui\Screen\Capability
 */
trait BorderCapableTrait {

  /**
   * Whether edges are drawn, once one way or the other has been stated.
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
   * The edges that are drawn; empty draws all four.
   *
   * @var list<\DrevOps\Tui\Theme\Side>
   */
  protected array $borderSides = [];

  /**
   * {@inheritdoc}
   */
  public function border(?Border $style = NULL, string $title = '', array $sides = []): static {
    $this->bordered = $style !== Border::None;
    $this->borderStyle = $style;
    $this->borderTitle = $title;
    $this->borderSides = $sides;

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
  public function borderSides(): array {
    return $this->borderSides === [] ? Side::all() : $this->borderSides;
  }

}
