<?php

/**
 * @file
 * Output as prose: wrapped paragraphs, rules and a banner.
 *
 * The text() call wraps a paragraph to the terminal and renders the same
 * markdown subset a field description carries, so a standalone paragraph reads
 * like the ones inside the panel. rule() draws a themed separator between
 * sections and banner() opens the program with a logo and version.
 *
 * Usage:
 *   php playground/17-output-text.php
 *
 *   # Off a TTY the wrapping stays, the colour goes:
 *   php playground/17-output-text.php 2>&1 | cat
 *
 *   # ASCII rules when the locale is not UTF-8:
 *   LC_ALL=C php playground/17-output-text.php
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

// Markdown is off by default; ->markdown() turns the subset on for text().
$out = (new Tui($form))->markdown()->output();

$out->banner('Produce Box', '1.2.3');

$out->rule();

// A long paragraph wraps to the terminal rather than running past its edge.
$out->text('Every box is picked the morning it ships. Nothing is charged until the crates leave the packing shed, and anything short is refunded rather than substituted.');

$out->text('');

// The markdown subset: bold, emphasis, inline code and bullet lists.
$out->text("**Before you order:**\n\n- Pick a *delivery day* between Monday and Saturday\n- Leave a note if the gate code is not `1234`\n- Cancel any time before seven the evening before");

$out->rule();
