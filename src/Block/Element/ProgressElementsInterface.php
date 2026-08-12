<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Block\Element;

/**
 * The elements the progress block composes.
 *
 * The indicator says what the work is doing; the mark says whether the reader
 * is standing on the row that starts it. Two questions, so the second is an
 * element of its own - and a glyph, so a terminal drawing no colour still
 * answers it.
 *
 * @package DrevOps\PhpTui\Block\Element
 */
interface ProgressElementsInterface {

  /**
   * The mark saying the work has focus.
   *
   * @param bool $selected
   *   Whether the cursor is on it.
   *
   * @return string
   *   The styled mark, or the gap standing in its place.
   */
  public function progressSelector(bool $selected): string;

  /**
   * Style the caption naming the work.
   *
   * @param string $text
   *   The caption.
   *
   * @return string
   *   The styled caption.
   */
  public function progressCaption(string $text): string;

  /**
   * The spinner glyph for a frame.
   *
   * Takes the frame rather than a glyph, so the theme owns both the characters
   * and how many there are - a Unicode theme can spin through ten frames where
   * an ASCII one cycles four.
   *
   * @param int $frame
   *   The frame number, which may run past the end and wrap.
   *
   * @return string
   *   The styled glyph.
   */
  public function progressSpinner(int $frame): string;

  /**
   * The filled and empty run of a bar.
   *
   * @param int $filled
   *   The cells filled.
   * @param int $width
   *   The cells the whole run takes.
   *
   * @return string
   *   The styled run.
   */
  public function progressTrack(int $filled, int $width): string;

  /**
   * Style the tally beside a bar.
   *
   * @param int $current
   *   The steps done.
   * @param int $total
   *   The steps there are.
   *
   * @return string
   *   The styled tally.
   */
  public function progressCount(int $current, int $total): string;

}
