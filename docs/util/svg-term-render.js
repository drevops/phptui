#!/usr/bin/env node
/**
 * Custom svg-term renderer with configurable lineHeight.
 *
 * This script uses svg-term as a library to have full control over the theme,
 * specifically setting lineHeight to fix box-drawing character rendering.
 *
 * Usage:
 *   node svg-term-render.js <input.json> <output.svg> [options]
 *
 * Options:
 *   --at <ms>          Timestamp of frame to render
 *   --line-height <n>  Line height multiplier (default: 1.0)
 *   --light            Render on a light surface (for light-mode palettes)
 *   --dos              Render on a CGA blue surface (for the dos theme)
 *   --font-family <s>  Font family (default: Consolas, monospace)
 */

const fs = require('fs');
const {render} = require('svg-term');
const {load} = require('load-asciicast');
const {pinRuns} = require('./svg-pin-runs');

// Parse command line arguments.
const args = process.argv.slice(2);

if (args.length < 2 || args.includes('--help')) {
  console.log('Usage: node svg-term-render.js <input.json> <output.svg> [options]');
  console.log('');
  console.log('Options:');
  console.log('  --at <ms>          Timestamp of frame to render');
  console.log('  --line-height <n>  Line height multiplier (default: 1.0)');
  console.log('  --light            Render on a light surface (for light-mode palettes)');
  console.log('  --dos              Render on a CGA blue surface (for the dos theme)');
  console.log('  --font-family <s>  Font family (default: Consolas, monospace)');
  process.exit(args.includes('--help') ? 0 : 1);
}

const inputFile = args[0];
const outputFile = args[1];

// Parse options.
let at = null;
let lineHeight = 1.0;
let light = false;
let dos = false;
let fontFamily = 'Consolas, "Courier New", Courier, "Liberation Mono", monospace';

for (let i = 2; i < args.length; i++) {
  if (args[i] === '--at' && i + 1 < args.length) {
    at = parseInt(args[i + 1], 10);
    i++;
  } else if (args[i] === '--line-height' && i + 1 < args.length) {
    lineHeight = parseFloat(args[i + 1]);
    i++;
  } else if (args[i] === '--light') {
    light = true;
  } else if (args[i] === '--dos') {
    dos = true;
  } else if (args[i] === '--font-family' && i + 1 < args.length) {
    fontFamily = args[i + 1];
    i++;
  }
}

// Read input cast file and normalize it.
// svg-term only supports asciicast v1 and v2 formats, but asciinema 3.x
// produces v3 format with two breaking differences:
//   1. Header uses {term: {cols, rows, type}} instead of {width, height}
//   2. Timestamps are relative (delta from previous event) not absolute
// Additionally, v3 introduces event type "x" (exit) which v2 doesn't have.
//
// With --at, all output up to the timestamp is also collapsed into a single
// event. svg-term's own `at` picks the frame with the *nearest* timestamp and
// keeps every frame sharing it - when the empty terminal-setup frame and the
// first paint land on the same quantized stamp, both render side by side and
// the visible viewport shows the empty one. A single merged event carries the
// full screen state at the timestamp, so exactly one frame can ever exist.
let input = fs.readFileSync(inputFile, 'utf8');
const lines = input.split('\n');
if (lines.length > 0) {
  try {
    const header = JSON.parse(lines[0]);
    const isV3 = header.version === 3;
    if (isV3) {
      header.version = 2;
      if (header.term) {
        header.width = header.term.cols;
        header.height = header.term.rows;
        if (!header.env) {
          header.env = {};
        }
        if (header.term.type) {
          header.env.TERM = header.term.type;
        }
        delete header.term;
      }
    }
    // Collect output events with absolute timestamps, dropping other types.
    const events = [];
    let absoluteTime = 0;
    for (let i = 1; i < lines.length; i++) {
      const line = lines[i].trim();
      if (!line) {
        continue;
      }
      try {
        const event = JSON.parse(line);
        absoluteTime = isV3 ? absoluteTime + event[0] : event[0];
        if (event[1] === 'o') {
          events.push([parseFloat(absoluteTime.toFixed(6)), 'o', event[2]]);
        }
      } catch (_) {
        // Skip malformed lines.
      }
    }
    let outEvents = events;
    if (at !== null) {
      const merged = events.filter((event) => event[0] * 1000 <= at).map((event) => event[2]).join('');
      if (merged === '') {
        console.error(`Error rendering SVG: no terminal output before --at ${at}ms`);
        process.exit(1);
      }
      outEvents = [[0, 'o', merged]];
    }
    input = [JSON.stringify(header)].concat(outEvents.map((event) => JSON.stringify(event))).join('\n') + '\n';
  } catch (_) {
    // Not valid JSON header - let svg-term handle the error.
  }
}

// Define custom theme with lineHeight set to 1.0.
// Based on Atom One Dark theme colors.
// Note: svg-term 1.3.1 expects RGB arrays, not hex strings.
const theme = {
  background: [40, 44, 52],       // #282c34
  text: [171, 178, 191],          // #abb2bf
  cursor: [82, 139, 255],         // #528bff
  black: [40, 44, 52],            // #282c34
  red: [224, 108, 117],           // #e06c75
  green: [152, 195, 121],         // #98c379
  yellow: [209, 154, 102],        // #d19a66
  blue: [97, 175, 239],           // #61afef
  magenta: [198, 120, 221],       // #c678dd
  cyan: [86, 182, 194],           // #56b6c2
  white: [171, 178, 191],         // #abb2bf
  brightBlack: [92, 99, 112],     // #5c6370
  brightRed: [224, 108, 117],     // #e06c75
  brightGreen: [152, 195, 121],   // #98c379
  brightYellow: [209, 154, 102],  // #d19a66
  brightBlue: [97, 175, 239],     // #61afef
  brightMagenta: [198, 120, 221], // #c678dd
  brightCyan: [86, 182, 194],     // #56b6c2
  brightWhite: [255, 255, 255],   // #ffffff
  bold: [171, 178, 191],          // #abb2bf
  fontSize: 1.67,
  lineHeight: lineHeight,
  fontFamily: fontFamily,
};

// A light surface for the themes' light palettes. The 256-colour accents are
// fixed xterm RGB and already read on both backgrounds; only the surface and
// the greyscale text (default and bold) need to flip to dark, mirroring
// svg-light-twin.php so the light twins share one look.
if (light) {
  theme.background = [250, 250, 250]; // #fafafa
  theme.text = [56, 58, 66];          // #383a42
  theme.bold = [40, 44, 52];          // #282c34
}

// The MS-DOS blue surface for the dos theme: the classic CGA blue background
// with the bright CGA foreground palette, so the 16-colour dos theme reads as
// an EDIT.COM / QBasic screen rather than the Atom One Dark defaults.
if (dos) {
  theme.background = [0, 0, 170];      // #0000aa CGA blue
  theme.text = [170, 170, 170];        // #aaaaaa CGA light grey
  theme.bold = [255, 255, 255];        // #ffffff
  theme.brightWhite = [255, 255, 255]; // #ffffff
  theme.brightCyan = [85, 255, 255];   // #55ffff CGA bright cyan
  theme.brightYellow = [255, 255, 85]; // #ffff55 CGA bright yellow
  theme.brightBlack = [128, 128, 128]; // #808080 - legible on the blue

  // svg-term paints a cell's background from its own bundled palette, not the
  // theme above: its Word renderer builds the background rect without passing
  // the custom theme through, so the rect falls back to svg-term's own
  // DEFAULT_THEME. The dos screen wash is ANSI background 4 (OnBlue), which in
  // that default palette is a light Atom One Dark blue - so the wash renders
  // light over the dark-blue surface while the surface itself (which does read
  // theme.background) stays dark. Point palette entry 4 at the same CGA blue so
  // the whole screen washes one uniform blue.
  //
  // svg-term 1.3.1 exports that palette object directly; a later major wraps it
  // as { defaultTheme }. Resolve both shapes so a version bump cannot leave the
  // mutation stranded on an empty wrapper and silently revert the wash.
  const svgTermDefault = require('svg-term/lib/default-theme');
  const svgTermPalette = svgTermDefault.defaultTheme || svgTermDefault;
  svgTermPalette[4] = [0, 0, 170];
}

// Render options.
const options = {
  theme: theme,
};

// With --at, load the merged cast and keep only its final frame. The cast
// loader synthesizes an initial blank screen state at the same stamp as the
// merged event, and svg-term renders every frame sharing the selected stamp -
// side by side, with the blank one filling the viewport. Passing exactly one
// frame makes that impossible.
let renderInput = input;
if (at !== null) {
  const cast = load(renderInput);
  renderInput = {
    version: cast.version,
    width: cast.width,
    height: cast.height,
    duration: 0,
    frames: [[0, cast.frames[cast.frames.length - 1][1]]],
  };
}

// Render SVG.
try {
  // Pin every text run to its grid width so box-drawing borders stay aligned
  // on viewers whose monospace font does not match the assumed cell advance.
  const svg = pinRuns(render(renderInput, options));
  fs.writeFileSync(outputFile, svg, 'utf8');
  console.log(`SVG rendered successfully: ${outputFile}`);
  console.log(`  lineHeight: ${lineHeight}`);
  console.log(`  fontFamily: ${fontFamily}`);
  if (at !== null) {
    console.log(`  at: ${at}ms`);
  }
} catch (error) {
  console.error('Error rendering SVG:', error.message);
  process.exit(1);
}
