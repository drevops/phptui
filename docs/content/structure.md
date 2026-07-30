---
title: Structure
description: 'How a screen is put together: three fixed sections, widgets inside them, and the two tiers a theme draws with.'
keywords: ['structure', 'layout', 'sections', 'widgets', 'atoms', 'molecules', 'theme']
---

# Structure

Everything on screen sits at one of four levels, and each level knows only about the one below it. Learn these four words and the rest of the page is a tour.

## The hierarchy

```
Screen
└─ Section     Header | Content | Footer      fixed; there are three
   └─ Widget   Breadcrumb, Panel, KeyHint     whatever a section holds
      └─ Field   (Panel only)                 a question in a panel
         └─ Mode   view | edit                how the field draws itself
```

A **section** is a fixed region of the screen. There are three and they never change: what varies between one screen and the next is which widgets are in which section.

A **widget** is anything a section holds. That's the whole definition: it owns a region and knows how to fill it, and the section knows nothing else about it. A breadcrumb is one, a key-hint line is one, a panel is one.

A **field** is not a widget. A Select, a Calendar, a Text field - none of them can sit in a section on their own, because each needs a label, a row and a panel around it. They're tenants of a Panel, which is the widget that holds them.

The test is worth keeping: if a section could hold the thing by itself, it's a widget. If it needs a panel around it, it's a field.

A **mode** is which of its two shapes a field is drawing - one line carrying the answer, or the open editor collecting it.

## Three sections

```
╭─────────────────────────────────────────────────────────╮
│ HEADER                                                  │
│   ┌─────────────────────────────────────────────────┐   │
│   │ Breadcrumb                                      │   │
│   └─────────────────────────────────────────────────┘   │
├─────────────────────────────────────────────────────────┤
│ CONTENT                                     ▲ scrolls   │
│   ┌─────────────────────────────────────────────────┐   │
│   │ Panel                                           │   │
│   │   Field   view mode                             │   │
│   │   Field   edit mode                             │   │
│   │   Field   view mode                             │   │
│   └─────────────────────────────────────────────────┘   │
│                                             ▼ scrolls   │
├─────────────────────────────────────────────────────────┤
│ FOOTER                                                  │
│   ┌─────────────────────────────────────────────────┐   │
│   │ KeyHint                                         │   │
│   └─────────────────────────────────────────────────┘   │
╰─────────────────────────────────────────────────────────╯
```

**Header** and **Footer** are pinned: they hold their ground while you move around. **Content** is the only section that scrolls, which is why the marks that say "there's more above" and "more below" belong to it rather than to the frame.

A section can hold more than one widget. Header holds the breadcrumb today and Footer the key hints, and both have room for whatever else belongs at the top or bottom of a screen.

Here it is on a real screen, with each level of the hierarchy labelled on the row it owns - section, the widget inside it, then the panel's fields and the mode each is drawing:

```
                      ╭──────────────────────────────────────────────────────╮
Header ▸ Breadcrumb   │ Orchard › Delivery                                   │
                      ├──────────────────────────────────────────────────────┤
Content ▸ Panel       │                                                      │
    Field  edit mode  │ ❯ Basket  ● Apple                                    │
                      │           ○ Carrot                                   │
                      │     Pick the produce.                                │
                      │                                                      │
    Field  view mode  │   Basket weight  1200                                │
                      │                                                      │
    Field  view mode  │   Harvest date  2026-07-15                           │
                      │                                                      │
                      │   ▼                                                  │
                      │                                                      │
                      ├──────────────────────────────────────────────────────┤
Footer ▸ KeyHint      │ ↑/↓ to move · ↵ to accept · ESC to cancel             │
                      ╰──────────────────────────────────────────────────────╯
```

The `Basket` field is open, so it's in edit mode: its two entries and its description all belong to that one field. Everything below it is a field in view mode, one line each. A fourth field continues past the bottom edge, which is what the mark under `Harvest date` is for.

Read the labels as a chain. `Header ▸ Breadcrumb` is a section holding a widget. `Content ▸ Panel` is a section holding the widget that holds fields, and each `Field` beneath it is one level deeper again.

## A field has two modes

In **view mode** a field is one line, and it draws every part of that line itself. Open it and it switches to **edit mode**, taking over the region right of its label to collect an answer:

```
view mode    ❯ Basket contents ⁱ  apple, carrot
                                  └─────┬─────┘
                                    the settled value

edit mode    ❯ Basket contents    ◼ Apple
                                  ◻ Carrot
                                  ◻ Tomato
                                  └───┬───┘
                                    the field collecting it
```

The label and the selector stay put across both modes. Only the value region changes shape, which is why a field in edit mode is still one row of its panel rather than something new on the screen. [Anatomy](/widgets/anatomy) names every piece of both.

## Atoms and molecules

A theme draws at two levels, and the difference decides which method you override.

An **atom** is the smallest styled piece. It takes a plain string and returns a styled one, and it knows nothing about what's around it:

```
Orchard  ›  Delivery
───┬───  ┬  ────┬───
   │     │      └── breadcrumbLabel()
   │     └───────── breadcrumbSeparator()
   └─────────────── breadcrumbLabel()
```

A **molecule** composes atoms into a line or a block. It decides order, spacing and how many atoms there are:

```
renderBreadcrumb(['Orchard', 'Delivery'])
        │
        ├── breadcrumbLabel('Orchard')
        ├── breadcrumbSeparator()
        └── breadcrumbLabel('Delivery')
```

Both are public, and they're overridden for different reasons:

| Tier | Shape | Override to |
|---|---|---|
| Atom | `breadcrumbLabel(string $text): string` | change color, weight or glyph |
| Molecule | `renderBreadcrumb(array $segments): string` | change layout, order or what's included |

Molecules are implemented on the abstract theme, so a custom theme inherits working layout for free and touches only the atoms it wants repainted. Overriding a molecule is the rare case: it means you're changing the shape of something, not its palette.

The same split applies to the key hints. `renderKeyHint()` is the molecule; `keyHintKey()`, `keyHintDescription()` and `keyHintSeparator()` are the atoms it composes:

```
↑/↓ to move  ·  ↵ to select
─┬─ ───┬───  ┬
 │     │     └── keyHintSeparator()
 │     └──────── keyHintDescription()
 └────────────── keyHintKey()
```

## Why it's split this way

A section that knew what a breadcrumb was would need to know what every other widget is too. Instead a section knows only that it holds widgets, a widget knows only how to fill the region it's given, a field knows only its own row, and a theme knows only how to style what it's handed. Nothing reaches past its own layer.

That's also what makes an atom reusable. Because `breadcrumbSeparator()` receives no field, no panel and no answers, the same atom can draw the separator inside a form and inside a standalone line of output. An atom that reached for form state could only ever be used from inside a form.
