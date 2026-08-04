<?php

declare(strict_types=1);

namespace DrevOps\Tui\Model;

/**
 * Where a field's editor is drawn: inline in the panel, or on its own screen.
 *
 * An inline field expands its editor in place on the panel when activated - the
 * field's own view, driven by its own keys, collapsing back to a one-line
 * summary on accept or cancel - so a value changes without leaving the panel.
 * A standalone field opens that same editor full-screen instead, the better fit
 * for a field that wants the whole viewport. Fields are inline by default; a
 * consumer opts one out with the builder's standalone() method.
 *
 * @package DrevOps\Tui\Model
 */
enum RenderMode {

  case Inline;
  case Standalone;

}
