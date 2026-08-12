<?php

/**
 * @file
 * File picker field with ->multiple(): several paths from one browse.
 *
 * Space toggles the highlighted entry while browsing continues, so picks can
 * span directories; Enter accepts the set. The field collects a list of
 * paths.
 *
 * Usage:
 *   php playground/02-fields-filepicker-multiple.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('File picker field')
  ->panel('main', 'File picker', function (PanelBuilder $p): void {
    $p->filePicker('price_lists', 'Price lists')->multiple()->startIn(__DIR__ . '/sample-project');
  });

try {
  // Interactive on a terminal; resolved (empty) when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
