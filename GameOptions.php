<?php

class GameOptions {

  public $name;
  public $usePredefinedHands;

  private $playerConfigs;

  const HUMAN = 0;
  const STANDARD = 1;
  const ADVANCED = 2;
  const CARD_COUNTING = 3;

  private function __construct($name, $usePredefinedHands, $playerConfigs) {
    $this->name = $name;
    $this->usePredefinedHands = $usePredefinedHands;
    $this->playerConfigs = $playerConfigs;
  }

  static function createDefaultOptions() {
    return new GameOptions('', false,
      [self::HUMAN, self::CARD_COUNTING, self::CARD_COUNTING, self::CARD_COUNTING]);
  }

  /**
   * @param string $name
   * @param int[] $playerTypes
   * @return GameOptions
   */
  static function createForPlayerEvaluation($name, $playerTypes) {
    return new GameOptions($name, true, $playerTypes);
  }

  /**
   * Creates the player that will take part in the game.
   *
   * @param int $playerId 0-based index of the player
   * @return Player
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
      default:
        throw new Exception("Unknown player option {$this->playerConfigs[$playerId]}");
    }
  }

}