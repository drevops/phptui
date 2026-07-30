<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\ScopedKeyMap;
use DrevOps\Tui\Primitive\Status;

/**
 * A theme's look: one method per themeable element.
 *
 * Every method here is a single knob. The stylers take text and return it in
 * that element's colour (already resolved for the theme's dark/light mode); the
 * symbol methods return a glyph for the theme's Unicode mode. To restyle an
 * element, a theme overrides just that one method - see {@see DefaultTheme} for
 * the dark/light palette that ships with the library.
 *
 * @code
 * class OceanTheme extends DefaultTheme {
 *   public function title(string $text): string { return $this->paint(Sgr::of(Sgr::Bold, Sgr::BrightCyan), $text); }
 *   public function marker(bool $selected): string { return $selected ? '~ ' : '  '; }
 * }
 * @endcode
 *
 * The {@see Mode}, {@see Spacing} and {@see Border} enums carry the display
 * options a consumer passes in the theme options array (as enum cases or
 * their string values). How the styled pieces are arranged into rows and
 * frames is the render*() layer on {@see DefaultTheme}.
 *
 * @package DrevOps\Tui\Theme
 */
interface ThemeInterface {

  /**
   * A heading or an editor label.
   */
  public function title(string $text): string;

  /**
   * A field label; bold when its row is selected.
   */
  public function label(string $text, bool $selected = FALSE): string;

  /**
   * A field value; bold when its row is selected.
   */
  public function value(string $text, bool $selected = FALSE): string;

  /**
   * A help/description line; bold when its row is selected.
   */
  public function description(string $text, bool $selected = FALSE): string;

  /**
   * The guidance voice - how to answer; bold when its row is selected.
   *
   * Draws every line that tells the reader what is expected before anything
   * has gone wrong: a field's own hint, and the constraint a bounded field
   * states above its options. Unrelated to {@see keyHint()} and
   * {@see keysHint()}, which draw the bound keys.
   *
   * An overriding theme must keep this apart from {@see description()} by
   * colour. The two are drawn on adjacent lines - a field's constraint sits
   * directly beneath the highlighted option's own description - so a reader
   * has no other cue to tell an expectation from prose. Weight and italic do
   * not survive every surface: an SVG render drops italic entirely, and a
   * terminal may too.
   */
  public function hint(string $text, bool $selected = FALSE): string;

  /**
   * One key as it appears in the legend.
   *
   * A worded key is uppercased here rather than at its source, so a translated
   * key name is uppercased too and the legend reads as keys throughout.
   */
  public function legendKey(string $text): string;

  /**
   * What one key in the legend does.
   */
  public function legendDescription(string $text): string;

  /**
   * What stands between one legend entry and the next.
   */
  public function legendSeparator(): string;

  /**
   * The focused entry's own text, drawn beneath it.
   *
   * Its own atom rather than a reuse of {@see description()}: a field's
   * description belongs to the field and never moves, while this is rewritten
   * every time the focus moves down the list, and the two are drawn a line or
   * two apart.
   */
  public function entryDescription(string $text): string;

  /**
   * The mark beside a label saying the field has help to show.
   *
   * A field's help is only ever drawn on request, so this is the only thing
   * announcing it. It marks the label rather than the description, because a
   * field can carry help without carrying a description at all.
   */
  public function helpMarker(): string;

  /**
   * The line that captions a browsable list, saying what is being shown.
   *
   * A file browser captions its entries with the directory they are in;
   * another browser captions its own with whatever it is looking at. The atom
   * is named for what the reader sees - a caption over a list - so it does not
   * bind the theme to any one widget's idea of where it is.
   *
   * Related to {@see hint()} and set apart from it by weight rather than hue:
   * both are the widget speaking about the list rather than listing it, but a
   * caption states where you are and guidance states what is expected.
   */
  public function caption(string $text): string;

  /**
   * One line of guidance, in the voice of {@see hint()}.
   *
   * The renderer every guidance line goes through, so what distinguishes an
   * expectation from the prose beside it is decided once. It carries the
   * fallback the atom cannot: with colour switched off there is no hue and no
   * dependable italic, so the line takes a leading mark instead - and a theme
   * that draws its own may open the line however its surface allows.
   */
  public function renderGuidance(string $text, bool $selected = FALSE): string;

  /**
   * A provenance badge (e.g. "edited"); bold when its row is selected.
   */
  public function badge(string $text, bool $selected = FALSE): string;

  /**
   * The active (focused) button.
   */
  public function cursor(string $text): string;

  /**
   * A footer: the status and hint lines.
   */
  public function footer(string $text): string;

  /**
   * The width, in columns, available for a widget's rendered content.
   *
   * The frame's inner width, already less any border and gutter. A widget wraps
   * or omits its own secondary lines against this so they fit the panel.
   */
  public function contentWidth(): int;

  /**
   * The navigator breadcrumb.
   */
  public function breadcrumb(string $text): string;

  /**
   * A scroll indicator (the up/down arrows).
   */
  public function indicator(string $text): string;

  /**
   * The highlighted (cursor) row in a list widget.
   */
  public function highlight(string $text): string;

  /**
   * A run of query-matched characters within an option label.
   */
  public function highlightMatch(string $text): string;

  /**
   * A non-selectable group heading in an option list.
   */
  public function heading(string $text): string;

  /**
   * Bold markup (`**text**`) inside a description or note.
   */
  public function strong(string $text): string;

  /**
   * Emphasis markup (`*text*`) inside a description or note.
   */
  public function emphasis(string $text): string;

  /**
   * Inline-code markup (`` `text` ``) inside a description or note.
   */
  public function code(string $text): string;

  /**
   * A hyperlink: a clickable label on capable terminals, else `text (url)`.
   *
   * @param string $text
   *   The visible link label.
   * @param string $url
   *   The link target.
   *
   * @return string
   *   The rendered link.
   */
  public function link(string $text, string $url): string;

  /**
   * The bullet glyph that leads an unordered-list item in markup.
   */
  public function bullet(): string;

  /**
   * A non-selectable separator line between options in an option list.
   */
  public function divider(): string;

  /**
   * A disabled (non-selectable) option's label and reason, dimmed.
   */
  public function disabled(string $text): string;

  /**
   * A validation error message.
   */
  public function error(string $text): string;

  /**
   * The editor-header underline.
   */
  public function rule(string $text): string;

  /**
   * The frame box, when a border is on.
   */
  public function border(string $text): string;

  /**
   * The selection cursor for a row: the marker glyph when selected, else a gap.
   */
  public function marker(bool $selected): string;

  /**
   * The drill-in / breadcrumb arrow symbol.
   */
  public function arrow(): string;

  /**
   * The breadcrumb separator symbol.
   */
  public function separator(): string;

  /**
   * The "move up" key hint symbol.
   */
  public function arrowUp(): string;

  /**
   * The "move down" key hint symbol.
   */
  public function arrowDown(): string;

  /**
   * The "move left" key hint symbol.
   */
  public function arrowLeft(): string;

  /**
   * The "move right" key hint symbol.
   */
  public function arrowRight(): string;

  /**
   * The enter/accept key hint symbol.
   */
  public function enter(): string;

  /**
   * The dot that joins hint and summary fragments.
   */
  public function dot(): string;

  /**
   * The "more above" scroll-indicator symbol.
   */
  public function indicatorUp(): string;

  /**
   * The "more below" scroll-indicator symbol.
   */
  public function indicatorDown(): string;

  /**
   * A radio symbol: filled in the cursor colour when on, empty when off.
   */
  public function radio(bool $on): string;

  /**
   * A checkbox symbol: filled in the value colour when checked, empty when off.
   */
  public function check(bool $on): string;

  /**
   * The text-input caret, in the cursor colour.
   */
  public function caret(): string;

  /**
   * Inline ghost-text: a dimmed completion suffix, empty without colour.
   *
   * @param string $text
   *   The completion suffix shown after the caret.
   *
   * @return string
   *   The dimmed suffix, or an empty string in no-colour mode - without ANSI it
   *   cannot be told apart from typed text, so it is suppressed.
   */
  public function ghost(string $text): string;

  /**
   * Render an editor input line, styled per the "field" theme option.
   *
   * The flat style returns the value with a plain caret. The boxed and
   * underline styles wrap it in a fixed-width filled or underlined field, so
   * the entry area reads as an input the way an MS-DOS form marked its fields:
   * the fill runs behind the value text, and a reverse-video caret sits over
   * the character it is on, so the letter still shows.
   *
   * @param string $before
   *   The buffer text before the caret.
   * @param string $after
   *   The buffer text after the caret.
   * @param string $ghost
   *   The inline ghost-text completion suffix, or an empty string.
   *
   * @return string
   *   The composed input line.
   */
  public function renderInput(string $before, string $after, string $ghost = ''): string;

  /**
   * Render an indeterminate spinner: an accent glyph before the caption.
   *
   * @param int $frame
   *   The animation frame counter; the glyph cycles through the frame set.
   * @param string $caption
   *   The caption shown beside the spinner.
   *
   * @return string
   *   The composed spinner line.
   */
  public function renderSpinner(int $frame, string $caption): string;

  /**
   * Render a determinate progress bar: a filling bar, a step count and a label.
   *
   * @param int $current
   *   The number of completed steps.
   * @param int $total
   *   The total number of steps; a zero total renders a full bar.
   * @param string $caption
   *   The caption shown before the bar.
   * @param string $label
   *   The trailing label, or an empty string for none.
   *
   * @return string
   *   The composed bar line.
   */
  public function renderProgressBar(int $current, int $total, string $caption, string $label): string;

  /**
   * Render a rating scale: a run of points filled up to the chosen one.
   *
   * The one scale renderer behind both a rating's editor and its collapsed
   * panel row, so overriding it restyles the two together.
   *
   * @param int $current
   *   The chosen point.
   * @param int $min
   *   The lowest point of the scale.
   * @param int $max
   *   The highest point of the scale.
   * @param string $caption
   *   The chosen point's caption, or an empty string when it has none; its line
   *   breaks fold to spaces so the scale stays one line.
   *
   * @return string
   *   The composed scale line.
   */
  public function renderScale(int $current, int $min, int $max, string $caption): string;

  /**
   * Render a static loading indicator: a caption and a themed ellipsis.
   *
   * The resting state of a progressable load - shown while a callable resolves
   * but before (or without) any advance.
   *
   * @param string $caption
   *   The caption shown before the ellipsis.
   *
   * @return string
   *   The composed loading line.
   */
  public function renderLoading(string $caption): string;

  /**
   * Render a card: a heading, a body and an optional grid, boxed or indented.
   *
   * The one card renderer behind both a standalone box and a note field's card,
   * so overriding it restyles the two together.
   *
   * @param string $title
   *   The heading shown as the card's first line; empty for a bare card.
   * @param list<string> $body
   *   The body lines. Each is word-wrapped to the card's inner width, and an
   *   empty entry stays an empty line so a caller can space the content out.
   * @param list<string> $headers
   *   The header cells of an optional grid below the body.
   * @param list<list<string>> $rows
   *   The body rows of that grid; with no headers or rows there is no grid.
   * @param bool $bordered
   *   Whether the card is boxed in the theme's border, or merely indented.
   * @param int $reserved
   *   Columns the caller lays the card out after, kept out of the width cap so
   *   the card's right edge still lands inside the frame.
   *
   * @return list<string>
   *   The card's physical lines; empty when it has no content at all.
   */
  public function renderCard(string $title, array $body, array $headers = [], array $rows = [], bool $bordered = TRUE, int $reserved = 0): array;

  /**
   * Render source text as wrapped, markup-styled lines at the frame width.
   *
   * @param string $text
   *   The source text; its own newlines split it into physical lines first.
   *
   * @return list<string>
   *   The styled lines.
   */
  public function renderText(string $text): array;

  /**
   * Render an aligned, bordered table from headers and rows.
   *
   * Lays the data out as a grid coloured with the theme's atoms and drawn in
   * its border style, honouring the Unicode and colour switches. The columns
   * size to their widest cell and the whole grid is capped at the frame width.
   *
   * @param list<string> $headers
   *   The header cells; an empty list draws the grid with no header row.
   * @param list<list<string>> $rows
   *   The body rows, each a list of cell strings.
   *
   * @return list<string>
   *   The table's physical lines, capped at the frame width.
   */
  public function renderTable(array $headers, array $rows): array;

  /**
   * Render a start banner: the logo above an optional version line.
   *
   * @param string $logo
   *   The banner logo; its newlines split it into lines.
   * @param string $version
   *   The version shown below the logo, or an empty string for none.
   *
   * @return string
   *   The composed banner.
   */
  public function renderBanner(string $logo, string $version): string;

  /**
   * Render a status line: the kind's glyph and the message, in its colour.
   *
   * @param \DrevOps\Tui\Primitive\Status $status
   *   The kind of status.
   * @param string $text
   *   The message; its line breaks fold to spaces so the status stays one line.
   *
   * @return string
   *   The composed line.
   */
  public function renderStatus(Status $status, string $text): string;

  /**
   * Render label/value pairs as an aligned definition list.
   *
   * @param array<array-key,string> $pairs
   *   The values keyed by their label. A numeric-string label arrives as an
   *   integer key and still renders as its own text.
   *
   * @return list<string>
   *   The list's physical lines; empty when there are no pairs.
   */
  public function renderDefinitions(array $pairs): array;

  /**
   * The masked-character symbol for secret values.
   */
  public function mask(): string;

  /**
   * Render a single key as its hint glyph (an arrow, a word or the character).
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key to render.
   *
   * @return string
   *   The glyph, respecting the theme's Unicode mode.
   */
  public function keyHint(Key $key): string;

  /**
   * Render a hint fragment: the primary keys of one or more actions, labelled.
   *
   * The glyphs are drawn from the live bindings, so a hint never contradicts a
   * remapped key. An action with no bound key contributes nothing, and when no
   * action is bound the fragment is empty.
   *
   * @param \DrevOps\Tui\Input\ScopedKeyMap $keys
   *   The scope's bindings.
   * @param string $label
   *   The label describing what the keys do (e.g. "move", "accept").
   * @param \DrevOps\Tui\Input\Action ...$actions
   *   The actions whose primary keys lead the fragment.
   *
   * @return string
   *   The fragment (e.g. "↑/↓ move"), or an empty string when nothing is bound.
   */
  public function keysHint(ScopedKeyMap $keys, string $label, Action ...$actions): string;

}
