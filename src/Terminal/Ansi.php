<?php

declare(strict_types=1);

namespace DrevOps\Tui\Terminal;

use DrevOps\Tui\Utils\Strings;

/**
 * ANSI helpers: styling, escape stripping and visible-width alignment.
 *
 * @package DrevOps\Tui\Terminal
 */
final class Ansi {

  /**
   * The escape character.
   */
  public const string ESC = "\033";

  /**
   * The sequence closing an OSC 8 hyperlink.
   */
  protected const string LINK_CLOSE = self::ESC . ']8;;' . "\007";

  /**
   * An OSC 8 hyperlink wrapper, BEL- or ST-terminated.
   */
  protected const string LINK_SEQUENCE = '\033\]8;[^\007\033]*(?:\007|\033\\\\)';

  /**
   * A CSI sequence; CSI sequences carry the styling.
   */
  protected const string STYLE_SEQUENCE = '\033\[[0-9;?<>=]*[A-Za-z]';

  /**
   * Wrap text in an SGR style, resetting afterwards.
   *
   * @param string $text
   *   The text.
   * @param string $sgr
   *   The SGR parameters (e.g. "1;32"); empty leaves the text unstyled.
   *
   * @return string
   *   The styled text.
   */
  public static function style(string $text, string $sgr): string {
    return $sgr === '' ? $text : self::ESC . '[' . $sgr . 'm' . $text . self::ESC . '[0m';
  }

  /**
   * Wrap text in an OSC 8 hyperlink, so a capable terminal makes it clickable.
   *
   * The sequence is BEL-terminated, matching the OSC the terminal query uses;
   * an incapable terminal shows the text and ignores the escape. The visible
   * width is the text alone, which {@see strip()} accounts for.
   *
   * @param string $text
   *   The visible link text.
   * @param string $url
   *   The target URL.
   *
   * @return string
   *   The hyperlinked text.
   */
  public static function link(string $text, string $url): string {
    // A raw ESC or BEL in either part would truncate the wrapper or inject
    // arbitrary control codes downstream, so every control byte is dropped
    // before the URL and text are embedded.
    $text = self::stripControl($text);
    $url = self::stripControl($url);

    return self::ESC . ']8;;' . $url . "\007" . $text . self::LINK_CLOSE;
  }

  /**
   * Drop the C0 control bytes (and DEL) that could break an escape wrapper.
   *
   * @param string $text
   *   The text.
   *
   * @return string
   *   The text with its control bytes removed.
   */
  public static function stripControl(string $text): string {
    return (string) preg_replace('/[\x00-\x1F\x7F]/', '', $text);
  }

  /**
   * Drop the control bytes a terminal acts on, keeping the ones text uses.
   *
   * Consumer text is drawn unaltered, so an escape sequence inside it clears
   * the screen, moves the cursor or recolours the output. Newline and tab are
   * text and are kept; every other C0 control and DEL is removed.
   *
   * A carriage return folds to a newline instead of being dropped, because it
   * is another platform's line break.
   *
   * The C1 controls are removed as the two bytes UTF-8 encodes them as: a
   * terminal in 8-bit mode reads U+009B as a CSI introducer. Their lead byte
   * never appears inside another character, so multi-byte text is unaffected.
   *
   * Invalid UTF-8 is filtered a second time over the bare C1 range. Valid text
   * cannot hold a bare C1 byte, so the extra pass only reaches malformed
   * input. A control byte deliberately encoded as a bare C1 is still removed.
   *
   * @param string $text
   *   The text.
   *
   * @return string
   *   The text with the control bytes a terminal acts on removed.
   */
  public static function sanitize(string $text): string {
    $text = (string) preg_replace('/\r\n?/', "\n", $text);
    $text = (string) preg_replace('/[\x00-\x08\x0B-\x1F\x7F]|\xC2[\x80-\x9F]/', '', $text);

    if (preg_match('//u', $text) === 1) {
      return $text;
    }

    return (string) preg_replace('/[\x80-\x9F]/', '', $text);
  }

  /**
   * Sanitize every string a value holds, whatever shape the value has.
   *
   * Arrays are walked to their leaves and a non-string value is returned
   * unchanged.
   *
   * Keys are left alone: a key addresses an entry rather than being read, and
   * filtering two keys into one would drop an entry. A key that is drawn is a
   * label, filtered by the code that draws it.
   *
   * @param mixed $value
   *   The value.
   *
   * @return mixed
   *   The value, with every string inside it sanitized.
   */
  public static function sanitizeValue(mixed $value): mixed {
    if (is_string($value)) {
      return self::sanitize($value);
    }

    if (!is_array($value)) {
      return $value;
    }

    $out = [];

    foreach ($value as $key => $item) {
      $out[$key] = self::sanitizeValue($item);
    }

    return $out;
  }

  /**
   * Strip ANSI escape sequences from text.
   *
   * Removes both the CSI styling sequences and the OSC 8 hyperlink wrappers
   * (BEL- or ST-terminated), leaving only the visible characters - so the
   * width and alignment maths see a linked label as its text alone.
   *
   * @param string $text
   *   The text.
   *
   * @return string
   *   The text without escape sequences.
   */
  public static function strip(string $text): string {
    $text = (string) preg_replace('/' . self::LINK_SEQUENCE . '/', '', $text);

    return (string) preg_replace('/' . self::STYLE_SEQUENCE . '/', '', $text);
  }

  /**
   * Cut text to a visible width, dressed as the whole of it was.
   *
   * A cut that dropped the escapes would leave the same row reading one way
   * inside a region and another at its edge, so the styling travels with the
   * characters it dresses: every sequence before the cut is carried, and
   * whatever is still open at the cut is closed, so nothing bleeds into what
   * follows.
   *
   * @param string $text
   *   The text (may carry ANSI codes).
   * @param int $width
   *   The visible width to cut to.
   *
   * @return string
   *   The text, cut to the width; unchanged when it already fits, so text
   *   carrying no escapes reads exactly as a plain slice of it.
   */
  public static function slice(string $text, int $width): string {
    if (self::width($text) <= $width) {
      return $text;
    }

    if ($width < 1) {
      return '';
    }

    $parts = preg_split('/(' . self::LINK_SEQUENCE . '|' . self::STYLE_SEQUENCE . ')/', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    $out = '';
    $left = $width;
    $styled = FALSE;
    $linked = FALSE;

    foreach ($parts as $index => $part) {
      if ($left < 1) {
        break;
      }

      // The pattern captures its one delimiter, so the pieces alternate: the
      // even ones are what shows and the odd ones are the escapes between.
      if ($index % 2 === 0) {
        $taken = Strings::substr($part, 0, $left);
        $out .= $taken;
        $left -= Strings::length($taken);

        continue;
      }

      $out .= $part;
      $styled = self::opensStyle($part) ?? $styled;
      $linked = self::opensLink($part) ?? $linked;
    }

    return $out . ($linked ? self::LINK_CLOSE : '') . ($styled ? self::ESC . '[0m' : '');
  }

  /**
   * The visible width of text (ANSI-stripped, code-point counted).
   *
   * @param string $text
   *   The text.
   *
   * @return int
   *   The visible width.
   */
  public static function width(string $text): int {
    return Strings::length(self::strip($text));
  }

  /**
   * The visible width of a block of lines: its widest line's width.
   *
   * @param list<string> $lines
   *   The lines.
   *
   * @return int
   *   The widest visible width, 0 for an empty block.
   */
  public static function blockWidth(array $lines): int {
    $width = 0;

    foreach ($lines as $line) {
      $width = max($width, self::width($line));
    }

    return $width;
  }

  /**
   * Place a left and right part on one line, right-aligning by visible width.
   *
   * @param string $left
   *   The left part.
   * @param string $right
   *   The right part.
   * @param int $width
   *   The total line width.
   *
   * @return string
   *   The composed line.
   */
  public static function alignRight(string $left, string $right, int $width): string {
    $pad = $width - self::width($left) - self::width($right);

    return $left . str_repeat(' ', max(1, $pad)) . $right;
  }

  /**
   * Wash a multi-line block with a background SGR so it fills edge to edge.
   *
   * A styled span closes with a full reset, which drops the background, so a
   * background opened once would survive only until the first span. This
   * re-opens the wash at the start of every line and again after every reset,
   * then erases each line to its end, so the gaps between spans and the padding
   * past the content all keep the background.
   *
   * @param string $text
   *   The block to wash (may span several lines and carry ANSI codes).
   * @param string $sgr
   *   The background SGR parameters (e.g. "44"); empty returns the text as-is.
   *
   * @return string
   *   The washed block.
   */
  public static function wash(string $text, string $sgr): string {
    if ($sgr === '') {
      return $text;
    }

    $open = self::ESC . '[' . $sgr . 'm';
    $reset = self::ESC . '[0m';
    $lines = explode("\n", $text);

    foreach ($lines as $index => $line) {
      $lines[$index] = $open . str_replace($reset, $reset . $open, $line) . $open . self::ESC . '[K';
    }

    return implode("\n", $lines);
  }

  /**
   * Whether a sequence leaves styling open, where it is one that says.
   *
   * @param string $sequence
   *   The escape sequence.
   *
   * @return bool|null
   *   TRUE when it opens styling, FALSE when it resets it, and NULL when it
   *   is not about styling at all.
   */
  protected static function opensStyle(string $sequence): ?bool {
    if (!str_ends_with($sequence, 'm')) {
      return NULL;
    }

    // A reset is written out of zeroes and the separators between them, so
    // whatever survives those being trimmed away is a style that stands.
    return trim(substr($sequence, 2, -1), '0;') !== '';
  }

  /**
   * Whether a sequence leaves a hyperlink open, where it is one that says.
   *
   * @param string $sequence
   *   The escape sequence.
   *
   * @return bool|null
   *   TRUE when it opens a hyperlink, FALSE when it closes one, and NULL when
   *   it is not a hyperlink at all.
   */
  protected static function opensLink(string $sequence): ?bool {
    if (!str_starts_with($sequence, self::ESC . ']')) {
      return NULL;
    }

    // The target is the last field of the sequence, so one that ends where the
    // field begins names no target and is the wrapper closing instead.
    $body = str_ends_with($sequence, "\007") ? substr($sequence, 0, -1) : substr($sequence, 0, -2);

    return !str_ends_with($body, ';');
  }

}
