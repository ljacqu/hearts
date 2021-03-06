<?php

/**
 * Game instance of Hearts.
 */
class Game {

  // Card move codes. Do not change.
  const MOVE_OK        = 10;
  const MOVE_BAD_SUIT  = 11;
  const MOVE_NO_HEARTS = 12;
  const MOVE_BAD_CARD  = 13;

  /** @var int The number of players the game has. */
  const N_OF_PLAYERS = 4;

  /** @var int The player ID of the human. */
  const HUMAN_ID = 0;

  /** @var int Number of the current hand. */
  private $handNumber;

  /** @var Player[] Contains the players of the game. */
  private $players;

  /** @var int[][] with hand and player ID as keys and subkeys */
  private $points;

  /** @var boolean Indicates whether Hearts have been played or not. */
  private $heartsPlayed;

  /** @var int Constant of {@link GameState} representing the current state (i.e. what should happen next). */
  private $state;

  /** @var string[] Cards of the current round. */
  private $currentRoundCards;

  private $currentRoundSuit;

  private $currentRoundStarter;

  /** @var int[] Points in the current hand by player. */
  private $currentHandPoints;

  function __construct() {
    $this->handNumber = 0;
    $this->points  = array();
    $this->players = array();
    for ($i = 0; $i < self::N_OF_PLAYERS; ++$i) {
      $this->players[] = new Player();
    }
    $this->state = GameState::HAND_START;
  }

  /**
   * Return the ID of the player who possesses the two of clubs.
   *
   * @return int player ID of owner of two of clubs card
   */
  private function findTwoOfClubsOwner() {
    for ($i = 0; $i < self::N_OF_PLAYERS; ++$i) {
      if ($this->players[$i]->hasCard(Card::CLUBS . '2')) {
        return $i;
      }
    }
    var_dump($this->players);
    throw new Exception('No player has two of clubs!');
  }

  /**
   *
   * @param string $card
   * @return int Game code to signal the success or the precise error of the move the human player wants to make
   */
  function processHumanMove($card) {
    if (!is_scalar($card) || strlen($card) < 2 || !$this->players[self::HUMAN_ID]->hasCard($card)) {
      return self::MOVE_BAD_CARD;
    }

    $checkSuit = $this->processHumanSuit($card);
    if ($checkSuit === self::MOVE_OK) {
      $this->registerHumanMove($card);
      return self::MOVE_OK;
    } else {
      return $checkSuit;
    }
  }

  private function processHumanSuit($card) {
    $suit = Card::getCardSuit($card);

    if (count($this->currentRoundCards) > 0) {
      if ($suit == $this->currentRoundSuit
        || !$this->players[self::HUMAN_ID]->hasCardsForSuit($this->currentRoundSuit))
      {
        return self::MOVE_OK;
      } else {
        return self::MOVE_BAD_SUIT;
      }
    } else {
      // Human starts the hand
      if ($suit == Card::HEARTS && !$this->heartsPlayed) {
        // Accept hearts if human has no other cards
        return (!$this->players[self::HUMAN_ID]->hasCardsForSuit(Card::CLUBS)
           && !$this->players[self::HUMAN_ID]->hasCardsForSuit(Card::DIAMONDS)
           && !$this->players[self::HUMAN_ID]->hasCardsForSuit(Card::SPADES))
        ? self::MOVE_OK
        : self::MOVE_NO_HEARTS;
      }
      return self::MOVE_OK;
    }
  }

  private function registerHumanMove($card) {
    $this->currentRoundCards[self::HUMAN_ID] = $card;
    $this->players[self::HUMAN_ID]->removeCard($card);
    if (count($this->currentRoundCards) === 1) {
      $this->currentRoundSuit = Card::getCardSuit($card);
    }
  }

  /**
   * Distributes the cards to players randomly.
   */
  function distributeCards() {
    $deck = $this->initializeDeck();
    shuffle($deck);

    $cardsPerPlayer = floor(count($deck) / self::N_OF_PLAYERS);
    foreach ($this->players as $index => $player) {
      $newCards = array_slice($deck, $index * $cardsPerPlayer, $cardsPerPlayer);
      $player->setCardsForNewRound($newCards);
    }
    $this->setupNewHand();
  }

  function playTillHuman() {
    $this->currentRoundCards = array();
    $playerId = $this->currentRoundStarter;
    if ($this->state == GameState::HAND_START && $playerId != self::HUMAN_ID) {
      $this->players[$playerId]->removeCard(Card::CLUBS . 2);
      $this->currentRoundCards[$playerId] = Card::CLUBS . 2;
      $playerId = $this->nextPlayer($playerId);
    }

    while ($playerId != self::HUMAN_ID) {
      $this->currentRoundCards[$playerId] = $this->players[$playerId]->playCard(
        $this->currentRoundSuit, $this->currentRoundCards, $this->heartsPlayed);
      if (count($this->currentRoundCards) === 1) {
        $this->currentRoundSuit = Card::getCardSuit(reset($this->currentRoundCards));
      }
      $playerId = $this->nextPlayer($playerId);
    }
    $this->state = GameState::AWAITING_HUMAN;
  }

  function playTillEnd() {
    $playerId = $this->nextPlayer(self::HUMAN_ID);
    while ($playerId != $this->currentRoundStarter) {
      if (isset($this->currentRoundCards[$playerId])) {
        var_dump($this->currentRoundCards);
        throw new Exception("Found card for $playerId");
      }
      $this->currentRoundCards[$playerId] = $this->players[$playerId]->playCard(
        $this->currentRoundSuit, $this->currentRoundCards, $this->heartsPlayed);
      $playerId = $this->nextPlayer($playerId);
    }

    if (count($this->currentRoundCards) != self::N_OF_PLAYERS) {
      var_dump($this->currentRoundCards);
      throw new Exception("Registered cards is not equals to the number of players!");
    }
    $nextStarter = $this->prepareNextRound();
    return $nextStarter;
  }

  private function nextPlayer($i) {
    return (++$i < self::N_OF_PLAYERS) ? $i : 0;
  }

  /**
   * Called at the end of a round: prepare for the next round, i.e. clear the
   * played cards, count points, determine the next player, set the game state.
   * Note: Do not clear $this->currentRoundCards here but empty it at the start
   *  of playTillHuman. This way, the Displayer class can still access and
   *  display the cards of the round to the human.
   *
   * @return int the ID of the player who has to start the next round; null if
   *  the hand has been completed.
   */
  private function prepareNextRound() {
    $nextStarter = -1;
    $maxCardInSuit = 0;
    $totalPoints = 0;
    foreach ($this->currentRoundCards as $playerId => $card) {
      $suit   = Card::getCardSuit($card);
      $number = Card::getCardRank($card);
      if ($suit === $this->currentRoundSuit && $number > $maxCardInSuit) {
        $nextStarter = $playerId;
        $maxCardInSuit = $number;
      }
      $totalPoints += Card::getPoints($suit, $number);
    }
    $this->currentHandPoints[$nextStarter] += $totalPoints;
    $this->currentRoundStarter = $nextStarter;

    $this->updateHeartsPlayed();
    if ($this->players[0]->hasEmptyCardList()) {
      $this->state = $this->endCurrentHand();
      return null;
    } else {
      $this->state = GameState::ROUND_END;
      return $nextStarter;
    }
  }

  private function endCurrentHand() {
    if (max($this->currentHandPoints) == 26) {
      foreach ($this->currentHandPoints as &$playerPoints) {
        if ($playerPoints == 26) $playerPoints =  0;
        else                     $playerPoints = 26;
      }
    }
    $this->points[] = $this->currentHandPoints;
    $this->currentHandPoints = array_fill(0, self::N_OF_PLAYERS, 0);

    // Sum points for every player; if one player has >= 100 points, signal that the game has ended.
    for ($i = 0; $i < self::N_OF_PLAYERS; ++$i) {
      $totalPoints = array_sum(array_column($this->points, $i));
      if ($totalPoints >= 100) {
        return GameState::GAME_END;
      }
    }
    return GameState::HAND_START;
  }

  function startNewHand() {
    $this->distributeCards();
    ++$this->handNumber;
    $this->currentRoundSuit = Card::CLUBS;
    $this->heartsPlayed = false;
    $this->currentRoundCards = array();
    $this->currentRoundStarter = $this->findTwoOfClubsOwner();
    if ($this->currentRoundStarter == self::HUMAN_ID) {
      $this->state = GameState::AWAITING_CLUBS;
    }
  }

  /**
   * Updates the hearts played flag if necessary. Once a hearts card has been played, players may start rounds
   * with a hearts card.
   */
  private function updateHeartsPlayed() {
    if (!$this->heartsPlayed) {
      foreach ($this->currentRoundCards as $card) {
        if (Card::getCardSuit($card) === Card::HEARTS) {
          $this->heartsPlayed = true;
          return;
        }
      }
    }
  }

  /**
   * Called at the end of distributeCards()
   */
  private function setupNewHand() {
    $this->state = GameState::HAND_START;
    $this->currentRoundStarter = $this->findTwoOfClubsOwner();
    $this->currentRoundSuit    = Card::CLUBS;
    $this->currentHandPoints  = array();
    for ($i = 0; $i < self::N_OF_PLAYERS; ++$i) {
      $this->currentHandPoints[] = 0;
    }
  }

  /**
   * Initializes a full deck of cards.
   *
   * @return string[] all cards, where the first character is the number for the suit, and the
   * remaining 1-2 characters are the rank of the card
   */
  private function initializeDeck() {
    return array_merge(
      $this->createCardsForSuit(Card::CLUBS),
      $this->createCardsForSuit(Card::DIAMONDS),
      $this->createCardsForSuit(Card::SPADES),
      $this->createCardsForSuit(Card::HEARTS));
  }

  private function createCardsForSuit($suit) {
    return array_map(function ($rank) use ($suit) {
      return $suit . $rank;
    }, range(2, Card::ACE));
  }

  function getHumanCards() {
    return $this->players[self::HUMAN_ID]->getCards();
  }
  function getState() {
    return $this->state;
  }
  function getCurrentRoundCards() {
    return $this->currentRoundCards;
  }
  function getCurrentRoundStarter() {
    return $this->currentRoundStarter;
  }
  function getPoints() {
    return $this->points;
  }
  function getHandNumber() {
    return $this->handNumber;
  }
  /**
   * Debug: returns the Player objects of the current game.
   * @return Player[] The players of the game
   */
  function getPlayers() {
    return $this->players;
  }
}
