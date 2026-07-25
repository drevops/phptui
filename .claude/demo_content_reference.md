# Demo content reference

Canonical demo content for every example in this repository - documentation code
blocks, playground scripts, and the generated SVG screenshots.

**Rule: examples use a fruit-and-vegetable theme and contain no software,
product, or technology references.** No programming languages, frameworks,
databases, services, package managers, container tools, timezones-as-config,
hostnames, or brand names. When an example needs sample data, draw it from the
vocabulary below so the docs, the playground scripts, and the screenshots all
read as one consistent world.

Why: the engine is application-agnostic. A neutral, friendly theme keeps the
examples about the *widgets* rather than any particular software stack, and
avoids dating the docs to a specific toolchain.

## The scenario

The running example is putting together a **produce order**: naming it, picking a
fruit, adding vegetables, choosing a quantity, and confirming.

## Vocabulary

- **Fruits**: Apple, Apricot, Banana, Cherry, Grape, Lemon, Mango, Melon, Orange, Peach, Pear, Plum.
- **Vegetables**: Beet, Broccoli, Cabbage, Carrot, Celery, Leek, Onion, Pepper, Potato, Spinach, Tomato.
- **Categories**: Fruit, Vegetable, Herb.
- **States**: Ripe / Unripe, Organic / Conventional.

## Canonical widget values

Use these exact ids, labels, options and defaults so the code and its screenshot
always match.

| Widget | Label | Default | Options / bounds |
| --- | --- | --- | --- |
| text | `Item` | `Pear` | complete: `Pear`, `Peach`, `Plum` |
| number | `Basket weight (g)` | `1200` | min `200`, max `9000`, step `100` |
| calendar | `Harvest date` | `2026-07-15` | - |
| textarea | `Tasting notes` | `Crisp and sweet\nHint of citrus` | - |
| password | `Order code` | `melon7` | revealable, confirmation |
| select | `Fruit` | `apple` | `apple` Apple, `banana` Banana, `cherry` Cherry |
| multiselect | `Basket` | `[apple]` | `apple` Apple, `carrot` Carrot, `tomato` Tomato |
| reorder | `Basket` | - | `apple` Apple, `carrot` Carrot, `tomato` Tomato |
| suggest | `Fruit` | - | `Apple`, `Apricot`, `Banana`, `Cherry`, `Mango` |
| search | `Vegetable` | `carrot` | `carrot` Carrot, `potato` Potato, `onion` Onion, `pepper` Pepper |
| multisearch | `Basket` | `[apple]` | `apple` Apple, `banana` Banana, `carrot` Carrot, `tomato` Tomato |
| confirm | `Organic only?` | `TRUE` | - |
| toggle | `Ripeness` | `ripe` | `ripe` Ripe, `unripe` Unripe |
| pause | `Review your basket` | - | - |
| note | `Fresh produce order` | - | title + body card, optional `->border()`, collects no value |
| filepicker | `Price list` | - | extensions `csv` |
| multifilepicker | `Price lists` | - | - |

## Option descriptions

For examples of the highlighted-option description line, use these one-line
notes so the scripts and screenshots stay consistent:

- **Apple**: Crisp and sweet, the everyday choice.
- **Apricot**: Small and tart; best when soft.
- **Banana**: Rich in potassium; ripens off the tree.
- **Cherry**: Short season; best eaten fresh.
- **Mango**: Fragrant and juicy when it yields to a squeeze.
- **Carrot**: Stays crisp for weeks when kept cold.
- **Onion**: Sharp raw, sweet once cooked.
- **Pepper**: Crunchy and bright; sweetest when red.
- **Potato**: Stores best somewhere cool and dark.
- **Tomato**: Best ripened on the vine, never chilled.

## Quick-start order form

The quick start and its playground script (`playground/01-quickstart.php`) build a
`New order` panel:

- `text('name', 'Order name')->required()`
- `select('fruit', 'Fruit')->default('banana')` - Apple / Banana / Cherry
- `select('veg', 'Vegetables')->multiple()->default(['carrot'])` - Carrot / Tomato / Spinach
- `number('quantity', 'Quantity')->min(1)->max(99)->default(6)`
- `confirm('organic', 'Organic only?')->default(FALSE)`

## Derived-value chain

For examples that show derived values (str2name transforms), derive a `slug` and
`label` from a produce name:

- `name` = `Red Apple` -> `slug` (machine) = `red_apple` -> `code` (constant) = `RED_APPLE`.

## Filesystem fixture

The file-picker examples browse a small **produce archive** (no source tree):
`baskets/` with `apples.csv` and `pears.csv`, `deliveries/` with `monday.log`,
and a top-level `pantry.yaml` and `README.md`.
