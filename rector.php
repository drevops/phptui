<?php

/**
 * @file
 * Rector configuration.
 *
 * Usage:
 * ./vendor/bin/rector process .
 */

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\InlineArrayReturnAssignRector;
use Rector\CodeQuality\Rector\Empty_\SimplifyEmptyCheckOnEmptyArrayRector;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\CodingStyle\Rector\ClassMethod\NewlineBeforeNewAssignSetRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php80\Rector\Switch_\ChangeSwitchToMatchRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;

return RectorConfig::configure()
  ->withPaths([
    __DIR__ . '/**',
  ])
  ->withPhpSets(php83: TRUE)
  ->withPreparedSets(
    deadCode: TRUE,
    codeQuality: TRUE,
    codingStyle: TRUE,
    typeDeclarations: TRUE,
    instanceOf: TRUE,
    earlyReturn: TRUE,
    phpunitCodeQuality: TRUE,
  )
  ->withComposerBased(phpunit: TRUE)
  ->withRules([
    SafeDeclareStrictTypesRector::class,
  ])
  ->withSkip([
    // Rules added by Rector's rule sets.
    CatchExceptionNameMatchingTypeRector::class,
    ChangeSwitchToMatchRector::class,
    // Promotion ties the constructor parameter name to the property name, but
    // the two are named for different things here: the parameter for the seed
    // value a caller passes, the property for the live answer it becomes.
    ClassPropertyAssignToConstructorPromotionRector::class => [
      __DIR__ . '/src/Field/Confirm.php',
    ],
    InlineArrayReturnAssignRector::class,
    NewlineAfterStatementRector::class,
    NewlineBeforeNewAssignSetRector::class,
    NewlineBetweenClassLikeStmtsRector::class,
    RemoveAlwaysTrueIfConditionRector::class,
    SimplifyEmptyCheckOnEmptyArrayRector::class,
    // Dependencies.
    '*/vendor/*',
    '*/node_modules/*',
  ])
  ->withFileExtensions([
    'php',
    'inc',
  ])
  ->withImportNames(importNames: TRUE, importDocBlockNames: FALSE, importShortClasses: FALSE);
