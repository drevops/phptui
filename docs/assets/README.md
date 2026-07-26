# Terminal SVG assets

Generated terminal renders used by the project README and the documentation
site. Do not edit these by hand - they are produced by the scripts in
[`../util`](../util).

## Naming convention

Every file spells out its properties, so you can tell what it is from the name
alone:

```text
<subject>-<mode>-<motion>[-bordered][-ascii][-no-ansi].svg
```

| Segment     | Values                 | Meaning                                            |
|-------------|------------------------|----------------------------------------------------|
| `subject`   | e.g. `widget-text`, `theme-midnight`, `progress-bar` | What is shown (a widget, a panel demo, a theme preview, a primitive) |
| `mode`      | `dark` \| `light`      | Colour scheme                                      |
| `motion`    | `animated` \| `static` | An animation, or a single frame                    |
| `-bordered` | present when set       | Inside the rounded border frame (theme previews)   |
| `-ascii`    | present when set       | Textual glyphs instead of Unicode (default Unicode)|
| `-no-ansi`  | present when set       | No colour (default colour)                         |

A theme preview's subject carries the theme name (`theme-midnight`), so its
`mode` segment still reads dark or light: `theme-midnight-dark-static.svg`.
Unicode and colour are the unmarked defaults, so `widget-text-dark-animated.svg`
is the dark, Unicode, colour animation, and
`widget-text-dark-static-ascii-no-ansi.svg` is its ASCII, no-colour static twin.
Animated demos render inside the rounded border frame by design and carry no
marker; the `-bordered` marker distinguishes the theme previews' framed statics
from their borderless twins.

## What generates what

`update-assets.php` is the single entry point: run without arguments it records every live-terminal job in parallel and spawns the three deterministic sibling generators alongside them, so one command regenerates the whole set.

- **`update-assets.php`** - the full panel demos, the widget montage and the
  option-group / password-reveal / discovery frames, recorded from a live
  terminal (`--record <job>` re-runs one job).
- **`render-widget-svgs.php`** - every per-widget asset, driven deterministically
  through the library's own keystroke harness with no terminal: the animated
  cards in all four display modes (`widget-*-dark-animated*.svg`, the unmarked one
  being the hero, framed by the rounded border) and the matching static
  screenshots (`widget-*-dark-static*.svg`, borderless).
- **`render-theme-svgs.php`** - the built-in theme previews, also through the
  keystroke harness: `theme-<name>-<dark|light>-static[-bordered].svg` for the
  adaptive themes, and the dark/light pair for `dos` (which draws its own window
  on its own surface, so it has no bordered twin).
- **`render-progress-svgs.php`** - the progress primitive's spinner and bar
  assets (`progress-spinner-*`, `progress-bar-*`). The primitive is not a
  keystroke widget: it is a single line the theme redraws in place, so this
  drives the real primitive against an in-memory terminal, splits the output
  into frames on the carriage return, and renders both the animation and a
  single mid-run static frame in all four display modes, borderless like the
  widget statics.
- **`render-social-card.php`** - the one non-SVG asset: `social-card.png`, the
  1200x630 Open Graph image composed from `quickstart-dark-static.svg` and the
  site branding, screenshotted through agent-browser. It runs after the workers
  inside `update-assets.php` (the SVG it composes must exist first) and is
  referenced by `themeConfig.image` in `docusaurus.config.js`.

Every dark SVG derives its `-light-` twin the moment it is written (the shared
`svg-light-twin.php` recolours the surface and foreground greys; the theme
previews render their light palettes for real instead), so the pairs the
documentation serves can never drift. Every animated SVG is slowed to a shared
playback factor so the motion stays readable.
