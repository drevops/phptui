<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Traits;

use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Field\Capability\PagingCapableInterface;
use DrevOps\Tui\Field\FieldInterface;

/**
 * Shared paging assertions for the list fields.
 *
 * Every paged field honours the same contract: a non-positive page size is
 * rejected at construction, a long list clips to the page with a "more below"
 * indicator, and the window follows the cursor down. A test supplies a factory
 * closure building its field at a given page size.
 */
trait AssertsPagingTrait {

  /**
   * The four-option fixture the paging assertions run against.
   *
   * @return array<string,string>
   *   The value => label map.
   */
  protected static function pagingOptions(): array {
    return ['a' => 'Apple', 'b' => 'Banana', 'c' => 'Cherry', 'd' => 'Date'];
  }

  /**
   * A non-positive page size is rejected at construction.
   *
   * @param \Closure $factory
   *   Builds the field: `fn (int $page_size): FieldInterface`.
   * @param int $page_size
   *   The invalid page size to pass.
   */
  protected function assertRejectsNonPositivePageSize(\Closure $factory, int $page_size): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage(sprintf('Page size must be a positive integer, %d given.', $page_size));

    $factory($page_size);
  }

  /**
   * A long list clips to the page and the window follows the cursor down.
   *
   * @param \Closure $factory
   *   Builds the field over the paging fixture at a page size of two:
   *   `fn (int $page_size): FieldInterface`.
   * @param int $downs
   *   The Down presses that carry the cursor onto the third item.
   */
  protected function assertPagesAndFollowsCursor(\Closure $factory, int $downs = 2): void {
    $field = $factory(2);
    $this->assertInstanceOf(FieldInterface::class, $field);
    $this->assertInstanceOf(PagingCapableInterface::class, $field);
    $this->assertSame(2, $field->pageSize());

    $view = Ansi::strip($field->view(new DefaultTheme()));

    $this->assertStringContainsString('Apple', $view);
    $this->assertStringContainsString('Banana', $view);
    $this->assertStringNotContainsString('Cherry', $view);
    $this->assertStringContainsString('▼', $view);

    for ($i = 0; $i < $downs; $i++) {
      $field->handle(Key::named(KeyName::Down));
    }

    $scrolled = Ansi::strip($field->view(new DefaultTheme()));

    // The window followed the cursor: the "more above" indicator shows and
    // the first option has scrolled off.
    $this->assertStringContainsString('Cherry', $scrolled);
    $this->assertStringContainsString('▲', $scrolled);
    $this->assertStringNotContainsString('Apple', $scrolled);

    $marked = Ansi::strip($field->view(new class(80) extends DefaultTheme {

      /**
       * {@inheritdoc}
       */
      #[\Override]
      public function fieldOverflowMarker(bool $above): string {
        return $above ? '<<' : '>>';
      }

    }));

    // The mark is the field's own element: a theme restyling it restyles what
    // a paged list draws, and the region's mark is left where it was.
    $this->assertStringContainsString('<<', $marked);
    $this->assertStringNotContainsString('▲', $marked);
  }

}
