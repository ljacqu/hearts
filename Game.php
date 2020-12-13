<?php

/**
 * Represents a game instance of Hearts
 * @author ljacqu
 */
class Game {
  // We assume that the following numbers
  // represent the suits. Do not change.
  const CLUBS    = 0;
  const DIAMONDS = 1;
  const SPADES   = 2;
  const HEARTS   = 3;

  // Internal card numbers. Do not change.
  const JACK  = 11;
  const QUEEN = 12;
  const KING  = 13;
  const ACE   = 14;

  // Game states. Do not change.
  const HAND_START      = 0;
  const AWAITING_CLUBS  = 1;
  const AWAITING_HUMAN  = 2;
  const ROUND_END       = 3;
  const GAME_END        = 5;

  // Card move codes. Do not change.
  const MOVE_OK        = 10;
  const MOVE_BAD_SUIT  = 11;
  const MOVE_NO_HEARTS = 12;
  const MOVE_BAD_CARD  = 13;

  /** The number of players the game has */
  const N_OF_PLAYERS = 4;

  /** The player ID of the human */
  const HUMAN_ID = 0;

  /** Number of the current hand */
  private $handNumber;

  /** Player[] array containing four players */
  private $players;

  /** int[][] with hand and player ID as keys and subkeys */
  private $points;

  /** Boolean indicating whether Hearts have been played or not */
  private $heartsPlayed;

  /** int attribute to save current state (i.e. what should happen next) */
  private $state;

  /** int[] Cards of the current round */
  private $currentRoundCards;

  private $currentRoundSuit;

  private $currentRoundStarter;

  /** int[] Points in the current hand */
  private $currentHandPoints;

  function __construct() {
    $this->handNumber = 0;
    $this->points  = array();
    $this->players = array();
    for ($i = 0; $i < self::N_OF_PLAYERS; ++$i) {
      $this->players[] = new Player;
    }
    $this->state = self::HAND_START;
  }

  /**
   * Return the ID of the player who possesses the two of clubs.
   * @return int Player ID of owner of two of clubs card.
   */
  function findTwoOfClubsOwner() {
    for ($i = 0; $i < self::N_OF_PLAYERS; ++$i) {
      if ($this->players[$i]->hasCard(self::CLUBS . '2')) {
        return $i;
      }
    }
  }

  /**
   *
   * @param string $card
   * @return Game code to signal the success or the precise error of the move
   *  the human player wants to make.
   */
  function processHumanMove($card) {
    if (!is_scalar($card) || strlen($card) < 2
        || !$this->players[self::HUMAN_ID]->hasCard($card))
    {
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
    $suit = substr($card, 0, 1);

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
      if ($suit == self::HEARTS && !$this->heartsPlayed) {
        // Accept hearts if human has no other cards
        return (!$this->players[self::HUMAN_ID]->hasCardsForSuit(self::CLUBS)
           && !$this->players[self::HUMAN_ID]->hasCardsForSuit(self::DIAMONDS)
           && !$this->players[self::HUMAN_ID]->hasCardsForSuit(self::SPADES))
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
      $this->currentRoundSuit = substr($card, 0, 1);
    }
  }

  /**
   * Distributes cards to players by giving each player a random card
   * until stack is empty.
   */
  function distributeCards() {
    for ($i = 0; $i < self::N_OF_PLAYERS; ++$i) {
      $this->players[$i]->emptyCardList();
    }
    $deck = $this->initializeDeck();
    while(count($deck) >= self::N_OF_PLAYERS) {
      shuffle($deck); // TODO: Sufficient to shuffle once outside of loop?
      for ($i = 0; $i < self::N_OF_PLAYERS; ++$i) {
        // TODO: Better way to do this?
        $this->players[$i]->addCard( array_shift($deck) );
      }
    }
    for ($i = 0; $i < self::N_OF_PLAYERS; ++$i) {
      $this->players[$i]->sortCards();
    }
    $this->setupNewHand();
  }

  function playTillHuman() {
    $this->currentRoundCards = array();
    $playerId = $this->currentRoundStarter;
    if ($this->state == self::HAND_START && $playerId != self::HUMAN_ID) {
      $this->players[$playerId]->removeCard(self::CLUBS . 2);
      $this->currentRoundCards[$playerId] = self::CLUBS . 2;
      $playerId = $this->nextPlayer($playerId);
    }

    while ($playerId != self::HUMAN_ID) {
      $this->currentRoundCards[$playerId] = $this->players[$playerId]->playCard(
          $this->currentRoundSuit,
          $this->currentRoundCards,
          $this->heartsPlayed
      );
      if (count($this->currentRoundCards) === 1) {
        $this->currentRoundSuit = substr(reset($this->currentRoundCards), 0, 1);
      }
      $playerId = $this->nextPlayer($playerId);
    }
    $this->state = self::AWAITING_HUMAN;
  }

  function playTillEnd() {
    $playerId = $this->nextPlayer(self::HUMAN_ID);
    while ($playerId != $this->currentRoundStarter) {
      if (isset($this->currentRoundCards[$playerId])) {
        var_dump($this->currentRoundCards);
        throw new Exception("Found card for $playerId");
      }
      $this->currentRoundCards[$playerId] = $this->players[$playerId]->playCard(
          $this->currentRoundSuit,
          $this->currentRoundCards,
          $this->heartsPlayed
      );
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
   * @return The ID of the player who has to start the next round; null if
   *  the hand has been completed.
   */
  private function prepareNextRound() {
    $nextStarter = -1;
    $maxCardInSuit = 0;
    $totalPoints = 0;
    foreach ($this->currentRoundCards as $playerId => $card) {
      $suit   = substr($card, 0, 1);
      $number = substr($card, 1);
      if ($suit == $this->currentRoundSuit && $number > $maxCardInSuit) {
        $nextStarter = $playerId;
        $maxCardInSuit = $number;
      }
      $totalPoints += $this->pointsFromCard($suit, $number);
    }
    $this->currentHandPoints[$nextStarter] += $totalPoints;
    $this->currentRoundStarter = $nextStarter;

    $this->updateHeartsPlayed();
    if ($this->players[0]->hasEmptyCardList()) {
      $this->state = $this->endCurrentHand();
      return null;
    } else {
      $this->state = self::ROUND_END;
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

    // Sum points for every player; if one player has >= 100 points,
    // signal that the game has ended.
    for ($i = 0; $i < self::N_OF_PLAYERS; ++$i) {
      $totalPoints = array_sum(array_column($this->points, $i));
      if ($totalPoints >= 100) return self::GAME_END;
    }
    return self::HAND_START;
  }

  function startNewHand() {
    $this->distributeCards();
    ++$this->handNumber;
    $this->currentRoundSuit = self::CLUBS;
    $this->heartsPlayed = false;
    $this->currentRoundCards = array();
    $this->currentRoundStarter = $this->findTwoOfClubsOwner();
    if ($this->currentRoundStarter == self::HUMAN_ID) {
      $this->state = self::AWAITING_CLUBS;
    }
  }

  private function pointsFromCard($suit, $number) {
    if ($suit == self::HEARTS) {
      return 1;
    } else if ($number == self::QUEEN && $suit == self::SPADES) {
      return 13;
    } else {
      return 0;
    }
  }

  /**
   * Returns whether or not there is any card of the Hearts suit.
   * @param array $playedCards
   * @return boolean
   */
  private function updateHeartsPlayed() {
    if ($this->heartsPlayed) {
      return;
    }
    foreach ($this->currentRoundCards as $card) {
      if (substr($card, 0, 1) == self::HEARTS) {
        $this->heartsPlayed = true;
        return;
      }
    }
  }

  /**
   * Called at the end of distributeCards()
   */
  private function setupNewHand() {
    $this->state = self::HAND_START;
    $this->currentRoundStarter = $this->findTwoOfClubsOwner();
    $this->currentRoundSuit    = self::CLUBS;
    $this->currentHandPoints  = array();
    for ($i = 0; $i < self::N_OF_PLAYERS; ++$i) {
      $this->currentHandPoints[] = 0;
    }
  }

  /**
   * Initializes a full deck of cards
   * @return string[] Array of all cards, where the first character is the
   *  number for the suit, and the remaining 1-2 characters are the number of
   *  the card.
   */
  private function initializeDeck() {
    $deck = array();
    for ($i = self::CLUBS; $i <= self::HEARTS; ++$i) {
      for ($j = 2; $j <= self::ACE; ++$j) {
        $deck[] = (string) $i.$j;
      }
    }
    return $deck;
  }

  function getHumanCards() {
    return $this->players[self::HUMAN_ID]->getCards();
  }
  function getState() {
    return $this->state;
  }
  /*function setState($state) {
    $this->state = $state;
  }*/
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
