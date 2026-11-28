<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Input;

use DrevOps\PhpTui\Block\FieldType;
use DrevOps\PhpTui\FormException;

/**
 * The resolved, validated key bindings for a whole form.
 *
 * Built from a flat list of {@see Binding}s and caller {@see Command}s - a
 * preset's defaults followed by any consumer overrides - it layers the
 * bindings into one {@see ScopedKeyMap} per
 * scope: the base defaults, panel navigation, and each field type that
 * overrides the base. Later bindings win, so a consumer replaces a default by
 * re-declaring it, and a scope override reassigns a key without disturbing the
 * base for other scopes. Commands resolve in the navigation scope only.
 *
 * Resolution validates eagerly and fails loudly, so a bad declaration is caught
 * when the form is built rather than mid-session:
 * - a key bound to two different actions in the same scope is a conflict;
 * - a command key already bound in the navigation scope, to an action or to
 *   another command, is a conflict;
 * - a printable character bound in the base scope, or in a scope whose field
 *   consumes typed input as its answer, would be un-typeable and is rejected;
 * - a command with no label or no keys is rejected;
 * - a character binding that is not exactly one character is rejected.
 *
 * @package DrevOps\PhpTui\Input
 */
final class KeyMap {

  /**
   * The base scope: the defaults shared by every field.
   */
  protected ScopedKeyMap $base;

  /**
   * The panel-navigation scope.
   */
  protected ScopedKeyMap $navigation;

  /**
   * The scopes that override the base, keyed by their scope token.
   *
   * @var array<string,\DrevOps\PhpTui\Input\ScopedKeyMap>
   */
  protected array $fields = [];

  /**
   * Resolve and validate a list of bindings into scoped maps.
   *
   * @param list<\DrevOps\PhpTui\Input\Binding|\DrevOps\PhpTui\Input\Command> $bindings
   *   The bindings and commands, a preset's defaults followed by any
   *   overrides.
   *
   * @throws \InvalidArgumentException
   *   When a binding or command conflicts, is un-typeable, or is malformed.
   */
  public function __construct(array $bindings) {
    $commands = $this->commands(array_values(array_filter($bindings, static fn(Binding|Command $entry): bool => $entry instanceof Command)));
    $bindings = array_values(array_filter($bindings, static fn(Binding|Command $entry): bool => $entry instanceof Binding));

    $layers = $this->layer($bindings);

    $base_inverted = $this->invert($layers[Scope::base()->token()] ?? [], Scope::base());
    $this->assertTypeable($base_inverted, Scope::base());
    $this->base = $this->toScoped($base_inverted);

    $this->navigation = $this->buildScope($base_inverted, $layers, Scope::navigation(), $commands);

    foreach ($bindings as $binding) {
      $token = $binding->scope->token();

      if ($binding->scope->fieldType instanceof FieldType && !isset($this->fields[$token])) {
        $this->fields[$token] = $this->buildScope($base_inverted, $layers, $binding->scope);
      }
    }
  }

  /**
   * The panel-navigation scope.
   *
   * @return \DrevOps\PhpTui\Input\ScopedKeyMap
   *   The navigation bindings.
   */
  public function navigation(): ScopedKeyMap {
    return $this->navigation;
  }

  /**
   * The scope for a field type, or the base when the type has no overrides.
   *
   * @param \DrevOps\PhpTui\Block\FieldType $type
   *   The field type.
   * @param bool $multiple
   *   Whether the multiple-collecting variant of the type is targeted.
   *
   * @return \DrevOps\PhpTui\Input\ScopedKeyMap
   *   The bindings for that field type.
   */
  public function forField(FieldType $type, bool $multiple = FALSE): ScopedKeyMap {
    return $this->fields[Scope::field($type, $multiple)->token()] ?? $this->base;
  }

  /**
   * The bindings for a scope.
   *
   * @param \DrevOps\PhpTui\Input\Scope $scope
   *   The scope.
   *
   * @return \DrevOps\PhpTui\Input\ScopedKeyMap
   *   The bindings for that scope.
   */
  public function scope(Scope $scope): ScopedKeyMap {
    if ($scope->navigation) {
      return $this->navigation;
    }

    return $scope->fieldType instanceof FieldType ? $this->forField($scope->fieldType, $scope->multiple) : $this->base;
  }

  /**
   * Group bindings into per-scope, per-action layers, later bindings winning.
   *
   * @param list<\DrevOps\PhpTui\Input\Binding> $bindings
   *   The bindings.
   *
   * @return array<string,array<string,array{action:\DrevOps\PhpTui\Input\Action,keys:list<\DrevOps\PhpTui\Input\Key>}>>
   *   The layers, keyed by scope token then action name.
   */
  protected function layer(array $bindings): array {
    $layers = [];

    foreach ($bindings as $binding) {
      $layers[$binding->scope->token()][$binding->action->name] = [
        'action' => $binding->action,
        'keys' => $this->normalize($binding->keys, $binding->scope),
      ];
    }

    return $layers;
  }

  /**
   * Build a scope by overlaying its own layer onto the base bindings.
   *
   * @param array<string,array{key:\DrevOps\PhpTui\Input\Key,action:\DrevOps\PhpTui\Input\Action}> $base_inverted
   *   The base bindings, inverted to key token => key/action.
   * @param array<string,array<string,array{action:\DrevOps\PhpTui\Input\Action,keys:list<\DrevOps\PhpTui\Input\Key>}>> $layers
   *   All layers, keyed by scope token.
   * @param \DrevOps\PhpTui\Input\Scope $scope
   *   The scope to build.
   * @param list<array{command:\DrevOps\PhpTui\Input\Command,keys:list<\DrevOps\PhpTui\Input\Key>}> $commands
   *   The resolved caller commands the scope carries.
   *
   * @return \DrevOps\PhpTui\Input\ScopedKeyMap
   *   The resolved scope.
   *
   * @throws \DrevOps\PhpTui\FormException
   *   When a command key is already bound to an action in the scope.
   */
  protected function buildScope(array $base_inverted, array $layers, Scope $scope, array $commands = []): ScopedKeyMap {
    $scope_inverted = $this->invert($layers[$scope->token()] ?? [], $scope);

    $effective = $base_inverted;

    foreach ($scope_inverted as $token => $entry) {
      $effective[$token] = $entry;
    }

    // Typed text that only narrows a list loses a bound letter to its binding,
    // so the caller may claim one there.
    if ($scope->consumesText() && !$scope->isFiltering()) {
      $this->assertTypeable($effective, $scope);
    }

    foreach ($commands as $entry) {
      foreach ($entry['keys'] as $key) {
        if (isset($effective[$key->token()])) {
          throw new FormException(sprintf('Key "%s" is bound to both %s and the "%s" command in the %s scope.', $key->label(), $effective[$key->token()]['action']->name, $entry['command']->label, $scope->label()));
        }
      }
    }

    return $this->toScoped($effective, $commands);
  }

  /**
   * Validate caller commands and normalize their keys.
   *
   * @param list<\DrevOps\PhpTui\Input\Command> $commands
   *   The commands, in declaration order.
   *
   * @return list<array{command:\DrevOps\PhpTui\Input\Command,keys:list<\DrevOps\PhpTui\Input\Key>}>
   *   The commands with their normalized, de-duplicated keys.
   *
   * @throws \DrevOps\PhpTui\FormException
   *   When a command has no label or no keys, uses the interrupt key, or
   *   shares a key with another.
   */
  protected function commands(array $commands): array {
    $scope = Scope::navigation();
    $claimed = [];
    $resolved = [];

    foreach ($commands as $command) {
      if (trim($command->label) === '') {
        throw new FormException('A command needs a label to show in the hint line.');
      }

      if ($command->keys === []) {
        throw new FormException(sprintf('The "%s" command needs at least one key.', $command->label));
      }

      $keys = [];

      foreach ($this->normalize($command->keys, $scope) as $key) {
        // The session stops on the interrupt key before routing it, so a
        // command bound to it would never run.
        if ($key->is(KeyName::Interrupt)) {
          throw new FormException(sprintf('The "%s" command cannot use the interrupt key, which always stops the session.', $command->label));
        }

        $token = $key->token();
        $owner = $claimed[$token] ?? NULL;

        if ($owner instanceof Command && $owner !== $command) {
          throw new FormException(sprintf('Key "%s" is bound to both the "%s" and "%s" commands in the %s scope.', $key->label(), $owner->label, $command->label, $scope->label()));
        }

        $claimed[$token] = $command;
        $keys[$token] = $key;
      }

      $resolved[] = ['command' => $command, 'keys' => array_values($keys)];
    }

    return $resolved;
  }

  /**
   * Invert an action => keys layer to a key token => key/action map.
   *
   * @param array<string,array{action:\DrevOps\PhpTui\Input\Action,keys:list<\DrevOps\PhpTui\Input\Key>}> $layer
   *   The layer.
   * @param \DrevOps\PhpTui\Input\Scope $scope
   *   The scope, for the conflict message.
   *
   * @return array<string,array{key:\DrevOps\PhpTui\Input\Key,action:\DrevOps\PhpTui\Input\Action}>
   *   The inverted map.
   *
   * @throws \DrevOps\PhpTui\FormException
   *   When one key is bound to two different actions in the layer.
   */
  protected function invert(array $layer, Scope $scope): array {
    $inverted = [];

    foreach ($layer as $entry) {
      foreach ($entry['keys'] as $key) {
        $token = $key->token();

        if (isset($inverted[$token]) && $inverted[$token]['action'] !== $entry['action']) {
          throw new FormException(sprintf('Key "%s" is bound to both %s and %s in the %s scope.', $key->label(), $inverted[$token]['action']->name, $entry['action']->name, $scope->label()));
        }

        $inverted[$token] = ['key' => $key, 'action' => $entry['action']];
      }
    }

    return $inverted;
  }

  /**
   * Assemble the two lookup tables a scoped map needs from an inverted map.
   *
   * @param array<string,array{key:\DrevOps\PhpTui\Input\Key,action:\DrevOps\PhpTui\Input\Action}> $inverted
   *   The inverted map.
   * @param list<array{command:\DrevOps\PhpTui\Input\Command,keys:list<\DrevOps\PhpTui\Input\Key>}> $commands
   *   The resolved caller commands the scope carries.
   *
   * @return \DrevOps\PhpTui\Input\ScopedKeyMap
   *   The scoped map.
   */
  protected function toScoped(array $inverted, array $commands = []): ScopedKeyMap {
    $by_key = [];
    $by_action = [];

    foreach ($inverted as $token => $entry) {
      $by_key[$token] = $entry['action'];
      $by_action[$entry['action']->name][] = $entry['key'];
    }

    return new ScopedKeyMap($by_key, $by_action, $commands);
  }

  /**
   * Reject printable-character bindings where they would be un-typeable.
   *
   * @param array<string,array{key:\DrevOps\PhpTui\Input\Key,action:\DrevOps\PhpTui\Input\Action}> $inverted
   *   The inverted bindings for the scope.
   * @param \DrevOps\PhpTui\Input\Scope $scope
   *   The scope being checked.
   *
   * @throws \DrevOps\PhpTui\FormException
   *   When a printable character is bound in the base or a text-entry scope.
   */
  protected function assertTypeable(array $inverted, Scope $scope): void {
    foreach ($inverted as $entry) {
      if (!$entry['key']->isChar()) {
        continue;
      }

      // Control characters (e.g. Ctrl-E) are command keys, never typed content,
      // so they may be bound where a printable character may not.
      if ($this->isControl((string) $entry['key']->char)) {
        continue;
      }

      if ($scope->fieldType instanceof FieldType) {
        throw new FormException(sprintf('The %s scope consumes typed characters, so the printable character "%s" cannot be bound to an action there.', $scope->label(), $entry['key']->label()));
      }

      throw new FormException(sprintf('The base scope may not bind the printable character "%s"; it would be un-typeable in text fields. Bind it in a specific non-text scope instead.', $entry['key']->label()));
    }
  }

  /**
   * Whether a single-byte character is a control character.
   *
   * @param string $char
   *   The character.
   *
   * @return bool
   *   TRUE for a control character (below the printable ASCII range).
   */
  protected function isControl(string $char): bool {
    return $char !== '' && ord($char) < 0x20;
  }

  /**
   * Normalize authored keys to Key objects.
   *
   * @param list<\DrevOps\PhpTui\Input\Key|\DrevOps\PhpTui\Input\KeyName|string> $keys
   *   The authored keys.
   * @param \DrevOps\PhpTui\Input\Scope $scope
   *   The scope, for the error message.
   *
   * @return list<\DrevOps\PhpTui\Input\Key>
   *   The normalized keys.
   *
   * @throws \DrevOps\PhpTui\FormException
   *   When a character binding is not exactly one character.
   */
  protected function normalize(array $keys, Scope $scope): array {
    $out = [];

    foreach ($keys as $key) {
      if ($key instanceof Key) {
        $out[] = $key;
      }
      elseif ($key instanceof KeyName) {
        $out[] = Key::named($key);
      }
      elseif (strlen($key) === 1) {
        $out[] = Key::char($key);
      }
      else {
        throw new FormException(sprintf('A character binding in the %s scope must be a single character, got "%s".', $scope->label(), $key));
      }
    }

    return $out;
  }

}
