#!/usr/bin/env node
/**
 * Pin each svg-term <text> run to its exact grid width.
 *
 * svg-term groups consecutive same-styled characters into one <text> element
 * positioned at the run's start column, then lets the viewer's font flow the
 * glyphs within it. When that font's advance width differs from the assumed
 * cell width - any viewer lacking the exact monospace metric the SVG names -
 * a long run drifts off the grid: box-drawing rules end short of the vertical
 * bars they should meet, so a bordered table or panel reads as broken even
 * though the character grid is correct.
 *
 * Setting textLength on every run makes the browser lay it out across exactly
 * (cells * cellWidth), pinning each run to the grid regardless of the font.
 * lengthAdjust="spacingAndGlyphs" stretches the glyphs to fill, so an ASCII
 * hyphen rule reaches its junctions and a Unicode line tiles seamlessly.
 *
 * Used by svg-term-render.js on every render. Runnable directly to migrate
 * already-rendered SVGs in place:
 *
 *   node svg-pin-runs.js <file.svg> [<file.svg> ...]
 */

'use strict';

const fs = require('fs');

// svg-term lays glyphs out at a cell advance of 0.6 * font-size.
const CELL_RATIO = 0.6;
const DEFAULT_FONT_SIZE = 1.67;

/**
 * Count the visible character cells in a run's markup content.
 *
 * Each XML entity (&amp;, &#160;, ...) and each code point is one terminal
 * cell, so entities collapse to a single placeholder before the count.
 *
 * @param {string} content The raw markup between <text> and </text>.
 * @returns {number} The number of cells the run occupies.
 */
function cellCount(content) {
  return Array.from(content.replace(/&[a-zA-Z0-9#]+;/g, 'x')).length;
}

/**
 * Pin every <text> run in an svg-term SVG to its exact grid width.
 *
 * @param {string} svg The SVG markup.
 * @returns {string} The SVG with textLength on each run (idempotent).
 */
function pinRuns(svg) {
  const fontMatch = svg.match(/font-size["'=:\s]+([0-9.]+)/);
  const cellWidth = CELL_RATIO * (fontMatch ? parseFloat(fontMatch[1]) : DEFAULT_FONT_SIZE);

  return svg.replace(/(<text\b[^>]*?)(>)([^<]*)(<\/text>)/g, (whole, head, gt, content, tail) => {
    if (head.includes('textLength')) {
      return whole;
    }

    const cells = cellCount(content);
    if (cells < 1) {
      return whole;
    }

    const length = Math.round(cells * cellWidth * 10000) / 10000;

    return `${head} textLength="${length}" lengthAdjust="spacingAndGlyphs"${gt}${content}${tail}`;
  });
}

module.exports = {pinRuns};

if (require.main === module) {
  const files = process.argv.slice(2);

  if (files.length === 0) {
    console.error('Usage: node svg-pin-runs.js <file.svg> [<file.svg> ...]');
    process.exit(1);
  }

  let changed = 0;
  for (const file of files) {
    const svg = fs.readFileSync(file, 'utf8');
    const pinned = pinRuns(svg);

    if (pinned !== svg) {
      fs.writeFileSync(file, pinned, 'utf8');
      changed++;
    }
  }

  console.log(`Pinned runs in ${changed}/${files.length} file(s).`);
}
