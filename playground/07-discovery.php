<?php

/**
 * @file
 * Discovery: detect defaults from an existing project directory.
 *
 * In update mode the engine evaluates each field's ->discover() rule against
 * the target directory before falling back to the declared default - here the
 * bundled sample-project/ directory. Four rule types are shown: a .env key, a
 * JSON dot-path, a path-exists check and a directory scan; ->discover() also
 * takes a closure for anything custom. Per-field environment variables use the
 * form-declared BOX_ prefix instead of the default PHPTUI_.
 *
 * Usage:
 *   php playground/07-discovery.php
 *
 *   # A per-field env override outranks a discovered value:
 *   BOX_SEASON=winter php playground/07-discovery.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\Discovery\Dotenv;
use DrevOps\PhpTui\Discovery\JsonValue;
use DrevOps\PhpTui\Discovery\PathExists;
use DrevOps\PhpTui\Discovery\Scan;
use DrevOps\PhpTui\Discovery\ScanType;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Discovery demo')
  // Per-field env overrides read BOX_<ID> instead of the default PHPTUI_<ID>.
  ->envPrefix('BOX_')
  ->panel('Box', function (PanelBuilder $p): void {
    // A dot-path into a JSON file: sample-project/box.json carries a "name"
    // key. A nested value reads the same way, e.g. 'delivery.day'.
    $p->text('Box name')->discover(new JsonValue('box.json', 'name'));

    // A key read from the directory's .env file.
    $p->text('Season')->default('summer')->discover(new Dotenv('SEASON'));

    // Whether a path exists, mapped onto a confirm.
    $p->confirm('In season?')->discover(new PathExists('harvest.csv'));

    // Directory entries as the answer; ScanType keeps dirs, files or both.
    $p->select('Baskets')->multiple()->options([
      'apples' => 'Apples',
      'pears' => 'Pears',
      'plums' => 'Plums',
    ])->discover(new Scan('baskets', type: ScanType::Dir));
  });

try {
  // collect() with update TRUE (the third argument) enables discovery; the
  // second argument points the run at the directory to inspect.
  $answers = (new Tui($form))->collect('', __DIR__ . '/sample-project', TRUE);
}
catch (CollectException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

// Discovered values are badged "detected" in the summary; an env or prompt
// input would be badged "edited" and win over discovery.
echo $answers->toSummary() . PHP_EOL;
