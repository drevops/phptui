<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Builder;

use DrevOps\PhpTui\FormException;
use DrevOps\PhpTui\Utils\Strings;

/**
 * The id a block answers to, derived from the label it draws.
 *
 * A block is declared by its label, and the id is the machine form of it, so a
 * question is written once and is both readable on screen and addressable in
 * the answers and the environment. An id that has to be a particular string -
 * one a payload key or an environment variable already fixed - is declared
 * after the label and stands as written.
 *
 * @package DrevOps\PhpTui\Builder
 */
final class Name {

  /**
   * The id a block answers to.
   *
   * @param string $label
   *   The label the block draws.
   * @param string $id
   *   The declared id, empty when it is derived from the label.
   *
   * @return string
   *   The id.
   *
   * @throws \DrevOps\PhpTui\FormException
   *   When a label holds nothing an id can be made of.
   */
  public static function id(string $label, string $id): string {
    if ($id !== '') {
      return $id;
    }

    $derived = Strings::machine($label);

    if ($derived === '') {
      throw new FormException(sprintf('No id can be derived from label "%s"; declare the id after it.', $label));
    }

    return $derived;
  }

  /**
   * The id and callback of a panel declared with one name or two.
   *
   * A panel is built by a callback, so the slot the callback arrives in says
   * whether an id came before it: in the second, the panel was declared by its
   * title alone and the id is derived from it.
   *
   * @param string $title
   *   The title the panel draws.
   * @param string|\Closure $id
   *   The declared id, or the callback when the id is derived from the title.
   * @param \Closure|null $build
   *   The callback, absent when it arrived in the slot before.
   *
   * @return array{string, \Closure}
   *   The id and the callback.
   *
   * @throws \DrevOps\PhpTui\FormException
   *   When a title holds nothing an id can be made of, or no callback is given
   *   to build the panel with.
   */
  public static function panel(string $title, string|\Closure $id, ?\Closure $build): array {
    if ($id instanceof \Closure) {
      return [self::id($title, ''), $id];
    }

    if (!$build instanceof \Closure) {
      throw new FormException(sprintf('Panel "%s" is declared without a callback to build it with.', $title));
    }

    return [self::id($title, $id), $build];
  }

}
