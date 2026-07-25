#!/usr/bin/env php
<?php

/**
 * @file
 * Render the documentation social card PNG from a generated SVG asset.
 *
 * Composes the quick-start terminal recording (a static SVG the asset
 * pipeline already produces) with the site branding on a 1200x630 canvas -
 * the Open Graph / Twitter card size - and screenshots it through
 * agent-browser, so the card is rendered by a real browser engine with no
 * extra image tooling. The result lands in docs/assets alongside the SVGs
 * and is referenced by `themeConfig.image` in docusaurus.config.js.
 *
 * Dependencies: agent-browser (the repository's sanctioned browser driver).
 *
 * Usage:
 * @code
 * php docs/util/render-social-card.php
 * @endcode
 */

declare(strict_types=1);

// The Open Graph card size every major platform renders natively.
const CARD_WIDTH = 1200;
const CARD_HEIGHT = 630;

// The composed base asset: the quick-start form, dark, single frame.
const BASE_SVG = 'quickstart-dark-static.svg';

const OUTPUT_PNG = 'social-card.png';

// The brand mark shared with the navbar and favicon, inlined into the card so
// the logo has a single source across the site and the social image.
const LOGO_SVG = 'static/img/logo.svg';

/**
 * Print a progress message.
 *
 * @param string $message
 *   The message.
 */
function info(string $message): void {
  print $message . PHP_EOL;
}

/**
 * Run an external command, throwing when it fails.
 *
 * @param array $parts
 *   The command and its arguments, escaped individually.
 *
 * @return string
 *   The command's stdout.
 */
function run(array $parts): string {
  $cmd = implode(' ', array_map(escapeshellarg(...), $parts));
  exec($cmd . ' 2>&1', $output_lines, $exit_code);
  $output = implode(PHP_EOL, $output_lines);

  if ($exit_code !== 0) {
    throw new \RuntimeException('Command failed (' . $exit_code . '): ' . $cmd . PHP_EOL . $output);
  }

  return $output;
}

/**
 * Embed a local image file as a data URI.
 *
 * @param string $path
 *   The file path.
 * @param string $mime
 *   The MIME type.
 *
 * @return string
 *   The data URI.
 */
function dataUri(string $path, string $mime): string {
  $content = file_get_contents($path);

  if ($content === FALSE) {
    throw new \RuntimeException('Cannot read: ' . $path);
  }

  return 'data:' . $mime . ';base64,' . base64_encode($content);
}

/**
 * Read an SVG file for inlining directly into the document.
 *
 * @param string $path
 *   The file path.
 *
 * @return string
 *   The raw SVG markup.
 */
function inlineSvg(string $path): string {
  $content = file_get_contents($path);

  if ($content === FALSE) {
    throw new \RuntimeException('Cannot read: ' . $path);
  }

  return $content;
}

/**
 * Build the card's HTML document.
 *
 * @param string $terminal_uri
 *   The terminal SVG as a data URI.
 * @param string $logo_svg
 *   The brand logo as inline SVG markup.
 *
 * @return string
 *   The HTML.
 */
function cardHtml(string $terminal_uri, string $logo_svg): string {
  $width = CARD_WIDTH;
  $height = CARD_HEIGHT;

  return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  html, body { width: {$width}px; height: {$height}px; overflow: hidden; }
  body {
    display: flex; align-items: center; gap: 44px;
    padding: 0 0 0 64px;
    background: linear-gradient(160deg, #21252b 0%, #1a1d23 100%);
    font-family: ui-monospace, 'SF Mono', Menlo, Consolas, 'Liberation Mono', monospace;
    position: relative;
  }
  body::before {
    content: ''; position: absolute; left: 0; top: 0; right: 0; height: 6px;
    background: linear-gradient(90deg, #2dd4bf, #06b6ad 55%, transparent);
  }
  .brand { width: 400px; flex: none; }
  .brand .logo svg { display: block; width: 320px; height: auto; }
  .brand .logo svg .tui-letter { fill: #E6E2D6 !important; }
  .brand p { margin-top: 26px; font-size: 30px; line-height: 1.35; color: #eae4d4; }
  .brand .url { margin-top: 30px; font-size: 24px; font-weight: 700; color: #2dd4bf; }
  .term {
    flex: none; border: 1px solid rgba(72, 79, 93, .55); border-radius: 12px;
    background: #282c34; overflow: hidden;
    box-shadow: 0 30px 70px -30px rgba(0, 0, 0, .9);
  }
  .term .bar {
    display: flex; align-items: center; gap: 7px; height: 34px; padding: 0 14px;
    background: #1a1d23; border-bottom: 1px solid rgba(72, 79, 93, .32);
  }
  .term .bar span { width: 11px; height: 11px; border-radius: 50%; background: rgba(72, 79, 93, .8); }
  .term .bar span:first-child { background: #e06c75; }
  .term .bar span:nth-child(2) { background: #e5c07b; }
  .term .bar span:nth-child(3) { background: #98c379; }
  .term img { display: block; width: 660px; height: auto; }
</style>
</head>
<body>
  <div class="brand">
    <div class="logo">{$logo_svg}</div>
    <p>Terminal user interfaces for&nbsp;PHP</p>
    <div class="url">phptui.dev</div>
  </div>
  <div class="term">
    <div class="bar"><span></span><span></span><span></span></div>
    <img src="{$terminal_uri}" alt="">
  </div>
</body>
</html>
HTML;
}

$script_dir = __DIR__;
$project_dir = dirname($script_dir, 2);
$assets_dir = dirname($script_dir) . '/assets';
$svg_path = $assets_dir . '/' . BASE_SVG;
$png_path = $assets_dir . '/' . OUTPUT_PNG;
$logo_path = dirname($script_dir) . '/' . LOGO_SVG;

if (!file_exists($svg_path)) {
  throw new \RuntimeException('Base asset missing: ' . $svg_path . ' - run the asset pipeline first.');
}

if (!file_exists($logo_path)) {
  throw new \RuntimeException('Logo asset missing: ' . $logo_path);
}

info('TUI - Social card');
info('=================');

$tmp_dir = $project_dir . '/.artifacts/tmp/social-card';

if (!is_dir($tmp_dir)) {
  mkdir($tmp_dir, 0755, TRUE);
}

$html_path = $tmp_dir . '/social-card.html';
$html = cardHtml(dataUri($svg_path, 'image/svg+xml'), inlineSvg($logo_path));

// A failed write must not let the browser screenshot a stale wrapper file.
if (file_put_contents($html_path, $html) === FALSE) {
  throw new \RuntimeException('Cannot write: ' . $html_path);
}

info('Rendering ' . CARD_WIDTH . 'x' . CARD_HEIGHT . ' via agent-browser...');

$session = 'tui-social-card';
run(['agent-browser', '--session', $session, 'open', 'file://' . $html_path]);

try {
  run(['agent-browser', '--session', $session, 'set', 'viewport', (string) CARD_WIDTH, (string) CARD_HEIGHT]);
  run(['agent-browser', '--session', $session, 'screenshot', $png_path]);
}
finally {
  run(['agent-browser', '--session', $session, 'close']);
}

$size = getimagesize($png_path);

if ($size === FALSE) {
  throw new \RuntimeException('The screenshot is not a readable image: ' . $png_path);
}

// A HiDPI browser profile would double the pixel size; the card must be the
// exact Open Graph geometry, so anything else fails loudly.
if ($size[0] !== CARD_WIDTH || $size[1] !== CARD_HEIGHT) {
  throw new \RuntimeException(sprintf('Expected %dx%d, got %dx%d: %s', CARD_WIDTH, CARD_HEIGHT, $size[0], $size[1], $png_path));
}

info('Wrote ' . $png_path . ' (' . $size[0] . 'x' . $size[1] . ')');
info('Done.');
