<?php

declare(strict_types=1);

namespace DrevOps\Tui\Model;

use DrevOps\Tui\Condition\ConditionInterface;

/**
 * The immutable form definition: a questionnaire and its own chrome.
 *
 * Carries what is asked (the panel tree), how the answers are namespaced, and
 * the form-specific chrome that frames it - its start banner and submit/cancel
 * buttons. It never describes the global TUI runtime (theme, key bindings,
 * colour, language); those are configured on the {@see \DrevOps\Tui\Tui}
 * facade and shared by every form an app runs.
 *
 * @package DrevOps\Tui\Model
 */
final readonly class FormDefinition {

  /**
   * The fields flattened across the panel tree, in declaration order.
   *
   * Derived once at construction - the panel tree is immutable, so the many
   * callers of fields() share one walk instead of re-flattening per call.
   *
   * @var \DrevOps\Tui\Model\Field[]
   */
  protected array $flatFields;

  /**
   * Construct the form definition.
   *
   * @param string $title
   *   The application title.
   * @param string $subject
   *   The subject being configured (e.g. the project name).
   * @param \DrevOps\Tui\Model\Panel[] $panels
   *   The top-level panels.
   * @param \DrevOps\Tui\Model\Fixup[] $fixups
   *   Post-settle fix-up rules, evaluated by the engine.
   * @param string $envPrefix
   *   The prefix namespacing per-question env-variable overrides (empty for
   *   the facade default).
   * @param string $banner
   *   The start banner (logo) shown before the interactive TUI (optional).
   * @param \DrevOps\Tui\Model\Buttons $buttons
   *   The submit/cancel buttons the interactive TUI shows on the root panel.
   * @param list<int> $layout
   *   The top-level panel grid: one entry per visual row naming how many
   *   panels sit side by side in it, consumed in declaration order. Empty
   *   renders the panels as today's row list.
   */
  public function __construct(
    public string $title,
    public string $subject,
    public array $panels = [],
    public array $fixups = [],
    public string $envPrefix = '',
    public string $banner = '',
    public Buttons $buttons = new Buttons(),
    public array $layout = [],
  ) {
    $this->flatFields = self::collectFields($this->panels);

    self::resolveConditionalDepths($this->flatFields);
  }

  /**
   * Find a field by id anywhere in the panel tree.
   *
   * @param string $id
   *   The field id to find.
   */
  public function field(string $id): ?Field {
    foreach ($this->fields() as $field) {
      if ($field->id === $id) {
        return $field;
      }
    }

    return NULL;
  }

  /**
   * All fields flattened across the panel tree, in declaration order.
   *
   * @return \DrevOps\Tui\Model\Field[]
   *   The fields.
   */
  public function fields(): array {
    return $this->flatFields;
  }

  /**
   * Recursively flatten the fields of a panel tree, in declaration order.
   *
   * @param \DrevOps\Tui\Model\Panel[] $panels
   *   Panels to walk.
   *
   * @return \DrevOps\Tui\Model\Field[]
   *   The flattened fields.
   */
  protected static function collectFields(array $panels): array {
    $fields = [];

    foreach ($panels as $panel) {
      foreach ($panel->fields as $field) {
        $fields[] = $field;
      }

      $fields = array_merge($fields, self::collectFields($panel->panels));
    }

    return $fields;
  }

  /**
   * Stamp every field with how many conditions deep it sits.
   *
   * A `when` rule may reference a field on any panel, so the depth is a
   * property of the whole form rather than of a panel or a field on its own.
   *
   * @param \DrevOps\Tui\Model\Field[] $fields
   *   The flattened fields.
   */
  protected static function resolveConditionalDepths(array $fields): void {
    $by_id = [];
    foreach ($fields as $field) {
      $by_id[$field->id] = $field;
    }

    $resolved = [];
    foreach ($fields as $field) {
      $field->conditionalDepth = self::conditionalDepth($field, $by_id, $resolved, []);
    }
  }

  /**
   * The depth of one field: one more than the deepest field it depends on.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field to measure.
   * @param array<string,\DrevOps\Tui\Model\Field> $by_id
   *   Every field in the form, keyed by id.
   * @param array<string,int> $resolved
   *   The depths measured so far, so a field shared by several rules is walked
   *   once.
   * @param array<string,bool> $walking
   *   The ids on the current walk, keyed by id.
   *
   * @return int
   *   The depth.
   */
  protected static function conditionalDepth(Field $field, array $by_id, array &$resolved, array $walking): int {
    if (array_key_exists($field->id, $resolved)) {
      return $resolved[$field->id];
    }

    if (!$field->when instanceof ConditionInterface) {
      return $resolved[$field->id] = 0;
    }

    // A reference leading back to a field already on the walk closes a cycle.
    // Such a rule can never hold a stable depth, so the back edge contributes
    // none and the walk ends rather than deepening forever.
    if (isset($walking[$field->id])) {
      return 0;
    }

    $walking[$field->id] = TRUE;

    $deepest = 0;
    foreach ($field->when->fields() as $id) {
      if (isset($by_id[$id])) {
        $deepest = max($deepest, self::conditionalDepth($by_id[$id], $by_id, $resolved, $walking));
      }
    }

    return $resolved[$field->id] = $deepest + 1;
  }

}
