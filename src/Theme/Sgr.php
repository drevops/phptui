<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Theme;

/**
 * The named SGR palette: colour and attribute names for the theme atoms.
 *
 * Every appearance atom paints with a named case rather than a raw SGR number,
 * so a palette reads as colours ("bold cyan", "sand") instead of codes. A case
 * carries one SGR parameter - an attribute, a 16-colour or 256-colour
 * foreground, or a background - and {@see self::of()} joins several into the
 * string paint() expects. The 256-colour hues are grouped by the theme that
 * introduced them, but any theme may reuse any case.
 *
 * @package DrevOps\PhpTui\Theme
 */
enum Sgr: string {

  // Attributes.
  case Bold = '1';
  case Dim = '2';
  case Italic = '3';
  case Underline = '4';
  case Reverse = '7';

  // Standard 16-colour foregrounds.
  case Black = '30';
  case Red = '31';
  case Green = '32';
  case Yellow = '33';
  case Blue = '34';
  case Magenta = '35';
  case Cyan = '36';
  case White = '37';
  case Grey = '90';
  case BrightYellow = '93';
  case BrightCyan = '96';
  case BrightWhite = '97';

  // 16-colour backgrounds.
  case OnBlue = '44';
  case OnGrey = '47';

  // 256-colour hues - midnight.
  case Violet = '38;5;141';
  case Indigo = '38;5;54';
  case Jade = '38;5;114';
  case Forest = '38;5;28';
  case Pink = '38;5;212';
  case Fuchsia = '38;5;162';
  case Purple = '38;5;97';
  case Slate = '38;5;61';

  // 256-colour hues - frost.
  case Sky = '38;5;117';
  case Cobalt = '38;5;25';
  case Sage = '38;5;150';
  case Moss = '38;5;65';
  case Sand = '38;5;222';
  case Ochre = '38;5;136';
  case Steel = '38;5;109';
  case Teal = '38;5;66';

  // 256-colour hues - ember.
  case Orange = '38;5;208';
  case Rust = '38;5;166';
  case Olive = '38;5;142';
  case Khaki = '38;5;100';
  case Gold = '38;5;214';
  case Bronze = '38;5;172';
  case Brown = '38;5;130';
  case Umber = '38;5;94';

  // 256-colour greys - mono.
  case Silver = '38;5;250';
  case Ash = '38;5;240';
  case Gunmetal = '38;5;244';
  case Pewter = '38;5;246';

  /**
   * Compose one or more parts into an SGR parameter string.
   *
   * @param \DrevOps\PhpTui\Theme\Sgr ...$parts
   *   The parts, in order (e.g. Sgr::Bold, Sgr::Cyan).
   *
   * @return string
   *   The ";"-joined SGR parameters (e.g. "1;36"), as paint() expects.
   */
  public static function of(self ...$parts): string {
    return implode(';', array_map(static fn(self $part): string => $part->value, $parts));
  }

}
