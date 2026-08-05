<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field;

use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\FieldType;
use DrevOps\Tui\Block\NumberBounds;
use DrevOps\Tui\Block\Option;
use DrevOps\Tui\Block\Template as TemplateModel;
use DrevOps\Tui\Field\Capability\PlaceholderCapableInterface;
use DrevOps\Tui\Field\Capability\QueryOptionsCapableInterface;
use DrevOps\Tui\Input\KeyMap;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Translation\Translator;

/**
 * Builds the editor a field opens onto, seeded with the value it holds.
 *
 * The kind of answer a field collects is what it turns on, and nothing it
 * builds refuses anything: what a field will not take is the field's own to
 * refuse, so an offered value is measured where the answer is held.
 *
 * @package DrevOps\Tui\Field
 */
class FieldFactory {

  /**
   * The resolved key bindings to inject into each field.
   */
  protected KeyMap $keymap;

  /**
   * Construct a field factory.
   *
   * @param \DrevOps\Tui\Input\KeyMap|null $keymap
   *   The resolved key bindings; NULL uses the default preset.
   * @param bool $externalEditorAvailable
   *   Whether an external editor is launchable here. A textarea field opts in
   *   per-field; the handoff shows only when one is also available.
   */
  public function __construct(?KeyMap $keymap = NULL, protected bool $externalEditorAvailable = FALSE) {
    $this->keymap = $keymap ?? KeyMapManager::create();
  }

  /**
   * Build the field a block opens onto, seeded with the value it holds.
   *
   * @param \DrevOps\Tui\Block\Field $block
   *   The block being opened.
   * @param mixed $current
   *   The current value to seed the field with.
   * @param array<string,mixed> $answers
   *   The answers collected so far, passed to a text completion closure.
   *
   * @return \DrevOps\Tui\Field\FieldInterface
   *   The field.
   *
   * @throws \LogicException
   *   When the block's kind needs a declaration it was not given, so there is
   *   nothing complete enough to open onto.
   */
  public function open(Field $block, mixed $current = NULL, array $answers = []): FieldInterface {
    $entries = $this->translate($block->entries());

    $field = match ($block->type()) {
      FieldType::Confirm => new Confirm((bool) $current),
      FieldType::Toggle => new Toggle($this->entryLabels($entries), $this->text($current)),
      FieldType::Select => new Select($entries, $this->seed($block, $current), $block->isMultiple(), $block->pageSize(), $block->selectionBounds()),
      FieldType::Reorder => new Reorder($entries, Field::stringList($current), $block->pageSize()),
      FieldType::Suggest => new Suggest($block->selectableValues(), $this->text($current), $block->pageSize(), $this->entryDescriptions($entries), $block->hasGhost()),
      FieldType::Search => new Search($entries, $this->seed($block, $current), $block->isMultiple(), $block->pageSize(), $block->selectionBounds()),
      FieldType::FilePicker => new FilePicker($block->pickerStart(), $this->seed($block, $current), $block->pickerConstraints(), $block->showsHidden(), $block->isMultiple(), $block->pageSize(), $block->selectionBounds()),
      FieldType::Number => new Number($this->number($current), $block->numberBounds()),
      FieldType::Rating => $this->rating($block, $current),
      FieldType::Calendar => new Calendar($this->text($current), $block->dateBounds()),
      FieldType::Textarea => new Textarea($this->text($current), $block->hasExternalEditor() && $this->externalEditorAvailable),
      FieldType::Password => new Password($this->text($current), $block->isRevealable(), $block->hasConfirmation()),
      FieldType::Pause => new Pause(),
      FieldType::Text => new Text($this->text($current), $this->completions($block, $answers)),
      FieldType::Template => new Template($this->template($block), $this->text($current)),
    };

    if ($field instanceof QueryOptionsCapableInterface && $block->source() instanceof \Closure) {
      $field->driveByQuery($block->queryMinLength());
    }

    if ($field instanceof PlaceholderCapableInterface) {
      $field->setPlaceholder($block->placeholderText());
    }

    return $field->setKeys($this->keymap->forField($block->type(), $block->isMultiple()));
  }

  /**
   * The field seed value for a block: a scalar, or a list when multiple.
   *
   * @param \DrevOps\Tui\Block\Field $block
   *   The block.
   * @param mixed $current
   *   The current value to seed the field with.
   *
   * @return string|list<string>
   *   The string current value for a single field, or the list of string
   *   values for a multiple one.
   */
  protected function seed(Field $block, mixed $current): string|array {
    return $block->isMultiple() ? Field::stringList($current) : $this->text($current);
  }

  /**
   * Build a rating field over a block's scale, seeded with its value.
   *
   * @param \DrevOps\Tui\Block\Field $block
   *   The block.
   * @param mixed $current
   *   The current value to seed the field with.
   *
   * @return \DrevOps\Tui\Field\Rating
   *   The field.
   */
  protected function rating(Field $block, mixed $current): Rating {
    $scale = $block->numberBounds();

    if (!$scale instanceof NumberBounds || $scale->min === NULL || $scale->max === NULL) {
      throw new \LogicException(sprintf('Field "%s" is a rating field carrying no closed scale.', $block->id()));
    }

    $point = is_int($current) || is_float($current) ? (int) $current : $scale->min;

    return new Rating($point, $scale->min, $scale->max, $this->localized($block->ratingCaptions()));
  }

  /**
   * The shape a block's template field fills in.
   *
   * @param \DrevOps\Tui\Block\Field $block
   *   The block.
   *
   * @return \DrevOps\Tui\Block\Template
   *   The template.
   */
  protected function template(Field $block): TemplateModel {
    $template = $block->template();

    if (!$template instanceof TemplateModel) {
      throw new \LogicException(sprintf('Field "%s" is a template field carrying no template.', $block->id()));
    }

    return $template;
  }

  /**
   * Resolve a block's completion source to a concrete candidate list.
   *
   * @param \DrevOps\Tui\Block\Field $block
   *   The block.
   * @param array<string,mixed> $answers
   *   The answers collected so far.
   *
   * @return list<string>
   *   The candidate strings; empty when the block declares no completion.
   */
  protected function completions(Field $block, array $answers): array {
    $completion = $block->completion();
    $source = $completion instanceof \Closure ? $completion($answers) : $completion;

    return Field::stringList($source);
  }

  /**
   * Coerce a current value to the string a text-seeded field starts from.
   *
   * @param mixed $current
   *   The current value.
   *
   * @return string
   *   The string value; empty when the value is not a string.
   */
  protected function text(mixed $current): string {
    return is_string($current) ? $current : '';
  }

  /**
   * Coerce a current value to the digit string the integer field starts from.
   *
   * @param mixed $current
   *   The current value.
   *
   * @return string
   *   The value as integer digits; empty when the value is not numeric.
   */
  protected function number(mixed $current): string {
    return is_int($current) || is_float($current) ? (string) (int) $current : '';
  }

  /**
   * A rating's captions, localized to the active language.
   *
   * Translated once here rather than at each draw, the way the option labels
   * are, so the caption a field shows is the caption the panel row shows.
   *
   * @param array<int,string> $captions
   *   The caption of each captioned point, keyed by the point.
   *
   * @return array<int,string>
   *   The localized captions, keyed the same way.
   */
  protected function localized(array $captions): array {
    return array_map(static fn(string $caption): string => $caption === '' ? '' : Translator::t($caption), $captions);
  }

  /**
   * The selectable value => label map for a set of options.
   *
   * @param list<\DrevOps\Tui\Block\Option> $options
   *   The localized options.
   *
   * @return array<string,string>
   *   The labels keyed by value, for fields that take a flat option map.
   */
  protected function entryLabels(array $options): array {
    $out = [];

    foreach ($options as $option) {
      if ($option->selectable()) {
        $out[$option->value] = $option->label;
      }
    }

    return $out;
  }

  /**
   * A set of options with their labels and disabled reasons translated.
   *
   * Translating once here, rather than at each field draw, keeps the list a
   * field searches identical to the list it shows, so a match runs against the
   * same text the user reads.
   *
   * @param list<\DrevOps\Tui\Block\Option> $options
   *   The options in display order.
   *
   * @return list<\DrevOps\Tui\Block\Option>
   *   The options in display order, localized to the active language.
   */
  protected function translate(array $options): array {
    return array_map(static fn(Option $option): Option => new Option(
      $option->value,
      Translator::t($option->label),
      $option->description !== '' ? Translator::t($option->description) : '',
      $option->kind,
      $option->disabled,
      $option->disabledReason !== '' ? Translator::t($option->disabledReason) : '',
    ), $options);
  }

  /**
   * The description shown for each selectable option value, keyed by value.
   *
   * For the value-based suggest field, which carries no option rows: the
   * localized per-option description keyed by its value.
   *
   * @param list<\DrevOps\Tui\Block\Option> $options
   *   The localized options.
   *
   * @return array<string,string>
   *   The description for each selectable option value.
   */
  protected function entryDescriptions(array $options): array {
    $out = [];

    foreach ($options as $option) {
      if (!$option->selectable()) {
        continue;
      }

      $out[$option->value] = $option->description;
    }

    return $out;
  }

}
