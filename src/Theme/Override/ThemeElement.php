<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme\Override;

/**
 * The elements an override can patch, named after the method that draws them.
 *
 * A closed set: a group's fluent call names one of these, so an override that
 * reaches nothing is a type error rather than a knob that quietly does nothing.
 *
 * @package DrevOps\Tui\Theme\Override
 */
enum ThemeElement: string {

  case BreadcrumbSeparator = 'breadcrumbSeparator';

  case LegendKey = 'legendKey';

  case LegendSeparator = 'legendSeparator';

  case FieldSelector = 'fieldSelector';

  case FieldHelpMarker = 'fieldHelpMarker';

  case FieldValueSeparator = 'fieldValueSeparator';

  case FieldEntrySelector = 'fieldEntrySelector';

  case FieldEntryMarker = 'fieldEntryMarker';

  case FieldCaret = 'fieldCaret';

}
