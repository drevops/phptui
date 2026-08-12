<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Input;

use DrevOps\PhpTui\Translation\Translator;

/**
 * One labelled fragment of a context's key-hint footer.
 *
 * A hint pairs a human label ("move", "accept", "none/all") with the actions
 * whose live keys illustrate it, so the glyphs are rendered from the active
 * bindings - {@see \DrevOps\PhpTui\Theme\DefaultTheme::renderHints()} - and
 * never drift from a remap. A context - a field or the panel hub - declares an
 * ordered list of these; the theme turns each into a fragment and joins them.
 * Listing two actions under one label groups their keys ("←/→ none/all").
 *
 * @package DrevOps\PhpTui\Input
 */
final readonly class Hint {

  /**
   * The label describing what the keys do, in the active language.
   */
  public string $label;

  /**
   * The actions whose primary keys lead the fragment.
   *
   * @var list<\DrevOps\PhpTui\Input\Action>
   */
  public array $actions;

  /**
   * Construct a hint.
   *
   * @param string $label
   *   The English source label describing what the keys do (e.g. "move",
   *   "accept"); translated to the active language.
   * @param \DrevOps\PhpTui\Input\Action ...$actions
   *   The actions whose primary keys illustrate the label.
   */
  public function __construct(
    string $label,
    Action ...$actions,
  ) {
    $this->label = Translator::t($label);
    $this->actions = array_values($actions);
  }

}
