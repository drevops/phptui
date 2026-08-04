<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Block\Capability\DependCapableInterface;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Condition\ConditionInterface;

/**
 * A declared block tree, read as a flat list.
 *
 * A panel knows what it holds and which panels hang from it, and nothing more:
 * flattening the two into the order a form is declared in is a question about
 * the whole tree rather than about any one panel. It lives here so the headless
 * collection and the machine-readable descriptions read one order rather than
 * each walking their own.
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
   * A block decides for itself whether it is there, but a section carries what
   * it holds: a block inside a section the answers took off the form is not
   * there either, however its own rule reads. Composing the two is a question
   * about the whole tree rather than about any one block, which is why it is
   * answered here rather than by whoever asks.
   *
   * Keyed by object rather than by id, because a section and a row it holds may
   * go by the same name and only one set of ids is ever checked for collisions.
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
   * Which blocks are there once the answers nobody was asked for stop counting.
   *
   * The companion to {@see within()} for a whole answer set read in one go. A
   * value belonging to a block the answers took off the form is a value the
   * form never asked for, so it cannot be what puts another block on it -
   * measuring the conditions against the raw set would let one do exactly that.
   * Dropping a value can take a further block away, so the reading is repeated
   * until nothing moves.
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
    // A settled reading exits below, so the bound only guards a set that never
    // settles: one pass per field covers the longest chain of blocks that can
    // take each other away, plus one to confirm nothing moved.
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
   * The answers left once the ones nobody was asked for are dropped.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to walk.
   * @param array<string,mixed> $answers
   *   The answers as they were supplied.
   * @param array<int,bool> $there
   *   Which blocks are there, keyed by object id.
   *
   * @return array<string,mixed>
   *   The answers, less the ones belonging to a block that is not there. A
   *   value under an id the tree does not know is left alone, because whether
   *   it belongs to the form at all is somebody else's question.
   */
  protected static function held(Panel $panel, array $answers, array $there): array {
    foreach (self::fields($panel) as $field) {
      if (!($there[spl_object_id($field)] ?? TRUE)) {
        unset($answers[$field->id()]);
      }
    }

    return $answers;
  }

  /**
   * The one rule deciding whether each block is there, as it can be read.
   *
   * The companion to {@see within()}: where that measures the composed rule
   * against one answer set, this hands the rule itself over, so anything
   * describing the form can publish what a block waits on rather than only
   * whether it is there right now.
   *
   * A rule a block decides for itself cannot be read, only asked, so it counts
   * as no rule here - the same reading {@see DependCapableTrait::rule()} takes.
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
   * The question {@see gates()} cannot answer. A rule a block decides for
   * itself is a real rule that simply cannot be read, so gates() hands back
   * nothing for it - and anything that took "no rule to publish" for "asked on
   * every run" would state something false about the form. This says only
   * whether a block waits on anything, which is answerable either way.
   *
   * Keyed by object rather than by id, for the reason {@see within()} is.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to walk.
   * @param bool $inside
   *   Whether the sections holding this one already wait on something.
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
   *   Both rules combined, the one that exists, or NULL when neither does. One
   *   rule is handed back as it is rather than wrapped, so a reader is never
   *   given an "all" of a single condition to unpick.
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
