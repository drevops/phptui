---
title: Structure
description: 'The levels a screen is built from, and the capability each one owns: layouts, regions, blocks, and the two tiers a theme draws with.'
keywords: ['structure', 'layout', 'region', 'block', 'field', 'panel', 'capability', 'theme']
---

# Structure

A screen is built from four kinds of thing - a *layout*, its *regions*, the *blocks* placed in them, and the *elements* a theme styles. Each kind owns a fixed set of capabilities, and owns them alone.

That last part is what makes the model useful. When something doesn't obviously fit - a new widget, a feature that could live in two places - the question is never "where does this go", it's **which level owns the capability it needs**. The answer follows.

## The hierarchy

```
Layout                 declares regions, and which of them scroll
└─ Region              a named area; holds blocks
   └─ Block            placed in a region; renders itself
      ├─ Panel         holds blocks, arranges them, navigates
      ├─ Field         answers, and has two modes
      ├─ Note          renders content
      ├─ Breadcrumb    renders content
      ├─ KeyHints      renders content
      ├─ Buttons       acts
      └─ Progress      acts
```

A *block* may nest a *layout* of its own, which is how the tree goes deeper than four levels without gaining a fifth kind of thing.

## Capabilities

Eight capabilities cover everything on screen. Each is declared by an interface and implemented by a trait, the way the existing `*CapableInterface` and `*CapableTrait` pairs already work.

| Capability | What it means | Owned by |
|---|---|---|
| **Region** | Declares named areas, and which of them scroll | Layout |
| **Hold** | Contains blocks | Region, and any block that holds |
| **Place** | Can sit in a region | Block |
| **Arrange** | Owns a layout of its own | Panel |
| **Focus** | The cursor can land on it | Block |
| **Answer** | Contributes a value to the collected payload | Field |
| **Edit** | Has an edit mode distinct from its view | Field |
| **Navigate** | Can be entered and left; joins the breadcrumb | Panel |
| **Act** | Runs work when activated | Block |

Two of those are worth stating as rules, because they're the ones a new widget will test.

**Scrolling belongs to the layout, not to a region or a block.** A region doesn't decide whether it scrolls - its layout does, when it declares the region. That's what lets one layout pin a column and scroll another without either the region or the blocks inside it knowing.

**Answering belongs to the field, not to the block.** A block that renders content and a block that collects an answer are the same kind of thing placed the same way; only one of them contributes to the payload.

## Blocks

A **block** is anything placeable in a *region*. That's the whole definition: it fills the space it's given, and the *region* knows nothing else about it.

Every block places and renders. What varies is which further capabilities it claims:

| Block | Hold | Arrange | Focus | Answer | Edit | Navigate | Act |
|---|---|---|---|---|---|---|---|
| Breadcrumb | | | | | | | |
| KeyHints | | | | | | | |
| Note | | | | | | | |
| Table | | | | | | | |
| Buttons | | | ✓ | | | | ✓ |
| Progress | | | ✓ | | | | ✓ |
| Field | | | ✓ | ✓ | ✓ | | |
| Panel | ✓ | ✓ | ✓ | | | ✓ | |

Read down the *Answer* column and the model earns its keep: only a *field* answers. Everything else on screen renders, focuses or acts, and none of it reaches the collected payload.

Read down *Focus* and *Act* and they come apart, which they can't today. A *progress* block takes the cursor and runs work but answers nothing. A *note* does neither. A *field* focuses and answers but doesn't act.

## Panel

A **panel** is the busiest block, so it's worth naming its capabilities exactly. It is a *block* that also holds, arranges and navigates:

```
Panel  "Delivery"
├─ Place      it sits in a region, like any block
├─ Hold       it contains blocks: fields, notes, sub-panels
├─ Arrange    it owns a layout, so its blocks land in named regions
├─ Focus      as a sub-panel, the cursor lands on it
└─ Navigate   entering it pushes a breadcrumb segment; leaving pops one
```

**Navigate is the capability nothing else has**, and it's what makes a panel more than a container. Entering a panel replaces the content region and adds a segment to the breadcrumb; leaving it restores both. A *region* can hold blocks and a *layout* can arrange them, but neither can be somewhere you go.

One panel fills the content region at a time. A sub-panel appears in its parent as a row you activate to descend, which is why a panel needs *Focus* when it's nested but not when it's the one on screen.

A **modal** is the same block placed in an overlay region instead of the content region. Nothing about the panel changes - only where it's placed.

## Field

A **field** is the *block* that answers. It's the only kind that contributes to the collected payload, and the only kind with two modes.

One field owns both modes. In **view** mode it's one line, drawing every part of that line itself. Open it and it switches to **edit** mode, taking over the region right of its *label*:

```
view mode    ❯ Basket contents ⁱ  apple, carrot
                                  └─────┬─────┘
                                    the settled value

edit mode    ❯ Basket contents    ● Apple
                                  ○ Carrot
                                  └───┬───┘
                                    the field collecting it
```

The *label* and the *selector* stay put across both. Only the *value* region changes shape, which is why a field in *edit* mode is still one row of its *panel* rather than something new on the screen. [Anatomy](/widgets/anatomy) names every piece of both.

Between the field and the theme sit its **capabilities** - the shared behaviour a field mixes in rather than reimplements. A field that offers a list is options-capable; one that filters as you type is search-capable; one with bounds is selection-bounded. The chain runs one way and never doubles back:

```
Field  ──▸  capabilities  ──▸  theme templates  ──▸  theme elements
```

## The theme's two tiers

An **element** is the smallest styled piece. It takes a plain string, returns a styled one, and knows nothing about what surrounds it:

```
Orchard  ›  Delivery
───┬───  ┬  ────┬───
   │     │      └── breadcrumbLabel()
   │     └───────── breadcrumbSeparator()
   └─────────────── breadcrumbLabel()
```

A **template** composes elements into a block's output, deciding order, spacing and how many elements there are:

```
renderBreadcrumb(['Orchard', 'Delivery'])
        │
        ├── breadcrumbLabel('Orchard')
        ├── breadcrumbSeparator()
        └── breadcrumbLabel('Delivery')
```

Both are public, and the split tells you which to override:

| Tier | Shape | Override to |
|---|---|---|
| Element | `breadcrumbLabel(string $text): string` | change color, weight or glyph |
| Template | `renderBreadcrumb(array $segments): string` | change layout, order or what's included |

Templates are implemented on the abstract theme, so a custom theme inherits working layout and touches only the elements it wants repainted. Overriding a template is the rare case: it means changing the shape of something, not its palette.

## On screen

Here it is on a real screen, each level labelled on the row it owns - the *region*, the *block* in it, then the *panel*'s *fields* and the *mode* each is drawing:

```
                              ╭──────────────────────────────────────────────────────╮
header  ▸ Breadcrumb          │ Orchard › Delivery                                   │
                              ├──────────────────────────────────────────────────────┤
content ▸ Panel               │                                                      │
          ▸ Field  edit mode  │ ❯ Basket  ● Apple                                    │
                              │           ○ Carrot                                   │
                              │     Pick the produce.                                │
                              │                                                      │
          ▸ Field  view mode  │   Basket weight  1200                                │
                              │                                                      │
          ▸ Field  view mode  │   Harvest date  2026-07-15                           │
                              │                                                      │
                              │   ▼                                                  │
                              │                                                      │
                              ├──────────────────────────────────────────────────────┤
footer  ▸ KeyHints            │ ↑/↓ to move · ↵ to accept · ESC to cancel             │
                              ╰──────────────────────────────────────────────────────╯
```

The `header` and `footer` *regions* are pinned; `content` is the one the *layout* declared as scrolling, which is why the mark under `Harvest date` belongs to it rather than to the frame. The `Basket` *field* is open, so it's in *edit* mode: its two *entries* and its *description* all belong to that one *field*.

## Building one

The hierarchy is what's there, not what you have to type. A three-field form names none of it:

```php
$form = Form::create('Orchard')
  ->panel('main', 'Delivery', function (PanelBuilder $p): void {
    $p->text('courier', 'Courier');
    $p->number('weight', 'Basket weight')->min(200)->max(9000);
    $p->confirm('organic', 'Organic only?');
  })
  ->build();

(new Tui($form))->run();
```

Every level has a default, and this is what those defaults are:

```php
Layout::threeRegion()
  ->region('header',  fn(RegionBuilder $r) => $r->add(new Breadcrumb()))
  ->region('content', fn(RegionBuilder $r) => $r->add($panel)->add(new Buttons()), scrolls: TRUE)
  ->region('footer',  fn(RegionBuilder $r) => $r->add(new KeyHints()));
```

Adding a *note* between two *fields* doesn't change the shape of the code, because a note and a number are both blocks placed in the same list. Only one of them answers:

```php
->panel('main', 'Delivery', function (PanelBuilder $p): void {
  $p->text('courier', 'Courier');
  $p->note('weighing', 'Every crate is weighed at the packing bench.');
  $p->number('weight', 'Basket weight')->min(200)->max(9000);
})
```

Two columns is the first thing that needs a *layout*, so it's the first thing that names one:

```php
->panel('main', 'Delivery', fn(PanelBuilder $p) => $p
  ->layout('two-column')
  ->in('left',  fn(RegionBuilder $r) => $r->text('courier', 'Courier'))
  ->in('right', fn(RegionBuilder $r) => $r->number('weight', 'Basket weight')))
```

Named regions mean a block says where it goes, rather than depending on the order it was declared in.

The screen's own layout is mentioned only when you change it:

```php
(new Tui($form))
  ->layout('three-region')
  ->place('header', new Note('preview', 'Read-only preview.'))
  ->run();
```

## Resolving a tension

When something doesn't fit, name the capability it needs and the level that owns it. Three worked examples:

**"Should a note be able to sit in the footer?"** It needs *Place*, which every block has. Yes - and it needs no new feature, only a placement.

**"Should a progress row appear in the answers?"** It would need *Answer*, which only a field has. No - it *Acts*, which is a different capability.

**"Should a panel be able to scroll its left column but not its right?"** It needs *Region* with per-region scrolling, which the layout owns. Yes - declare it in the layout, and neither the regions nor the blocks inside them change.

The point of the split is that each of those has one answer rather than an argument. A *region* that knew what a breadcrumb was would need to know what every block is; instead a region knows only that it holds blocks, a block knows only how to fill the space it's given, and a theme knows only how to style what it's handed.

That's also what makes an element reusable. Because `breadcrumbSeparator()` receives no field, no panel and no answers, the same element draws the separator inside a form and inside a standalone line of output. An element that reached for form state could only ever be used from inside a form.
