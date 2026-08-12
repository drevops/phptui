# Architecture diagrams

The PlantUML sources behind the diagrams on **[phptui.dev/specification](https://phptui.dev/specification#how-it-runs)**, and the SVGs rendered from them. This directory is served as a static directory, so each `.svg` here is reachable at the site root.

| Source                  | Renders                                              | Shows                                                     |
| ----------------------- | ---------------------------------------------------- | --------------------------------------------------------- |
| `architecture.puml`     | `architecture.svg`, `architecture-dark.svg`          | the `src/` packages around the block tree, and their edges |
| `dataflow-collect.puml` | `dataflow-collect.svg`, `dataflow-collect-dark.svg`  | headless collection, with no screen anywhere               |
| `dataflow-tui.puml`     | `dataflow-tui.svg`, `dataflow-tui-dark.svg`          | an interactive session, keys inward and drawing outward    |

The walkthrough that embeds them - what you assemble to build a form, and what happens when it runs - is the [How it runs](https://phptui.dev/specification#how-it-runs) part of [`docs/content/specification.mdx`](../content/specification.mdx), below the model it implements. It is derived from `src/`, so if the prose and the code disagree, the code wins.

## Regenerating

Each `.puml` renders to a light `.svg`, and a dark `-dark.svg` is derived from it. After editing a source, re-render and re-derive:

    plantuml -tsvg docs/architecture/*.puml
    node docs/util/derive-dark-diagram.js docs/architecture/*.svg

The [`render-phptui-diagrams`](../../.claude/skills/render-phptui-diagrams/SKILL.md) skill covers rendering, adding a new data-flow diagram, and keeping the walkthrough current. After any structural change, update the walkthrough and re-render in the same pass so the prose and the visuals agree.
