<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Field;

use DrevOps\PhpTui\Block\Field;
use DrevOps\PhpTui\Block\FieldType;
use DrevOps\PhpTui\Block\NumberBounds;
use DrevOps\PhpTui\Block\Option;
use DrevOps\PhpTui\Block\Template as TemplateModel;
use DrevOps\PhpTui\Field\Capability\PlaceholderCapableInterface;
use DrevOps\PhpTui\Field\Capability\QueryOptionsCapableInterface;
use DrevOps\PhpTui\Input\KeyMap;
use DrevOps\PhpTui\Input\KeyMapManager;
use DrevOps\PhpTui\Terminal\Ansi;
use DrevOps\PhpTui\Translation\Translator;

/**
 * Builds the interactive field for a block, seeded with the value it holds.
 *
 * The block's field type selects the field class. A built field does not
 * validate: an offered value is validated where the answer is held.
 *
 * @package DrevOps\PhpTui\Field
 */
class FieldFactory {

  /**
   * The resolved key bindings to inject into each field.
   */
  protected KeyMap $keyMap;

  /**
   * Construct a field factory.
   *
   * @param \DrevOps\PhpTui\Input\KeyMap|null $key_map
   *   The resolved key bindings; NULL uses the default preset.
   * @param bool $externalEditorAvailable
   *   Whether an external editor is launchable here. A textarea field opts in
   *   per-field; the handoff shows only when one is also available.
   */
  public function __construct(?KeyMap $key_map = NULL, protected bool $externalEditorAvailable = FALSE) {
    $this->keyMap = $key_map ?? KeyMapManager::create();
  }

  /**
   * Build the interactive field for a block, seeded with the value it holds.
   *
   * @param \DrevOps\PhpTui\Block\Field $block
   *   The block being opened.
   * @param mixed $current
   *   The current value to seed the field with.
   * @param array<string,mixed> $answers
   *   The answers collected so far, passed to a text completion closure.
   *
   * @return \DrevOps\PhpTui\Field\FieldInterface
   *   The field.
   *
   * @throws \LogicException
   *   When the block's type requires a declaration the block does not carry.
   */
  public function open(Field $block, mixed $current = NULL, array $answers = []): FieldInterface {
    $options = $this->translate($block->options());

    $field = match ($block->type()) {
      FieldType::Confirm => new Confirm((bool) $current),
      FieldType::Toggle => new Toggle($this->optionLabels($options), $this->text($current)),
      FieldType::Select => new Select($options, $this->seed($block, $current), $block->isMultiple(), $block->pageSize(), $block->selectionBounds()),
      FieldType::Reorder => new Reorder($options, Field::stringList($current), $block->pageSize()),
      FieldType::Suggest => new Suggest($block->selectableValues(), $this->text($current), $block->pageSize(), $this->optionDescriptions($options), $block->isGhost()),
      FieldType::Search => new Search($options, $this->seed($block, $current), $block->isMultiple(), $block->pageSize(), $block->selectionBounds()),
      FieldType::FilePicker => new FilePicker($block->pickerStart(), $this->seed($block, $current), $block->pickerConstraints(), $block->showsHidden(), $block->isMultiple(), $block->pageSize(), $block->selectionBounds()),
      FieldType::Number => new Number($this->number($current), $block->numberBounds()),
      FieldType::Rating => $this->rating($block, $current),
      FieldType::Calendar => new Calendar($this->text($current), $block->dateBounds()),
      FieldType::Textarea => new Textarea($this->text($current), $block->isExternalEditor() && $this->externalEditorAvailable),
      FieldType::Password => new Password($this->text($current), $block->isRevealable(), $block->isConfirmation()),
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

    return $field->setKeys($this->keyMap->forField($block->type(), $block->isMultiple()));
  }

  /**
   * The field seed value for a block: a scalar, or a list when multiple.
   *
   * @param \DrevOps\PhpTui\Block\Field $block
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
   * @param \DrevOps\PhpTui\Block\Field $block
   *   The block.
   * @param mixed $current
   *   The current value to seed the field with.
   *
   * @return \DrevOps\PhpTui\Field\Rating
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
   * The template a block's template field fills in.
   *
   * @param \DrevOps\PhpTui\Block\Field $block
   *   The block.
   *
   * @return \DrevOps\PhpTui\Block\Template
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
   * @param \DrevOps\PhpTui\Block\Field $block
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

    return array_map(Ansi::sanitize(...), Field::stringList($source));
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
   * Translated once here rather than at each draw, so the caption the field
   * shows is the caption the panel row shows.
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
   * @param list<\DrevOps\PhpTui\Block\Option> $options
   *   The localized options.
   *
   * @return array<string,string>
   *   The labels keyed by value.
   */
  protected function optionLabels(array $options): array {
    $out = [];

    foreach ($options as $option) {
      if ($option->isSelectable()) {
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
   * @param list<\DrevOps\PhpTui\Block\Option> $options
   *   The options in display order.
   *
   * @return list<\DrevOps\PhpTui\Block\Option>
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
   * @param list<\DrevOps\PhpTui\Block\Option> $options
   *   The localized options.
   *
   * @return array<string,string>
   *   The description for each selectable option value.
   */
  protected function optionDescriptions(array $options): array {
    $out = [];

    foreach ($options as $option) {
      if (!$option->isSelectable()) {
        continue;
      }

      $out[$option->value] = $option->description;
    }

    return $out;
  }

}
