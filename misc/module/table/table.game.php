<?php

namespace Bga\GameFramework\Components {

    class Deck extends \Deck {
    }

    final class DeckFactory {
        /**
         * Create a Deck component and set the DB table name.
         *
         * @param string $tableName name of the DB table
         * @return Deck a new Deck object
         */
        public function createDeck(string $tableName = 'deck'): Deck {
            $res = new Deck();
            $res->init($tableName);
            return $res;
        }
    }
}

namespace Bga\GameFramework {

    use Exception;

    abstract class Table extends \Table {
    }

    /**
     * Exception not visible to the players, added to the production logs (unexpected errors). Should not be translated.
     */
    class SystemException extends Exception {
        /**
         * @param string|NotificationMessage $message Error message (not translated) with optional arguments.
         */
        public function __construct(string|NotificationMessage $message) {
            parent::__construct(is_string($message) ? $message : $message->message);
        }
    }

    /**
     * Exception visible to the players, not added to the production logs (expected errors). Should be translated.
     */
    class UserException extends Exception {
        /**
         * @param string|NotificationMessage $message Error message to be surrounded by `clienttranslate`, with optional arguments.
         */
        public function __construct(string|NotificationMessage $message) {
            parent::__construct(is_string($message) ? $message : $message->message);
        }
    }

    /**
     * Exception visible to the players, added to the production logs (unexpected errors).
     * Only to help the game dev have more information on a complex bug.
     */
    class VisibleSystemException extends Exception {
        /**
         * @param string|NotificationMessage $message Error message (not translated) with optional arguments.
         */
        public function __construct(string|NotificationMessage $message) {
            parent::__construct(is_string($message) ? $message : $message->message);
        }
    }

    class NotificationMessage {
        public function __construct(
            public string $message = '',
            public array $args = [],
        ) {
        }
    }

    class Notify {
        private array $notifications = [];
        private array $decorators = [];

        /**
         * Add a decorator function, to be applied on args when a notif function is called.
         *
         * @param callable $fn The decorator function. Expected signature: `function(string $message, array $args): array`
         * @return void
         */
        public function addDecorator(callable $fn) {
            $this->decorators[] = $fn;
        }

        private function applyDecorators(string $message, array $args): array {
            foreach ($this->decorators as $fn) {
                $args = $fn($message, $args);
            }
            return $args;
        }

        /**
         * Expand a NotificationMessage into a message string and merged args.
         * If the NotificationMessage args contain nested NotificationMessage values,
         * they are recursively expanded into ["log" => ..., "args" => ...] sub-arrays.
         */
        private function expandMessage(string | NotificationMessage $message, array $args): array {
            if ($message instanceof NotificationMessage) {
                $expandedArgs = array_merge($this->expandArgs($message->args), $args);
                return [$message->message, $expandedArgs];
            }
            return [$message, $this->expandArgs($args)];
        }

        /**
         * Recursively expand any NotificationMessage values within an args array
         * into ["log" => ..., "args" => ...] sub-arrays.
         */
        private function expandArgs(array $args): array {
            foreach ($args as $key => $value) {
                if ($value instanceof NotificationMessage) {
                    $args[$key] = [
                        "log" => $value->message,
                        "args" => $this->expandArgs($value->args),
                    ];
                }
            }
            return $args;
        }

        /**
         * Send a notification to a single player of the game.
         *
         * @param int $playerId the player ID to send the notification to.
         * @param string $notifName a comprehensive string code that explain what is the notification for.
         * @param string $message some text that can be displayed on player's log window (should be surrounded by clienttranslate if not empty).
         * @param array $args notification arguments.
         */
        public function player(int $playerId, string $notifName, string | NotificationMessage $message = '', array $args = []): void {
            [$msg, $args] = $this->expandMessage($message, $args);
            $args = $this->applyDecorators($msg, $args);
            $this->notifications[] = ["type" => $notifName, "log" => $msg, "args" => $args, "channel" => "player", "player_id" => $playerId];
        }

        /**
         * Send a notification to all players of the game and spectators (public).
         *
         * @param string $notifName a comprehensive string code that explain what is the notification for.
         * @param string $message some text that can be displayed on player's log window (should be surrounded by clienttranslate if not empty).
         * @param array $args notification arguments.
         */
        public function all(string $notifName, string | NotificationMessage $message = '', array $args = []): void {
            [$msg, $args] = $this->expandMessage($message, $args);
            $args = $this->applyDecorators($msg, $args);
            $this->notifications[] = ["type" => $notifName, "log" => $msg, "args" => $args, "channel" => "broadcast"];
        }

        public function _getNotifications() {
            return $this->notifications;
        }
    }
}

namespace Bga\GameFramework {

    use feException;

    enum StateType {
        case GAME;
        case ACTIVE_PLAYER;
        case MULTIPLE_ACTIVE_PLAYER;
        case PRIVATE;
    }

    class GamestateMachine {

        public $table_globals;
        private $current_state = 2;
        private $active_player = null;
        var $states = [];
        private $private_states = [];

        function __construct() {
        }

        function _setStates($states = []) {
            $this->states = $states;
        }

        function getStateNumberByTransition($transition) {
            $state = $this->state();
            foreach ($state['transitions'] as $pos => $next_state) {
                if ($transition == $pos || !$transition) {
                    return $next_state;
                }
            }
            throw new \feException("This transition ($transition) is impossible at this state ($this->current_state)");
        }

        /**
         * You can call this method to make any player active.
         *
         * NOTE: you CANNOT use this method in an "activeplayer" or "multipleactiveplayer" state. You must use a "game"
         * type game state for this.
         * 
         * @param int $playerId the new active player.
         */
        final public function changeActivePlayer(int $playerId): void {
            $this->active_player = $playerId;
            $this->states[$this->current_state]['active_player'] = $playerId;
        }

        /**
         * This works exactly like `Table::checkAction()`, except that it does NOT check if the current player is
         * active.
         * 
         * @param string $action_name the current state information
         */
        final public function checkPossibleAction(string $action_name): void {
            //
        }
        public function getActivePlayerId() {
            $state = $this->state();
            return $state['active_player'] ?? $this->active_player;
        }

        /**
         * With this method you can retrieve the list of the active player at any time.
         *
         * - During a "game" type game state, it will return a void array.
         * - During an "activeplayer" type game state, it will return an array with one value (the active player id).
         * - During a "multipleactiveplayer" type game state, it will return an array of the active players' id.
         *
         * NOTE: You should only use this method in the latter case.
         * 
         * @return string[] The list of active players (ids typed as strings).
         */
        final public function getActivePlayerList(): array {
            $state = $this->state();
            if ($state['type'] == 'activeplayer') {
                return [$this->getActivePlayerId()];
            } else if ($state['type'] == 'multipleactiveplayer') {
                return $state['multiactive'] ?? [];
            } else
                return [];
        }

        /**
         * This return the private state or null if not initialized or not in private state.
         * 
         * @deprecated use getCurrentState($playerId)
         * 
         * @param int $playerId the current player id
         * @return array the current private state for the player as an array
         */
        final public function getPrivateState(int $playerId): ?array {
            return $this->private_states[$playerId] ?? null;
        }

        /**
         * Player with the specified id is entering a first private state defined in the master state initial private
         * parameter.
         *
         * Everytime you need to start a private parallel states you need to call this or similar methods above
         *
         * - Note: player needs to be active (see above) and current game state must be a multiactive state with initial
         * private parameter defined
         * - Note: initial private parameter of master state should be set to the id of the first private state. This
         * private state needs to be defined in states.php with the type set to 'private'.
         * - Note: this method is usually preceded with activating that player
         * - Note: initializing private state can run action or args methods of the initial private state
         *
         * @param int $playerId
         */
        final public function initializePrivateState(int $playerId): void {
            $state = $this->state();
            $privstate = $state['initialprivate'];
            $this->setPrivateState($playerId, $privstate);
        }

        /**
         * All active players in a multiactive state are entering a first private state defined in the master state's
         * initialprivate parameter.
         *
         * Every time you need to start a private parallel states you need to call this or similar methods below.
         *
         * - Note: at least one player needs to be active (see above) and current game state must be a multiactive state
         * with initialprivate parameter defined
         * - Note: initialprivate parameter of master state should be set to the id of the first private state. This
         * private state needs to be defined in states.php with the type set to 'private'.
         * - Note: this method is usually preceded with activating some or all players
         * - Note: initializing private state can run action or args methods of the initial private state
         */
        final public function initializePrivateStateForAllActivePlayers(): void {
            //
        }

        /**
         * Players with specified ids are entering a first private state defined in the master state initialprivate
         * parameter.
         *
         * @param array<int> $playerIds
         */
        final public function initializePrivateStateForPlayers(array $playerIds): void {
            //
        }

        /**
         * Return true if we are in multipleactiveplayer state, false otherwise.
         * 
         * @deprecated use isMultiactiveState
         */
        final public function isMutiactiveState(): bool {
            throw new feException("typo");
        }

        /**
         * Return true if we are in multipleactiveplayer state, false otherwise.
         * 
         * @return bool if the main state is MULTIPLE_ACTIVE_PLAYER.
         */
        public function isMultiactiveState() {
            $state = $this->state();
            return (($state['type'] ?? 'activeplayer') == 'multipleactiveplayer');
        }

        /**
         * Return true if specified player is active right now.
         *
         * This method take into account game state type, ie nobody is active if game state is "game" and several
         * players can be active if game state is "multiplayer".
         * 
         * @param int $player_id the player id
         * @return bool if this player is active.
         */
        final public function isPlayerActive(int $player_id): bool {
            return false;
        }

        /**
         * Change current state to a new state. ⚠️ the $nextState parameter is the key of the state, not the state name.
         *
         * NOTE: This is very advanced method, it should not be used in normal cases. Specific advanced cases
         * include - jumping to specific state from "do_anytime" actions, jumping to dispatcher state or jumping to
         * recovery state from zombie player function.
         * 
         * @param int|class-string<Bga\GameFramework\States\GameState> $next_state the state id, or class name if using Class states
         */
        final public function jumpToState(int|string $next_state): void {
            $this->current_state = $next_state;
        }

        /**
         * Player with specified id will transition to next private state specified by provided transition.
         *
         * - Note: game needs to be in a master state which allows private parallel states
         * - Note: transition should lead to another private state (i.e. a state with type defined as 'private'
         * - Note: transition should be defined in private state in which the players currently are.
         * - Note: this method can run action or args methods of the target state for specified player
         * - Note: this is usually used after some player actions to move to next private state
         * 
         * @param int $playerId the player id
         * @param string|int|class-string<Bga\GameFramework\States\GameState> $transition the transition name, or state id, or class name if using Class states
         */
        final public function nextPrivateState(int $playerId, int|string $transition): void {
            $privstate = $this->getStateNumberByTransition($transition);
            $this->setPrivateState($playerId, $privstate);
        }

        /**
         * All active players will transition to next private state by specified transition.
         *
         * - Note: game needs to be in a master state which allows private parallel states
         * - Note: transition should lead to another private state (i.e. a state with type defined as 'private'
         * - Note: transition should be defined in private state in which the players currently are.
         * - Note: this method can run action or args methods of the target state
         * - Note: this is usually used after initializing the private state to move players to specific private state
         * according to the game logic
         * 
         * @param string|int|class-string<Bga\GameFramework\States\GameState> $transition the transition name, or state id, or class name if using Class states
         */
        final public function nextPrivateStateForAllActivePlayers(int|string $transition): void {
            //
        }

        /**
         * Players with specified ids will transition to next private state specified by provided transition.
         * Same considerations apply as for the method above.
         *
         *
         * @param array<int> $playerIds the player ids to transition
         * @param string|int|class-string<Bga\GameFramework\States\GameState> $transition the transition name, or state id, or class name if using Class states
         */
        final public function nextPrivateStateForPlayers(array $playerIds, int|string $transition): void {
            //
        }

        /**
         * Change current state to a new state.
         *
         * NOTE: the `$transition` parameter is the name of the transition, and NOT the name of the target game state.
         *
         * @see states.inc.php
         * 
         * @param string $transition the transition name
         */
        final public function nextState(string $transition = ''): void {
            $x = $this->getStateNumberByTransition($transition);
            $this->jumpToState($x);
        }

        /**
         * Reload the current state.
         * 
         * @return array the result of gamstate->state()
         */
        final public function reloadState(): array {
            return $this->state();
        }

        /**
         * All playing players are made active. Update notification is sent to all players (this will trigger
         * `onUpdateActionButtons`).
         *
         * Usually, you use this method at the beginning of a game state (e.g., `stGameState`) which transitions to a
         * `multipleactiveplayer` state in which multiple players have to perform some action. Do not use this method if
         * you're going to make some more changes in the active player list. (I.e., if you want to take away
         * `multipleactiveplayer` status immediately afterward, use `setPlayersMultiactive` instead).
         * 
         * @param int[] $players the players to activate
         * @param string|int|class-string<Bga\GameFramework\States\GameState> $next_state the transition name, or state id, or class name if using Class states
         * @param bool $bInactivePlayersNotOnTheList if the players not in the list should be made inactive
         */
        final public function setAllPlayersMultiactive(): void {
            //
        }

        /**
         * All playing players are made inactive. Transition to next state.
         * 
         * @param string|int|class-string<Bga\GameFramework\States\GameState> $next_state the transition name, or state id, or class name if using Class states
         */
        final public function setAllPlayersNonMultiactive(string $next_state): bool {
            return false;
        }

        /**
         * During a multi-active game state, make the specified player inactive.
         *
         * Usually, you call this method during a multi-active game state after a player did his action. It is also
         * possible to call it directly from multiplayer action handler. If this player was the last active player, the
         * method trigger the "next_state" transition to go to the next game state.
         * 
         * @param int $player_id the players to make inactive
         * @param string|int|class-string<Bga\GameFramework\States\GameState> $next_state the transition name, or state id, or class name if using Class states
         * @return bool if the call moved to the next state
         */
        final public function setPlayerNonMultiactive(int $player, string $nextState): bool {
            return false;
        }

        /**
         * Make a specific list of players active during a multiactive game state. Update notification is sent to all
         * players whose state changed.
         *
         * - "players" is the array of player id that should be made active. If "players" is not empty the value of
         * "next_state" will be ignored (you can put whatever you want).
         * - If "bExclusive" parameter is not set or false it doesn't deactivate other previously active players. If
         * it's set to true, the players who will be multiactive at the end are only these in "$players" array.
         * - In case "players" is empty, the method trigger the "next_state" transition to go to the next game state.
         * 
         * @param int[] $players the players to activate
         * @param string|int|class-string<Bga\GameFramework\States\GameState> $next_state the transition name, or state id, or class name if using Class states
         * @param bool $bInactivePlayersNotOnTheList if the players not in the list should be made inactive
         * @return bool if the call moved to the next state
         */
        final public function setPlayersMultiactive(array $players, string $nextState, bool $bInactivePlayersNotOnTheList = false): bool {
            return false;
        }

        /**
         * For player with specified id a new private state would be set.
         *
         * - Note: game needs to be in a master state which allows private parallel states
         * - Note: this should be rarely used as it doesn't check if the transition is allowed (it doesn't even specify
         * transition). This can be useful in very complex cases when standard state machine is not adequate (i.e.
         * specific cards can lead to some micro action in various states where defining transitions back and forth can
         * become very tedious.)
         * - Note: this method can run action or args methods of the target state for specified player
         * 
         * @param int $playerId the player id
         * @param int $newStateId the new state id
         */
        final public function setPrivateState(int $playerId, int $newStateId): void {
            $this->private_states[$playerId] = $newStateId;
        }

        /**
         * Get an associative array of current game state attributes.
         *
         * @see states.inc.php
         * 
         * @deprecated use getCurrentMainState() or getCurrentState(int $playerId)
         * 
         * @return array the current state information
         */
        final public function state(bool $bSkipStateArgs = false, bool $bOnlyVariableContent = false, bool $bSkipReflexionTimeLoad = false): array {
            if (array_key_exists($this->current_state, $this->states)) {
                $state = $this->states[$this->current_state];
                $state['id'] = $this->current_state;
                return $state;
            }
            return ['type' => 'activeplayer'];
        }

        /**
         * Get the id of the current game state (rarely useful, it's best to use name, unless you use constants for
         * state ids).
         * 
         * @deprecated use getCurrentMainStateId() or getCurrentStateId(int $playerId)
         * 
         * @return int the state id
         */
        final public function state_id(): int {
            return $this->current_state;
        }

        /**
         * For player with specified id private state will be reset to null, which means they will get out of private
         * parallel states and be in a master state like the private states are not used.
         *
         * - Note: game needs to be in a master state which allows private parallel states
         * - Note: this is usually used when deactivating player to clean up their parallel state
         * - Note: After unseating private state only actions on master state are possible
         * - Note: Usually it is not necessary to unset private state as it will be initialized to first private state
         * when private states are needed again. Nevertheless, it is generally better to clean private state when not
         * needed to avoid bugs.
         *
         * @param int $playerId
         */
        final public function unsetPrivateState(int $playerId): void {
            //
        }

        /**
         * All players private state will be reset to null, which means they will get out of private parallel states and
         * be in a master state like the private states are not used.
         *
         * - Note: game needs to be in a master state which allows private parallel states
         * - Note: this is usually used to clean up after leaving a master state in which private states were used, but
         * can be used in other cases when we want to exit private parallel states and use a regular multiactive state
         * for all players
         * - Note: After unseating private state only actions on master state are possible
         * - Note: Usually it is not necessary to unset private states as they will be initialized to first private
         * state when private states are needed again. Nevertheless, it is generally better to clean private state after
         * exiting private parallel states to avoid bugs.
         */
        final public function unsetPrivateStateForAllPlayers(): void {
            //
        }

        /**
         * For players with specified ids private state will be reset to null, which means they will get out of private
         * parallel states and be in a master state like the private states are not used.
         *
         * @param array<int> $playerIds
         */
        final public function unsetPrivateStateForPlayers(array $playerIds): void {
            //
        }

        /**
         * Sends update notification about multiplayer changes. All multiactive set* functions above do that, however if
         * you want to change state manually using db queries for complex calculations, you have to call this yourself
         * after.
         *
         * Do not call this if you're calling one of the other setters above.
         * 
         * @param string|int|class-string<Bga\GameFramework\States\GameState> $next_state the transition name, or state id, or class name if using Class states
         * @return bool if the call moved to the next state
         */
        final public function updateMultiactiveOrNextState(string $nextStateIfNone): void {
            //
        }

        /**
         * Returns the game states as an array. Used for the front side.
         * 
         * @deprecated use getCurrentMainState() or getCurrentState(int $playerId) to get the informations of the current state
         * 
         * @return array<array> the states, typed as arrays.
         */
        public function getStatesAsArray(): array {
            return [];
        }

        /**
         * Returns the current state for a player. If the player is in private parallel state, it means the current private state for this player.
         * 
         * @param int $playerId the current player id
         * @return GameState the game state the player is in
         */
        public function getCurrentState(?int $playerId): ?GameState {
            return null;
        }

        /**
         * Returns the current state id for a player. If the player is in private parallel state, it means the current private state for this player.
         * 
         * @param int $playerId the current player id
         * @return int the game state id the player is in
         */
        public function getCurrentStateId(?int $playerId): ?int {
            return  $this->private_states[$playerId] ?? $this->current_state;
        }

        /**
         * Returns the current main state, ignoring private parallel states.
         * 
         * @return GameState the current main game state (ignoring private states)
         */
        public function getCurrentMainState(): ?GameState {
            return null;
        }

        /**
         * Returns the current main state id, ignoring private parallel states.
         * 
         * @return int the current main game state id (ignoring private states)
         */
        public function getCurrentMainStateId(): ?int {
            return $this->current_state;
        }

        /**
         * Run a State Handler state zombie function.
         * Will use the returned value to redirect to the next state.
         */
        public function runStateClassZombie(GameState $state, int $playerId): void {
        }
    }
}

namespace Bga\GameFramework\States {
    #[\Attribute]
    class PossibleAction {
    }

    abstract class GameState {
        public \Bga\GameFramework\Bga $bga;
        public \Bga\GameFramework\Db\Globals $globals;
        public \Bga\GameFramework\Notify $notify;
        public \Bga\GameFramework\Legacy $legacy;
        public \Bga\GameFramework\TableOptions $tableOptions;
        public \Bga\GameFramework\UserPreferences $userPreferences;
        public \Bga\GameFramework\TableStats $tableStats;
        public \Bga\GameFramework\PlayerStats $playerStats;
        public \Bga\GameFramework\Components\DeckFactory $deckFactory;
        public \Bga\GameFramework\Components\Counters\CounterFactory $counterFactory;
        public \Bga\GameFramework\Components\Counters\PlayerCounter $playerScore;
        public \Bga\GameFramework\Components\Counters\PlayerCounter $playerScoreAux;

        public ?\Bga\GameFramework\GameStateMachine $gamestate = null;

        public function __construct(
            /*protected \Bga\GameFramework\Table*/
            $game,
            public int $id,
            public \Bga\GameFramework\StateType $type,

            public ?string $name = null,
            public string $description = '',
            public string $descriptionMyTurn = '',
            public array $transitions = [],
            public bool $updateGameProgression = false,
            public ?int $initialPrivate = null,
        ) {
            if ($name == null) $this->name = substr(strrchr(get_class($this), '\\') ?: get_class($this), 1);
        }

        /**
         * Returns a random choice from an array of possible choices, for Zombie Mode level 1.
         * 
         * @param array $choices an of $choiceKey
         * @return mixed a random $choiceKey
         */
        public function getRandomZombieChoice(array $choices): mixed {
            return null;
        }

        /**
         * Returns a random top choice from an array of possible choices, for Zombie Mode level 2
         * 
         * @param array $choices an associative array of $choiceKey => $associatedPoints.
         * @param bool $reversed if the least points would be the best answer, instead of the top points
         * @return mixed the best $choiceKey
         */
        public function getBestZombieChoice(array $choices, bool $reversed = false): mixed {
            return null;
        }
    }
}

namespace {

    use Bga\GameFramework\GamestateMachine;

    if (!defined('APP_GAMEMODULE_PATH')) {
        define('APP_GAMEMODULE_PATH', '');
    }

    /**
     * Collection of stub classes for testing and stubs
     */
    class APP_Object {

        function dump($v, $value) {
            echo "$v=";
            var_dump($value);
        }

        function info($value) {
            echo "$value\n";
        }

        function trace($value) {
            echo "$value\n";
        }

        function debug($value) {
            echo "$value\n";
        }

        function watch($value) {
            echo "$value\n";
        }

        function warn($value) {
            echo "$value\n";
        }

        function error($msg) {
            echo "$msg\n";
        }
    }

    class APP_DbObject extends APP_Object {
        public static $query;

        /**
         * Performs a query on the database.
         */
        static public function DbQuery($sql, $specific_db = null, $bMulti = false) {
            APP_DbObject::$query = $sql;
        }

        /**
         * Returns a unique value from the database, or null if no value is found.
         *
         * @throws \BgaSystemException if more than 1 row is returned
         */
        static public function getUniqueValueFromDB($sql, $low_priority_select = false) {
            return 0;
        }

        /**
         * Returns an associative array of rows. The key is the first field of the SELECT.
         * If $bSingleValue=true and SELECT has 2 fields A,B, returns A=>B instead of A=>[A,B].
         *
         * @return array associative array keyed by first column
         */
        static public function getCollectionFromDB($sql, $bSingleValue = false, $low_priority_select = false) {
            //echo "dbquery call: $sql\n";
            return [];
        }

        /**
         * Same as getCollectionFromDB, but throws an exception if the collection is empty.
         *
         * @throws \BgaSystemException if the collection is empty
         */
        function getNonEmptyCollectionFromDB($sql) {
            return [];
        }

        /**
         * Returns one row as an associative array, or null if no result.
         *
         * @throws \BgaSystemException if the query returns more than one row
         */
        function getObjectFromDB($sql) {
            return [];
        }

        /**
         * Same as getObjectFromDB, but throws an exception if no row is returned.
         *
         * @throws \BgaSystemException if the query returns no row
         */
        function getNonEmptyObjectFromDB($sql) {
            return [];
        }

        /**
         * Returns an array of rows (not keyed by first column, unlike getCollectionFromDB).
         * If $single=true and SELECT has 1 field, returns an array of values.
         */
        function getObjectListFromDB($query, $single = false) {
            return [];
        }

        /**
         * Returns a double-keyed associative array from a SELECT with at least 2 columns.
         * First column = first key, second column = second key.
         */
        function getDoubleKeyCollectionFromDB($sql, $bSingleValue = false) {
            return [];
        }

        /**
         * Return the PRIMARY key of the last inserted row.
         */
        function DbGetLastId() {
        }

        /**
         * Return the number of rows affected by the last operation.
         */
        function DbAffectedRow() {
        }

        /**
         * Escape a string for safe use in SQL queries. Use on any unsafe user-provided data.
         */
        function escapeStringForDB($string) {
        }
    }

    class APP_Action extends APP_DbObject {
    }

    class APP_GameClass extends APP_DbObject {

        public function __construct() {
        }
    }

    class GameState {
        public $table_globals;
        private $current_state = 2;
        private $active_player = null;
        var $states = [];
        private $private_states = [];

        function __construct($states = []) {
            $this->states = $states;
        }

        function state_id() {
            return $this->current_state;
        }

        function state() {
            if (array_key_exists($this->current_state, $this->states)) {
                $state = $this->states[$this->current_state];
                $state['id'] = $this->current_state;
                return $state;
            }
            return ['type' => 'activeplayer'];
        }

        function getStateNumberByTransition($transition) {
            $state = $this->state();
            foreach ($state['transitions'] as $pos => $next_state) {
                if ($transition == $pos || !$transition) {
                    return $next_state;
                }
            }
            throw new feException("This transition ($transition) is impossible at this state ($this->current_state)");
        }

        function changeActivePlayer($player_id) {
            $this->active_player = $player_id;
            $this->states[$this->current_state]['active_player'] = $player_id;
        }

        function setAllPlayersMultiactive() {
        }

        function setPlayersMultiactive($players, $next_state, $bExclusive = false) {
        }

        function setPlayerNonMultiactive($player_id, $next_state) {
        }

        public function isMutiactiveState() {
            $state = $this->state();
            return ($state['type'] == 'multipleactiveplayer');
        }
        public function isMultiactiveState() {
            $state = $this->state();
            return (($state['type'] ?? 'activeplayer') == 'multipleactiveplayer');
        }

        public function getActivePlayerId() {
            $state = $this->state();
            return $state['active_player'] ?? $this->active_player;
        }

        public function getActivePlayerList() {
            $state = $this->state();
            if ($state['type'] == 'activeplayer') {
                return [$this->getActivePlayerId()];
            } else if ($state['type'] == 'multipleactiveplayer') {
                return $state['multiactive'] ?? [];
            } else
                return [];
        }

        // Return true if specified player is active right now.
        // This method take into account game state type, ie nobody is active if game state is "game" and several
        // players can be active if game state is "multiplayer"
        public function isPlayerActive($player_id) {
            return false;
        }

        function updateMultiactiveOrNextState($next_state_if_none) {
        }

        function nextState($transition) {
            $x = $this->getStateNumberByTransition($transition);
            $this->jumpToState($x);
        }

        function jumpToState($stateNum) {
            $this->current_state = $stateNum;
        }

        function checkPossibleAction($action) {
        }

        function reloadState() {
            return $this->state();
        }

        function getPrivateState($playerId) {
            return $this->private_states[$playerId] ?? null;
        }

        function nextPrivateStateForPlayers($ids, $transition) {
        }

        function nextPrivateStateForAllActivePlayers($transition) {
        }

        function nextPrivateState($playerId, $transition) {
            $privstate = $this->getStateNumberByTransition($transition);
            $this->setPrivateState($playerId, $privstate);
        }

        function setPrivateState($playerId, $newStateId) {
            $this->private_states[$playerId] = $newStateId;
        }

        function initializePrivateStateForAllActivePlayers() {
        }

        function initializePrivateStateForPlayers($ids) {
        }

        function initializePrivateState($playerId) {
            $state = $this->state();
            $privstate = $state['initialprivate'];
            $this->setPrivateState($playerId, $privstate);
        }

        function unsetPrivateState($playerId) {
            $this->private_states[$playerId] = null;
        }

        function unsetPrivateStateForPlayers($ids) {
        }

        function unsetPrivateStateForAllPlayers() {
        }
    }

    class BgaUserException extends feException {

        public function __construct($message, $code = 100, ?array $args = null) {
            parent::__construct($message, true, true, $code);
        }
    }

    class BgaSystemException extends feException {

        public function __construct($message, $code = 100) {
            parent::__construct($message, false, false, $code);
        }
    }

    class BgaVisibleSystemException extends BgaSystemException {

        public function __construct($message, $code = 100) {
            parent::__construct($message, $code);
        }
    }

    class feException extends Exception {

        public function __construct($message, $expected = false, $visibility = true, $code = 100, $publicMsg = '') {
            parent::__construct($message, $code);
        }
    }

    abstract class Table extends APP_GameClass {
        var $_colors = [];
        var $players = []; // cache
        public $gamename;

        /**
         * Access the underlying game state machine object.
         */
        readonly public \Bga\GameFramework\GamestateMachine $gamestate;

        public bool $not_a_move_notification = false;
        public Bga\GameFramework\Notify $notify;
        public Bga\GameFramework\Components\DeckFactory $deckFactory;
        public $globals;
        /**
         * when set to true there is another table to track multiactive players
         */
        public $bIndependantMultiactiveTable = false;
        /**
         * hold player prefrences table - only available during setupNewGame
         */
        var $player_preferences;

        public $debugLastNotif = null;

        public function __construct() {
            parent::__construct();
            $this->gamestate = new GamestateMachine();
            $this->deckFactory = new \Bga\GameFramework\Components\DeckFactory();
            $this->players = [
                1 => ['player_name' => $this->getActivePlayerName(), 'player_color' => 'ff0000'],
                2 => ['player_name' => 'player2', 'player_color' => '0000ff']
            ];
            $this->notify = new Bga\GameFramework\Notify();
        }

        function getAllTableDatas() {
            return [];
        }

        /**
         * Get the "active_player", whatever the current state type.
         * Note: it does NOT mean that this player is active right now, because state type could be "game" or "multiplayer".
         * Note: avoid using this method in a "multiplayer" state because it does not mean anything.
         *
         * @return string the active player id typed as string
         */
        function getActivePlayerId() {
            return $this->gamestate->getActivePlayerId();
        }

        /**
         * @deprecated use getPlayerNameById($activePlayerId) with $activePlayerId magic param
         */
        function getActivePlayerName() {
            return "player1";
        }

        function getTableOptions() {
            return [];
        }

        /**
         * Send buffered notifications to players.
         * This method is autmatically called at the end of each AJAX action, but can be called more often if long
         * operations are beeing performed.
         * It is not recommended to override or call this method manually.
         */
        function sendNotifications() {
        }

        /**
         * Save data for undo.
         * Not recommended to override or call manually, its automatically called at the end of sendNotifications if
         * undoSavePoint() was called prior
         */
        function doUndoSavePoint() {
        }

        function getTablePreferences(): array {
            // use json if possible
            if (file_exists('./gamepreferences.json')) {
                $raw = file_get_contents('./gamepreferences.json');
                try {
                    return json_decode($raw, true);
                } catch (Exception $e) {
                    return [];
                }
            }
            // use old format
            $game_preferences = [];
            try {
                if (file_exists('./gameoptions.inc.php')) {
                    require './gameoptions.inc.php';
                }
                return $game_preferences;
            } catch (Throwable $e) {
                die("bad gameoptions.inc.php");
            }
            return $game_preferences;
        }
        // stub method when using for tests
        function _getColors() {
            if (!empty($this->_colors)) {
                return $this->_colors;
            }
            return ["ff0000", "008000", "0000ff", "ffa500", "4c1b5b"];
        }

        /**
         * Returns an associative array of player infos, indexed by player_id.
         * Each entry contains: player_id, player_color, player_name, player_zombie, player_no, player_eliminated.
         *
         * @return array<int, array> player infos keyed by player_id
         */
        function loadPlayersBasicInfos() {
            $default_colors =  $this->_getColors();
            $values = [];
            $id = 10;
            $no = 1;
            foreach ($default_colors as $color) {
                $values[$id] = [
                    'player_id' => $id,
                    'player_color' => $color,
                    'player_name' => "player$id",
                    'player_zombie' => 0,
                    'player_no' => $no,
                    'player_eliminated' => 0
                ];
                $id++;
                $no++;
            }
            return $values;
        }

        /**
         * Get the "current_player". The current player is the one from which the action originated.
         * In general, you shouldn't use this method, unless you are in "multiplayer" state.
         * NOTE: This is not necessarily the active player!
         *
         * @return string the current player id, typed as string
         */
        final public function getCurrentPlayerId(bool $bReturnNullIfNotLogged = false): string {
            return $this->_getCurrentPlayerId();
        }

        protected function getCurrentPlayerName() {
            return '';
        }

        protected function getCurrentPlayerColor() {
            return $this->getPlayerColorById($this->getCurrentPlayerId());
        }

        function isCurrentPlayerZombie() {
            return false;
        }

        /**
         * Get the player name by player id.
         *
         * @param int $player_id the player id
         * @return string the player name
         */
        public function getPlayerNameById($player_id) {
            $players = self::loadPlayersBasicInfos();
            return $players[$player_id]['player_name'] ?? "player$player_id";
        }

        /**
         * Get 'player_no' (number) by player id.
         *
         * @param int $player_id the player id
         * @return string the player no typed as string
         */
        public function getPlayerNoById($player_id) {
            $players = self::loadPlayersBasicInfos();
            return $players[$player_id]['player_no'];
        }

        /**
         * Get the player color by player id.
         *
         * @param int $player_id the player id
         * @return string the player color
         */
        public function getPlayerColorById($player_id) {
            $players = self::loadPlayersBasicInfos();
            return $players[$player_id]['player_color'];
        }

        /**
         * Eliminate a player from the game so they can start another game without waiting for this one to end.
         *
         * @param int $player_id the player to eliminate
         */
        function eliminatePlayer($player_id) {
        }

        /**
         * Setup correspondance "labels to id"
         *
         * @param [] $labels
         *            - map string -> int (label of state variable -> numeric id in the database)
         */
        function initGameStateLabels($labels) {
        }

        /**
         * Set the initial value of a global.
         *
         * @param string $value_label the label
         * @param int $value_value the initial value
         */
        function setGameStateInitialValue(string $value_label, int $value_value) {
        }

        /**
         * Retrieve the value of a global. Returns $default if global has not been initialized.
         *
         * @param string $value_label the label
         * @param ?int $default a default value if the label doesn't have an associated value
         * @return int|string the value
         */
        function getGameStateValue($value_label, ?int $def = null) {
            return 0;
        }

        /**
         * Set the value of a global.
         *
         * @param string $value_label the label
         * @param int $value_value the value to set
         */
        function setGameStateValue($value_label, $value_value) {
        }

        /**
         * Increment the value of a global and return the new value.
         *
         * @param string $value_label the label
         * @param int $increment the increment (can be negative)
         * @return int the new value
         */
        function incGameStateValue($value_label, $increment) {
            return 0;
        }

        /**
         * Make the next player active (in natural order)
         */
        protected function activeNextPlayer() {
        }

        /**
         * Make the previous player active (in natural order)
         */
        protected function activePrevPlayer() {
        }

        /**
         * Check if action is valid regarding current game state (exception if fails)
         * if "bThrowException" is set to "false", the function return false in case of failure instead of throwing and
         * exception
         *
         * @param string $actionName
         * @param boolean $bThrowException
         */
        function checkAction($actionName, $bThrowException = true) {
        }

        /**
         * Return an associative array which associates each player with the next player around the table.
         * Key 0 is associated to the first player to play.
         *
         * @return array<int, int>
         */
        function getNextPlayerTable() {
            $players = $this->loadPlayersBasicInfos();
            return $this->createNextPlayerTable(array_keys($players));
        }

        function getPrevPlayerTable() {
            $players = $this->loadPlayersBasicInfos();
            return $this->createPrevPlayerTable(array_keys($players));
        }

        /**
         * Get player playing after given player in natural playing order.
         *
         * @param int $player_id a player id
         * @return int the player after
         */
        function getPlayerAfter($player_id) {
            $player_table = $this->getNextPlayerTable();
            return $player_table[$player_id];
        }

        /**
         * Get player playing before given player in natural playing order.
         *
         * @param int $player_id a player id
         * @return int the player before
         */
        function getPlayerBefore($player_id) {
            $player_table = $this->getPrevPlayerTable();
            return $player_table[$player_id];
        }

        protected function createNextPlayerTable($players, $bLoop = true) {
            $player_table = [];
            $prev = $first = array_shift($players);
            while (count($players) > 0) {
                $prev = $player_table[$prev] = array_shift($players);
            }
            $player_table[$prev] = $bLoop ? $first : null;
            $player_table[0] = $first;
            return $player_table;
        }

        protected function createPrevPlayerTable($players, $bLoop = true) {
            $result = self::createNextPlayerTable($players);
            unset($result[0]);
            $result = array_flip($result);
            return $result;
        }

        /**
         * Send a notification to all players of the game and spectators.
         *
         * @param string $type notification type (comprehensive string code)
         * @param string $message log message (should be surrounded by clienttranslate if not empty)
         * @param array $args notification arguments
         */
        function notifyAllPlayers($type, $message, $args) {
            $this->debugLastNotif = [
                'type' => $type,
                'log' => $message,
                'args' => $args,
                'player_id' => 0
            ];
            //echo "notifyAllPlayers: $type: $message\n";
        }

        /**
         * Send a notification to a single player of the game.
         *
         * @param int $player_id the player ID to send the notification to
         * @param string $type notification type (comprehensive string code)
         * @param string $message log message (should be surrounded by clienttranslate if not empty)
         * @param array $args notification arguments
         */
        function notifyPlayer($player_id, $type, $message, $args) {
            $this->debugLastNotif = [
                'type' => $type,
                'log' => $message,
                'args' => $args,
                'player_id' => $player_id
            ];
        }

        function getStatTypes() {
            return ['player' => [], 'table' => [],];
        }

        /**
         * Create a statistic entry with a default value. Must be called for each statistic before using it.
         *
         * @deprecated use $this->bga->tableStats->init / $this->bga->playerStats->init
         * @param string $table_or_player 'table' or 'player'
         * @param string $name statistic name as defined in stats.json
         * @param int|float $value default value
         * @param ?int $player_id player id (required if $table_or_player is 'player')
         */
        function initStat($table_or_player, $name, $value, $player_id = null) {
        }

        /**
         * Set a statistic value.
         *
         * @deprecated use $this->bga->tableStats->set / $this->bga->playerStats->set
         * @param int|float $value the value
         * @param string $name statistic name as defined in stats.json
         * @param ?int $player_id if null, sets table stat; otherwise sets player stat
         */
        function setStat($value, $name, $player_id = null, $bDoNotLoop = false) {
            echo "stat: $name=$value\n";
        }

        /**
         * Increment a statistic value by a delta.
         *
         * @deprecated use $this->bga->tableStats->inc / $this->bga->playerStats->inc
         * @param int|float $delta signed difference to apply
         * @param string $name statistic name as defined in stats.json
         * @param ?int $player_id if null, increments table stat; otherwise player stat
         */
        function incStat($delta, $name, $player_id = null) {
        }

        /**
         * Return the value of a statistic.
         *
         * @deprecated use $this->bga->tableStats->get / $this->bga->playerStats->get
         * @param string $name statistic name as defined in stats.json
         * @param ?int $player_id if null, returns table stat; otherwise player stat
         * @return int the statistic value
         */
        function getStat($name, $player_id = null) {
            return 0;
        }

        function _($s) {
            return $s;
        }

        /**
         * Returns the number of players playing at the table.
         *
         * @return int the number of players
         */
        function getPlayersNumber() {
            return count($this->loadPlayersBasicInfos());
        }

        function reattributeColorsBasedOnPreferences($players, $colors) {
        }

        function reloadPlayersBasicInfos() {
        }

        function getNew($deck_definition): object {
            return null;
        }

        /**
         * Give standard extra time to this player (standard extra time is a game option).
         *
         * @param int $player_id the player id
         * @param ?int $specific_time optional specific time in seconds (overrides game option)
         */
        function giveExtraTime($player_id, $specific_time = null) {
        }

        function getStandardGameResultObject(): array {
            return [];
        }

        function applyDbUpgradeToAllDB($sql) {
        }

        function getGameinfos() {
            unset($gameinfos);
            require('gameinfos.inc.php');
            if (isset($gameinfos)) {
                return $gameinfos;
            }
            throw new feException("gameinfos.inp.php suppose to define \$gameinfos variable");
        }

        /* Method to override to set up each game */
        abstract protected function setupNewGame($players, $options = []);

        public function stMakeEveryoneActive() {
            $this->gamestate->setAllPlayersMultiactive();
        }

        /**
         * Save undo state after all transactions are done.
         * Call this at the end of a player action to allow undo.
         */
        function undoSavepoint() {
        }

        /**
         * Restore DB to the last saved undo state.
         */
        function undoRestorePoint() {
        }

        /**
         * Returns the BGA environment: "studio" or "production".
         *
         * @return string the environment name
         */
        function getBgaEnvironment() {
            return "studio";
        }

        function say($text) {
            return;
        }

        // stub method when using for tests
        private int $curid;

        public function _getCurrentPlayerId() {
            return $this->curid;
        }

        public function _setCurrentPlayerId(int $playerId) {
            $this->curid = $playerId;
        }
    }

    class Page {
        public $blocks = [];

        public function begin_block($template, $block) {
            $this->blocks[$block] = [];
        }

        public function insert_block($block, $args) {
            $this->blocks[$block][] = $args;
        }
    }

    class GUser {

        public function get_id() {
            return 1;
        }
    }
    // Arg types
    define('AT_int', 0); //  an integer
    define('AT_posint', 1); //  a positive integer
    define('AT_float', 2); //  a float
    define('AT_email', 3); //  an email
    define('AT_url', 4); //  a URL
    define('AT_bool', 5); //  1/0/true/false
    define('AT_enum', 6); //  argTypeDetails list the possible values
    define('AT_alphanum', 7); //  only 0-9a-zA-Z_ and space
    define('AT_username', 8); //  TEL username policy: alphanum caracters + accents
    define('AT_login', 9); //  AT_username | AT_email
    define('AT_numberlist', 13); //  exemple: 1,4;2,3;-1,2
    define('AT_uuid', 17); // an UUID under the forme 0123-4567-89ab-cdef
    define('AT_version', 18); // A tournoi site version (ex: 100516-1243)
    define('AT_cityname', 20); // City name: 0-9a-zA-Z_ , space, accents, ' and -
    define('AT_filename', 21); // File name: 0-9a-zA-Z_ , and "."
    define('AT_groupname', 22); //  4-50 alphanum caracters + accents + :
    define('AT_timezone', 23); //  alphanum caracters + /
    define('AT_mediawikipage', 24); // Mediawiki valid page name
    define('AT_html_id', 26); // HTML identifier: 0-9a-zA-Z_-
    define('AT_alphanum_dash', 27); //  only 0-9a-zA-Z_ and space + dash
    define('AT_date', 28); //  0-9 + "/" + "-"
    define('AT_num', 29); //  0-9
    define('AT_alpha_strict', 30); //  only a-zA-Z
    define('AT_namewithaccent', 31); // Like City name: 0-9a-zA-Z_ , space, accents, ' and -
    define('AT_json', 32); // JSON string
    define('AT_base64', 33); // Base64 string
    define("FEX_bad_input_argument", 300);

    class APP_GameAction extends APP_Action {
        protected Table $game;
        protected $view;
        protected $viewArgs;

        function getArg($name, $type, $mandatory = true, $default = null) {
            return '';
        }

        protected function setAjaxMode($bCheckCsrf = true) {
        }

        protected function ajaxResponse($data = '') {
        }

        protected function isArg($argName) {
            return true;
        }
    }

    function totranslate($text) {
        return $text;
    }

    function clienttranslate($x) {
        return $x;
    }

    function mysql_fetch_assoc($res) {
        return [];
    }

    function bga_rand($min, $max) {
        return $min;
    }

    function getKeysWithMaximum($array, $bWithMaximum = true) {
        return [];
    }

    function getKeyWithMaximum($array) {
        return '';
    }
}
