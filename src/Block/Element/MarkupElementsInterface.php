<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Block\Element;

/**
 * The elements the markup block composes.
 *
 * A passage is not one string with one style: a word may be emphatic, a
 * fragment may be code and a target may be somewhere to go, so each span is its
 * own element. A theme that restyles what is emphatic restyles it wherever a
 * passage is drawn rather than in the one place that happened to compose it.
 *
 * @package DrevOps\PhpTui\Block\Element
 */
interface MarkupElementsInterface {

  /**
   * Style the title above a body of markup.
   *
   * @param string $text
   *   The title.
   *
   * @return string
   *   The styled title.
   */
  public function markupTitle(string $text): string;

  /**
   * Style one line of a body of markup.
   *
   * @param string $text
   *   The line.
   *
   * @return string
   *   The styled line.
   */
  public function markupLine(string $text): string;

  /**
   * Style a span the passage states emphatically.
   *
   * @param string $text
   *   The span.
   *
   * @return string
   *   The styled span.
   */
  public function markupStrong(string $text): string;

  /**
   * Style a span the passage leans on.
   *
   * @param string $text
   *   The span.
   *
   * @return string
   *   The styled span.
   */
  public function markupEmphasis(string $text): string;

  /**
   * Style a span the passage quotes verbatim.
   *
   * @param string $text
   *   The span.
   *
   * @return string
   *   The styled span.
   */
  public function markupCode(string $text): string;

  /**
   * Style a span that leads somewhere.
   *
   * @param string $text
   *   The visible label.
   * @param string $url
   *   Where it leads.
   *
   * @return string
   *   The styled span - a label a capable terminal can follow, or the label and
   *   the target side by side where it cannot.
   */
  public function markupLink(string $text, string $url): string;

  /**
   * The mark leading one item of a list.
   *
   * @return string
   *   The styled mark.
   */
  public function markupBullet(): string;

}
