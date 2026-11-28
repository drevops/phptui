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
 * A caller {@see Command} has no action behind it, so its hint names the key
 * itself.
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
   * The keys that lead the fragment as they are, after the actions' keys.
   *
   * @var list<\DrevOps\PhpTui\Input\Key>
   */
  public array $keys;

  /**
   * Construct a hint.
   *
   * @param string $label
   *   The English source label describing what the keys do (e.g. "move",
   *   "accept"); translated to the active language.
   * @param \DrevOps\PhpTui\Input\Action|\DrevOps\PhpTui\Input\Key ...$triggers
   *   The actions whose primary keys illustrate the label, and any keys that
   *   illustrate it directly.
   */
  public function __construct(
    string $label,
    Action|Key ...$triggers,
  ) {
    $this->label = Translator::t($label);
    $this->actions = array_values(array_filter($triggers, static fn(Action|Key $trigger): bool => $trigger instanceof Action));
    $this->keys = array_values(array_filter($triggers, static fn(Action|Key $trigger): bool => $trigger instanceof Key));
  }

}
