#!/usr/bin/env php
<?php

/**
 * @file
 * Audit every generated SVG asset - the post-generation and CI gate.
 *
 * Scans the committed assets for every defect class the pipeline has ever
 * produced, so a bad asset fails loudly instead of shipping:
 * - recording-setup leaks: the expect spawn announcement, pty geometry or
 *   filesystem paths painted into frames;
 * - wrapped rows: a right-aligned provenance badge whose tail landed at the
 *   left edge of the following line;
 * - expect interaction failures captured into the recording;
 * - demo-content violations: technology words the produce theme forbids;
 * - structural defects: missing text, suspiciously small files, static
 *   captures holding more than one frame window;
 * - per-demo content needles, word by word, the same way the generation
 *   pipeline verifies them;
 * - dark/light twin pairing, so the pairs the documentation serves in both
 *   colour schemes never drift apart.
 *
 * The update-assets orchestrator runs this after a full regeneration, and
 * the docs CI workflow runs it against the committed assets on every push.
 *
 * Usage:
 * @code
 * php docs/util/audit-svgs.php
 * php docs/util/audit-svgs.php path/to/assets
 * @endcode
 */

declare(strict_types=1);

// Text that only enters a frame when recording setup leaks into it. The
// demo content is produce-themed, so none of these can occur legitimately.
const SETUP_FINGERPRINTS = ['spawn', 'LINES=', 'COLUMNS=', '/home/user', 'playground/', '.php'];

// A crashed expect interaction prints its trace into the pty.
const EXPECT_MARKERS = ['invalid command name', 'while executing', 'usage: ', 'Traceback'];

// The demo-content rule bans software references outright; these are the
// stand-ins most likely to sneak back in through copied examples.
const TECH_WORDS = ['Redis', 'Solr', 'ClamAV', 'Drupal', 'docker', 'npm '];

// This project's own name, which the produce theme has no word for. A demo
// resolving an answer against the run context reads the directory it was run
// from, so a checkout named for the library paints that name into the frame
// unless the recording is pinned somewhere else. Matched whole-word and with
// any trailing digits a clone's directory carries, so a name merely containing
// the letters never trips it.
const PROJECT_NAMES = ['tui'];

// The provenance badge labels; a wrap tears them apart at a line boundary.
const BADGE_WORDS = ['edited', 'derived', 'discovered', 'default'];

/**
 * Per-demo content needles, matched word by word like the pipeline does.
 *
 * A needle missing from a committed asset means the recording no longer
 * shows the moment its demo exists to show. Kept to the recorded demos;
 * the deterministic field and theme renders verify themselves at
 * generation time.
 *
 * @return array<string, list<string>>
 *   Needles keyed by asset filename.
 */
function contentNeedles(): array {
  return [
    'fields-dark-animated.svg' => ['Pause'],
    'produce-box-dark-animated.svg' => ['Contents'],
    'derived-values-dark-animated.svg' => ['red_plum'],
    'conditional-fields-dark-animated.svg' => ['Herb bundle'],
    'conditional-indent-dark-animated.svg' => ['Weekly delivery?'],
    'inline-editing-dark-animated.svg' => ['Harvest date'],
    'key-bindings-vim-dark-animated.svg' => ['Fruit'],
    'translations-dark-animated.svg' => ['Фрукти'],
    'quickstart-dark-static.svg' => ['Vegetables'],
    'headless-collect-dark-static.svg' => ['Weekly Box'],
    'testing-dark-static.svg' => ['final frame'],
    'bordered-panels-dark-animated.svg' => ['Basics'],
    'borderless-panels-dark-animated.svg' => ['Basics'],
    'nested-panels-dark-animated.svg' => ['Order'],
    'modal-panels-dark-animated.svg' => ['Gift options'],
    'fullscreen-panels-dark-animated.svg' => ['Order name'],
    'panel-layout-dark-animated.svg' => ['Vegetables'],
    'theme-ocean-dark-animated.svg' => ['Seaside stall'],
    'discovery-dark-static.svg' => ['Box name'],
    'field-password-reveal-dark-static.svg' => ['melon7'],
  ];
}

/**
 * Audit one SVG file.
 *
 * @param string $file
 *   Path to the SVG.
 * @param list<string> $names
 *   Every asset filename in the set, for twin pairing.
 *
 * @return list<string>
 *   Human-readable issues, empty when the file is clean.
 */
function auditFile(string $file, array $names): array {
  $name = basename($file);
  $content = file_get_contents($file);

  if ($content === FALSE || $content === '') {
    return [$name . ': unreadable or empty'];
  }

  $issues = [];

  if (strlen($content) < 800) {
    $issues[] = $name . ': suspiciously small (' . strlen($content) . ' bytes)';
  }

  if (!str_contains($content, '<text')) {
    $issues[] = $name . ': no text content';

    return $issues;
  }

  $text = html_entity_decode(strip_tags($content));

  foreach (SETUP_FINGERPRINTS as $fingerprint) {
    if (str_contains($text, $fingerprint)) {
      $issues[] = sprintf('%s: setup leak "%s"', $name, $fingerprint);
    }
  }

  foreach (EXPECT_MARKERS as $marker) {
    if (str_contains($text, $marker)) {
      $issues[] = sprintf('%s: expect failure marker "%s"', $name, $marker);
    }
  }

  foreach (TECH_WORDS as $word) {
    if (str_contains($text, $word)) {
      $issues[] = sprintf('%s: demo-content violation "%s"', $name, $word);
    }
  }

  $words = spacedText($content);

  foreach (PROJECT_NAMES as $project) {
    if (preg_match('/\b' . preg_quote($project, '/') . '\d*\b/i', $words, $found) === 1) {
      $issues[] = sprintf('%s: project name "%s" in a frame - the recording read its own directory', $name, $found[0]);
    }
  }

  foreach (wrapSignatures($content) as $signature) {
    $issues[] = sprintf('%s: %s', $name, $signature);
  }

  // A static render holds exactly one frame window; a second one sits
  // outside the viewBox and hides the real content behind an empty frame.
  if (str_contains($name, '-static') && substr_count($content, '<use xlink:href="#a"') > 1) {
    $issues[] = $name . ': static file holds multiple frame windows';
  }

  foreach (contentNeedles()[$name] ?? [] as $needle) {
    foreach (preg_split('/\s+/', $needle) ?: [] as $word) {
      if ($word !== '' && !str_contains($text, $word)) {
        $issues[] = sprintf('%s: missing content needle word "%s"', $name, $word);
      }
    }
  }

  // The ocean recording demonstrates a custom palette and deliberately has
  // no light twin; every other dark asset pairs with one.
  if (str_contains($name, '-dark-')) {
    $twin = str_replace('-dark-', '-light-', $name);

    if ($name !== 'theme-ocean-dark-animated.svg' && !in_array($twin, $names, TRUE)) {
      $issues[] = $name . ': missing light twin';
    }
  }
  elseif (str_contains($name, '-light-') && !in_array(str_replace('-light-', '-dark-', $name), $names, TRUE)) {
    $issues[] = $name . ': missing dark twin';
  }

  return $issues;
}

/**
 * An SVG's text with the runs kept apart.
 *
 * The renderer emits one text node per styled run, so stripping the markup
 * runs neighbouring words together and a word boundary stops meaning
 * anything. Replacing each tag with a space keeps the boundaries a pattern can
 * be written against.
 *
 * @param string $content
 *   The SVG markup.
 *
 * @return string
 *   The text content, one space between runs.
 */
function spacedText(string $content): string {
  return html_entity_decode((string) preg_replace('/<[^>]*>/', ' ', $content));
}

/**
 * Wrapped-row signatures in a rendered SVG.
 *
 * A right-aligned badge that overflows the terminal hard-wraps: its tail
 * chip and label fragment land at column zero of the next line, where no
 * badge can legitimately start mid-word. Complete chips at the left edge
 * (a hub summary badge) are fine; narrow partial chips are not.
 *
 * @param string $content
 *   The SVG markup.
 *
 * @return list<string>
 *   Signature descriptions, empty when none found.
 */
function wrapSignatures(string $content): array {
  $signatures = [];

  $suffixes = [];
  foreach (BADGE_WORDS as $word) {
    for ($i = 1; $i < strlen($word); $i++) {
      $suffixes[substr($word, $i)] = TRUE;
    }
  }

  $chip_classes = [];
  if (preg_match_all('/\.([a-z]+)\{fill:rgb\(185,192,203\)\}/', $content, $matches)) {
    $chip_classes = $matches[1];
  }

  foreach ($chip_classes as $chip_class) {
    if (!preg_match_all('/<rect[^>]*class="' . $chip_class . '"[^>]*>/', $content, $rects)) {
      continue;
    }

    foreach ($rects[0] as $rect) {
        if (!preg_match('/\bwidth="([0-9.]+)"/', $rect, $width)) {
            continue;
        }
        if (!preg_match('/\bx="([0-9.]+)"/', $rect, $x)) {
            continue;
        }
        if ((float) $x[1] > 3.0) {
            continue;
        }
        if ((float) $width[1] >= 8.0) {
            continue;
        }
        $signatures[] = 'wrapped badge tail chip at x=' . $x[1] . ' width=' . $width[1];
    }
  }

  if (preg_match_all('/<text x="([0-9.]+)"[^>]*>([^<]*)<\/text>/', $content, $texts, PREG_SET_ORDER)) {
    foreach ($texts as $text) {
      $word = trim(html_entity_decode($text[2]));

      if ((float) $text[1] <= 3.0 && $word !== '' && isset($suffixes[$word])) {
        $signatures[] = 'wrapped badge fragment "' . $word . '" at x=' . $text[1];
      }
    }
  }

  return $signatures;
}

/**
 * Main functionality.
 */
function main(): void {
  global $argv;

  $assets_dir = rtrim($argv[1] ?? dirname(__DIR__) . '/assets', '/');
  $files = glob($assets_dir . '/*.svg');

  if ($files === FALSE || $files === []) {
    throw new \RuntimeException('No SVG assets found in: ' . $assets_dir);
  }

  sort($files);
  $names = array_map(basename(...), $files);
  $issues = [];

  foreach ($files as $file) {
    foreach (auditFile($file, $names) as $issue) {
      $issues[] = $issue;
    }
  }

  if ($issues !== []) {
    foreach ($issues as $issue) {
      info($issue);
    }

    throw new \RuntimeException(count($issues) . ' issue(s) across ' . count($files) . ' asset(s).');
  }

  info('CLEAN: ' . count($files) . ' assets audited, no issues.');
}

/**
 * Print an informational message.
 *
 * @param string $message
 *   The message to print.
 */
function info(string $message): void {
  if (getenv('SCRIPT_QUIET') === '1') {
    return;
  }
  print $message . PHP_EOL;
}

// Entrypoint.
ini_set('display_errors', '1');

if (PHP_SAPI !== 'cli' || !empty($_SERVER['REMOTE_ADDR'])) {
  die('This script can be only ran from the command line.');
}

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
  if ((error_reporting() & $severity) === 0) {
    return FALSE;
  }
  throw new \ErrorException($message, 0, $severity, $file, $line);
});

try {
  main();
}
catch (\Exception $exception) {
  info('');
  info('ERROR: ' . $exception->getMessage());
  exit(1);
}
