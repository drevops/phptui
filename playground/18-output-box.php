<?php

/**
 * @file
 * Output as a box: framed chrome before and after a form run.
 *
 * The output() call returns the standalone output primitives - the chrome a
 * consumer writes around a form rather than inside it. box() frames a body
 * under an optional title, sized to its content, wrapped to the terminal and
 * drawn by the active theme (set one with ->theme(...)), so a welcome box
 * before the form and a summary box after it match the panel between them.
 * Off a TTY, piped or redirected, the colour drops and the plain text remains.
 *
 * Usage:
 *   php playground/18-output-box.php
 *
 *   # Off a TTY the frame stays, the colour goes:
 *   php playground/18-output-box.php 2>&1 | cat
 *
 *   # ASCII corners when the locale is not UTF-8:
 *   LC_ALL=C php playground/18-output-box.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Produce order')
  ->panel('order', 'New order', function (PanelBuilder $p): void {
    $p->text('name', 'Order name')->required();
  });

$out = (new Tui($form))->output();

// A titled box: the title heads the frame, the body wraps inside it. An empty
// line in the list stays blank, so the content can be spaced out.
$out->box([
  'Everything below is picked the morning it ships.',
  '',
  'Nothing is charged until the box leaves the packing shed.',
], 'Welcome to the produce box');

// A box with no title is a bare frame around its body.
$out->box('Pick a fruit, add vegetables, and confirm the quantity.');
