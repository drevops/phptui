<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Panel;

/**
 * Collects a form's answers with no screen at all.
 *
 * Four capabilities survive here - collecting, constraining, refusing, and
 * depending on another answer - and the thirteen that do not are the ones about
 * drawing. That line is the point: the four are the form's meaning and the rest
 * is how it looks, which is why one declaration serves both paths.
 *
 * No screen, layout or region is built, and neither is any block that only
 * shows. Fields are built without their modes, because there is no cursor to
 * open anything.
 *
 * @package DrevOps\Tui\Screen
 */
final class Collector {

  /**
   * Collect a panel's answers.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to collect, and any panels beneath it.
   * @param array<string,mixed> $supplied
   *   Values supplied for its fields, keyed by field id.
   *
   * @return array<string,mixed>
   *   The answers, keyed by field id.
   */
  public function collect(Panel $panel, array $supplied = []): array {
    $answers = [];

    foreach ($this->fields($panel) as $field) {
      // A field its condition hides was never asked for, so it contributes
      // nothing rather than contributing a default nobody chose.
      if (!$field->isActive()) {
        continue;
      }

      if (array_key_exists($field->id(), $supplied) && !$field->accept($supplied[$field->id()])) {
        throw new \InvalidArgumentException(sprintf('Cannot collect "%s": %s', $field->id(), $field->refusal()));
      }

      $answers[$field->id()] = $field->value();
    }

    return $answers;
  }

  /**
   * Every field in a panel and the panels beneath it, in declaration order.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel.
   *
   * @return list<\DrevOps\Tui\Block\Field>
   *   The fields.
   */
  protected function fields(Panel $panel): array {
    $fields = [];

    foreach ($panel->currentLayout()->names() as $name) {
      foreach ($panel->currentLayout()->in($name)->blocks() as $block) {
        if ($block instanceof Field) {
          $fields[] = $block;

          continue;
        }

        if ($block instanceof Panel) {
          foreach ($this->fields($block) as $nested) {
            $fields[] = $nested;
          }
        }
      }
    }

    return $fields;
  }

}
