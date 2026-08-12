#!/usr/bin/env node
/**
 * Derive a dark-scheme variant of a rendered architecture diagram SVG.
 *
 * The PlantUML sources in docs/architecture stay the single source of truth:
 * the light SVG is rendered from them, and this derives the dark SVG from that
 * light render by remapping a small, fully-known palette. No second .puml and
 * no hand-drawn dark diagram, so the structure can never drift between the two.
 *
 * The map is safe to hard-code because the palette is tiny and ours: the two
 * sequence diagrams are pure black-on-white, and the component diagram adds
 * only the documented pastel package fills. A colour outside the map aborts
 * generation (a new package colour must be mapped, not silently shipped), and a
 * source colour surviving the remap aborts too (a form we failed to convert).
 *
 * Usage:
 *   node derive-dark-diagram.js docs/architecture/architecture.svg [...more.svg]
 *
 * Each input FOO.svg is written as its sibling FOO-dark.svg. Inputs already
 * ending in -dark.svg are skipped.
 */

const fs = require('fs');

// The root <svg style="...;background:#FFFFFF;"> is the only non-foreground use
// of white. Drop the solid page so the dark diagram floats on whatever hosts it
// - the Docusaurus dark surface and GitHub's dark canvas differ, and a
// transparent backdrop sits cleanly on both. Handled before the colour swaps so
// the background white is gone before the fill white is remapped.
const BACKGROUND = {from: 'background:#FFFFFF', to: 'background:transparent'};

// Source hex (exactly as PlantUML emits it) -> hue-preserving dark equivalent,
// tuned so the light text reads on each. White box/participant/activation fills
// become a raised surface (kept lighter than every package tint so component
// boxes still read as cards); black text and strokes become light grey; each
// pastel package fill becomes a dark tint of the same hue. Keep this table in
// step with the palette documented in the render-phptui-diagrams skill.
const COLORS = {
  '#FFFFFF': '#3b3b3d',
  '#000000': '#e3e3e3',
  '#F0F4C3': '#33351a',
  '#E3F2FD': '#193249',
  '#FFF3E0': '#40301b',
  '#E8F5E9': '#1b3423',
  '#F3E5F5': '#332536',
  '#ECEFF1': '#282d31',
  '#FFEBEE': '#3b2226',
};

const KNOWN = new Set(Object.keys(COLORS));

/**
 * Distinct 6-digit hex colours in an SVG, upper-cased.
 *
 * @param {string} svg
 *   The SVG markup.
 *
 * @return {string[]}
 *   Distinct colours, each like '#RRGGBB'.
 */
function distinctColors(svg) {
  const found = svg.match(/#[0-9A-Fa-f]{6}/g) || [];

  return Array.from(new Set(found.map((color) => color.toUpperCase())));
}

/**
 * Derive the dark-scheme SVG from a light-scheme SVG.
 *
 * @param {string} svg
 *   The light-scheme SVG markup.
 *
 * @return {string}
 *   The dark-scheme SVG markup.
 *
 * @throws {Error}
 *   When the input carries a colour outside the palette, or a source colour
 *   survives the remap.
 */
function deriveDarkSvg(svg) {
  const unknown = distinctColors(svg).filter((color) => !KNOWN.has(color));
  if (unknown.length > 0) {
    throw new Error(`Unmapped colour(s) ${unknown.join(', ')}: extend the palette in derive-dark-diagram.js (or the input is already dark).`);
  }

  // Case insensitive throughout to survive a future lower-cased render.
  let out = svg.replace(new RegExp(BACKGROUND.from, 'gi'), BACKGROUND.to);

  // Bare-hex swaps are form-agnostic, so they catch a colour whether it sits in
  // a fill="..." presentation attribute or an inline style="stroke:...".
  for (const [from, to] of Object.entries(COLORS)) {
    out = out.replace(new RegExp(from, 'gi'), to);
  }

  const survived = distinctColors(out).filter((color) => KNOWN.has(color));
  /* istanbul ignore next -- defensive invariant: unreachable unless a swap target re-introduces a source colour. */
  if (survived.length > 0) {
    throw new Error(`Source colour(s) ${survived.join(', ')} survived the remap - an unhandled markup form.`);
  }

  return out;
}

/**
 * Print a message unless quiet.
 *
 * @param {string} message
 *   The message to print.
 */
function log(message) {
  if (process.env.SCRIPT_QUIET === '1') {
    return;
  }

  console.log(message);
}

/**
 * Derive dark variants for the given SVG paths.
 *
 * @param {string[]} argv
 *   CLI arguments: the light SVG paths.
 */
function main(argv) {
  const inputs = argv.filter((argument) => !argument.startsWith('-'));

  if (inputs.length === 0 || argv.includes('--help')) {
    console.log('Usage: node derive-dark-diagram.js <diagram.svg> [...more.svg]');
    process.exit(argv.includes('--help') ? 0 : 1);
  }

  for (const input of inputs) {
    if (input.endsWith('-dark.svg')) {
      log(`Skipped ${input} (already a dark variant).`);
      continue;
    }

    const dark = deriveDarkSvg(fs.readFileSync(input, 'utf8'));
    const output = input.replace(/\.svg$/, '-dark.svg');
    fs.writeFileSync(output, dark, 'utf8');
    log(`Wrote ${output}`);
  }
}

module.exports = {deriveDarkSvg, distinctColors, COLORS, main};

/* istanbul ignore next -- entry guard; main() is covered directly by tests. */
if (require.main === module) {
  try {
    main(process.argv.slice(2));
  } catch (error) {
    console.error(`Error: ${error.message}`);
    process.exit(1);
  }
}
