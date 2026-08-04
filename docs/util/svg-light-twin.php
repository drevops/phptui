<?php

declare(strict_types=1);

/**
 * @file
 * Derive the light-scheme twin of a dark terminal SVG by remapping the dark
 * palette's surface and foreground greys to light equivalents. The accent
 * colours (teal caret, green values, blue cursor, muted grey) already read on
 * both backgrounds, so only the surface and foreground greys invert.
 * Deterministic and exact: a twin shares its dark source's geometry.
 *
 * Shared by update-assets.php and render-field-svgs.php so every generator
 * emits its complete dark/light pair the moment a dark SVG lands - there is
 * no separate twin pass to run or subject list to maintain.
 * render-theme-svgs.php does not use it: theme previews render their light
 * palettes for real rather than deriving them.
 */

/**
 * Write the light twin beside a dark SVG.
 *
 * @param string $dark_file
 *   The '<subject>-dark-*.svg' source path.
 *
 * @return string
 *   The written light twin's path.
 */
function deriveLightTwin(string $dark_file): string {
  // strtr() applies all pairs simultaneously against the original, so the
  // background target and the emphasis target never cascade into each other.
  $map = [
    'rgb(40,44,52)' => 'rgb(250,250,250)',
    'rgb(171,178,191)' => 'rgb(56,58,66)',
    'rgb(185,192,203)' => 'rgb(40,44,52)',
    'rgb(128,128,128)' => 'rgb(90,96,105)',
  ];

  $name = basename($dark_file);

  if (!str_contains($name, '-dark-')) {
    throw new \RuntimeException('Not a dark SVG: ' . $dark_file);
  }

  $dark = file_get_contents($dark_file);

  if ($dark === FALSE) {
    throw new \RuntimeException('Failed to read source: ' . $dark_file);
  }

  $out = dirname($dark_file) . '/' . str_replace('-dark-', '-light-', $name);

  if (file_put_contents($out, strtr($dark, $map)) === FALSE) {
    throw new \RuntimeException('Failed to write: ' . $out);
  }

  return $out;
}
