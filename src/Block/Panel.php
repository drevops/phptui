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
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Model\Buttons;
use DrevOps\Tui\Model\FormException;
use DrevOps\Tui\Screen\Layout\LayoutInterface;
use DrevOps\Tui\Screen\Region;
use DrevOps\Tui\Theme\Capability\OccupyCapableInterface;
use DrevOps\Tui\Theme\Spacing;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Translation\Translator;

/**
 * A destination you can go into and come back from.
 *
 * It is the only block that nests a layout, and the only one you navigate into:
 * entering it replaces the screen and grows the trail; leaving restores both.
 * A region can hold blocks and a layout can arrange them, but neither is
 * somewhere you go.
 *
 * Nested inside another panel it draws a row you select. Once entered it draws
 * nothing of its own, because its blocks do.
 *
 * A whole section can come and go with the answers, exactly as one row can: the
 * condition decides whether the section is there at all, and a section that is
 * not there takes everything it holds with it - so a question inside one is
 * never asked, drawn or navigated into.
 *
 * @package DrevOps\Tui\Block
 */
final class Panel extends AbstractBlock implements BindCapableInterface, DependCapableInterface, DescendCapableInterface, FocusCapableInterface, OverlayCapableInterface {

  use BindCapableTrait;
  use DependCapableTrait;
  use FocusCapableTrait;

  /**
   * The region a layout names for the rows a panel holds itself.
   */
  protected const string ROWS = 'content';

  /**
   * The gutter the rows under a panel's title are stepped in by.
   */
  protected const string INDENT = '    ';

  /**
   * How many answers a panel says it is holding before it stops counting.
   */
  protected const int SUMMARY_ANSWERS = 4;

  /**
   * How many picks a panel spells out before it says how many there were.
   */
  protected const int SUMMARY_ITEMS = 3;

  /**
   * The layout arranging this panel's blocks, once one is given.
   */
  protected ?LayoutInterface $layout = NULL;

  /**
   * Whether it draws over what is behind it rather than replacing it.
   */
  protected bool $modal = FALSE;

  /**
   * Whether this is the panel you are currently in.
   */
  protected bool $entered = FALSE;

  /**
   * The standing text under its title.
   */
  protected string $description = '';

  /**
   * The way out of it, once it draws over what is behind it.
   */
  protected Buttons $buttons;

  /**
   * What prepares it before it is first entered, until that has been done.
   */
  protected ?\Closure $preload = NULL;

  /**
   * How the panels nested in it sit side by side, one entry per visual row.
   *
   * @var list<int>
   */
  protected array $grid = [];

  /**
   * Construct a panel.
   *
   * @param string $id
   *   The id it is addressed by.
   * @param string $title
   *   The title it carries into the trail.
   */
  public function __construct(
    protected string $id,
    protected string $title,
  ) {
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
   * The title this panel carries into the trail.
   *
   * @return string
   *   The title.
   */
  public function title(): string {
    return $this->title;
  }

  /**
   * Set the standing text under this panel's title.
   *
   * @param string $description
   *   The description.
   *
   * @return static
   *   The panel.
   */
  public function description(string $description): static {
    $this->description = $description;

    return $this;
  }

  /**
   * The standing text under this panel's title.
   *
   * @return string
   *   The description, empty when it carries none.
   */
  public function descriptionText(): string {
    return $this->description;
  }

  /**
   * Label the way out of this panel.
   *
   * @param \DrevOps\Tui\Model\Buttons $buttons
   *   The pair that closes it.
   *
   * @return static
   *   The panel.
   *
   * @throws \DrevOps\Tui\Model\FormException
   *   When the pair is hidden on a panel that draws over what is behind it,
   *   which would strand it with no way out.
   */
  public function buttons(Buttons $buttons): static {
    $this->assertWayOut($this->modal, $buttons);
    $this->buttons = $buttons;

    return $this;
  }

  /**
   * The way out of this panel.
   *
   * @return \DrevOps\Tui\Model\Buttons
   *   The pair that closes it.
   */
  public function currentButtons(): Buttons {
    return $this->buttons;
  }

  /**
   * Prepare this panel before it is first entered.
   *
   * @param \Closure $work
   *   An `fn (): void` doing the preparation, such as one fetch several of the
   *   panel's fields then read.
   *
   * @return static
   *   The panel.
   */
  public function preload(\Closure $work): static {
    $this->preload = $work;

    return $this;
  }

  /**
   * What prepares this panel before it is first entered.
   *
   * @return \Closure|null
   *   The preparation, or NULL when there is none left to do.
   */
  public function preparation(): ?\Closure {
    return $this->preload;
  }

  /**
   * Do what was to be done before this panel is first entered.
   *
   * @return bool
   *   Whether anything was done. Preparation happens once, so every call after
   *   the first answers FALSE.
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
   * @throws \DrevOps\Tui\Model\FormException
   *   When its buttons are hidden, which would leave it drawn over everything
   *   with no way out.
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
   * A nested panel is a row you select rather than somewhere you are, so it
   * takes no key until you have gone into it - which is what leaves the keys
   * that move the cursor past it reaching the panel it sits in.
   */
  public function binds(Key $key): bool {
    return $this->entered && $this->boundAction($key) instanceof Action;
  }

  /**
   * {@inheritdoc}
   *
   * These are the keys you have while you are in a panel rather than inside
   * anything it holds, which is why moving the cursor, going into a nested
   * panel and coming back out again all resolve here.
   */
  public function hints(): array {
    // Windows sitting beside each other are moved between in two directions
    // rather than one, so what the keys do depends on how they are arranged.
    $move = $this->grid === []
      ? new Hint('move', Action::MoveUp, Action::MoveDown)
      : new Hint('move', Action::MoveUp, Action::MoveDown, Action::MoveLeft, Action::MoveRight);

    return [
      $move,
      new Hint('select', Action::Activate),
      new Hint('go back', Action::Back),
    ];
  }

  /**
   * Sit the panels nested in this one side by side.
   *
   * @param int ...$rows
   *   One entry per visual row, naming how many nested panels share it, top to
   *   bottom; none leaves them one under another.
   *
   * @return static
   *   The panel.
   */
  public function grid(int ...$rows): static {
    $this->grid = array_values($rows);

    // How its rows run is what arranges them, and what arranges the blocks in a
    // region is the region: saying it there is what lets whatever draws one
    // read the shape off the region it was handed.
    if ($this->layout instanceof LayoutInterface) {
      $this->place()->grid(...$this->grid);
    }

    return $this;
  }

  /**
   * How the panels nested in this one sit side by side.
   *
   * @return list<int>
   *   The count of each visual row, empty when they run one under another.
   */
  public function gridRows(): array {
    return $this->grid;
  }

  /**
   * The fields this panel holds, in the order they were placed.
   *
   * Its own only: a nested panel is somewhere you go rather than something this
   * one holds, so what it asks belongs to it.
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
   * Every row that carries one, whether it collects an answer or only shows
   * something: an id that shows is still an id the form knows, which is what
   * tells a stray answer apart from one meant for a row that takes none.
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
   * The panels you can descend into from this one.
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
   * A row you select: the way in, what it is called, and enough of what is
   * behind it to decide whether to go there.
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
   * This panel drawn as a window into it rather than as a row.
   *
   * Where a row says what is behind it in one line, a window shows the rows
   * themselves - which is what a panel sitting beside its siblings has the
   * space for, and what makes a grid of them read as several views of the same
   * form rather than as a list turned sideways.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return string
   *   The window; newlines separate rows.
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

      $drawn = $block->render($theme);

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
   * @return \DrevOps\Tui\Screen\Region
   *   The region: the one its layout names for a panel's rows, else the first
   *   region it declares.
   */
  public function place(): Region {
    $names = $this->currentLayout()->names();

    return $this->in(in_array(self::ROWS, $names, TRUE) ? self::ROWS : ($names[0] ?? self::ROWS));
  }

  /**
   * The theme this panel draws through, refusing to draw an entered one.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return \DrevOps\Tui\Block\Element\PanelElementsInterface
   *   The theme, narrowed to the elements a panel draws with.
   *
   * @throws \LogicException
   *   When the panel is the one you are in, which draws nothing of its own.
   */
  protected function guard(ThemeInterface $theme): PanelElementsInterface {
    // Show and Focus are a nested panel's, not an entered one's: once you are
    // in it, its blocks draw and it draws nothing of its own.
    if ($this->entered) {
      throw new \LogicException(sprintf('Panel "%s" is entered, so its blocks draw rather than the panel itself.', $this->id));
    }

    return $this->elements($theme, PanelElementsInterface::class, 'a panel');
  }

  /**
   * The gutter this panel's rows are laid out after.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return string
   *   The gutter, empty when nothing steps the section in.
   */
  protected function gutter(ThemeInterface $theme): string {
    return $this->elements($theme, ChromeElementsInterface::class, 'a conditional section')->chromeIndent($this->depth);
  }

  /**
   * The row that names this panel and says it leads somewhere.
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
   * The rows this panel's standing text comes to, as they are drawn.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\Tui\Block\Element\PanelElementsInterface $elements
   *   The same theme, narrowed to the elements a panel draws with.
   *
   * @return list<string>
   *   The rows, none when it carries no standing text or there is no room for
   *   it: the text is secondary to the rows it introduces, so a theme with
   *   nothing to spare drops it.
   */
  protected function guidance(ThemeInterface $theme, PanelElementsInterface $elements): array {
    if ($this->description === '' || $this->terse($theme)) {
      return [];
    }

    $markup = $this->elements($theme, MarkupElementsInterface::class, 'a description');

    return Prose::lines(Translator::t($this->description), $markup, $elements->panelDescription(...));
  }

  /**
   * What this panel is holding, as one line of answers.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\Tui\Block\Element\PanelElementsInterface $elements
   *   The same theme, narrowed to the elements a panel draws with.
   *
   * @return string
   *   The answers, empty when it holds none, when none of them is there, or
   *   when the theme has no room to say them.
   */
  protected function summary(ThemeInterface $theme, PanelElementsInterface $elements): string {
    if ($this->terse($theme)) {
      return '';
    }

    $answers = [];

    foreach ($this->fields() as $field) {
      if ($field->type()->isDisplayOnly()) {
        continue;
      }

      // A row the answers took off the screen says nothing about the panel,
      // because it is not there to say it.
      if ($field->isHidden()) {
        continue;
      }

      $answers[] = $this->held($theme, $field);

      // A row summarizes rather than lists, so it stops well before it would
      // be read as the panel itself.
      if (count($answers) >= self::SUMMARY_ANSWERS) {
        break;
      }
    }

    return implode(' ' . $elements->panelSummarySeparator() . ' ', $answers);
  }

  /**
   * One answer, as the row that stands for a whole panel says it.
   *
   * A handful of picks reads as the picks themselves; more than that would be
   * the panel's whole content spelled out on the line meant to stand for it,
   * so past a handful the line says how many were picked instead.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\Tui\Block\Field $field
   *   The field holding the answer.
   *
   * @return string
   *   The answer as it reads on the panel's own row.
   */
  protected function held(ThemeInterface $theme, Field $field): string {
    $value = $field->value();

    if (!is_array($value) || count($value) <= self::SUMMARY_ITEMS) {
      return $field->valueText($theme);
    }

    return Translator::formatPlural(count($value), '1 item selected', '@count items selected');
  }

  /**
   * Whether the theme has no room for anything but the rows themselves.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return bool
   *   TRUE when it says so.
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
   * Reject a panel that would draw over everything with no way out.
   *
   * @param bool $modal
   *   Whether it draws over what is behind it.
   * @param \DrevOps\Tui\Model\Buttons $buttons
   *   The pair that closes it.
   *
   * @throws \DrevOps\Tui\Model\FormException
   *   When such a panel hides its buttons.
   */
  protected function assertWayOut(bool $modal, Buttons $buttons): void {
    if ($modal && !$buttons->show) {
      throw new FormException(sprintf('Panel "%s" draws over what is behind it, so its buttons are its only way out and cannot be hidden.', $this->id));
    }
  }

}
