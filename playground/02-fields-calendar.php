<?php

/**
 * @file
 * Calendar field: a month grid returning a normalized ISO date.
 *
 * Left/Right move by day, Up/Down by week, PageUp/PageDown by month; Enter
 * accepts. The field collects a "YYYY-MM-DD" string whatever the terminal
 * locale. ->minDate()/->maxDate() bound the selectable range and
 * ->weekStart() picks the first weekday column.
 *
 * Usage:
 *   php playground/02-fields-calendar.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('Calendar field')
  ->panel('main', 'Calendar', function (PanelBuilder $p): void {
    $p->calendar('harvest', 'Harvest date')->default('2026-07-15');
  });

try {
  // Interactive on a terminal; resolved from the default when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
