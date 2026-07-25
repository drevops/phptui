<?php

declare(strict_types=1);

namespace DrevOps\Tui\Model;

use DrevOps\Tui\Translation\Translator;

/**
 * Optional type, extension and size limits on what a file picker may accept.
 *
 * The one home for what counts as a valid pick: the mode (any entry, files or
 * directories), the extensions selectable files are limited to, and a maximum
 * file size. The predicate and the human phrase live here once, so the
 * interactive widget, the headless engine and the answer-set validator all
 * agree - mirroring {@see NumberBounds} and {@see SelectionBounds}, but
 * constraining a filesystem path rather than a number or a count.
 *
 * @package DrevOps\Tui\Model
 */
final readonly class FilePickerConstraints {

  /**
   * The normalized allowed extensions (dot-less, lowercase); empty allows all.
   *
   * @var list<string>
   */
  public array $extensions;

  /**
   * Construct file picker constraints.
   *
   * @param \DrevOps\Tui\Model\FilePickerMode $mode
   *   Which entries may be selected (any, files or directories).
   * @param list<string> $extensions
   *   The extensions selectable files are limited to (dot-less,
   *   case-insensitive); empty allows every extension.
   * @param int|null $maxSize
   *   The inclusive maximum file size in bytes, or NULL for no size limit.
   *
   * @throws \DrevOps\Tui\Model\FormException
   *   When the maximum size is below one byte (a size limit below one byte can
   *   never be met; omit it instead).
   */
  public function __construct(
    public FilePickerMode $mode = FilePickerMode::Any,
    array $extensions = [],
    public ?int $maxSize = NULL,
  ) {
    if ($this->maxSize !== NULL && $this->maxSize < 1) {
      throw new FormException(sprintf('File picker constraints declare a maximum size of %d below one byte.', $this->maxSize));
    }

    $this->extensions = array_values(array_filter(array_map(
      static fn(string $extension): string => strtolower(ltrim($extension, '.')),
      $extensions,
    ), static fn(string $extension): bool => $extension !== ''));
  }

  /**
   * Whether nothing is constrained - any entry, any extension, any size.
   *
   * An unconstrained picker enforces nothing, so a supplied path is not even
   * required to exist; the moment any one limit is set the whole set is
   * enforced, existence included.
   *
   * @return bool
   *   TRUE when no limit is declared.
   */
  public function isUnconstrained(): bool {
    return $this->mode === FilePickerMode::Any && $this->extensions === [] && $this->maxSize === NULL;
  }

  /**
   * Whether an entry of the given kind may be selected under the mode.
   *
   * @param bool $is_dir
   *   Whether the entry is a directory.
   *
   * @return bool
   *   TRUE when an entry of that kind is selectable.
   */
  public function allowsType(bool $is_dir): bool {
    return match ($this->mode) {
      FilePickerMode::Any => TRUE,
      FilePickerMode::File => !$is_dir,
      FilePickerMode::Directory => $is_dir,
    };
  }

  /**
   * Whether a filename's extension is allowed.
   *
   * @param string $name
   *   The entry name or path.
   *
   * @return bool
   *   TRUE when the extension is allowed (or no extension limit applies).
   */
  public function extensionAllowed(string $name): bool {
    if ($this->extensions === []) {
      return TRUE;
    }

    return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $this->extensions, TRUE);
  }

  /**
   * The phrase for a path (or list of paths) that violates a limit, else NULL.
   *
   * The shared primitive behind every enforcement surface: it narrows the value
   * to the path(s) it governs (an empty value is the required check's concern
   * and passes), then returns the first offending path's phrase.
   *
   * @param mixed $value
   *   The value to test - a path string, or a list of paths in multiple mode.
   *
   * @return string|null
   *   The phrase (e.g. "an existing file") when a path is invalid, else NULL.
   */
  public function violation(mixed $value): ?string {
    if ($this->isUnconstrained() || $value === '' || $value === []) {
      return NULL;
    }

    $paths = is_array($value) ? $value : [$value];

    foreach ($paths as $path) {
      if (!is_string($path)) {
        continue;
      }
      if ($path === '') {
        continue;
      }
      $phrase = $this->pathViolation($path);
      if ($phrase !== NULL) {
        return $phrase;
      }
    }

    return NULL;
  }

  /**
   * The first limit a single path violates, as a phrase, else NULL.
   *
   * @param string $path
   *   The path.
   *
   * @return string|null
   *   The violation phrase, or NULL when the path meets every limit.
   */
  protected function pathViolation(string $path): ?string {
    if ($this->mode === FilePickerMode::File && !is_file($path)) {
      return Translator::t('an existing file');
    }

    if ($this->mode === FilePickerMode::Directory && !is_dir($path)) {
      return Translator::t('an existing directory');
    }

    if ($this->mode === FilePickerMode::Any && !file_exists($path)) {
      return Translator::t('an existing path');
    }

    if ($this->extensions !== [] && is_file($path) && !$this->extensionAllowed($path)) {
      return Translator::t('a file with a permitted extension (@extensions)', ['@extensions' => implode(', ', $this->extensions)]);
    }

    if ($this->maxSize !== NULL && is_file($path) && (int) filesize($path) > $this->maxSize) {
      return Translator::t('a file no larger than @size', ['@size' => self::formatBytes($this->maxSize)]);
    }

    return NULL;
  }

  /**
   * The human phrase for the active limits, e.g. "Files only. Max 2 MB.".
   *
   * @return string
   *   The phrase, or an empty string when nothing is constrained.
   */
  public function describe(): string {
    $parts = [];

    if ($this->mode === FilePickerMode::File) {
      $parts[] = Translator::t('Files only');
    }

    if ($this->mode === FilePickerMode::Directory) {
      $parts[] = Translator::t('Directories only');
    }

    if ($this->extensions !== []) {
      $parts[] = Translator::t('Extensions: @extensions', ['@extensions' => implode(', ', $this->extensions)]);
    }

    if ($this->maxSize !== NULL) {
      $parts[] = Translator::t('Max @size', ['@size' => self::formatBytes($this->maxSize)]);
    }

    return $parts === [] ? '' : implode('. ', $parts) . '.';
  }

  /**
   * Format a byte count as a compact human size, e.g. 2097152 as "2 MB".
   *
   * @param int $bytes
   *   The byte count.
   *
   * @return string
   *   The formatted size, scaled to the largest unit that keeps it above one.
   */
  public static function formatBytes(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    $value = (float) $bytes;
    $unit = 0;
    while ($value >= 1024.0 && $unit < count($units) - 1) {
      $value /= 1024.0;
      $unit++;
    }

    $rounded = round($value, 1);
    $formatted = $rounded === (float) (int) $rounded ? (string) (int) $rounded : (string) $rounded;

    return $formatted . ' ' . $units[$unit];
  }

}
