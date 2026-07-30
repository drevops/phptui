---
title: Structure
description: 'How a screen is put together: three fixed sections, widgets inside them, and the two tiers a theme draws with.'
keywords: ['structure', 'layout', 'sections', 'widgets', 'atoms', 'molecules', 'theme']
---

# Structure

A screen is three sections deep, and every section holds widgets. That's the whole layout model. What varies between one screen and the next is which widgets are in which section, never the sections themselves.

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
│   ┌─────────────────────────────────────────────────┐   │
│   │ Buttons                                         │   │
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

Here it is on a real screen:

```
         ╭────────────────────────────────────────────────╮
header   │ Orchard › Delivery                             │
         ├────────────────────────────────────────────────┤
         │                                                │
content  │ ❯ Basket contents ⁱ  apple, carrot             │
         │     Pick the produce for this delivery.        │
         │                                                │
         │   Basket weight  1200                          │
         │   ▼                                            │
         ├────────────────────────────────────────────────┤
footer   │ ↑/↓ to move · ↵ to select · ESC to go back      │
         ╰────────────────────────────────────────────────╯
```

## Everything in a section is a widget

A **widget** is anything that renders itself into a region. The breadcrumb is one. The key hints are one. A panel is one, and so is each field editor inside it.

```
Screen
├─ Header    ──▸ Breadcrumb
├─ Content   ──▸ Panel                    ← the only section that scrolls
│                  ├─ Field ──▸ view mode
│                  └─ Field ──▸ edit mode ──▸ Select
│             ──▸ Buttons
└─ Footer    ──▸ KeyHint
```

That one word covers two things people usually keep apart, and on purpose: a breadcrumb and a select list have nothing in common except the part that matters here, which is that each owns a region and knows how to fill it. A section doesn't care which kind it's holding.

A section can hold more than one. Content holds the panel and the button bar. Footer holds the key hints today and has room for whatever else belongs at the bottom of a screen.

## A field has two modes

A field is not a widget itself - it's a question, and it borrows a widget to collect an answer. In **view mode** the field draws its own single line. Open it and it switches to **edit mode**, handing the region right of its label to a widget:

```
view mode    ❯ Basket contents ⁱ  apple, carrot
                                  └─────┬─────┘
                                     the field draws this

edit mode    ❯ Basket contents    ◼ Apple
                                  ◻ Carrot
                                  ◻ Tomato
                                  └───┬───┘
                                  the Select widget draws this
```

The label and the selector stay put across both modes. Only the value region changes hands. [Anatomy](/widgets/anatomy) names every piece of both.

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

A section that knew what a breadcrumb was would need to know what every other widget is too. Instead a section knows only that it holds widgets, a widget knows only how to fill the region it's given, and a theme knows only how to style what it's handed. Nothing reaches past its own layer.

That's also what makes an atom reusable. Because `breadcrumbSeparator()` receives no field, no panel and no answers, the same atom can draw the separator inside a form and inside a standalone line of output. An atom that reached for form state could only ever be used from inside a form.
