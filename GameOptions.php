<?php

/**
 * Holds various configurations used when a new game is created.
 */
class GameOptions {

  public $name;
  public $usePredefinedHands;

  private $playerConfigs;

  const HUMAN = 0;
  const STANDARD = 1;
  const ADVANCED = 2;
  const CARD_COUNTING = 3;
  const COMBINER = 4;

  private function __construct($name, $usePredefinedHands, $playerConfigs) {
    $this->name = $name;
    $this->usePredefinedHands = $usePredefinedHands;
    $this->playerConfigs = $playerConfigs;
  }

  /**
   * Creates an options instance for a regular game play with a human player.
   *
   * @return GameOptions new options instance
   */
  static function createDefaultOptions() {
    return new GameOptions('', false,
      [self::HUMAN, self::COMBINER, self::COMBINER, self::COMBINER]);
  }

  /**
   * Creates an options instance for a game in evaluation mode.
   *
   * @param string $name the game name
   * @param int[] $playerTypes players by player ID to use (see constants)
   * @return GameOptions instance with the provided values
   */
  static function createForPlayerEvaluation($name, $playerTypes) {
    return new GameOptions($name, true, $playerTypes);
  }

  /**
   * Creates the player that will take part in the game.
   *
   * @param int $playerId 0-based index of the player
   * @return Player the player to use
   */
  function createPlayer($playerId) {
    if (!isset($this->playerConfigs[$playerId])) {
      throw new Exception('Unsupported player ID: ' . $playerId);
    }
    switch ($this->playerConfigs[$playerId]) {
      case self::HUMAN:         return new HumanPlayer();
      case self::STANDARD:      return new StandardPlayer();
      case self::ADVANCED:      return new AdvancedPlayer($playerId);
      case self::CARD_COUNTING: return new CardCountingPlayer($playerId);
      case self::COMBINER:      return new PlayerCombiner($playerId);
      default:
        throw new Exception("Unknown player option {$this->playerConfigs[$playerId]}");
    }
  }
}