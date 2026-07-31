<?php

declare(strict_types=1);

namespace DrevOps\Tui\Builder;

use DrevOps\Tui\Block\Capability\DependCapableInterface;
use DrevOps\Tui\Block\Field as FieldBlock;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Panel as PanelBlock;
use DrevOps\Tui\Block\Progress;
use DrevOps\Tui\Condition\ConditionInterface;
use DrevOps\Tui\Model\Buttons;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Model\Modal;
use DrevOps\Tui\Model\Panel;

/**
 * Builds the form definition a declared block tree describes.
 *
 * The tree is where a declaration lands, so this reads it rather than the
 * builder that wrote it: every panel, every question and every piece of content
 * comes back out of the blocks holding them. What the tree does not hold is the
 * form's own chrome - its title, banner, buttons and env prefix belong to no
 * block, so they are handed over beside it.
 *
 * A block that only shows or only runs still becomes a field here, because the
 * definition has one kind of row and the kind of row it is says what it does.
 *
 * @package DrevOps\Tui\Builder
 */
final class DefinitionFactory {

  /**
   * Build the definition a block tree and a form's own chrome describe.
   *
   * @param \DrevOps\Tui\Block\Panel $root
   *   The panel every declared panel hangs from.
   * @param string $title
   *   The application title.
   * @param string $subject
   *   The subject being configured.
   * @param \DrevOps\Tui\Model\Buttons $buttons
   *   The submit/cancel buttons the interactive TUI shows on the root panel.
   * @param string $banner
   *   The start banner (logo).
   * @param string $env_prefix
   *   The prefix namespacing per-question env-variable overrides.
   * @param \DrevOps\Tui\Model\Fixup[] $fixups
   *   The post-settle fix-up rules.
   * @param list<int> $layout
   *   The top-level panel grid: one entry per visual row naming how many panels
   *   sit side by side in it.
   *
   * @return \DrevOps\Tui\Model\FormDefinition
   *   The form definition.
   */
  public function create(PanelBlock $root, string $title, string $subject, Buttons $buttons, string $banner = '', string $env_prefix = '', array $fixups = [], array $layout = []): FormDefinition {
    return new FormDefinition($title, $subject, $this->panels($root), $fixups, $env_prefix, $banner, $buttons, $layout);
  }

  /**
   * The panels nested in a panel, in the order they were placed.
   *
   * @param \DrevOps\Tui\Block\Panel $parent
   *   The panel to read.
   *
   * @return list<\DrevOps\Tui\Model\Panel>
   *   The panels.
   */
  protected function panels(PanelBlock $parent): array {
    return array_map($this->panel(...), $parent->children());
  }

  /**
   * One panel, with everything it holds.
   *
   * @param \DrevOps\Tui\Block\Panel $block
   *   The panel block.
   *
   * @return \DrevOps\Tui\Model\Panel
   *   The panel.
   */
  protected function panel(PanelBlock $block): Panel {
    return new Panel(
      $block->id(),
      $block->title(),
      $block->descriptionText(),
      $this->fields($block),
      $this->panels($block),
      $block->isModal() ? new Modal($block->currentButtons()) : NULL,
      $block->gridRows(),
      $block->preparation(),
    );
  }

  /**
   * The rows of a panel, in the order they were placed.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel block.
   *
   * @return list<\DrevOps\Tui\Model\Field>
   *   The fields, including the rows that only show or only run.
   */
  protected function fields(PanelBlock $panel): array {
    $fields = [];

    foreach ($this->blocks($panel) as $block) {
      if ($block instanceof FieldBlock) {
        $fields[] = $this->field($block);

        continue;
      }

      if ($block instanceof Markup) {
        $fields[] = $this->note($block);

        continue;
      }

      if ($block instanceof Progress) {
        $fields[] = $this->progress($block);
      }
    }

    return $fields;
  }

  /**
   * Every block a panel holds, region by region, in the order they were placed.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel block.
   *
   * @return list<\DrevOps\Tui\Block\BlockInterface>
   *   The blocks.
   */
  protected function blocks(PanelBlock $panel): array {
    $blocks = [];
    $layout = $panel->currentLayout();

    foreach ($layout->names() as $name) {
      foreach ($layout->in($name)->blocks() as $block) {
        $blocks[] = $block;
      }
    }

    return $blocks;
  }

  /**
   * The question a field block asks.
   *
   * @param \DrevOps\Tui\Block\Field $block
   *   The field block.
   *
   * @return \DrevOps\Tui\Model\Field
   *   The field.
   */
  protected function field(FieldBlock $block): Field {
    return new Field(
      $block->id(),
      $block->label(),
      $block->descriptionText(),
      $block->type(),
      $block->value(),
      $block->entries(),
      $block->isRequired(),
      $block->requiredMessage(),
      $this->condition($block),
      $block->derivation(),
      $block->discovery(),
      $block->validator(),
      $block->transformer(),
      $block->isRevealable(),
      $block->hasConfirmation(),
      $block->hasExternalEditor(),
      $block->numberBounds(),
      $block->pickerConstraints(),
      $block->pickerStart(),
      $block->showsHidden(),
      $block->pageSize(),
      $block->completion(),
      $block->dateBounds(),
      $block->renderMode(),
      $block->isMultiple(),
      selectionBounds: $block->selectionBounds(),
      optionsLoader: $block->loader(),
      schemaDefault: $block->schemaDefaultValue(),
      hasSchemaDefault: $block->hasSchemaDefault(),
      template: $block->template(),
      optionsSource: $block->source(),
      queryMinLength: $block->queryMinLength(),
      help: $block->helpText(),
      placeholder: $block->placeholderText(),
      envName: $block->envName(),
      envAliases: $block->aliases(),
      ghost: $block->hasGhost(),
      ratingCaptions: $block->ratingCaptions(),
      optionsResolver: $block->resolver(),
    );
  }

  /**
   * The card a markup block draws.
   *
   * Its title is the row's label and its content the row's description, which
   * is how the definition has always carried a card: a row that collects
   * nothing and shows what it was given.
   *
   * @param \DrevOps\Tui\Block\Markup $block
   *   The markup block.
   *
   * @return \DrevOps\Tui\Model\Field
   *   The field.
   */
  protected function note(Markup $block): Field {
    return new Field(
      $block->id(),
      $block->titleText(),
      $block->bodyText(),
      FieldType::Note,
      '',
      when: $this->condition($block),
      bordered: $block->isBordered(),
      table: $block->tableSpec(),
    );
  }

  /**
   * The row a progress block runs.
   *
   * @param \DrevOps\Tui\Block\Progress $block
   *   The progress block.
   *
   * @return \DrevOps\Tui\Model\Field
   *   The field.
   */
  protected function progress(Progress $block): Field {
    return new Field(
      $block->id(),
      $block->caption(),
      '',
      FieldType::Progress,
      '',
      when: $this->condition($block),
      progressSteps: $block->total(),
      progressWork: $block->workload(),
    );
  }

  /**
   * The declared rule deciding whether a block is there at all.
   *
   * @param \DrevOps\Tui\Block\Capability\DependCapableInterface $block
   *   The block.
   *
   * @return \DrevOps\Tui\Condition\ConditionInterface|null
   *   The rule, or NULL when the block is always there. A rule the block
   *   decides for itself is not one the definition can carry, because every
   *   surface reading it has to be able to say which answers it is about.
   */
  protected function condition(DependCapableInterface $block): ?ConditionInterface {
    $when = $block->condition();

    return $when instanceof ConditionInterface ? $when : NULL;
  }

}
