<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Block\Capability\DependCapableInterface;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Condition\ConditionInterface;

/**
 * A declared block tree, read as a flat list.
 *
 * A panel knows only its own blocks and its child panels. Flattening the
 * tree lives here so the headless collection and the machine-readable
 * descriptions read one order rather than each walking their own.
 *
 * The order is declaration order: a panel's own rows first, then everything
 * beneath it, panel by panel.
 *
 * @package DrevOps\Tui\Block
 */
final class Tree {

  /**
   * Every field a panel and the panels beneath it hold.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to walk.
   *
   * @return list<\DrevOps\Tui\Block\Field>
   *   The fields, in declaration order.
   */
  public static function fields(Panel $panel): array {
    $fields = $panel->fields();

    foreach ($panel->children() as $child) {
      $fields = [...$fields, ...self::fields($child)];
    }

    return $fields;
  }

  /**
   * Every panel beneath a panel, the panel itself included.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to walk.
   *
   * @return list<\DrevOps\Tui\Block\Panel>
   *   The panels, in declaration order, outermost first.
   */
  public static function panels(Panel $panel): array {
    $panels = [$panel];

    foreach ($panel->children() as $child) {
      $panels = [...$panels, ...self::panels($child)];
    }

    return $panels;
  }

  /**
   * Which blocks a panel and the panels beneath it leave on the form.
   *
   * A block's own condition decides its presence, and an absent section
   * removes every block it holds, however that block's own rule reads.
   * Composing the two is a question about the whole tree, so it is answered
   * here once rather than by each caller.
   *
   * Keyed by object rather than by id: a section and a row it holds may
   * share a name, and only one set of ids is ever checked for collisions.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to walk.
   * @param array<string,mixed> $answers
   *   The answers every condition is measured against.
   * @param bool $inside
   *   Whether the sections holding this one are all there.
   *
   * @return array<int,bool>
   *   TRUE for each block that is there, keyed by its object id.
   */
  public static function within(Panel $panel, array $answers, bool $inside = TRUE): array {
    $there = [];

    foreach ($panel->blocks() as $block) {
      $active = $inside && (!$block instanceof DependCapableInterface || $block->isActive($answers));
      $there[spl_object_id($block)] = $active;

      if ($block instanceof Panel) {
        foreach (self::within($block, $answers, $active) as $id => $held) {
          $there[$id] = $held;
        }
      }
    }

    return $there;
  }

  /**
   * Which blocks are there once answers for absent blocks stop counting.
   *
   * The companion to {@see within()} for a whole answer set read in one go.
   * A value belonging to an absent block was never asked for, so it must not
   * put another block on the form; measuring the conditions against the raw
   * set would allow exactly that. Dropping a value can remove a further
   * block, so the reading is repeated until nothing changes.
   *
   * Keyed by object rather than by id, for the reason {@see within()} is.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to walk.
   * @param array<string,mixed> $answers
   *   The answers every condition is measured against.
   *
   * @return array<int,bool>
   *   TRUE for each block that is there, keyed by its object id.
   */
  public static function settled(Panel $panel, array $answers): array {
    $there = self::within($panel, $answers);
    // A settled reading returns inside the loop, so the bound only guards a
    // set that never settles: one pass per field covers the longest removal
    // chain, plus one pass to confirm nothing changed.
    $limit = count(self::fields($panel)) + 1;

    for ($pass = 0; $pass < $limit; $pass++) {
      $next = self::within($panel, self::held($panel, $answers, $there));

      if ($next === $there) {
        return $there;
      }

      $there = $next;
    }

    return $there;
  }

  /**
   * The answers left once those belonging to absent blocks are dropped.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to walk.
   * @param array<string,mixed> $answers
   *   The answers as they were supplied.
   * @param array<int,bool> $there
   *   Which blocks are there, keyed by object id.
   *
   * @return array<string,mixed>
   *   The answers, less the ones belonging to an absent block. A value under
   *   an id the tree does not know is left alone; whether it belongs to the
   *   form at all is out of scope here.
   */
  public static function held(Panel $panel, array $answers, array $there): array {
    foreach (self::fields($panel) as $field) {
      if (!($there[spl_object_id($field)] ?? TRUE)) {
        unset($answers[$field->id()]);
      }
    }

    return $answers;
  }

  /**
   * The one rule deciding whether each block is there, in readable form.
   *
   * The companion to {@see within()}: where that measures the composed rule
   * against one answer set, this returns the rule itself, so a description
   * of the form can publish what a block depends on rather than only whether
   * it is there right now.
   *
   * A closure rule can be evaluated but not read, so it counts as no rule
   * here - the same reading {@see DependCapableTrait::rule()} takes.
   *
   * Keyed by object rather than by id, for the reason {@see within()} is.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to walk.
   * @param \DrevOps\Tui\Condition\ConditionInterface|null $inherited
   *   The rule the sections holding this one already impose.
   *
   * @return array<int,\DrevOps\Tui\Condition\ConditionInterface|null>
   *   The rule each block waits on, or NULL where it is always there, keyed by
   *   its object id.
   */
  public static function gates(Panel $panel, ?ConditionInterface $inherited = NULL): array {
    $gates = [];

    foreach ($panel->blocks() as $block) {
      $own = $block instanceof DependCapableInterface ? $block->condition() : NULL;
      $gate = self::both($inherited, $own instanceof ConditionInterface ? $own : NULL);
      $gates[spl_object_id($block)] = $gate;

      if ($block instanceof Panel) {
        foreach (self::gates($block, $gate) as $id => $held) {
          $gates[$id] = $held;
        }
      }
    }

    return $gates;
  }

  /**
   * Whether anything at all decides that each block is there.
   *
   * The question {@see gates()} cannot answer: a closure rule is a real rule
   * that cannot be read, so gates() returns NULL for it, and a NULL gate
   * must not be taken for "asked on every run". This reports only whether a
   * block depends on anything, which both kinds of rule can answer.
   *
   * Keyed by object rather than by id, for the reason {@see within()} is.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to walk.
   * @param bool $inside
   *   Whether the sections holding this one already depend on something.
   *
   * @return array<int,bool>
   *   TRUE for each block the answers can take off the form, keyed by its
   *   object id.
   */
  public static function gated(Panel $panel, bool $inside = FALSE): array {
    $gated = [];

    foreach ($panel->blocks() as $block) {
      $waits = $inside || ($block instanceof DependCapableInterface && $block->condition() !== NULL);
      $gated[spl_object_id($block)] = $waits;

      if ($block instanceof Panel) {
        foreach (self::gated($block, $waits) as $id => $held) {
          $gated[$id] = $held;
        }
      }
    }

    return $gated;
  }

  /**
   * The single rule two rules amount to.
   *
   * @param \DrevOps\Tui\Condition\ConditionInterface|null $outer
   *   The rule the section imposes, or NULL when it imposes none.
   * @param \DrevOps\Tui\Condition\ConditionInterface|null $own
   *   The block's own rule, or NULL when it declares none.
   *
   * @return \DrevOps\Tui\Condition\ConditionInterface|null
   *   Both rules combined, the one that exists, or NULL when neither does. A
   *   single rule is returned as it is rather than wrapped, so a reader is
   *   never given an "all" of one condition to unpick.
   */
  protected static function both(?ConditionInterface $outer, ?ConditionInterface $own): ?ConditionInterface {
    if (!$outer instanceof ConditionInterface) {
      return $own;
    }

    return $own instanceof ConditionInterface ? Condition::all($outer, $own) : $outer;
  }

  /**
   * The ids of every row a panel and the panels beneath it hold.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to walk.
   *
   * @return list<string>
   *   The ids, in declaration order.
   */
  public static function ids(Panel $panel): array {
    $ids = $panel->ids();

    foreach ($panel->children() as $child) {
      $ids = [...$ids, ...self::ids($child)];
    }

    return $ids;
  }

}
