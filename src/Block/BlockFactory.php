<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Condition\ConditionInterface;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Model\DateBounds;
use DrevOps\Tui\Model\Field as FieldModel;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Model\Modal;
use DrevOps\Tui\Model\NumberBounds;
use DrevOps\Tui\Model\OptionKind;
use DrevOps\Tui\Model\Panel as PanelModel;
use DrevOps\Tui\Model\RenderMode;
use DrevOps\Tui\Model\SelectionBounds;
use DrevOps\Tui\Model\TableSpec;
use DrevOps\Tui\Model\Template;
use DrevOps\Tui\Screen\Layout\PanelLayout;

/**
 * Builds the block tree a form definition describes.
 *
 * A declaration lands in blocks and a definition is derived from them, so a
 * consumer holding only the definition still has a tree to walk: this reads it
 * back the way it was written, one block per row and one panel per panel.
 *
 * What a definition cannot carry it cannot hand back - a rule a block decides
 * for itself, and the phrase it states before anything is refused, are the
 * screen's rather than the answer's - so the tree it yields describes exactly
 * what the definition knows.
 *
 * @package DrevOps\Tui\Block
 */
final class BlockFactory {

  /**
   * Build the tree a form definition describes.
   *
   * @param \DrevOps\Tui\Model\FormDefinition $form
   *   The definition to read.
   *
   * @return \DrevOps\Tui\Block\Panel
   *   The root panel every declared panel hangs from, carrying the form's own
   *   name.
   */
  public function create(FormDefinition $form): Panel {
    $root = (new Panel($form->title, $form->title))->layout(new PanelLayout())->grid(...$form->layout);

    foreach ($form->panels as $panel) {
      $root->in('content')->add($this->panel($panel));
    }

    return $root;
  }

  /**
   * One panel, with everything it holds.
   *
   * @param \DrevOps\Tui\Model\Panel $model
   *   The panel to read.
   *
   * @return \DrevOps\Tui\Block\Panel
   *   The panel.
   */
  protected function panel(PanelModel $model): Panel {
    $panel = (new Panel($model->id, $model->title))->layout(new PanelLayout())->description($model->description)->grid(...$model->layout);

    if ($model->modal instanceof Modal) {
      $panel->buttons($model->modal->buttons)->modal();
    }

    if ($model->preload instanceof \Closure) {
      $panel->preload($model->preload);
    }

    foreach ($model->fields as $field) {
      $panel->in('content')->add($this->row($field));
    }

    foreach ($model->panels as $child) {
      $panel->in('content')->add($this->panel($child));
    }

    return $panel;
  }

  /**
   * The block one row of a panel is.
   *
   * @param \DrevOps\Tui\Model\Field $model
   *   The row to read.
   *
   * @return \DrevOps\Tui\Block\Field|\DrevOps\Tui\Block\Markup|\DrevOps\Tui\Block\Progress
   *   The block: the one that collects, the one that shows, or the one that
   *   runs.
   */
  protected function row(FieldModel $model): Field|Markup|Progress {
    return match ($model->type) {
      FieldType::Note => $this->markup($model),
      FieldType::Progress => $this->progress($model),
      default => $this->field($model),
    };
  }

  /**
   * The markup a note row draws.
   *
   * @param \DrevOps\Tui\Model\Field $model
   *   The row to read.
   *
   * @return \DrevOps\Tui\Block\Markup
   *   The block.
   */
  protected function markup(FieldModel $model): Markup {
    $markup = (new Markup($model->id, $model->description, $model->label))->bordered($model->bordered);

    if ($model->table instanceof TableSpec) {
      $markup->table($model->table->headers, $model->table->rows);
    }

    if ($model->when instanceof ConditionInterface) {
      $markup->when($model->when);
    }

    return $markup;
  }

  /**
   * The progress row a progress row runs.
   *
   * @param \DrevOps\Tui\Model\Field $model
   *   The row to read.
   *
   * @return \DrevOps\Tui\Block\Progress
   *   The block.
   */
  protected function progress(FieldModel $model): Progress {
    $progress = new Progress($model->id, $model->label);

    if ($model->progressSteps !== NULL) {
      $progress->steps($model->progressSteps);
    }

    if ($model->progressWork instanceof \Closure) {
      $progress->work($model->progressWork);
    }

    if ($model->when instanceof ConditionInterface) {
      $progress->when($model->when);
    }

    return $progress;
  }

  /**
   * The field a question row collects with.
   *
   * @param \DrevOps\Tui\Model\Field $model
   *   The row to read.
   *
   * @return \DrevOps\Tui\Block\Field
   *   The block.
   */
  protected function field(FieldModel $model): Field {
    $field = new Field($model->id, $model->label, $model->type);

    $field
      ->default($model->default)
      ->description($model->description)
      ->help($model->help)
      ->placeholder($model->placeholder)
      ->required($model->required, $model->requiredMessage)
      ->multiple($model->multiple)
      ->revealable($model->revealable)
      ->confirmation($model->confirm)
      ->externalEditor($model->externalEditor)
      ->standalone($model->render === RenderMode::Standalone)
      ->ghost($model->ghost)
      ->complete($model->completion)
      ->picker($model->pickerConstraints)
      ->startIn($model->pickerStart)
      ->showHidden($model->pickerShowHidden);

    $this->constraints($field, $model);
    $this->entries($field, $model);
    $this->behaviour($field, $model);

    if ($model->hasSchemaDefault) {
      $field->schemaDefault($model->schemaDefault);
    }

    if ($model->when instanceof ConditionInterface) {
      $field->when($model->when);
    }

    return $field;
  }

  /**
   * Restate what a field measures a value against.
   *
   * @param \DrevOps\Tui\Block\Field $field
   *   The block being built.
   * @param \DrevOps\Tui\Model\Field $model
   *   The row to read.
   */
  protected function constraints(Field $field, FieldModel $model): void {
    if ($model->bounds instanceof NumberBounds) {
      $field->bounds($model->bounds);
    }

    if ($model->dateBounds instanceof DateBounds) {
      $field->dates($model->dateBounds);
    }

    if ($model->selectionBounds instanceof SelectionBounds) {
      $field->selections($model->selectionBounds);
    }

    if ($model->template instanceof Template) {
      $field->pattern($model->template);
    }

    if ($model->pageSize !== NULL) {
      $field->paginate($model->pageSize);
    }

    // Captions are measured against the scale, so the scale is set first.
    if ($model->ratingCaptions !== []) {
      $field->captions($model->ratingCaptions);
    }
  }

  /**
   * Restate the rows a field opens onto, however they arrive.
   *
   * @param \DrevOps\Tui\Block\Field $field
   *   The block being built.
   * @param \DrevOps\Tui\Model\Field $model
   *   The row to read.
   */
  protected function entries(Field $field, FieldModel $model): void {
    foreach ($model->options as $option) {
      match ($option->kind) {
        OptionKind::Heading => $field->heading($option->label),
        OptionKind::Separator => $field->separator(),
        OptionKind::Option => $field->entry($option->value, $option->label, $option->description, $option->disabled, $option->disabledReason),
      };
    }

    if ($model->optionsLoader instanceof \Closure) {
      $field->load($model->optionsLoader);
    }

    if ($model->optionsResolver instanceof \Closure) {
      $field->resolve($model->optionsResolver);
    }

    if ($model->optionsSource instanceof \Closure) {
      $field->query($model->optionsSource);
    }

    if ($model->queryMinLength > 0) {
      $field->minQuery($model->queryMinLength);
    }
  }

  /**
   * Restate what a field computes, detects, refuses and normalizes.
   *
   * @param \DrevOps\Tui\Block\Field $field
   *   The block being built.
   * @param \DrevOps\Tui\Model\Field $model
   *   The row to read.
   */
  protected function behaviour(Field $field, FieldModel $model): void {
    if ($model->derive instanceof Derive) {
      $field->derive($model->derive);
    }

    if ($model->discover !== NULL) {
      $field->discover($model->discover);
    }

    if ($model->validate instanceof \Closure) {
      $field->validate($model->validate);
    }

    if ($model->transform instanceof \Closure) {
      $field->transform($model->transform);
    }

    // The canonical name is set first: an alias repeating it would never be
    // reached, and declaring one is refused rather than silently ignored.
    if ($model->envName !== '') {
      $field->env($model->envName);
    }

    if ($model->envAliases !== []) {
      $field->envAliases($model->envAliases);
    }
  }

}
