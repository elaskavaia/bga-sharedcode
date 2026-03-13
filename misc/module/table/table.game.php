<?php

/** @noinspection PhpDocRedundantThrowsInspection */
/** @noinspection PhpInconsistentReturnPointsInspection */
/** @noinspection PhpUnreachableStatementInspection */

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

    /**
     * A builder for game states.
     * To be called with `[game state id] => GameStateBuilder::create()->...[set all necessary properties]->build()`
     * in the states.inc.php file.
     */
    final class GameStateBuilder
    {
        /**
         * Create a new GameStateBuilder.
         */
        public static function create(): self
        {
            return new self();
        }

        /**
         * Return the game setup state (should have id 1).
         * To be called with `[game state id] => GameStateBuilder::gameSetup(10)->build()` if your first game state is 10.
         * If not set in the $machinestates array, it will be automatically created with a transition to state 2.
         *
         * @param $nextStateId the first real game state, just after the setup (default 2).
         */
        public static function gameSetup(int|string $nextStateId = 2): self
        {
            return self::create();
        }

        /**
         * Return the game end score state (usually, id 98).
         * This is a common state used for end game scores & stats computation.
         * If the game dev uses it, they must define the function `stEndScore` with a call to `$this->gamestate->nextState();` at the end.
         */
        public static function endScore(): self
        {
            return self::create();
        }

        /**
         * Return the game end state (should have id 99).
         * If not set in the $machinestates array, it will be automatically created.
         */
        public static function gameEnd(): self
        {
            return self::create();
        }

        /**
         * The name of the state.
         */
        public function name(string $name): self
        {
            return $this;
        }

        /**
         * The type of the state. MANAGER should not be used, except for setup and end game states.
         */
        public function type(StateType $type): self
        {
            return $this;
        }

        /**
         * The description for inactive players. Should be `clienttranslate('...')` if not empty.
         */
        public function description(string $description): self
        {
            return $this;
        }

        /**
         * The description for active players. Should be `clienttranslate('...')` if not empty.
         */
        public function descriptionMyTurn(string $descriptionMyTurn): self
        {
            return $this;
        }

        /**
         * The PHP function to call when entering the state.
         * Usually prefixed by `st`.
         */
        public function action(string $action): self
        {
            return $this;
        }

        /**
         * The PHP function returning the arguments to send to the front when entering the state.
         * Usually prefixed by `arg`.
         */
        public function args(string $args): self
        {
            return $this;
        }

        /**
         * The list of possible actions in the state.
         * Usually prefixed by `act`.
         */
        public function possibleActions(array $possibleActions): self
        {
            return $this;
        }

        /**
         * The list of transitions to other states. The key is the transition name and the value is the state to transition to.
         * Example: `['endTurn' => ST_END_TURN]`.
         */
        public function transitions(array $transitions): self
        {
            return $this;
        }

        /**
         * Set to true if the game progression has changed (to be recalculated with `getGameProgression`)
         */
        public function updateGameProgression(bool $update): self
        {
            return $this;
        }

        /**
         * For multi active states with inner private states, the initial state to go to.
         */
        public function initialPrivate(int $initial): self
        {
            return $this;
        }

        /**
         * Export the built GameState.
         */
        public function build(): GameState
        {
            return new GameState();
        }
    }

    /**
     * Object to regroup all framework subobjects.
     */
    abstract class Bga {
        public Db\Globals $globals;
        public Notify $notify;
        public Logs $logs;
        public Legacy $legacy;
        public Tournament $tournament;
        public TableOptions $tableOptions;
        public UserPreferences $userPreferences;
        public TableStats $tableStats;
        public PlayerStats $playerStats;
        public Components\DeckFactory $deckFactory;
        public Components\Counters\CounterFactory $counterFactory;
        public Debug $debug;

        public Components\Counters\PlayerCounter $playerScore;
        public Components\Counters\PlayerCounter $playerScoreAux;
    }

    abstract class Logs {
        /**
         * Returns the current move id, when doing an action, that should be stored along informations to undo.
         *
         * @return int the current move id
         */
        function getCurrentMoveId(): int {
            return 0;
        }

        /**
         * Remove all logs from a move id that was stored during an action using `getCurrentMoveId()`.
         * The game should be in the exact same point as it was before the stored action.
         */
        function remove(int $startMoveId): void {
        }
    }

    abstract class Legacy {
        /**
         * Get data associated with $key for the current game.
         *
         * This data is common to ALL tables from the same game for this player, and persist from one table to another.
         *
         * Note: calling this function has an important cost => please call it few times (possibly: only ONCE) for each player for 1 game if possible
         *
         * @param string $key the key of the legacy data to get
         * @param int $playerId the player id (or 0 for data shared on all tables)
         * @param mixed $defaultValue the value to return if the key doesn't exist in the legacy data for this player
         */
        public function get(string $key, int $playerId, mixed $defaultValue = null): mixed {
            return null;
        }

        /**
         * Store some data associated with $key for the given user / current game
         * In the opposite of all other game data, this data will PERSIST after the end of this table, and can be re-used in a future table with the same game.
         *
         * @param string $key the key of the legacy data to save
         * @param int $playerId the player id (or 0 for data shared on all tables)
         * @param mixed $value the value to save as the legacy data for this player
         * @param int $ttl time-to-live: the maximum, and default, is 365 days.
         */
        public function set(string $key, int $playerId, mixed $value, int $ttl = 365): void {
        }

        /**
         * Remove some legacy data with the given key
         *
         * @param string $key the key of the legacy data to remove
         * @param int $playerId the player id (or 0 for data shared on all tables)
         */
        public function delete(string $key, int $playerId): void {
        }

        /**
         * Get data associated with the team for the current game.
         *
         * This data is common to ALL tables from the same game for this team, and persist from one table to another.
         *
         * Note: calling this function has an important cost => please call it few times (possibly: only ONCE) for 1 game if possible
         *
         * @param mixed $defaultValue the value to return if the legacy data doesn't exist or is null for this team
         */
        public function getTeam(mixed $defaultValue = null): mixed {
            return null;
        }

        /**
         * Store some data associated to the team of the current table (all players at the table) / current game
         * In the opposite of all other game data, this data will PERSIST after the end of this table, and can be re-used in a future table with the same game.
         *
         * @param mixed $value the value to save as the legacy data for this team
         * @param int $ttl time-to-live: the maximum, and default, is 365 days.
         */
        public function setTeam(mixed $value, int $ttl = 365): void {
        }

        /**
         * Remove the legacy data for a team
         */
        public function deleteTeam(): void {
        }
    }

    abstract class Tournament
    {
        /**
         * Returns true if this table is a tournament encounter.
         */
        public function isTournament(): bool
        {
            return false;
        }

        /**
         * Retrieve tournament seeds for deterministic randomness.
         *
         * Returns an empty array when the table is not part of a tournament.
         *
         * Note: `parent_tournament` refer to the main tournament of Groups Stage tournaments (tournaments of either of the two stages, will reference the same "parent")
         *
         * @return array{
         *   tournament_seed?: int,
         *   step_seed?: int,
         *   parent_tournament_seed?: int
         * }
         */
        public function getSeedInfo(): array
        {
            return [];
        }

        /**
         * Store player game data associated with the given key for a tournament (of which the table must be a part of).
         *
         * The cumulative size of the data you can store for a given player for a tournament is 64 KiB.
         *
         * Note: As with every game framework API that interacts with the BGA mainsite, please use it thoughtfully.
         *
         * @param int $playerId
         * @param string $key
         * @param mixed $data
         */
        public function storePlayerGameData(int $playerId, string $key, mixed $data): void
        {
            //
        }

        /**
         * Get player game data associated with the given key for a tournament.
         *
         * You can use '%' in the key to retrieve multiple values at once matching a pattern.
         *
         * If '%' is used the return value will be an array of key-value pairs (or [], if no match is found).
         * Otherwise, a single value is returned (or null, if no match is found).
         *
         * Returned values are decoded from JSON.
         *
         * @param int $playerId
         * @param string $key
         *
         * @return null|string|array<string,string>
         */
        public function retrievePlayerGameData(int $playerId, string $key): null|string|array
        {
            return null;
        }

        /**
         * Remove player game data associated with the given key for a tournament.
         *
         * In any case, all data related to a tournament is removed when the tournament is finished.
         *
         * @param int $playerId
         * @param string $key
         */
        public function removePlayerGameData(int $playerId, string $key): void
        {
            //
        }
    }

    abstract class TableOptions {
        /**
         * Get the value of a table option.
         *
         * @param int $optionId the option id as in the gameoptions.json file
         * @return int|null the option value, or null if the option doesn't exist (for example on a table created before a new option was added).
         */
        public function get(int $optionId): ?int {
            return 0;
        }

        /**
         * Indicates if the table is Turn-based.
         *
         * @return bool if the table is Turn-based.
         */
        function isTurnBased(): bool {
            return false;
        }

        /**
         * Indicates if the table is Real-time.
         *
         * @return bool if the table is Real-time.
         */
        function isRealTime(): bool {
            return false;
        }
    }

    abstract class UserPreferences {
        /**
         * Gets the value of a user preference for a player (cached in game DB).
         *
         * @param int $playerId the player id
         * @param int $prefId the preference id, as described in the gamepreferences.json file
         * @return int|null the user preference value, or null if unset
         */
        function get(int $playerId, int $prefId): ?int
        {
            return null;
        }
    }

    abstract class TableStats {
        /**
         * Create a statistic entry with a default value.
         *
         * @param string|array $nameOrNames Statistic identifier(s) defined in `stats.json`.
         * @param int|float|bool $value Default value to register.
         */
        public function init(string|array $nameOrNames, int|float|bool $value): void {
        }

        /**
         * Update a table statistic to the provided value.
         *
         * @param string $name Statistic identifier defined in `stats.json`.
         * @param int|float|bool $value Value to persist.
         */
        public function set(string $name, int|float|bool $value): void {
        }

        /**
         * Increment a table statistic by the given delta.
         *
         * @param string $name Statistic identifier defined in `stats.json`.
         * @param int|float $delta Signed difference to apply.
         */
        public function inc(string $name, int|float $delta): void {
        }

        /**
         * Fetch a table statistic.
         *
         * @param string $name Statistic identifier defined in `stats.json`.
         *
         * @return int|float|bool The statistic value.
         */
        public function get(string $name): int|float|bool {
            return 0;
        }
    }

    abstract class PlayerStats {
        /**
         * Create a statistic entry with a default value.
         *
         * @param string|array $nameOrNames Statistic identifier(s) defined in `stats.json`.
         * @param int|float|bool $value Default value to register.
         * @param bool $updateTableStat if there is a table stat of the same name to init at the same time (for example, for a turnNumber counter that would store the turns played by each player but also the total of turns for the table)
         */
        public function init(string|array $nameOrNames, int|float|bool $value, bool $updateTableStat = false): void {
        }

        /**
         * Update a player statistic to the provided value.
         *
         * @param string $name Statistic identifier defined in `stats.json`.
         * @param int|float|bool $value Value to persist.
         * @param int $player_id Target player id.
         */
        public function set(string $name, int|float|bool $value, int $player_id): void {
        }

        /**
         * Apply the same value to a player statistic for every player.
         *
         * @param string $name Statistic identifier defined in `stats.json`.
         * @param int|float|bool $value Value to persist for all players.
         */
        public function setAll(string $name, int|float|bool $value): void {
        }

        /**
         * Increment a player statistic by the given delta.
         *
         * @param string $name Statistic identifier defined in `stats.json`.
         * @param int|float $delta Signed difference to apply.
         * @param int $player_id Target player id.
         * @param bool $updateTableStat if there is a table stat of the same name to update at the same time (for example, for a turnNumber counter that would store the turns played by each player but also the total of turns for the table)
         */
        public function inc(string $name, int|float $delta, int $player_id, bool $updateTableStat = false): void {
        }

        /**
         * Increment a statistic for every player.
         *
         * @param string $name Statistic identifier defined in `stats.json`.
         * @param int|float $delta Signed difference to apply.
         */
        public function incAll(string $name, int|float $delta): void {
        }

        /**
         * Fetch a player statistic.
         *
         * @param string $name Statistic identifier defined in `stats.json`.
         * @param int $player_id Target player id.
         *
         * @return int|float|bool The statistic value.
         */
        public function get(string $name, int $player_id): int|float|bool {
            return 0;
        }

        /**
         * Retrieve the statistic for all players, keyed by player id.
         *
         * @param string $name Statistic identifier defined in `stats.json`.
         *
         * @return array<int, int|float|bool> Player id keyed map of the statistic values.
         */
        public function getAll(string $name): array {
            return [];
        }
    }

    abstract class Debug {
        public function playUntil(callable $fn): void {
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

namespace Bga\GameFramework\Actions {
    #[\Attribute]
    class CheckAction {
        public function __construct(
            public bool $enabled = true,
        ) {}
    }

    #[\Attribute]
    class Debug {
        public function __construct(
            public bool $reload = false,
        ) {}
    }
}

namespace Bga\GameFramework\Actions\Types {
    #[\Attribute]
    class IntParam {
        public function __construct(
            ?string $name = null,
            public ?int $min = null,
            public ?int $max = null,
        ) {}

        public function getValue(string $paramName): int { return 0; }
    }

    #[\Attribute]
    class BoolParam {
        public function __construct(
            ?string $name = null,
        ) {}

        public function getValue(string $paramName): bool { return false; }
    }

    #[\Attribute]
    class FloatParam {
        public function __construct(
            ?string $name = null,
            public ?float $min = null,
            public ?float $max = null,
        ) {}

        public function getValue(string $paramName): float { return 0; }
    }

    #[\Attribute]
    class IntArrayParam {
        public function __construct(
            ?string $name = null,
            public ?int $min = null,
            public ?int $max = null,
        ) {}

        public function getValue(string $paramName): array { return []; }
    }

    #[\Attribute]
    class StringParam {
        public function __construct(
            ?string $name = null,
            public ?bool $alphanum = false,
            public ?bool $alphanum_dash = false,
            public ?bool $base64 = false,
            public ?array $enum = null,
        ) {}

        public function getValue(string $paramName): string { return ''; }
    }

    #[\Attribute]
    class JsonParam {
        public function __construct(
            ?string $name = null,
            public ?bool $associative = true,
            public ?bool $alphanum = true,
        ) {}

        public function getValue(string $paramName): mixed { return []; }
    }
}

namespace Bga\GameFramework\Db {
    abstract class Globals
    {
        /**
         * Delete global variables.
         */
        public function delete(string ...$names): void
        {
            //
        }

        /**
         * Returns the value of `$name` if it exists. Otherwise, fallback on `$defaultValue`.
         *
         * @template T of object
         * @param string $name the variable name
         * @param mixed $defaultValue the value to return if the variable doesn't exist in database
         * @param class-string<T>|string $class the class of the expected object, to returned a typed object. For example `Undo::class`.
         * @return ($class is class-string<T> ? T : mixed)
         */
        public function get(string $name, mixed $defaultValue = null, ?string $class = null): mixed
        {
            return null;
        }

        /**
         * Retrieve all variables stored in DB (or a selected subset, if the function is called with parameters).
         */
        public function getAll(string ...$names): array
        {
            return [];
        }

        /**
         * Returns true if globals has a key `$name`.
         */
        public function has(string $name): bool
        {
            return false;
        }

        /**
         * Increment the global `$name` by `$step`.
         *
         * @throws BgaSystemException if the global `$name` is not a numeric value.
         */
        public function inc(string $name, int $step): int
        {
            return 0;
        }

        /**
         * Set `$name` with the value `$value`.
         */
        public function set(string $name, mixed $value): void
        {
            //
        }
    }
}

namespace Bga\GameFramework\Components\Counters {
    /**
     * Factory to create counters.
     */
    final class CounterFactory {
        /**
         * Create a PlayerCounter component.
         *
         * @param string $name the name of the counter, used to link it to the JS counter
         * @param ?int $min the minimum value of the counter (null = no minimum)
         * @param ?int $max the maximum value of the counter (null = no maximum)
         * @return PlayerCounter a new PlayerCounter object
         */
        public function createPlayerCounter(string $name, ?int $min = 0, ?int $max = null): PlayerCounter {
            return new class extends PlayerCounter {}();
        }

        /**
         * Create a TableCounter component.
         *
         * @param string $name the name of the counter, used to link it to the JS counter
         * @param ?int $min the minimum value of the counter (null = no minimum)
         * @param ?int $max the maximum value of the counter (null = no maximum)
         * @return TableCounter a new TableCounter object
         */
        public function createTableCounter(string $name, ?int $min = 0, ?int $max = null): TableCounter {
            return new class extends TableCounter{}();
        }
    }

    /**
     * Represents a player counter that is stored in DB, one value for each player. For example, the money the player have.
     */
    abstract class PlayerCounter {
        /**
         * Initialize the DB elements. Must be called during game `setupNewGame`.
         *
         * @param int $initialValue, if different than 0
         */
        public function initDb(array $playerIds, int $initialValue = 0) {
        }

        /**
         * Returns the current value of the counter.
         *
         * @param int $playerId the player id
         * @return int the value
         * @throws UnknownPlayerException if $playerId is not in the player ids initialized by initDb
         */
        public function get(int $playerId): int {
            return 0;
        }

        /**
         * Set the value of the counter, and send a notif to update the value on the front side.
         *
         * @param int $playerId the player id
         * @param int $value the new value
         * @param ?NotificationMessage $message the notif to send to the front, with a message and optional args. Empty message for no log, null for no notif at all (the front will not be updated).
         * @return int the new value
         * @throws OutOfRangeCounterException if the value is outside the min/max
         * @throws UnknownPlayerException if $playerId is not in the player ids initialized by initDb
         */
        public function set(int $playerId, int $value, ?\Bga\GameFramework\NotificationMessage $message = new \Bga\GameFramework\NotificationMessage()): int {
            return 0;
        }

        /**
         * Increment the value of the counter, and send a notif to update the value on the front side.
         *
         * Note: if the inc is 0, no notif will be sent.
         *
         * @param int $playerId the player id
         * @param int $inc the value to add to the current value
         * @param ?NotificationMessage $message the notif to send to the front, with a message and optional args. Empty message for no log, null for no notif at all (the front will not be updated).
         * @return int the new value
         * @throws OutOfRangeCounterException if the value is outside the min/max
         * @throws UnknownPlayerException if $playerId is not in the player ids initialized by initDb
         */
        public function inc(int $playerId, int $inc, ?\Bga\GameFramework\NotificationMessage $message = new \Bga\GameFramework\NotificationMessage()): int {
            return 0;
        }

        /**
         * Return the lowest value.
         *
         * @return int the lowest value
         */
        public function getMin(): int {
            return 0;
        }

        /**
         * Return the highest value.
         *
         * @return int the highest value
         */
        public function getMax(): int {
            return 0;
        }

        /**
         * Return the values for each player, as an associative array $playerId => $value.
         *
         * @return array<int, int> the values
         */
        public function getAll(): array {
            return [];
        }

        /**
         * Set the value of the counter for all the players, and send a notif to update the value on the front side.
         *
         * @param int $value the new value
         * @param ?NotificationMessage $message the notif to send to the front, with a message and optional args. Empty message for no log, null for no notif at all (the front will not be updated).
         * @return int the new value
         * @throws OutOfRangeCounterException if the value is outside the min/max
         */
        public function setAll(int $value, ?\Bga\GameFramework\NotificationMessage $message = new \Bga\GameFramework\NotificationMessage()): int {
            return 0;
        }

        /**
         * Updates the result object, to be used in the `getAllDatas` function.
         * Will set the value on each $result["players"] sub-array.
         *
         * @param array $result the object to update.
         * @param ?string $fieldName the field name to set in $result["players"], if different than the counter name.
         */
        public function fillResult(array &$result, ?string $fieldName = null) {
        }
    }

    /**
     * Represents a game counter that is stored in DB. For example, the number of rounds.
     */
    abstract class TableCounter {
        /**
         * Initialize the DB elements. Must be called during game `setupNewGame`.
         *
         * @param int $initialValue, if different than 0
         */
        public function initDb(int $initialValue = 0) {}

        /**
         * Returns the current value of the counter.
         *
         * @return int the value
         */
        public function get(): int {
            return 0;
        }

        /**
         * Set the value of the counter, and send a notif to update the value on the front side.
         *
         * @param int $value the new value
         * @param ?NotificationMessage $message the notif to send to the front, with a message and optional args. Empty message for no log, null for no notif at all (the front will not be updated).
         * @return int the new value
         * @throws OutOfRangeCounterException if the value is outside the min/max
         */
        public function set(int $value, ?\Bga\GameFramework\NotificationMessage $message = new \Bga\GameFramework\NotificationMessage()): int {
            return 0;
        }

        /**
         * Increment the value of the counter, and send a notif to update the value on the front side.
         *
         * Note: if the inc is 0, no notif will be sent.
         *
         * @param int $inc the value to add to the current value
         * @param ?NotificationMessage $message the notif to send to the front, with a message and optional args. Empty message for no log, null for no notif at all (the front will not be updated).
         * @return int the new value
         * @throws OutOfRangeCounterException if the value is outside the min/max
         */
        public function inc(int $inc, ?\Bga\GameFramework\NotificationMessage $message = new \Bga\GameFramework\NotificationMessage()): int {
            return 0;
        }

        /**
         * Updates the result object, to be used in the `getAllDatas` function.
         *
         * @param array $result the object to update.
         * @param ?string $fieldName the field name to set in $result, if different than the counter name.
         */
        public function fillResult(array &$result, ?string $fieldName = null) {
        }
    }
}

namespace Bga\GameFramework\Helpers {
    final class Json {

        /**
         * Decode an object stored in JSON. Will return associative arrays as such.
         *
         * @param $class the class to map the object into
         */
        public static function decode(string $json_obj, ?string $class = null): mixed {
            return null;
        }

        /**
         * Encode an object to JSON. Will add a flag to mark associative arrays so `decode` can return them as expected.
         */
        public static function encode(mixed $obj): string {
            return '';
        }
    }
}

namespace Bga\GameFramework\GameResult {
    class Player
    {
        public function __construct(
            public int $id,
            public string $name,
            public string $color = '000000',
            public ?int $score = null,
            public ?int $scoreAux = null,
        ) {}

        /**
         * @return Player
         */
        public static function fromPlayerDb(array $playerDb): self {
            return new self(0, '');
        }

        /**
         * @return Player[]
         */
        public static function fromPlayersDb(array $playersDb): array {
            return [];
        }
    }

    class GameResult
    {

        /**
         * Score all players separately (no-team game).
         *
         * @param Player[] $players The players at this table. Currently, real players only.
         * @param bool $reverseScore Whether negative scores should be rewarded
         * @param bool $reverseScoreAux Whether auxiliary score ordering is reversed
         *
         * @return self
         */
        public static function individualRanking(
            array $players,
            bool $reverseScore = false,
            bool $reverseScoreAux = false,
        ) {
            return new self();
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

namespace Bga\GameFramework\Components\Counters {
    abstract class OutOfRangeCounterException extends \BgaSystemException
    {
    }

    abstract class UnknownPlayerException extends \BgaSystemException
    {
    }
}
