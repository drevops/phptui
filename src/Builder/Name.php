<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Builder;

use DrevOps\PhpTui\FormException;
use DrevOps\PhpTui\Utils\Strings;

/**
 * The names a block is declared with: the id it answers to and the label.
 *
 * A block is declared with one name or two. Two name it in full - the id it is
 * addressed by, then the label it draws. One names the label alone, and the id
 * is the machine form of it, so a question written once is both readable on
 * screen and addressable in the answers and the environment.
 *
 * @package DrevOps\PhpTui\Builder
 */
final class Name {

  /**
   * The id and label of a block declared with one name or two.
   *
   * @param string $id
   *   The id, or the label when it is the only name given.
   * @param string $label
   *   The label, empty when only one name is given.
   *
   * @return array{string, string}
   *   The id and the label.
   *
   * @throws \DrevOps\PhpTui\FormException
   *   When a label holds nothing an id can be made of.
   */
  public static function pair(string $id, string $label): array {
    if ($label !== '') {
      return [$id, $label];
    }

    $derived = Strings::machine($id);

    if ($derived === '') {
      throw new FormException(sprintf('No id can be derived from label "%s"; declare the id before it.', $id));
    }

    return [$derived, $id];
  }

  /**
   * The id, title and callback of a panel declared with one name or two.
   *
   * A panel is built by a callback, so the slot the callback arrives in says
   * how many names came before it: in the second, the panel was declared with
   * its title alone and the id is derived from it.
   *
   * @param string $id
   *   The id, or the title when it is the only name given.
   * @param string|\Closure $title
   *   The title, or the callback when only one name is given.
   * @param \Closure|null $build
   *   The callback, absent when it arrived in the slot before.
   *
   * @return array{string, string, \Closure}
   *   The id, the title and the callback.
   *
   * @throws \DrevOps\PhpTui\FormException
   *   When a title holds nothing an id can be made of, or no callback is
   *   given to build the panel with.
   */
  public static function panel(string $id, string|\Closure $title, ?\Closure $build): array {
    if ($title instanceof \Closure) {
      [$derived, $declared] = self::pair($id, '');

      return [$derived, $declared, $title];
    }

    if (!$build instanceof \Closure) {
      throw new FormException(sprintf('Panel "%s" is declared without a callback to build it with.', $id));
    }

    return [$id, $title, $build];
  }

}
