<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Handler\HandlerRegistry;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Input\KeyMap;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Translation\Translator;

/**
 * Builds the widget for a field, seeded with the field's current value.
 *
 * Each widget is wired with the field's validator and transformer - the
 * declared closure, else the handler registry's reusable static one - so an
 * interactive edit enforces the same behaviour a headless collection does.
 *
 * @package DrevOps\Tui\Widget
 */
class WidgetFactory {

  /**
   * The resolved key bindings to inject into each widget.
   */
  protected KeyMap $keymap;

  /**
   * Construct a widget factory.
   *
   * @param \DrevOps\Tui\Input\KeyMap|null $keymap
   *   The resolved key bindings; NULL uses the default preset.
   * @param bool $externalEditorAvailable
   *   Whether an external editor is launchable here. A textarea field opts in
   *   per-field; the handoff shows only when one is also available.
   * @param \DrevOps\Tui\Handler\HandlerRegistry|null $handlers
   *   The registry resolving a field id to its reusable static
   *   validate()/transform() behaviour; NULL leaves only the declared closures.
   */
  public function __construct(?KeyMap $keymap = NULL, protected bool $externalEditorAvailable = FALSE, protected ?HandlerRegistry $handlers = NULL) {
    $this->keymap = $keymap ?? KeyMapManager::create();
  }

  /**
   * Create a widget for a field, wired with its scope's key bindings.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   * @param mixed $current
   *   The current value to seed the widget with.
   * @param array<string,mixed> $answers
   *   The answers collected so far, passed to a text completion closure.
   *
   * @return \DrevOps\Tui\Widget\WidgetInterface
   *   The widget.
   */
  public function create(Field $field, mixed $current, array $answers = []): WidgetInterface {
    $widget = match ($field->type) {
      FieldType::Confirm => new ConfirmWidget((bool) $current),
      FieldType::Toggle => new ToggleWidget($this->labels($field), $this->text($current)),
      FieldType::Select => new SelectWidget($this->options($field), $this->seed($field, $current), $field->multiple, $field->pageSize, $field->selectionBounds),
      FieldType::Reorder => new ReorderWidget($this->options($field), Field::stringList($current), $field->pageSize),
      FieldType::Suggest => new SuggestWidget($field->selectableValues(), $this->text($current), $field->pageSize, $this->suggestDescriptions($field)),
      FieldType::Search => new SearchWidget($this->options($field), $this->seed($field, $current), $field->multiple, $field->pageSize, $field->selectionBounds),
      FieldType::FilePicker => new FilePickerWidget($field->pickerStart, $this->seed($field, $current), $field->pickerConstraints, $field->pickerShowHidden, $field->multiple, $field->pageSize, $field->selectionBounds),
      FieldType::Number => new NumberWidget($this->number($current), $field->bounds),
      FieldType::Calendar => new CalendarWidget($this->text($current), $field->dateBounds),
      FieldType::Textarea => new TextareaWidget($this->text($current), $field->externalEditor && $this->externalEditorAvailable),
      FieldType::Password => new PasswordWidget($this->text($current), $field->revealable, $field->confirm),
      FieldType::Pause => new PauseWidget(),
      FieldType::Text => new TextWidget($this->text($current), $this->completionsFor($field, $answers)),
      // A note is presentational: the theme renders it and the cursor skips it,
      // so it is never edited and needs no widget.
      FieldType::Note => throw new \LogicException('Note fields are presentational and have no editor widget.'),
      // A progress row runs its work on activation and draws itself in its
      // panel row; it has no editor to open.
      FieldType::Progress => throw new \LogicException('A progress row is not edited.'),
    };

    // The field declaration always wins over the registry's convention-resolved
    // behaviour, mirroring the engine's headless resolution.
    $widget->setHandlers($this->guarded($field, $field->validate ?? $this->handlers?->validator($field->id)), $field->transform ?? $this->handlers?->transformer($field->id));

    return $widget->setKeys($this->keymap->forField($field->type, $field->multiple));
  }

  /**
   * A validator that rejects an empty value before the field's own runs.
   *
   * Composed here rather than inside the widgets so every type enforces
   * emptiness identically, in the same order the engine validates a supplied
   * input.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   * @param \Closure|null $validator
   *   The field's resolved validator, or NULL when it declares none.
   *
   * @return \Closure|null
   *   The guarded validator, the resolved one unchanged for an optional field,
   *   or NULL when there is nothing to enforce.
   */
  protected function guarded(Field $field, ?\Closure $validator): ?\Closure {
    if (!$field->required) {
      return $validator;
    }

    return static fn(mixed $value): ?string => $field->requiredViolation($value) ?? ($validator instanceof \Closure ? $validator($value) : NULL);
  }

  /**
   * Coerce a current value to the string a text-seeded widget starts from.
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
   * Coerce a current value to the digit string the integer widget starts from.
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
   * The widget seed value for a field: a scalar, or a list when multiple.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   * @param mixed $current
   *   The current value to seed the widget with.
   *
   * @return string|list<string>
   *   The string current value for a single field, or the list of string
   *   values for a multiple one.
   */
  protected function seed(Field $field, mixed $current): string|array {
    return $field->multiple ? Field::stringList($current) : $this->text($current);
  }

  /**
   * Resolve a text field's completion source to a concrete candidate list.
   *
   * A closure source is called with the answers collected so far; the result is
   * coerced to a list of strings, so a mistyped source degrades to no
   * completion rather than erroring.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   * @param array<string,mixed> $answers
   *   The answers collected so far.
   *
   * @return list<string>
   *   The candidate strings; empty when the field declares no completion.
   */
  protected function completionsFor(Field $field, array $answers): array {
    $source = $field->completion instanceof \Closure ? ($field->completion)($answers) : $field->completion;

    return Field::stringList($source);
  }

  /**
   * The selectable value => label map for a field's options.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   *
   * @return array<string,string>
   *   The localized labels keyed by value, for widgets that take a flat option
   *   map.
   */
  protected function labels(Field $field): array {
    $out = [];

    foreach ($this->options($field) as $option) {
      if ($option->selectable()) {
        $out[$option->value] = $option->label;
      }
    }

    return $out;
  }

  /**
   * A field's options with their labels and disabled reasons translated.
   *
   * Translating once here, rather than at each widget draw, keeps the list a
   * widget searches identical to the list it shows, so a match runs against the
   * same text the user reads.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   *
   * @return list<\DrevOps\Tui\Model\Option>
   *   The options in display order, localized to the active language.
   */
  protected function options(Field $field): array {
    return array_map(static fn(Option $option): Option => new Option(
      $option->value,
      Translator::t($option->label),
      $option->description !== '' ? Translator::t($option->description) : '',
      $option->kind,
      $option->disabled,
      $option->disabledReason !== '' ? Translator::t($option->disabledReason) : '',
    ), $field->options);
  }

  /**
   * The description shown for each selectable option value, keyed by value.
   *
   * For the value-based suggest widget, which carries no option rows: the
   * localized per-option description keyed by its value.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   *
   * @return array<string,string>
   *   The description for each selectable option value.
   */
  protected function suggestDescriptions(Field $field): array {
    $out = [];

    foreach ($this->options($field) as $option) {
      if (!$option->selectable()) {
        continue;
      }

      $out[$option->value] = $option->description;
    }

    return $out;
  }

}
