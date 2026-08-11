<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Block\Capability\BindCapableInterface;
use DrevOps\Tui\Block\Capability\BindCapableTrait;
use DrevOps\Tui\Block\Capability\DependCapableInterface;
use DrevOps\Tui\Block\Capability\DependCapableTrait;
use DrevOps\Tui\Block\Capability\DescendCapableInterface;
use DrevOps\Tui\Block\Capability\FocusCapableInterface;
use DrevOps\Tui\Block\Capability\FocusCapableTrait;
use DrevOps\Tui\Block\Capability\OverlayCapableInterface;
use DrevOps\Tui\Block\Element\ChromeElementsInterface;
use DrevOps\Tui\Block\Element\MarkupElementsInterface;
use DrevOps\Tui\Block\Element\PanelElementsInterface;
use DrevOps\Tui\FormException;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Furniture;
use DrevOps\Tui\Screen\Layout\LayoutInterface;
use DrevOps\Tui\Screen\Region;
use DrevOps\Tui\Terminal\Ansi;
use DrevOps\Tui\Theme\Capability\OccupyCapableInterface;
use DrevOps\Tui\Theme\Spacing;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Translation\Translator;

/**
 * A block that navigation enters and leaves.
 *
 * It is the only block that nests a layout and the only one navigation
 * enters: entering replaces the screen and adds a trail segment; leaving
 * restores both. A region holds blocks and a layout arranges them, but
 * neither is entered.
 *
 * Nested inside another panel it draws as a selectable row. Once entered it
 * draws nothing of its own; its blocks draw instead.
 *
 * A condition decides whether a whole section is there, exactly as one
 * decides a single row. An absent section takes everything it holds with it,
 * so a question inside one is never asked, drawn or entered.
 *
 * @package DrevOps\Tui\Block
 */
final class Panel extends AbstractBlock implements BindCapableInterface, DependCapableInterface, DescendCapableInterface, FocusCapableInterface, OverlayCapableInterface {

  use BindCapableTrait;
  use DependCapableTrait;
  use FocusCapableTrait;

  /**
   * The default name of the region holding a panel's own rows.
   */
  protected const string ROWS = 'content';

  /**
   * The indent the rows under a panel's title are stepped in by.
   */
  protected const string INDENT = '    ';

  /**
   * The maximum number of answers the summary line lists.
   */
  protected const int SUMMARY_ANSWERS = 4;

  /**
   * The maximum number of picks listed before a count replaces them.
   */
  protected const int SUMMARY_ITEMS = 3;

  /**
   * The layout arranging this panel's blocks, or NULL before one is set.
   */
  protected ?LayoutInterface $layout = NULL;

  /**
   * Whether the panel draws over what is behind it rather than replacing it.
   */
  protected bool $modal = FALSE;

  /**
   * Whether this panel is the entered one.
   */
  protected bool $entered = FALSE;

  /**
   * The title shown in the trail.
   */
  protected string $title;

  /**
   * The description shown under the title.
   */
  protected string $description = '';

  /**
   * The button pair that closes a modal panel.
   */
  protected Buttons $buttons;

  /**
   * The preparation run before the panel is first entered, until it has run.
   */
  protected ?\Closure $preload = NULL;

  /**
   * Construct a panel.
   *
   * @param string $id
   *   The id it is addressed by.
   * @param string $title
   *   The title shown in the trail.
   */
  public function __construct(
    protected string $id,
    string $title,
  ) {
    $this->title = Ansi::sanitize($title);
    $this->buttons = new Buttons();
  }

  /**
   * The id this panel is addressed by.
   *
   * @return string
   *   The id.
   */
  public function id(): string {
    return $this->id;
  }

  /**
   * The title shown in the trail.
   *
   * @return string
   *   The title.
   */
  public function title(): string {
    return $this->title;
  }

  /**
   * Set the description shown under this panel's title.
   *
   * @param string $description
   *   The description.
   *
   * @return static
   *   The panel.
   */
  public function description(string $description): static {
    $this->description = Ansi::sanitize($description);

    return $this;
  }

  /**
   * The description shown under this panel's title.
   *
   * @return string
   *   The description, empty when none is set.
   */
  public function descriptionText(): string {
    return $this->description;
  }

  /**
   * Set the button pair that closes this panel.
   *
   * @param \DrevOps\Tui\Block\Buttons $buttons
   *   The pair that closes it.
   *
   * @return static
   *   The panel.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the pair is hidden on a modal panel, whose buttons are its only
   *   way out.
   */
  public function buttons(Buttons $buttons): static {
    $this->assertWayOut($this->modal, $buttons);
    $this->buttons = $buttons;

    return $this;
  }

  /**
   * The button pair that closes this panel.
   *
   * @return \DrevOps\Tui\Block\Buttons
   *   The pair that closes it.
   */
  public function currentButtons(): Buttons {
    return $this->buttons;
  }

  /**
   * Prepare this panel before it is first entered.
   *
   * @param \Closure $work
   *   An `fn (): void` doing the preparation, for example one fetch several
   *   of the panel's fields then read.
   *
   * @return static
   *   The panel.
   */
  public function preload(\Closure $work): static {
    $this->preload = $work;

    return $this;
  }

  /**
   * The preparation run before this panel is first entered.
   *
   * @return \Closure|null
   *   The preparation, or NULL when none remains.
   */
  public function preparation(): ?\Closure {
    return $this->preload;
  }

  /**
   * Run the preparation before this panel is first entered.
   *
   * @return bool
   *   Whether anything ran. Preparation runs once, so every call after the
   *   first returns FALSE.
   */
  public function prepare(): bool {
    if (!$this->preload instanceof \Closure) {
      return FALSE;
    }

    ($this->preload)();
    $this->preload = NULL;

    return TRUE;
  }

  /**
   * Arrange this panel's blocks with a layout.
   *
   * @param \DrevOps\Tui\Screen\Layout\LayoutInterface $layout
   *   The layout.
   *
   * @return static
   *   The panel.
   */
  public function layout(LayoutInterface $layout): static {
    $this->layout = $layout;

    return $this;
  }

  /**
   * The layout arranging this panel's blocks.
   *
   * @return \DrevOps\Tui\Screen\Layout\LayoutInterface
   *   The layout.
   */
  public function currentLayout(): LayoutInterface {
    if (!$this->layout instanceof LayoutInterface) {
      throw new \LogicException(sprintf('Panel "%s" has no layout, so it has no regions to place a block in.', $this->id));
    }

    return $this->layout;
  }

  /**
   * The region of a given name, to place blocks in.
   *
   * @param string $name
   *   The region name.
   *
   * @return \DrevOps\Tui\Screen\Region
   *   The region.
   */
  public function in(string $name): Region {
    return $this->currentLayout()->in($name);
  }

  /**
   * {@inheritdoc}
   */
  public function enter(): static {
    $this->entered = TRUE;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function leave(): static {
    $this->entered = FALSE;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isEntered(): bool {
    return $this->entered;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \DrevOps\Tui\FormException
   *   When its buttons are hidden; a modal panel's buttons are its only way
   *   out.
   */
  public function modal(): static {
    $this->assertWayOut(TRUE, $this->buttons);
    $this->modal = TRUE;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isModal(): bool {
    return $this->modal;
  }

  /**
   * {@inheritdoc}
   *
   * An un-entered panel binds no keys, so the keys that move the cursor past
   * it reach the panel it sits in.
   */
  public function binds(Key $key): bool {
    return $this->entered && $this->boundAction($key) instanceof Action;
  }

  /**
   * {@inheritdoc}
   *
   * These keys apply inside a panel itself rather than inside a block it
   * holds: moving the cursor, entering a nested panel and going back all
   * resolve here.
   */
  public function hints(): array {
    // Regions drawn side by side are moved between in two directions, so the
    // move hint depends on the arrangement: a Columns axis draws every region
    // abreast, and a grid places several regions on one line.
    $abreast = $this->layout instanceof LayoutInterface
      && ($this->layout->axis() === Axis::Columns || array_filter($this->layout->lines(), static fn(array $line): bool => count($line) > 1) !== []);
    $move = $abreast
      ? new Hint('move', Action::MoveUp, Action::MoveDown, Action::MoveLeft, Action::MoveRight)
      : new Hint('move', Action::MoveUp, Action::MoveDown);

    return [
      $move,
      new Hint('select', Action::Activate),
      new Hint('go back', Action::Back),
    ];
  }

  /**
   * The fields this panel holds, in the order they were placed.
   *
   * Its own only: a nested panel's fields belong to that panel and are not
   * included.
   *
   * @return list<\DrevOps\Tui\Block\Field>
   *   The fields.
   */
  public function fields(): array {
    $fields = [];

    foreach ($this->blocks() as $block) {
      if ($block instanceof Field) {
        $fields[] = $block;
      }
    }

    return $fields;
  }

  /**
   * The ids of the rows this panel holds, in the order they were placed.
   *
   * Every row that carries an id is included, whether it collects an answer
   * or only shows something. A display-only id is still an id the form
   * knows, so a stray answer can be told from one aimed at a row that takes
   * none.
   *
   * @return list<string>
   *   The ids.
   */
  public function ids(): array {
    $ids = [];

    foreach ($this->blocks() as $block) {
      if ($block instanceof Field || $block instanceof Markup || $block instanceof Progress) {
        $ids[] = $block->id();
      }
    }

    return $ids;
  }

  /**
   * The panels that can be entered from this one.
   *
   * @return list<\DrevOps\Tui\Block\Panel>
   *   The sub-panels, in the order they were placed.
   */
  public function children(): array {
    $children = [];

    foreach ($this->blocks() as $block) {
      if ($block instanceof self) {
        $children[] = $block;
      }
    }

    return $children;
  }

  /**
   * Everything placed in this panel, region by region, in placement order.
   *
   * @return list<\DrevOps\Tui\Block\BlockInterface>
   *   The blocks; empty while the panel has no layout to hold any.
   */
  public function blocks(): array {
    if (!$this->layout instanceof LayoutInterface) {
      return [];
    }

    $blocks = [];

    foreach ($this->layout->names() as $name) {
      foreach ($this->layout->in($name)->blocks() as $block) {
        $blocks[] = $block;
      }
    }

    return $blocks;
  }

  /**
   * {@inheritdoc}
   *
   * Draws the panel as a selectable row: its headline, its description and a
   * summary of the answers it holds.
   */
  public function render(ThemeInterface $theme): string {
    $elements = $this->guard($theme);
    $lines = [$this->headline($elements)];

    foreach ($this->guidance($theme, $elements) as $line) {
      $lines[] = self::INDENT . $line;
    }

    $summary = $this->summary($theme, $elements);

    if ($summary !== '') {
      $lines[] = self::INDENT . $elements->panelSummary($summary);
    }

    return $this->stepped(implode("\n", $lines), $this->gutter($theme));
  }

  /**
   * This panel drawn as its headline row only.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return string
   *   The row: the selector, the title and the descend mark.
   */
  public function wayIn(ThemeInterface $theme): string {
    return $this->stepped($this->headline($this->guard($theme)), $this->gutter($theme));
  }

  /**
   * This panel drawn as a preview of its rows rather than as one row.
   *
   * A preview shows the panel's rows themselves where {@see render()} shows
   * one summary line, so a panel drawn beside its siblings in a grid reads
   * as a view of the form.
   *
   * A preview is one column, so a nested panel inside it is drawn as its
   * headline line only: the full row with a description and a summary
   * belongs to a list, which has a whole width to spend on it.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return string
   *   The preview; newlines separate rows.
   */
  public function preview(ThemeInterface $theme): string {
    $elements = $this->guard($theme);
    $lines = [$this->headline($elements)];

    foreach ($this->guidance($theme, $elements) as $line) {
      $lines[] = self::INDENT . $line;
    }

    foreach ($this->blocks() as $block) {
      if ($block instanceof DependCapableInterface && $block->isHidden()) {
        continue;
      }

      $drawn = $block instanceof self ? $block->wayIn($theme) : $block->render($theme);

      if ($drawn === '') {
        continue;
      }

      foreach (explode("\n", $drawn) as $line) {
        $lines[] = $line;
      }
    }

    return $this->stepped(implode("\n", $lines), $this->gutter($theme));
  }

  /**
   * The region this panel's own rows are drawn in.
   *
   * The screen layout and a panel layout answer this the same way, so the
   * region is read off the layout's Body furnishing rather than found by a
   * fixed name.
   *
   * @return \DrevOps\Tui\Screen\Region
   *   The region.
   */
  public function place(): Region {
    return $this->in($this->currentLayout()->furnishes(Furniture::Body) ?? self::ROWS);
  }

  /**
   * The theme this panel draws through, rejecting an entered panel.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return \DrevOps\Tui\Block\Element\PanelElementsInterface
   *   The theme, narrowed to the elements a panel draws with.
   *
   * @throws \LogicException
   *   When the panel is entered; an entered panel draws nothing of its own.
   */
  protected function guard(ThemeInterface $theme): PanelElementsInterface {
    // Drawing and focus belong to a nested panel, not an entered one: once
    // entered, its blocks draw and the panel draws nothing of its own.
    if ($this->entered) {
      throw new \LogicException(sprintf('Panel "%s" is entered, so its blocks draw rather than the panel itself.', $this->id));
    }

    return $this->elements($theme, PanelElementsInterface::class, 'a panel');
  }

  /**
   * The gutter this panel's rows are stepped in by.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return string
   *   The gutter, empty when the section is not stepped in.
   */
  protected function gutter(ThemeInterface $theme): string {
    return $this->elements($theme, ChromeElementsInterface::class, 'a conditional section')->chromeIndent($this->depth);
  }

  /**
   * The row joining the selector, the title and the descend mark.
   *
   * @param \DrevOps\Tui\Block\Element\PanelElementsInterface $elements
   *   The theme, narrowed to the elements a panel draws with.
   *
   * @return string
   *   The row.
   */
  protected function headline(PanelElementsInterface $elements): string {
    return $elements->panelSelector($this->isFocused()) . ' ' . $elements->panelTitle(Translator::t($this->title)) . ' ' . $elements->panelDescend();
  }

  /**
   * This panel's description, drawn as rows.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\Tui\Block\Element\PanelElementsInterface $elements
   *   The same theme, narrowed to the elements a panel draws with.
   *
   * @return list<string>
   *   The rows; none when there is no description or the theme is compact.
   *   The description is secondary to the rows it introduces, so a compact
   *   theme drops it.
   */
  protected function guidance(ThemeInterface $theme, PanelElementsInterface $elements): array {
    if ($this->description === '' || $this->terse($theme)) {
      return [];
    }

    $markup = $this->elements($theme, MarkupElementsInterface::class, 'a description');

    return Prose::lines(Translator::t($this->description), $markup, $elements->panelDescription(...));
  }

  /**
   * The answers this panel holds, as one line.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\Tui\Block\Element\PanelElementsInterface $elements
   *   The same theme, narrowed to the elements a panel draws with.
   *
   * @return string
   *   The answers, empty when the panel holds none, when every field is
   *   hidden, or when the theme is compact.
   */
  protected function summary(ThemeInterface $theme, PanelElementsInterface $elements): string {
    if ($this->terse($theme)) {
      return '';
    }

    $answers = [];

    foreach ($this->fields() as $field) {
      if ($field->isHidden()) {
        continue;
      }

      $answers[] = $this->held($theme, $field);

      if (count($answers) >= self::SUMMARY_ANSWERS) {
        break;
      }
    }

    return implode(' ' . $elements->panelSummarySeparator() . ' ', $answers);
  }

  /**
   * One field's answer, as the summary line shows it.
   *
   * A value of up to SUMMARY_ITEMS picks is listed as the picks themselves;
   * a longer one is shown as a count, so the summary line stays a summary
   * rather than the panel's whole content.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\Tui\Block\Field $field
   *   The field holding the answer.
   *
   * @return string
   *   The answer as it reads on the summary line.
   */
  protected function held(ThemeInterface $theme, Field $field): string {
    $value = $field->value();

    if (!is_array($value) || count($value) <= self::SUMMARY_ITEMS) {
      return $field->valueText($theme);
    }

    return Translator::formatPlural(count($value), '1 item selected', '@count items selected');
  }

  /**
   * Whether the theme is set to compact spacing.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return bool
   *   TRUE when the spacing is compact.
   */
  protected function terse(ThemeInterface $theme): bool {
    return $theme instanceof OccupyCapableInterface && $theme->spacing() === Spacing::Compact;
  }

  /**
   * {@inheritdoc}
   */
  protected function keyScope(): Scope {
    return Scope::navigation();
  }

  /**
   * Reject a modal panel with hidden buttons, which would have no way out.
   *
   * @param bool $modal
   *   Whether the panel draws over what is behind it.
   * @param \DrevOps\Tui\Block\Buttons $buttons
   *   The pair that closes it.
   *
   * @throws \DrevOps\Tui\FormException
   *   When such a panel hides its buttons.
   */
  protected function assertWayOut(bool $modal, Buttons $buttons): void {
    if ($modal && !$buttons->show) {
      throw new FormException(sprintf('Panel "%s" draws over what is behind it, so its buttons are its only way out and cannot be hidden.', $this->id));
    }
  }

}
