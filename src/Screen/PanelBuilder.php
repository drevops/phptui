<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

use DrevOps\Tui\Block\BlockInterface;
use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Panel;

/**
 * Declares a panel without naming a level of the hierarchy.
 *
 * Every level has a default, so a three-field form mentions no screen, no
 * layout and no region: the depth is what is there rather than what has to be
 * typed. Naming one is what a form does only when it needs it.
 *
 * @package DrevOps\Tui\Screen
 */
final class PanelBuilder {

  /**
   * The panel being declared.
   */
  protected Panel $panel;

  /**
   * The region blocks are added to until another is named.
   */
  protected string $region = 'content';

  /**
   * Construct a builder.
   *
   * @param string $id
   *   The panel id.
   * @param string $title
   *   The panel title.
   * @param \DrevOps\Tui\Screen\AbstractLayout|null $layout
   *   The layout it is arranged by; NULL gives it the one region a panel wants
   *   most of the time.
   */
  public function __construct(string $id, string $title, ?AbstractLayout $layout = NULL) {
    $this->panel = (new Panel($id, $title))->layout($layout ?? new PanelLayout());
  }

  /**
   * Add blocks to a named region from here on.
   *
   * @param string $name
   *   The region name.
   *
   * @return $this
   *   The builder.
   */
  public function in(string $name): self {
    // Reached now rather than when a block arrives, so a name that was never
    // declared is caught where it was written.
    $this->panel->in($name);
    $this->region = $name;

    return $this;
  }

  /**
   * Declare a field.
   *
   * @param string $id
   *   The field id.
   * @param string $label
   *   The field label.
   *
   * @return \DrevOps\Tui\Screen\FieldBuilder
   *   The field, which hands this builder back when it is done.
   */
  public function field(string $id, string $label): FieldBuilder {
    $field = new Field($id, $label);
    $this->add($field);

    return new FieldBuilder($this, $field);
  }

  /**
   * Declare a block of markup.
   *
   * @param string $id
   *   The block id.
   * @param string $body
   *   The content.
   * @param string $title
   *   An optional title above it.
   *
   * @return $this
   *   The builder.
   */
  public function markup(string $id, string $body, string $title = ''): self {
    return $this->add(new Markup($id, $body, $title));
  }

  /**
   * Add a block to the region in hand.
   *
   * @param \DrevOps\Tui\Block\BlockInterface $block
   *   The block.
   *
   * @return $this
   *   The builder.
   */
  public function add(BlockInterface $block): self {
    $this->panel->in($this->region)->add($block);

    return $this;
  }

  /**
   * The panel this builder declared.
   *
   * @return \DrevOps\Tui\Block\Panel
   *   The panel.
   */
  public function build(): Panel {
    return $this->panel;
  }

}
