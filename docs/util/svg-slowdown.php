<?php

/**
 * @file
 * Shared playback-speed helper for the SVG generators.
 *
 * Both update-assets.php and render-field-svgs.php emit animated SVGs and slow
 * them to the same factor, so the constant and the scaler live here and are
 * required by both.
 */

declare(strict_types=1);

// Every animated SVG this project ships runs at this fraction of its recorded
// speed.
const ANIMATION_SLOWDOWN = 1.25;

/**
 * Scale every animation duration in an SVG by a factor.
 *
 * @param string $svg
 *   The SVG markup.
 * @param float $factor
 *   The multiplier; greater than one slows the animation down.
 *
 * @return string
 *   The SVG with scaled durations.
 */
function slowAnimation(string $svg, float $factor): string {
  return (string) preg_replace_callback(
    '/animation-duration:([0-9.]+)s/',
    static fn(array $matches): string => 'animation-duration:' . rtrim(rtrim(sprintf('%.3f', (float) $matches[1] * $factor), '0'), '.') . 's',
    $svg
  );
}
