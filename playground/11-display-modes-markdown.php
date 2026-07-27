<?php

/**
 * @file
 * Markdown and links: rich text in descriptions and notes.
 *
 * Links work everywhere - a label, a description, a note or the summary can
 * carry a `[text](url)` link, rendered as a clickable OSC 8 hyperlink on a
 * capable terminal and as `text (url)` otherwise. ->markdown() additionally
 * renders a small safe subset - **bold**, *emphasis*, `code` and `- ` bullet
 * lists - in descriptions and notes, mapped to the theme's style atoms. Both
 * honour the colour switch, so NO_COLOR (or ->color(FALSE)) degrades the whole
 * lot to clean plain text.
 *
 * Usage:
 *   php playground/11-display-modes-markdown.php
 *   # Force the plain-text degrade with the NO_COLOR convention:
 *   NO_COLOR=1 php playground/11-display-modes-markdown.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Produce order')
  ->panel('order', 'New order', function (PanelBuilder $p): void {
    $p->note('intro', 'Fresh produce order')
      ->description('Pick what is **ripe** today:' . chr(10) . '- crisp apples' . chr(10) . '- sweet pears' . chr(10) . 'See the [seasonal guide](https://example.com/seasonal-guide).')
      ->border();
    $p->text('item', 'Item')->default('Pear')
      ->description('Type any fruit - `Pear` and `Plum` keep well. Full list in the [orchard index](https://example.com/orchard).');
    $p->number('quantity', 'Quantity')->min(1)->max(99)->default(6)
      ->description('Baskets hold up to **99**; order more in a *second* basket.');
  });

try {
  // ->markdown() enables the bold/emphasis/code/bullet subset; links render
  // either way, and NO_COLOR degrades the whole lot to clean plain text.
  $answers = (new Tui($form))->markdown()->run();
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
catch (EngineException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

echo $answers->toSummary() . PHP_EOL;
