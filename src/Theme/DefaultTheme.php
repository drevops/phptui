<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Input\ScopedKeyMap;
use DrevOps\Tui\Primitive\Status;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\Box;
use DrevOps\Tui\Render\Markup;
use DrevOps\Tui\Render\MarkupKind;
use DrevOps\Tui\Render\MarkupSegment;
use DrevOps\Tui\Render\Overlay;
use DrevOps\Tui\Render\Scroller;
use DrevOps\Tui\Render\Table;
use DrevOps\Tui\Render\Viewport;
use DrevOps\Tui\Theme\Capability\ColorSchemeCapableInterface;
use DrevOps\Tui\Theme\Capability\ColorSchemeCapableTrait;
use DrevOps\Tui\Theme\Capability\DimCapableInterface;
use DrevOps\Tui\Theme\Capability\MarkdownCapableInterface;
use DrevOps\Tui\Theme\Capability\OccupyCapableInterface;
use DrevOps\Tui\Theme\Capability\UnicodeCapableInterface;
use DrevOps\Tui\Theme\Capability\UnicodeCapableTrait;
use DrevOps\Tui\Theme\Override\Glyph;
use DrevOps\Tui\Theme\Override\Overrides;
use DrevOps\Tui\Theme\Override\ThemeElement;
use DrevOps\Tui\Translation\Translator;
use DrevOps\Tui\Utils\Strings;

/**
 * The default theme: the appearance atoms plus the assembly that arranges them.
 *
 * Two layers, one class. The **atoms** are one method per colour and glyph
 * (title(), value(), marker(), border(), caret()…) - each takes text or a flag
 * and returns it styled for the theme's mode; these are what a consumer theme
 * overrides. The **render*()** methods are the assembly: they take plain
 * strings and arrays and arrange those atoms into cards, tables, the scrolled
 * frame and the editor. Pure box geometry (character sets, width fitting) lives
 * in {@see Box}; everything visual routes through the atoms.
 *
 * It raises {@see AbstractTheme}'s floor by declaring colour, a scheme and
 * Unicode, so every element it inherits is answered with a palette and a glyph
 * instead of the plain string the floor hands back. Each element delegates to
 * the atom that draws it, which is what keeps a subclass's palette effective:
 * override the atom and every element drawn from it follows.
 *
 * A consumer theme extends this and overrides just what it wants - usually an
 * atom, occasionally a render method for a layout tweak:
 *
 * @code
 * class OceanTheme extends DefaultTheme {
 *   public function title(string $text): string { return $this->paint(Sgr::of(Sgr::Bold, Sgr::BrightCyan), $text); }
 *   public function panelTitle(string $text): string { return $this->title($text); }
 * }
 * @endcode
 *
 * @package DrevOps\Tui\Theme
 */
class DefaultTheme extends AbstractTheme implements ThemeInterface, ColorSchemeCapableInterface, DimCapableInterface, MarkdownCapableInterface, OccupyCapableInterface, UnicodeCapableInterface {

  use ColorSchemeCapableTrait;
  use UnicodeCapableTrait;

  /**
   * The default frame width, used when a caller does not specify one.
   */
  public const int DEFAULT_WIDTH = 76;

  /**
   * The nominal width of a boxed or underlined input field, in columns.
   */
  protected const int FIELD_WIDTH = 40;

  /**
   * The minimum width of a boxed or underlined input field, in columns.
   */
  protected const int FIELD_MIN_WIDTH = 12;

  /**
   * The default minimum terminal height for fullscreen mode, in rows.
   *
   * Vertical overflow scrolls gracefully, so only a small floor is needed:
   * enough for the chrome and a few body lines.
   */
  protected const int MIN_HEIGHT = 10;

  /**
   * The rows reserved for the two scroll indicators (▲/▼).
   *
   * The scrolled body window carries its indicators outside the viewport
   * height, so the frame budget reserves a row for each.
   */
  protected const int INDICATOR_LINES = 2;

  /**
   * The Unicode spinner animation frames, one glyph per tick.
   */
  protected const array SPINNER_FRAMES = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

  /**
   * The determinate progress bar's width in cells.
   */
  protected const int PROGRESS_WIDTH = 24;

  /**
   * The columns one condition of nesting indents a field by.
   *
   * Matches the gutter an unbordered note card already sits in, so an indented
   * row and a card line up on the same steps.
   */
  protected const int CONDITIONAL_INDENT = 2;

  /**
   * The left indent of a definition list, in columns.
   */
  protected const int DEFINITION_INDENT = 2;

  /**
   * The gap between a definition's label column and its value, in columns.
   */
  protected const int DEFINITION_GAP = 2;

  /**
   * The chrome a box spends per line: a border column and a gutter, each side.
   */
  protected const int BOX_CHROME = 4;

  /**
   * The columns an unbordered card indents its content by.
   */
  protected const int CARD_INDENT = 2;

  /**
   * Whether markdown in descriptions and notes is rendered, from "markdown".
   */
  protected bool $markdown;

  /**
   * Whether conditional fields are indented, from "indent_conditional".
   */
  protected bool $indentConditional;

  /**
   * The outer frame width, including the border when one is drawn.
   */
  protected int $outerWidth;

  /**
   * What a consumer states differently, consulted before every element.
   */
  protected Overrides $overrides;

  /**
   * Construct a theme.
   *
   * @param int $width
   *   The frame width used for right-aligned badges and the border.
   * @param array<string,mixed> $options
   *   Display options keyed by name and validated against optionSchema():
   *   "mode" (a MODE_* value), "color" and "unicode" (booleans; unset defaults
   *   on), "spacing" (a SPACING_* value), "border" (a BORDER_* value), plus any
   *   option a concrete theme declares.
   */
  public function __construct(protected int $width = self::DEFAULT_WIDTH, protected array $options = []) {
    $this->validateOptions();

    $this->overrides = new Overrides();
    $this->color = is_bool($this->options['color'] ?? NULL) ? $this->options['color'] : TRUE;
    $this->unicode = is_bool($this->options['unicode'] ?? NULL) ? $this->options['unicode'] : TRUE;
    $this->markdown = is_bool($this->options['markdown'] ?? NULL) && $this->options['markdown'];
    $this->indentConditional = is_bool($this->options['indent_conditional'] ?? NULL) && $this->options['indent_conditional'];
    $this->isDark = $this->mode() === Mode::Dark;

    // In fullscreen the given width is the whole terminal's; a max-width cap
    // keeps the frame readable on very wide terminals (0 leaves it uncapped).
    if ($this->isFullscreen() && $this->maxWidth() > 0) {
      $this->width = min($this->width, $this->maxWidth());
    }

    $this->outerWidth = $this->width;

    // A border consumes two frame columns plus a one-column gutter each side.
    // Lay rows out that much narrower to keep right-aligned badges inside it.
    if ($this->borderStyle() !== Border::None) {
      $this->width = max(1, $this->width - 4);
    }
  }

  /**
   * Validate the options against optionSchema(), failing loudly on a mistake.
   *
   * @throws \InvalidArgumentException
   *   When an option key is unknown or its value is not allowed.
   */
  protected function validateOptions(): void {
    $schema = $this->optionSchema();
    $integers = $this->integerOptions();

    foreach ($this->options as $key => $value) {
      if (in_array($key, $integers, TRUE)) {
        if (!is_int($value) || $value < 0) {
          throw new \InvalidArgumentException(Translator::t('@value is not a valid "@key". Use a non-negative integer.', [
            '@value' => $this->showValue($value),
            '@key' => $key,
          ]));
        }

        continue;
      }

      if (!array_key_exists($key, $schema)) {
        throw new \InvalidArgumentException(Translator::t('Unknown theme option "@key". Known: @known.', [
          '@key' => $key,
          '@known' => implode(', ', [...array_keys($schema), ...$integers]),
        ]));
      }

      // An enum case and its backing value are interchangeable as an option.
      $candidate = $value instanceof \BackedEnum ? $value->value : $value;

      if (!in_array($candidate, $schema[$key], TRUE)) {
        throw new \InvalidArgumentException(Translator::t('@value is not a valid "@key". Allowed: @allowed.', [
          '@value' => $this->showValue($candidate),
          '@key' => $key,
          '@allowed' => implode(', ', array_map($this->showValue(...), $schema[$key])),
        ]));
      }
    }

    // An explicit minimum above an explicit maximum can never be satisfied:
    // fail at declaration rather than dead-ending the session behind an
    // unresolvable resize notice.
    foreach ([['min_width', 'max_width'], ['min_height', 'max_height']] as [$min_key, $max_key]) {
      if (array_key_exists($min_key, $this->options) && $this->intOption($max_key, 0) > 0 && $this->intOption($min_key, 0) > $this->intOption($max_key, 0)) {
        throw new \InvalidArgumentException(Translator::t('"@min" must not exceed "@max".', [
          '@min' => $min_key,
          '@max' => $max_key,
        ]));
      }
    }
  }

  /**
   * The allowed options and their permitted values, keyed by option name.
   *
   * A concrete theme adds its own options by merging over the base -
   * `return ['accent' => ['cool', 'warm']] + parent::optionSchema();`.
   *
   * @return array<string,list<mixed>>
   *   The option name => allowed-values map.
   */
  protected function optionSchema(): array {
    return [
      'mode' => array_column(Mode::cases(), 'value'),
      'color' => [TRUE, FALSE],
      'unicode' => [TRUE, FALSE],
      'markdown' => [TRUE, FALSE],
      'indent_conditional' => [TRUE, FALSE],
      'spacing' => array_column(Spacing::cases(), 'value'),
      'border' => array_column(Border::cases(), 'value'),
      'field' => array_column(FieldStyle::cases(), 'value'),
      'fullscreen' => [TRUE, FALSE],
      'halign' => array_column(HAlign::cases(), 'value'),
      'valign' => array_column(VAlign::cases(), 'value'),
    ];
  }

  /**
   * The option names that accept any non-negative integer.
   *
   * These complement optionSchema(), whose entries enumerate their allowed
   * values - an integer option accepts a whole range instead. A concrete theme
   * adds its own by merging over the base.
   *
   * @return list<string>
   *   The option names.
   */
  protected function integerOptions(): array {
    return ['min_width', 'min_height', 'max_width', 'max_height'];
  }

  /**
   * Render an option value for an error message.
   *
   * @param mixed $value
   *   The value.
   *
   * @return string
   *   A readable representation.
   */
  protected function showValue(mixed $value): string {
    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }

    return is_scalar($value) ? '"' . $value . '"' : gettype($value);
  }

  /**
   * A string display option, or a default when unset or non-string.
   *
   * @param string $name
   *   The option name (e.g. "spacing", "border", or a theme's own).
   * @param string $default
   *   The value to use when the option is unset.
   *
   * @return string
   *   The option value.
   */
  protected function option(string $name, string $default): string {
    $value = $this->options[$name] ?? $default;

    return is_string($value) ? $value : $default;
  }

  /**
   * An integer display option, or a default when unset or non-integer.
   *
   * @param string $name
   *   The option name (e.g. "min_width").
   * @param int $default
   *   The value to use when the option is unset.
   *
   * @return int
   *   The option value.
   */
  protected function intOption(string $name, int $default): int {
    $value = $this->options[$name] ?? $default;

    return is_int($value) ? $value : $default;
  }

  /**
   * An enum display option, or a default when unset or unrecognized.
   *
   * @param string $name
   *   The option name (e.g. "spacing", "halign").
   * @param class-string<T> $enum
   *   The backed enum the option's cases belong to.
   * @param T $default
   *   The case to use when the option is unset.
   *
   * @return T
   *   The option case.
   *
   * @template T of \BackedEnum
   */
  protected function enumOption(string $name, string $enum, \BackedEnum $default): \BackedEnum {
    $value = $this->options[$name] ?? NULL;

    if ($value instanceof $enum) {
      return $value;
    }

    return is_string($value) ? ($enum::tryFrom($value) ?? $default) : $default;
  }

  /**
   * The colour-mode option.
   *
   * @return \DrevOps\Tui\Theme\Mode
   *   The mode; dark when unset.
   */
  protected function mode(): Mode {
    return $this->enumOption('mode', Mode::class, Mode::Dark);
  }

  /**
   * The frame width the renderer lays out to.
   *
   * @return int
   *   The width.
   */
  protected function width(): int {
    return $this->width;
  }

  /**
   * {@inheritdoc}
   */
  public function contentWidth(): int {
    return $this->width;
  }

  /**
   * The vertical spacing option.
   *
   * @return \DrevOps\Tui\Theme\Spacing
   *   The spacing; padded when unset.
   */
  public function spacing(): Spacing {
    return $this->enumOption('spacing', Spacing::class, Spacing::Padded);
  }

  /**
   * Whether the markdown subset is drawn, from "markdown".
   *
   * @return bool
   *   TRUE when it is drawn rather than left as literal text.
   */
  public function hasMarkdown(): bool {
    return $this->markdown;
  }

  /**
   * The border-style option.
   *
   * @return \DrevOps\Tui\Theme\Border
   *   The border style; a rounded box when unset - a form is framed unless
   *   it explicitly asks for no border.
   */
  public function borderStyle(): Border {
    return $this->enumOption('border', Border::class, Border::Rounded);
  }

  /**
   * The field-input style option.
   *
   * @return \DrevOps\Tui\Theme\FieldStyle
   *   The field style; flat when unset.
   */
  protected function field(): FieldStyle {
    return $this->enumOption('field', FieldStyle::class, FieldStyle::Flat);
  }

  /**
   * Whether the frame expands to the whole terminal screen.
   *
   * @return bool
   *   TRUE when the "fullscreen" option is on.
   */
  public function isFullscreen(): bool {
    return ($this->options['fullscreen'] ?? FALSE) === TRUE;
  }

  /**
   * The horizontal alignment of content within the available width.
   *
   * @return \DrevOps\Tui\Theme\HAlign
   *   The alignment; left when unset.
   */
  public function halign(): HAlign {
    return $this->enumOption('halign', HAlign::class, HAlign::Left);
  }

  /**
   * The vertical alignment of content within the available height.
   *
   * @return \DrevOps\Tui\Theme\VAlign
   *   The alignment; top when unset.
   */
  public function valign(): VAlign {
    return $this->enumOption('valign', VAlign::class, VAlign::Top);
  }

  /**
   * The minimum terminal width fullscreen mode needs, in columns.
   *
   * @return int
   *   The explicit "min_width" option, or 0 when the minimum should be
   *   measured from the form's content instead.
   */
  public function minWidth(): int {
    return $this->intOption('min_width', 0);
  }

  /**
   * The minimum terminal height fullscreen mode needs, in rows.
   *
   * @return int
   *   The minimum height.
   */
  public function minHeight(): int {
    return $this->intOption('min_height', self::MIN_HEIGHT);
  }

  /**
   * The widest frame fullscreen mode may stretch to, in columns.
   *
   * @return int
   *   The cap, or 0 for uncapped.
   */
  public function maxWidth(): int {
    return $this->intOption('max_width', 0);
  }

  /**
   * The tallest frame fullscreen mode may stretch to, in rows.
   *
   * @return int
   *   The cap, or 0 for uncapped.
   */
  public function maxHeight(): int {
    return $this->intOption('max_height', 0);
  }

  /**
   * The outer frame width, including the border when one is drawn.
   *
   * @return int
   *   The width.
   */
  public function outerWidth(): int {
    return $this->outerWidth;
  }

  /**
   * The background the theme washes the screen with, or NULL for none.
   *
   * A styled span closes with a full reset, so a background opened once would
   * not survive it. The render layer instead re-opens this background on every
   * line and after every reset and erases each line to its end, so the whole
   * screen - the gaps between spans and the padding past the content included -
   * fills with it. A theme declares its background here the same way it
   * declares a title colour.
   *
   * @return string|null
   *   The background SGR parameters (e.g. "44" for blue), or NULL to keep the
   *   terminal's own background.
   */
  public function background(): ?string {
    return NULL;
  }

  /**
   * Take the elements a consumer states differently.
   *
   * @param \DrevOps\Tui\Theme\Override\Overrides $overrides
   *   The patch; anything it does not name keeps the theme's own answer.
   *
   * @return static
   *   The theme.
   */
  public function overrides(Overrides $overrides): static {
    $this->overrides = $overrides;

    return $this;
  }

  /**
   * The glyph a consumer stated for an element, resolved for the display mode.
   *
   * @param \DrevOps\Tui\Theme\Override\ThemeElement $element
   *   The element.
   *
   * @return string|null
   *   The glyph, or NULL when nobody stated one.
   */
  protected function overriddenGlyph(ThemeElement $element): ?string {
    $override = $this->overrides->glyph($element);

    return $override instanceof Glyph ? $this->glyph($override->glyph, $override->ascii) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function title(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::Cyan) : Sgr::of(Sgr::Bold, Sgr::Blue), $this->linkify($text));
  }

  /**
   * {@inheritdoc}
   */
  public function label(string $text, bool $selected = FALSE): string {
    return $this->paint($this->emphasize('', $selected), $this->linkify($text));
  }

  /**
   * {@inheritdoc}
   */
  public function value(string $text, bool $selected = FALSE): string {
    return $this->paint($this->emphasize(Sgr::of(Sgr::Green), $selected), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function description(string $text, bool $selected = FALSE): string {
    // Secondary text stays secondary on the selected row too: bolding it puts
    // a field's explanation at the same weight as the answer it explains, and
    // the row already reads as selected from its marker and its value.
    return $this->paint(Sgr::of(Sgr::Grey), $this->linkify($text));
  }

  /**
   * {@inheritdoc}
   */
  public function hint(string $text, bool $selected = FALSE): string {
    // Guidance on how to answer is never louder than the question itself, but
    // still reads as its own voice - and a constraint sits directly beneath an
    // option's own description, so the two must not be mistaken for each other
    // on any surface. It steps along the grey ramp rather than taking a hue of
    // its own: a coloured guidance line reads as output the field produced
    // rather than as chrome telling you what it expects. Italic reinforces it
    // where the surface honours it, and where neither survives the voice falls
    // back to a mark, which nothing can strip.
    return $this->paint($this->emphasize(Sgr::of(Sgr::Italic, Sgr::Pewter), $selected), $this->linkify($text));
  }

  /**
   * {@inheritdoc}
   */
  public function caption(string $text): string {
    // The guidance hue at a different weight: a caption and a constraint are
    // both the field speaking about the list rather than listing it, so they
    // read as a pair - but bold against italic keeps them apart, and keeps the
    // caption from being read as the panel's own trail.
    return $this->paint(Sgr::of(Sgr::Bold, Sgr::Steel), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function renderGuidance(string $text, bool $selected = FALSE): string {
    // Strip the surface back far enough and neither the hue nor the italic
    // survives, leaving guidance indistinguishable from the description beside
    // it; a mark is the one cue nothing can take away, so it appears exactly
    // when the others are gone.
    return $this->hint($this->hasColor() ? $text : ($this->hasUnicode() ? '› ' : '> ') . $text, $selected);
  }

  /**
   * {@inheritdoc}
   */
  public function badge(string $text, bool $selected = FALSE): string {
    return $this->paint($this->emphasize(Sgr::of(Sgr::Reverse), $selected), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function cursor(string $text): string {
    return $this->paint(Sgr::of(Sgr::Bold, Sgr::Reverse), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function footer(string $text): string {
    return $this->paint(Sgr::of(Sgr::Grey), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function breadcrumb(string $text): string {
    return $this->paint(Sgr::of(Sgr::Grey), $text);
  }

  /**
   * Recede text into the background, so a modal reads as floating above it.
   *
   * @param string $text
   *   The text.
   *
   * @return string
   *   The dimmed text (unchanged when colour is off).
   */
  public function dim(string $text): string {
    return $this->paint(Sgr::of(Sgr::Dim), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function indicator(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::Yellow) : Sgr::of(Sgr::Magenta), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function highlight(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::Cyan) : Sgr::of(Sgr::Bold, Sgr::Blue), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function highlightMatch(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::Yellow) : Sgr::of(Sgr::Bold, Sgr::Magenta), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function heading(string $text): string {
    return $this->paint(Sgr::of(Sgr::Bold, Sgr::Grey), $this->linkify($text));
  }

  /**
   * {@inheritdoc}
   */
  public function strong(string $text): string {
    return $this->paint(Sgr::of(Sgr::Bold), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function emphasis(string $text): string {
    return $this->paint(Sgr::of(Sgr::Italic), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function code(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::BrightYellow) : Sgr::of(Sgr::Magenta), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function link(string $text, string $url): string {
    return Markup::hyperlink($text, $url, $this->color);
  }

  /**
   * {@inheritdoc}
   */
  public function bullet(): string {
    return $this->unicode ? '•' : '-';
  }

  /**
   * Resolve any `[text](url)` links in a single line of chrome text.
   *
   * @param string $text
   *   The text.
   *
   * @return string
   *   The text with links resolved to the terminal's capability.
   */
  protected function linkify(string $text): string {
    return Markup::links($text, $this->color);
  }

  /**
   * Render description or note-body text as themed physical lines.
   *
   * Links resolve on every terminal; the rest of the markdown subset - bold,
   * emphasis, inline code and bullet lists - is rendered only when the
   * "markdown" option is on, and otherwise left as literal text. Each span is
   * styled by its own atom, so a custom theme restyles markup by overriding
   * those atoms.
   *
   * @param string $source
   *   The source text; newlines separate physical lines.
   * @param bool $selected
   *   Whether the owning row is selected.
   *
   * @return list<string>
   *   The rendered lines.
   */
  protected function markupBody(string $source, bool $selected): array {
    $lines = [];

    foreach (Markup::parse($source, $this->markdown) as $line) {
      $rendered = $line->bullet ? $this->description($this->bullet() . ' ', $selected) : '';

      foreach ($line->segments as $segment) {
        $rendered .= $this->markupSegment($segment, $selected);
      }

      $lines[] = $rendered;
    }

    return $lines;
  }

  /**
   * Style one parsed markup span with its atom.
   *
   * Plain text is the description atom, so a theme that restyles description
   * text restyles the body of a description and a note with it - not only the
   * one-line rows that call the atom directly.
   *
   * @param \DrevOps\Tui\Render\MarkupSegment $segment
   *   The span.
   * @param bool $selected
   *   Whether the owning row is selected.
   *
   * @return string
   *   The styled span.
   */
  protected function markupSegment(MarkupSegment $segment, bool $selected): string {
    return match ($segment->kind) {
      MarkupKind::Bold => $this->strong($segment->text),
      MarkupKind::Emphasis => $this->emphasis($segment->text),
      MarkupKind::Code => $this->code($segment->text),
      MarkupKind::Link => $this->link($segment->text, $segment->url),
      // The parser has already split every link into its own span, so the
      // atom's own link resolution finds nothing left to do here.
      MarkupKind::Text => $this->description($segment->text, $selected),
    };
  }

  /**
   * {@inheritdoc}
   */
  public function divider(): string {
    return $this->footer(str_repeat($this->unicode ? '─' : '-', max(1, $this->width)));
  }

  /**
   * {@inheritdoc}
   */
  public function disabled(string $text): string {
    return $this->paint(Sgr::of(Sgr::Grey), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function error(string $text): string {
    return $this->paint(Sgr::of(Sgr::Red), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function rule(string $text): string {
    return $this->paint(Sgr::of(Sgr::Grey), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function border(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Cyan) : Sgr::of(Sgr::Blue), $text);
  }

  /**
   * {@inheritdoc}
   */
  public function marker(bool $selected): string {
    return $selected ? $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::Cyan) : Sgr::of(Sgr::Bold, Sgr::Blue), $this->unicode ? '❯' : '>') : ' ';
  }

  /**
   * {@inheritdoc}
   */
  public function arrow(): string {
    return $this->unicode ? '›' : '>';
  }

  /**
   * {@inheritdoc}
   */
  public function separator(): string {
    return $this->unicode ? '›' : '>';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function legendKey(string $text): string {
    // Case is what sets a key apart from what it does, so the legend needs no
    // weight to carry it. Uppercased here rather than at each source, so a
    // translated key name is uppercased with the rest.
    return $this->paint($this->overrides->style(ThemeElement::LegendKey) ?? Sgr::of(Sgr::Grey), Strings::upper($text));
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function legendDescription(string $text): string {
    return $this->paint(Sgr::of(Sgr::Grey), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function legendSeparator(): string {
    return $this->paint(Sgr::of(Sgr::Ash), $this->overriddenGlyph(ThemeElement::LegendSeparator) ?? $this->dot());
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function chromeBorder(string $text): string {
    return $this->border($text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function chromeOverflowMarker(bool $above): string {
    return $this->indicator($above ? $this->indicatorUp() : $this->indicatorDown());
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function breadcrumbLabel(string $text): string {
    return $this->breadcrumb($text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function breadcrumbSeparator(): string {
    return $this->overriddenGlyph(ThemeElement::BreadcrumbSeparator) ?? $this->separator();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldSelector(bool $selected): string {
    $glyph = $this->overriddenGlyph(ThemeElement::FieldSelector);

    // An override names the mark, not the palette: the cursor and everything
    // else the theme accents stay one signal, whichever glyph carries it.
    if ($glyph === NULL || !$selected) {
      return $this->marker($selected);
    }

    return $this->highlight($glyph);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldIndent(int $depth): string {
    if (!$this->indentConditional) {
      return '';
    }

    return str_repeat(' ', self::CONDITIONAL_INDENT * max(0, $depth));
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldLabel(string $text): string {
    return $this->label($text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldHelpMarker(): string {
    return $this->overriddenGlyph(ThemeElement::FieldHelpMarker) ?? $this->helpMarker();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldValue(string $text): string {
    return $this->value($text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldValueSeparator(): string {
    return $this->overrides->text(ThemeElement::FieldValueSeparator) ?? ', ';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldBadge(string $text): string {
    return $this->badge($text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldDescription(string $text): string {
    return $this->description($text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldEntry(string $text, bool $chosen): string {
    return $this->label($text, $chosen);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldEntrySelector(bool $selected): string {
    $glyph = $this->overriddenGlyph(ThemeElement::FieldEntrySelector);

    if ($glyph === NULL || !$selected) {
      return $this->marker($selected);
    }

    return $this->highlight($glyph);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldEntryMarker(bool $chosen): string {
    $glyph = $this->overriddenGlyph(ThemeElement::FieldEntryMarker);

    // Only the picked state is stated, so an entry nobody picked keeps the
    // mark the theme draws for it and the patch stays a patch.
    if ($glyph === NULL || !$chosen) {
      return $this->check($chosen);
    }

    return $this->value($glyph);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldEntryNote(string $text): string {
    return $this->disabled($text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldEntryDescription(string $text): string {
    return $this->entryDescription($text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldConstraint(string $text): string {
    return $this->hint($text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldError(string $text): string {
    return $this->error($text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldCaret(): string {
    $glyph = $this->overriddenGlyph(ThemeElement::FieldCaret);

    return $glyph === NULL ? $this->caret() : $this->highlight($glyph);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldDraft(string $text): string {
    // What is being typed takes no colour of its own: the caret is what says
    // where you are in it, and painting it would read as an accepted answer.
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldState(string $text): string {
    return $this->footer($text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldCaption(string $text): string {
    return $this->caption($text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function panelSelector(bool $selected): string {
    return $this->marker($selected);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function panelTitle(string $text): string {
    return $this->title($text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function panelDescend(): string {
    return $this->description($this->arrow());
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function panelDescription(string $text): string {
    return $this->description($text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function panelSummary(string $text): string {
    return $this->value($text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function panelSummarySeparator(): string {
    return $this->description($this->dot());
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function markupTitle(string $text): string {
    return $this->title($text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function markupLine(string $text): string {
    return $this->description($text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function actionButton(string $label): string {
    return $this->value($this->frameAction($label));
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function actionSelected(string $label): string {
    return $this->cursor($this->frameAction($label));
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function actionSeparator(): string {
    return '  ';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function progressCaption(string $text): string {
    return $this->oneLine($text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function progressSpinner(int $frame): string {
    $frames = $this->unicode ? self::SPINNER_FRAMES : self::SPINNER_ASCII;

    return $this->highlight($frames[abs($frame) % count($frames)]);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function progressTrack(int $filled, int $width): string {
    [$fill, $track] = $this->unicode ? ['█', '░'] : ['#', '-'];
    $filled = max(0, min($width, $filled));

    return '[' . ($filled > 0 ? $this->highlight(str_repeat($fill, $filled)) : '') . str_repeat($track, $width - $filled) . ']';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function progressCount(int $current, int $total): string {
    return $current . '/' . $total;
  }

  /**
   * Frame an action's label.
   *
   * The framing is the theme's rather than the block's, so a theme that draws a
   * button differently changes this alone and the block goes on knowing only
   * that it has labels.
   *
   * @param string $label
   *   The label.
   *
   * @return string
   *   The framed label.
   */
  protected function frameAction(string $label): string {
    return '[ ' . $label . ' ]';
  }

  /**
   * {@inheritdoc}
   */
  public function entryDescription(string $text): string {
    // Italicized against the description's grey: it says the same kind of thing
    // about a smaller subject, and the slant marks it as belonging to the entry
    // above rather than to the field.
    return $this->paint(Sgr::of(Sgr::Italic, Sgr::Grey), $this->linkify($text));
  }

  /**
   * {@inheritdoc}
   */
  public function helpMarker(): string {
    // The label's own colour and never bolded: the mark belongs to the label it
    // follows, and bolding it on the selected row would have it competing with
    // the label instead of hanging off it.
    //
    // A superscript rather than an enclosed glyph. An enclosed question mark
    // either has no glyph in a monospace font at all (⍰ renders as a
    // missing-character box) or is naturally wider than one cell and gets
    // squeezed out of shape by a surface that pins each cell to a fixed
    // advance (ⓘ, ℹ). A superscript is narrow by design, so it survives both.
    return $this->paint('', $this->unicode ? 'ⁱ' : '[?]');
  }

  /**
   * {@inheritdoc}
   */
  public function arrowUp(): string {
    return $this->unicode ? '↑' : '^';
  }

  /**
   * {@inheritdoc}
   */
  public function arrowDown(): string {
    return $this->unicode ? '↓' : 'v';
  }

  /**
   * {@inheritdoc}
   */
  public function arrowLeft(): string {
    return $this->unicode ? '←' : '<';
  }

  /**
   * {@inheritdoc}
   */
  public function arrowRight(): string {
    return $this->unicode ? '→' : '>';
  }

  /**
   * {@inheritdoc}
   */
  public function enter(): string {
    return $this->unicode ? '↵' : '<';
  }

  /**
   * {@inheritdoc}
   */
  public function dot(): string {
    return $this->unicode ? '·' : '*';
  }

  /**
   * {@inheritdoc}
   */
  public function indicatorUp(): string {
    return $this->unicode ? '▲' : '^';
  }

  /**
   * {@inheritdoc}
   */
  public function indicatorDown(): string {
    return $this->unicode ? '▼' : 'v';
  }

  /**
   * {@inheritdoc}
   */
  public function radio(bool $on): string {
    return $on ? $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::Cyan) : Sgr::of(Sgr::Bold, Sgr::Blue), $this->unicode ? '●' : '(*)') : ($this->unicode ? '○' : '( )');
  }

  /**
   * {@inheritdoc}
   */
  public function check(bool $on): string {
    return $on ? $this->value($this->unicode ? '◼' : '[x]') : ($this->unicode ? '◻' : '[ ]');
  }

  /**
   * {@inheritdoc}
   */
  public function caret(): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::Cyan) : Sgr::of(Sgr::Bold, Sgr::Blue), $this->unicode ? '█' : '|');
  }

  /**
   * {@inheritdoc}
   */
  public function ghost(string $text): string {
    return $this->color ? $this->paint(Sgr::of(Sgr::Grey), $text) : '';
  }

  /**
   * {@inheritdoc}
   */
  public function renderInput(string $before, string $after, string $ghost = ''): string {
    if (!$this->color || $this->field() === FieldStyle::Flat) {
      return $before . $this->caret() . $after . ($ghost === '' ? '' : $this->ghost($ghost));
    }

    // The caret reverses the character it sits on (a space at the line end), so
    // the cursor highlights the letter rather than hiding it behind a block.
    $cursor_char = $after === '' ? ' ' : Strings::substr($after, 0, 1);
    $tail = $after === '' ? '' : Strings::substr($after, 1);

    $target = max(self::FIELD_MIN_WIDTH, min($this->width, self::FIELD_WIDTH));
    $visible = Strings::length($before) + 1 + Strings::length($tail) + Strings::length($ghost);
    $pad = str_repeat(' ', max(0, $target - $visible));

    // The caret (reverse) and ghost (dim) toggle off again (27, 22) instead of
    // resetting, so the field fill runs unbroken behind the whole value - the
    // only reset is Ansi::style()'s closing one.
    $cursor = Ansi::ESC . '[7m' . $cursor_char . Ansi::ESC . '[27m';
    $suffix = $ghost === '' ? '' : Ansi::ESC . '[2m' . $ghost . Ansi::ESC . '[22m';

    // Underline styles draw the value colour; a box fills it - light on dark
    // (black on grey), dark on light (white on blue) - so the field reads
    // against either terminal background.
    $fill = $this->field() === FieldStyle::Underline ? Sgr::of(Sgr::Underline, Sgr::Green) : ($this->isDark ? Sgr::of(Sgr::Black, Sgr::OnGrey) : Sgr::of(Sgr::BrightWhite, Sgr::OnBlue));

    return Ansi::style($before . $cursor . $tail . $suffix . $pad, $fill);
  }

  /**
   * {@inheritdoc}
   */
  public function mask(): string {
    return $this->unicode ? '•' : '*';
  }

  /**
   * {@inheritdoc}
   */
  public function renderSpinner(int $frame, string $caption): string {
    // Composed from the same atoms the standalone spinner draws, so a theme
    // that restyles the glyph restyles it everywhere it turns up.
    $glyph = $this->progressSpinner($frame);
    $caption = $this->progressCaption($caption);

    return $caption === '' ? $glyph : $glyph . ' ' . $caption;
  }

  /**
   * {@inheritdoc}
   */
  public function renderProgressBar(int $current, int $total, string $caption, string $label): string {
    // Composed from the same atoms the standalone bar draws, so a theme that
    // restyles the track or the count restyles both places it appears.
    $caption = $this->progressCaption($caption);
    $label = $this->oneLine($label);

    // A total of zero has no ratio to take, and the bar reads as finished
    // rather than as empty: there was nothing left to do.
    $ratio = $total > 0 ? $current / $total : 1.0;
    $bar = $this->progressTrack((int) round($ratio * self::PROGRESS_WIDTH), self::PROGRESS_WIDTH);
    $line = ($caption === '' ? '' : $caption . ' ') . $bar . ' ' . $this->progressCount($current, $total);

    return $label === '' ? $line : $line . ' ' . $label;
  }

  /**
   * {@inheritdoc}
   */
  public function renderScale(int $current, int $min, int $max, string $caption): string {
    // The filled run carries the theme's accent through highlight(); the empty
    // remainder and the readout stay plain, so the scale reads with colour off
    // and in ASCII alike.
    [$on, $off] = $this->unicode ? ['●', '○'] : ['*', '-'];
    $caption = $this->oneLine($caption);

    // Clamp onto the scale: this method is public, so a direct call with a
    // point outside the range must not hand str_repeat() a negative count. The
    // lowest point still fills one, because it is a point like any other. The
    // frame bounds the run because nothing wider than it can be drawn anyway,
    // so an absurd range costs a truncated line rather than the whole heap.
    $points = max(1, min($this->width, $max - $min + 1));
    $filled = max(1, min($points, $current - $min + 1));

    $line = $this->highlight(str_repeat($on, $filled)) . str_repeat($off, $points - $filled) . ' ' . $current . '/' . $max;

    return $caption === '' ? $line : $line . ' ' . $caption;
  }

  /**
   * {@inheritdoc}
   */
  public function renderLoading(string $caption): string {
    // The ellipsis carries the theme's accent through highlight(), matching the
    // spinner and bar; the caption stays plain.
    $dots = $this->highlight($this->unicode ? '…' : '...');
    $caption = $this->oneLine($caption);

    return $caption === '' ? $dots : $caption . ' ' . $dots;
  }

  /**
   * {@inheritdoc}
   */
  public function renderCard(string $title, array $body, array $headers = [], array $rows = [], bool $bordered = TRUE, int $reserved = 0): array {
    // A bordered card spends a border column and a gutter each side; an
    // unbordered one only its indent. Either way the content wraps to what is
    // left rather than being clipped against the chrome.
    $inner = max(1, $this->width - $reserved - ($bordered ? self::BOX_CHROME : self::CARD_INDENT));

    $content = [];

    if ($title !== '') {
      // Wrapped like the body: a title too long for the card would otherwise be
      // clipped against the border rather than carried onto the next row.
      foreach ($this->wrapLines($title, $inner) as $line) {
        $content[] = $this->heading($line);
      }
    }

    foreach ($body as $source) {
      foreach ($this->wrapMarkup($source, $inner) as $line) {
        $content[] = $line;
      }
    }

    foreach ($this->cardTable($headers, $rows, $inner, $content !== []) as $line) {
      $content[] = $line;
    }

    if ($content === []) {
      return [];
    }

    if (!$bordered) {
      return array_map(static fn(string $line): string => str_repeat(' ', self::CARD_INDENT) . $line, $content);
    }

    return $this->boxedNote($content, $reserved);
  }

  /**
   * {@inheritdoc}
   */
  public function renderText(string $text): array {
    return $this->wrapMarkup($text, max(1, $this->width));
  }

  /**
   * {@inheritdoc}
   */
  public function renderTable(array $headers, array $rows): array {
    return $this->tableLines($headers, $rows, $this->width);
  }

  /**
   * A card's grid, set off from any title and body above it by a blank line.
   *
   * @param list<string> $headers
   *   The header cells.
   * @param list<list<string>> $rows
   *   The body rows.
   * @param int $width
   *   The width available inside the card's own chrome.
   * @param bool $spaced
   *   Whether a blank line precedes the grid; FALSE when the grid opens the
   *   card and so has nothing above to be set off from.
   *
   * @return list<string>
   *   The grid's lines, empty when there is no grid.
   */
  protected function cardTable(array $headers, array $rows, int $width, bool $spaced): array {
    if ($headers === [] && $rows === []) {
      return [];
    }

    $table = $this->tableLines($headers, $rows, $width);

    if ($table === [] || !$spaced) {
      return $table;
    }

    return array_merge([''], $table);
  }

  /**
   * Wrap source text to a width and style each physical line as markup.
   *
   * @param string $text
   *   The source text; its own newlines split it into physical lines first.
   * @param int $width
   *   The width to wrap to.
   *
   * @return list<string>
   *   The styled lines, each fitting the width.
   */
  protected function wrapMarkup(string $text, int $width): array {
    $lines = [];

    foreach ($this->wrapLines($text, $width) as $chunk) {
      if ($chunk === '') {
        $lines[] = '';

        continue;
      }

      foreach ($this->markupBody($chunk, FALSE) as $rendered) {
        $lines[] = $rendered;
      }
    }

    return $lines;
  }

  /**
   * Split source text into physical lines and word-wrap each to a width.
   *
   * @param string $text
   *   The source text; its own line endings split it first.
   * @param int $width
   *   The width to wrap to.
   *
   * @return list<string>
   *   The wrapped lines, unstyled.
   */
  protected function wrapLines(string $text, int $width): array {
    $lines = [];

    foreach (explode("\n", $this->normalizeLines($text)) as $physical) {
      $wrapped = Strings::wrap($physical, $width);

      // A line with no visible characters wraps to nothing; keeping it blank
      // lets a caller space the content out.
      foreach ($wrapped === [] ? [''] : $wrapped as $line) {
        $lines[] = $line;
      }
    }

    return $lines;
  }

  /**
   * {@inheritdoc}
   */
  public function renderStatus(Status $status, string $text): string {
    // The glyph and the message share one colour so the line reads as a single
    // statement, and the glyph alone still tells the five apart without it.
    $line = rtrim($this->statusSymbol($status) . ' ' . $this->linkify($this->oneLine($text)));

    return match ($status) {
      Status::Note => $this->description($line),
      Status::Info => $this->highlight($line),
      Status::Success => $this->value($line),
      Status::Warning => $this->indicator($line),
      Status::Error => $this->error($line),
    };
  }

  /**
   * The glyph that leads a status line.
   *
   * @param \DrevOps\Tui\Primitive\Status $status
   *   The kind of status.
   *
   * @return string
   *   The glyph, respecting the theme's Unicode mode.
   */
  protected function statusSymbol(Status $status): string {
    // Every glyph is one column wide in any terminal - none has an emoji
    // presentation or an East Asian width - so a run of status lines aligns.
    return match ($status) {
      Status::Note => $this->unicode ? '•' : '-',
      Status::Info => $this->unicode ? '›' : '>',
      Status::Success => $this->unicode ? '✓' : '+',
      Status::Warning => '!',
      Status::Error => $this->unicode ? '✗' : 'x',
    };
  }

  /**
   * {@inheritdoc}
   */
  public function renderDefinitions(array $pairs): array {
    $rows = [];

    foreach ($pairs as $label => $value) {
      // A numeric-string label arrives as an integer array key; the cast keeps
      // the width maths and the styling working on strings either way.
      $rows[] = [$this->oneLine((string) $label), $this->oneLine($value)];
    }

    if ($rows === []) {
      return [];
    }

    $widest = 0;

    foreach ($rows as $row) {
      $widest = max($widest, Ansi::width($row[0]));
    }

    // A runaway label would squeeze the values to nothing, so the label column
    // stops at half the frame and anything longer is clipped to it.
    $column = max(1, min($widest, intdiv($this->width, 2)));
    $value_width = max(1, $this->width - self::DEFINITION_INDENT - $column - self::DEFINITION_GAP);

    $lines = [];

    foreach ($rows as $row) {
      foreach ($this->definitionLines($row[0], $row[1], $column, $value_width) as $line) {
        $lines[] = $line;
      }
    }

    return $lines;
  }

  /**
   * Render one definition: its label, its value, and any wrapped continuation.
   *
   * @param string $label
   *   The label.
   * @param string $value
   *   The value.
   * @param int $column
   *   The width of the label column.
   * @param int $value_width
   *   The width available to the value.
   *
   * @return list<string>
   *   The pair's physical lines.
   */
  protected function definitionLines(string $label, string $value, int $column, int $value_width): array {
    $indent = str_repeat(' ', self::DEFINITION_INDENT);
    $gap = str_repeat(' ', self::DEFINITION_GAP);
    $chunks = Strings::wrap($value, $value_width);

    // A pair whose value is empty still shows its label.
    if ($chunks === []) {
      $chunks = [''];
    }

    $lines = [$indent . $this->label(Box::fit($label, $column)) . $gap . $this->value($chunks[0])];

    // A wrapped value continues under the value column, clear of the labels.
    $continuation = str_repeat(' ', self::DEFINITION_INDENT + $column + self::DEFINITION_GAP);

    foreach (array_slice($chunks, 1) as $chunk) {
      $lines[] = $continuation . $this->value($chunk);
    }

    return $lines;
  }

  /**
   * Fold a caption or label to a single physical line for the indicators.
   *
   * The spinner and bar redraw in place with carriage returns, so a CR or LF
   * would reposition the cursor or leave a stale row behind; newlines collapse
   * to a space.
   *
   * @param string $text
   *   The text.
   *
   * @return string
   *   The text with its line breaks folded to spaces.
   */
  protected function oneLine(string $text): string {
    return str_replace(["\r\n", "\r", "\n"], ' ', $text);
  }

  /**
   * Wrap a note's content lines in the theme's border box.
   *
   * The box is sized to its widest content line and capped at the frame width;
   * an explicit note border shows even when the frame itself is borderless, so
   * a None frame style falls back to the single-line box.
   *
   * @param list<string> $content
   *   The styled content lines (title and body).
   * @param int $reserved
   *   Columns the caller lays the box out after, kept out of the width cap so
   *   the box's right edge still lands inside the frame.
   *
   * @return list<string>
   *   The boxed lines.
   */
  protected function boxedNote(array $content, int $reserved = 0): array {
    $style = $this->borderStyle();
    if ($style === Border::None) {
      $style = Border::Line;
    }

    $chars = Box::chars($style, $this->unicode);

    $inner = 0;
    foreach ($content as $line) {
      $inner = max($inner, Ansi::width($line));
    }

    // boxLine adds a one-column gutter and a border column each side, so the
    // outer width is the content width plus four columns of chrome.
    $outer = min(max(1, $this->width - $reserved), $inner + 4);

    $lines = [$this->borderRule($chars['tl'], $chars['tr'], $chars['h'], $outer)];

    foreach ($content as $line) {
      $lines[] = $this->boxLine($line, $chars['v'], $outer);
    }

    $lines[] = $this->borderRule($chars['bl'], $chars['br'], $chars['h'], $outer);

    return $lines;
  }

  /**
   * Render a table at an explicit width cap, styled with the theme's atoms.
   *
   * @param list<string> $headers
   *   The header cells.
   * @param list<list<string>> $rows
   *   The body rows.
   * @param int $max_width
   *   The widest the table may be, in columns.
   *
   * @return list<string>
   *   The styled table lines.
   */
  protected function tableLines(array $headers, array $rows, int $max_width): array {
    // An explicit table always draws its grid, so a None frame falls back to
    // the single-line box, exactly as a bordered note does.
    $style = $this->borderStyle() === Border::None ? Border::Line : $this->borderStyle();

    return Table::render(
      $headers,
      $rows,
      $style,
      $this->unicode,
      $max_width,
      fn(string $cell): string => $this->heading($cell),
      fn(string $cell): string => $this->value($cell),
      fn(string $glyphs): string => $this->border($glyphs),
    );
  }

  /**
   * The chrome rows a frame adds around the scrolled body window.
   *
   * Everything renderFrame() emits that is neither a header/footer line nor a
   * body-window line: border rules and spacing pads for a boxed frame, the
   * footer gap for a borderless one - plus the reserved scroll-indicator rows.
   * The single home of the frame-height budget, so a caller sizing the body
   * viewport to the terminal never overflows it.
   *
   * @param bool $has_footer
   *   Whether the frame draws footer lines (a boxed frame separates them with
   *   an extra rule).
   *
   * @return int
   *   The chrome row count.
   */
  public function chromeHeight(bool $has_footer): int {
    if ($this->borderStyle() === Border::None) {
      return ($this->spacing() === Spacing::Compact ? 0 : 1) + self::INDICATOR_LINES;
    }

    $pad = $this->spacing() === Spacing::Padded ? 2 : 0;

    return 3 + ($has_footer ? 1 : 0) + $pad + self::INDICATOR_LINES;
  }

  /**
   * Compose a frame: pinned header, scrolled body with indicators, footer.
   *
   * In fullscreen the body window stretches to its full budget - the block
   * aligns per the halign/valign options and the frame fills the terminal
   * exactly; otherwise the frame stays as tall as its content.
   *
   * @param list<string> $header
   *   The pinned header lines.
   * @param list<string> $body
   *   The full body lines.
   * @param list<string> $footer
   *   The pinned footer lines.
   * @param \DrevOps\Tui\Render\Viewport $viewport
   *   The computed viewport.
   * @param int $height
   *   The body viewport height.
   *
   * @return string
   *   The composed frame.
   */
  public function renderFrame(array $header, array $body, array $footer, Viewport $viewport, int $height): string {
    return $this->renderBoxed($header, $body, $footer, $viewport, $height, $this->outerWidth, $this->borderStyle(), $this->isFullscreen());
  }

  /**
   * Compose a frame at an explicit width and border, else the same as a frame.
   *
   * The width/border are parameters so a modal can reuse the theme's boxing in
   * a narrower box; the standard frame passes its own outer width and border.
   *
   * @param list<string> $header
   *   The pinned header lines.
   * @param list<string> $body
   *   The full body lines.
   * @param list<string> $footer
   *   The pinned footer lines.
   * @param \DrevOps\Tui\Render\Viewport $viewport
   *   The computed viewport.
   * @param int $height
   *   The body viewport height.
   * @param int $outer_width
   *   The outer width, including the border columns.
   * @param \DrevOps\Tui\Theme\Border $border
   *   The border style to draw.
   * @param bool $stretch
   *   Whether the body window stretches to its full budget with the block
   *   aligned inside it (the fullscreen frame), rather than hugging the
   *   content (a modal dialog's box).
   *
   * @return string
   *   The composed frame.
   */
  protected function renderBoxed(array $header, array $body, array $footer, Viewport $viewport, int $height, int $outer_width, Border $border, bool $stretch = FALSE): string {
    if ($border === Border::None) {
      return $this->renderBorderless($header, $body, $footer, $viewport, $height, $stretch);
    }

    $chars = Box::chars($border, $this->unicode);
    $middle = $this->scrolledBody($body, $viewport, $height);
    $pad = $this->spacing() === Spacing::Padded;

    if ($stretch) {
      $middle = $this->alignBlock($middle, max(1, $outer_width - 4), $height + self::INDICATOR_LINES);
    }

    $out = [$this->borderRule($chars['tl'], $chars['tr'], $chars['h'], $outer_width)];

    foreach ($header as $line) {
      $out[] = $this->boxLine($line, $chars['v'], $outer_width);
    }

    $out[] = $this->borderRule($chars['ml'], $chars['mr'], $chars['h'], $outer_width);

    if ($pad) {
      $out[] = $this->boxLine('', $chars['v'], $outer_width);
    }

    foreach ($middle as $line) {
      $out[] = $this->boxLine($line, $chars['v'], $outer_width);
    }

    if ($pad) {
      $out[] = $this->boxLine('', $chars['v'], $outer_width);
    }

    if ($footer !== []) {
      $out[] = $this->borderRule($chars['ml'], $chars['mr'], $chars['h'], $outer_width);

      foreach ($footer as $line) {
        $out[] = $this->boxLine($line, $chars['v'], $outer_width);
      }
    }

    $out[] = $this->borderRule($chars['bl'], $chars['br'], $chars['h'], $outer_width);

    return implode("\n", $out);
  }

  /**
   * Compose a borderless frame, detaching the status line by spacing.
   *
   * @param list<string> $header
   *   The header lines.
   * @param list<string> $body
   *   The body lines.
   * @param list<string> $footer
   *   The footer lines.
   * @param \DrevOps\Tui\Render\Viewport $viewport
   *   The viewport.
   * @param int $height
   *   The body viewport height.
   * @param bool $stretch
   *   Whether the body window stretches to its full budget with the block
   *   aligned inside it.
   *
   * @return string
   *   The composed frame.
   */
  protected function renderBorderless(array $header, array $body, array $footer, Viewport $viewport, int $height, bool $stretch = FALSE): string {
    $middle = $this->scrolledBody($body, $viewport, $height);

    if ($stretch) {
      $middle = $this->alignBlock($middle, $this->width, $height + self::INDICATOR_LINES);
    }

    $lines = array_merge($header, $middle);

    if ($this->spacing() !== Spacing::Compact) {
      $lines[] = '';
    }

    return implode("\n", array_merge($lines, $footer));
  }

  /**
   * Align a block of lines within an area, padding it to the area's size.
   *
   * The lines move as one unit - their left edges stay mutually aligned - to
   * the anchor the halign/valign options pick: blank rows pad the block to the
   * target height and a uniform indent shifts it across the width.
   *
   * @param list<string> $lines
   *   The block lines (may carry ANSI codes).
   * @param int $inner_width
   *   The width of the area the block aligns within.
   * @param int $target_height
   *   The height the block pads to.
   *
   * @return list<string>
   *   The aligned lines, exactly the target height when the block fits it.
   */
  protected function alignBlock(array $lines, int $inner_width, int $target_height): array {
    $block_width = Ansi::blockWidth($lines);

    [$top, $left] = Overlay::place($inner_width, $target_height, $block_width, count($lines), $this->halign(), $this->valign());

    $indent = str_repeat(' ', $left);
    $out = array_fill(0, $top, '');

    foreach ($lines as $line) {
      $out[] = $line === '' ? '' : $indent . $line;
    }

    while (count($out) < $target_height) {
      $out[] = '';
    }

    return $out;
  }

  /**
   * The visible body window, wrapped with the scroll indicators.
   *
   * @param list<string> $body
   *   The full body lines.
   * @param \DrevOps\Tui\Render\Viewport $viewport
   *   The computed viewport.
   * @param int $height
   *   The body viewport height.
   *
   * @return list<string>
   *   The visible lines, with an indicator line for each hidden side.
   */
  protected function scrolledBody(array $body, Viewport $viewport, int $height): array {
    $lines = [];

    if ($viewport->hasAbove) {
      $lines[] = $this->indicator('  ' . $this->indicatorUp());
    }

    $lines = array_merge($lines, (new Scroller())->slice($body, $viewport->offset, $height));

    if ($viewport->hasBelow) {
      $lines[] = $this->indicator('  ' . $this->indicatorDown());
    }

    return $lines;
  }

  /**
   * Build a horizontal border rule, coloured with the border atom.
   *
   * @param string $left
   *   The left corner or junction glyph.
   * @param string $right
   *   The right corner or junction glyph.
   * @param string $fill
   *   The horizontal fill glyph.
   * @param int $outer_width
   *   The total width the rule spans.
   *
   * @return string
   *   The styled rule.
   */
  protected function borderRule(string $left, string $right, string $fill, int $outer_width): string {
    return $this->border(Box::rule($left, $right, $fill, $outer_width));
  }

  /**
   * Wrap a content line in vertical borders with a one-column gutter each side.
   *
   * @param string $content
   *   The content (may carry ANSI codes and be shorter than the inner width).
   * @param string $vertical
   *   The vertical border glyph.
   * @param int $outer_width
   *   The outer width the line is padded to, including the border columns.
   *
   * @return string
   *   The boxed line, padded to the outer width.
   */
  protected function boxLine(string $content, string $vertical, int $outer_width): string {
    $bar = $this->border($vertical);

    return $bar . ' ' . Box::fit($content, max(1, $outer_width - 4)) . ' ' . $bar;
  }

  /**
   * {@inheritdoc}
   */
  public function renderBanner(string $logo, string $version): string {
    $lines = [];

    foreach (explode("\n", $logo) as $line) {
      $lines[] = $this->title($line);
    }

    if ($version !== '') {
      $lines[] = '';
      $lines[] = $this->footer(Translator::t('Version: @version', ['@version' => $version]));
    }

    return implode("\n", $lines);
  }

  /**
   * {@inheritdoc}
   */
  public function keyHint(Key $key): string {
    $name = $key->name;

    if (!$name instanceof KeyName) {
      return $key->label();
    }

    return match ($name) {
      KeyName::Up, KeyName::MouseWheelUp => $this->arrowUp(),
      KeyName::Down, KeyName::MouseWheelDown => $this->arrowDown(),
      KeyName::Left => $this->arrowLeft(),
      KeyName::Right => $this->arrowRight(),
      KeyName::Enter => $this->enter(),
      KeyName::Escape => Translator::t('esc'),
      KeyName::Interrupt => Translator::t('ctrl-c'),
      KeyName::Tab => Translator::t('tab'),
      KeyName::Space => Translator::t('space'),
      KeyName::Backspace => $this->unicode ? '⌫' : Translator::t('bksp'),
      KeyName::Delete => Translator::t('del'),
      KeyName::Home => Translator::t('home'),
      KeyName::End => Translator::t('end'),
      KeyName::PageUp => Translator::t('pgup'),
      KeyName::PageDown => Translator::t('pgdn'),
    };
  }

  /**
   * {@inheritdoc}
   */
  public function keysHint(ScopedKeyMap $keys, string $label, Action ...$actions): string {
    $glyphs = [];

    foreach ($actions as $action) {
      $key = $keys->primary($action);

      if ($key instanceof Key) {
        $glyphs[] = $this->keyHint($key);
      }
    }

    if ($glyphs === []) {
      return '';
    }

    // "KEY to action" rather than "KEY action": the preposition is what makes a
    // legend entry a sentence you can read rather than two words abutted.
    return $this->legendKey(implode('/', $glyphs)) . ' ' . $this->legendDescription(Translator::t('to @action', ['@action' => $label]));
  }

  /**
   * Render a context's hint fragments as one dot-joined footer line.
   *
   * Each {@see Hint} becomes a labelled fragment drawn from the live bindings,
   * so the line never contradicts a remapped key. Fragments whose actions are
   * all unbound drop out, and an entirely unbound context yields an empty line.
   *
   * @param \DrevOps\Tui\Input\ScopedKeyMap $keys
   *   The active scope's bindings.
   * @param \DrevOps\Tui\Input\Hint ...$hints
   *   The hint fragments, in display order.
   *
   * @return string
   *   The themed hint line, or an empty string when nothing is bound.
   */
  public function renderHints(ScopedKeyMap $keys, Hint ...$hints): string {
    $fragments = [];

    foreach ($hints as $hint) {
      $fragment = $this->keysHint($keys, $hint->label, ...$hint->actions);

      if ($fragment !== '') {
        $fragments[] = $fragment;
      }
    }

    return $fragments === [] ? '' : $this->renderHintLine(...$fragments);
  }

  /**
   * Render a dimmed line of key hints, joined with the dot glyph.
   *
   * @param string ...$hints
   *   The hint fragments (e.g. "enter accept", "esc cancel"). Empty fragments -
   *   an unbound action - are dropped so the line has no dangling separators.
   *
   * @return string
   *   The themed hint line.
   */
  public function renderHintLine(string ...$hints): string {
    // The parts arrive styled by their own atoms, so the line only joins them.
    return implode(' ' . $this->legendSeparator() . ' ', array_filter($hints));
  }

  /**
   * Render the header shown above a field's editor: its label, underlined.
   *
   * @param string $label
   *   The field label.
   *
   * @return string
   *   The two-line themed header.
   */
  public function renderEditorHeader(string $label): string {
    $underline = str_repeat($this->unicode ? '─' : '-', max(1, Markup::width($label, FALSE, $this->color)));

    return $this->title($label) . "\n" . $this->rule($underline);
  }

  /**
   * Compose a field's editor screen: the label, the field view and its hints.
   *
   * @param string $label
   *   The field label.
   * @param string $view
   *   The field's rendered view.
   * @param list<\DrevOps\Tui\Input\Hint> $hints
   *   The field's hint fragments; an empty list draws no hint line, so the
   *   footer can be turned off form-wide.
   * @param \DrevOps\Tui\Input\ScopedKeyMap|null $keys
   *   The editor's scope bindings, so the hint glyphs reflect the active keys.
   * @param int $rows
   *   The terminal rows a fullscreen editor stretches its frame to; 0 keeps
   *   the screen as tall as its content.
   *
   * @return string
   *   The editor screen - boxed when the theme has a border, stretched to the
   *   given rows in fullscreen, else plain.
   */
  public function renderEditor(string $label, string $view, array $hints = [], ?ScopedKeyMap $keys = NULL, int $rows = 0): string {
    $hint = $keys instanceof ScopedKeyMap ? $this->renderHints($keys, ...$hints) : '';
    $footer = $hint === '' ? [] : [$hint];
    $stretch = $this->isFullscreen() && $rows > 0;

    if ($this->borderStyle() !== Border::None || $stretch) {
      $body = explode("\n", $view);
      $height = count($body);

      // A borderless editor keeps its label-over-rule header inside the frame.
      $header = $this->borderStyle() === Border::None ? explode("\n", $this->renderEditorHeader($label)) : [$this->title($label)];

      // A fullscreen editor stretches its frame like the hub does - the hint
      // footer pins to the bottom row. A view taller than the budget keeps
      // its full height - fields page inside themselves, so slicing here
      // would hide rows they expect to show.
      if ($stretch) {
        $height = max($height, $rows - count($header) - count($footer) - $this->chromeHeight($footer !== []));
      }

      return $this->renderFrame($header, $body, $footer, new Viewport(0, FALSE, FALSE), $height);
    }

    $screen = $this->renderEditorHeader($label) . "\n" . $view;

    return $hint === '' ? $screen : $screen . "\n\n" . $hint;
  }

  /**
   * Compose a field's help as a full-screen page.
   *
   * The text is markup-formatted and wrapped like a description, since it is
   * same voice at greater length, and takes the whole screen because that is
   * what lets it run to paragraphs.
   *
   * @param string $label
   *   The label of the field the help belongs to.
   * @param string $help
   *   The help text; blank lines separate paragraphs.
   * @param \DrevOps\Tui\Input\ScopedKeyMap $keys
   *   The bindings the close hint is resolved against.
   *
   * @return string
   *   The rendered page.
   */
  public function renderHelp(string $label, string $help, ScopedKeyMap $keys): string {
    $lines = [$this->title($label), ''];

    foreach ($this->markupBody($help, FALSE) as $line) {
      $lines[] = $line;
    }

    $lines[] = '';
    $lines[] = $this->renderHints($keys, new Hint('close', Action::Help));

    return implode("\n", $lines);
  }

  /**
   * Render a row of inline submit/cancel buttons.
   *
   * @param list<string> $labels
   *   The button labels, in order.
   * @param int $selected
   *   The index of the selected button, or -1 for none.
   *
   * @return string
   *   The themed button row with the buttons side by side.
   */
  public function renderButtonBar(array $labels, int $selected): string {
    $parts = [];

    foreach ($labels as $index => $label) {
      $text = '[ ' . $label . ' ]';
      $parts[] = $index === $selected ? $this->cursor($text) : $this->value($text);
    }

    return '  ' . implode('  ', $parts);
  }

  /**
   * Normalize a value's line endings to newlines.
   *
   * A carriage return would send the terminal cursor back to the start of the
   * row and overprint what is already there, and it counts toward the visible
   * width that right-aligns a badge. Folding CRLF and CR endings - what an
   * external editor's save can carry in - to the newline the row layout splits
   * on keeps both correct.
   *
   * @param string $value
   *   The value.
   *
   * @return string
   *   The value with every line ending as a newline.
   */
  protected function normalizeLines(string $value): string {
    return str_replace(["\r\n", "\r"], "\n", $value);
  }

}
