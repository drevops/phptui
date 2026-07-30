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

## Four levels, and what each one means

```
Screen
└─ Section     Header | Content | Footer      fixed; there are three
   └─ Widget   Breadcrumb, Panel, KeyHint     whatever a section holds
      └─ Field   (Panel only)                 a question in a panel
         └─ Mode   view | edit                how the field draws itself
```

A **widget** is anything a section holds. That's the whole definition: it owns a region and knows how to fill it, and the section knows nothing else about it. A breadcrumb is one, a key-hint line is one, a panel is one.

A **field** is not a widget. A Select, a Calendar, a Text field - none of them can sit in a section on their own, because each needs a label, a row and a panel around it. They're tenants of a Panel, which is the widget that holds them.

The test is worth keeping: if a section could hold it by itself, it's a widget. If it needs a panel around it, it's a field.

A section can hold more than one widget. Header holds the breadcrumb today and Footer the key hints, and both have room for whatever else belongs at the top or bottom of a screen.

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
