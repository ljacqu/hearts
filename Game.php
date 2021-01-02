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
  const MOVE_EXPECTING_TWO_OF_CLUBS = 14;

  /** @var int The number of players the game has. */
  const N_OF_PLAYERS = 4;

  /** @var int The player ID of the human. */
  const HUMAN_ID = 0;

  // ------ Game state
  /** @var int Number of the current hand. */
  private $handNumber;

  /** @var Player[] Contains the players of the game. */
  private $players;

  /** @var int[][] Points with hand and player ID as keys and subkeys. */
  private $points;

  /** @var int Constant of {@link GameState} representing the current state (i.e. what should happen next). */
  private $state;

  // ------ Hand state
  /** @var boolean Indicates whether Hearts have been played or not. */
  private $heartsPlayed;

  /** @var CardContainer[] Cards of the current hand by player index. */
  private $currentHandCards;

  /** @var int[] Points in the current hand by player. */
  private $currentHandPoints;

  /** @var boolean True when it's the start of the hand and the round starter has two play the two of clubs. */
  private $needTwoOfClubs;

  // ------ Round state
  /** @var string[] Cards of the current round. */
  private $currentRoundCards;

  /** @var int The suit of the round (value of a constant on {@link Card}). */
  private $currentRoundSuit;

  /** @var int The player ID that starts the round. */
  private $currentRoundStarter;


  function __construct() {
    $this->handNumber = 0;
    $this->points  = [];
    $this->players = [];
    for ($i = 0; $i < self::N_OF_PLAYERS; ++$i) {
      $this->players[] = $i === self::HUMAN_ID ? new HumanPlayer() : new StandardPlayer();
    }
    $this->state = GameState::HAND_START;
  }

  /**
   * Returns the ID of the player who possesses the two of clubs.
   *
   * @return int player ID of owner of two of clubs card
   */
  private function findTwoOfClubsOwner() {
    $twoOfClubs = Card::CLUBS . 2;
    foreach ($this->currentHandCards as $index => $playerCardContainer) {
      if ($playerCardContainer->hasCard($twoOfClubs)) {
        return $index;
      }
    }
    var_dump($this->currentHandCards);
    throw new Exception('No player has two of clubs!');
  }

  /**
   * Processes the input card by the human player.
   *
   * @param string $card code of the chosen card
   * @return int Game code to signal the success or the precise error of the move the human player wants to make
   */
  function processHumanMove($card) {
    $checkSuit = $this->validateCardPlay($this->currentHandCards[self::HUMAN_ID], $card);
    if ($checkSuit === self::MOVE_OK) {
      $this->registerHumanMove($card);
      return self::MOVE_OK;
    } else {
      return $checkSuit;
    }
  }

  /**
   * Validates the card chosen by the player. This method does not change any game state except for setting
   * {@link needTwoOfClubs} to false when appropriate.
   *
   * @param CardContainer $playerCards the cards of the player whose chosen card should be validated
   * @param string $chosenCard the code of the chosen card
   * @return int Game constant indicating the result of the card validation
   */
  private function validateCardPlay($playerCards, $chosenCard) {
    if (!$playerCards->hasCard($chosenCard)) {
      return self::MOVE_BAD_CARD;
    }
    if ($this->needTwoOfClubs) {
      if ($chosenCard === Card::CLUBS . 2) {
        $this->needTwoOfClubs = false;
        return self::MOVE_OK;
      }
      return self::MOVE_EXPECTING_TWO_OF_CLUBS;
    }

    $suit = Card::getCardSuit($chosenCard);
    if (!empty($this->currentRoundCards)) {
      return $suit === $this->currentRoundSuit || !$playerCards->hasCardForSuit($this->currentRoundSuit)
        ? self::MOVE_OK
        : self::MOVE_BAD_SUIT;
    }

    // New round is started: need to make sure hearts are allowed to be played
    if ($suit === Card::HEARTS && !$this->heartsPlayed
        && $playerCards->hasCardForSuit(Card::CLUBS, Card::DIAMONDS, Card::SPADES)) {
      return self::MOVE_NO_HEARTS;
    }
    return self::MOVE_OK;
  }

  private function registerHumanMove($card) {
    $this->currentRoundCards[self::HUMAN_ID] = $card;
    $this->currentHandCards[self::HUMAN_ID]->removeCard($card);
    if (count($this->currentRoundCards) === 1) {
      $this->currentRoundSuit = Card::getCardSuit($card);
    }
  }

  /**
   * Distributes the cards to players randomly.
   */
  private function distributeCards() {
    $deck = $this->initializeDeck();
    shuffle($deck);

    $this->currentHandCards = [];
    $cardsPerPlayer = floor(count($deck) / self::N_OF_PLAYERS);
    foreach ($this->players as $index => $player) {
      $newCards = array_slice($deck, $index * $cardsPerPlayer, $cardsPerPlayer);
      $this->currentHandCards[$index] = CardContainer::fromCardCodes($newCards);
      $player->processCardsForNewHand($newCards);
    }
  }

  function playTillHuman() {
    $this->currentRoundCards = array();
    $playerId = $this->currentRoundStarter;

    while ($playerId != self::HUMAN_ID) {
      $this->getAndRegisterCardFromPlayer($playerId);
      $playerId = $this->nextPlayer($playerId);
    }
    $this->state = GameState::AWAITING_HUMAN;
  }

  function playTillEnd() {
    $playerId = $this->nextPlayer(self::HUMAN_ID);
    while ($playerId != $this->currentRoundStarter) {
      $this->getAndRegisterCardFromPlayer($playerId);
      $playerId = $this->nextPlayer($playerId);
    }

    if (count($this->currentRoundCards) !== self::N_OF_PLAYERS) {
      var_dump($this->currentRoundCards);
      throw new Exception("Registered cards is not equals to the number of players!");
    }
    foreach ($this->players as $player) {
      $player->processRound($this->currentRoundSuit, $this->currentRoundCards);
    }

    $nextStarter = $this->prepareNextRound();
    return $nextStarter;
  }

  /**
   * Calls the appropriate method on the player to get his card. Validates the card choice and
   * adds it as his card to {@link currentHandCards}. Sets also the current hand suit if
   * it is the start of the hand.
   *
   * @param int $playerId ID of the player that should play
   */
  private function getAndRegisterCardFromPlayer($playerId) {
    if (isset($this->currentRoundCards[$playerId])) {
      var_dump($this->currentRoundCards);
      throw new Exception("Unexpectedly found card for $playerId");
    }

    $player = $this->players[$playerId];
    $playerCardsCopy = $this->currentHandCards[$playerId]->createCopy();
    if (!empty($this->currentRoundCards)) {
      $card = $player->playInRound($playerCardsCopy, $this->currentRoundSuit, $this->currentRoundCards);
    } else if ($this->needTwoOfClubs) {
      $card = $player->startHand($playerCardsCopy);
    } else {
      $card = $player->startRound($playerCardsCopy, $this->heartsPlayed);
    }
    $cardMoveCode = $this->validateCardPlay($this->currentHandCards[$playerId], $card);
    if ($cardMoveCode !== Game::MOVE_OK) {
      throw new Exception(
        "Player $playerId played card '$card' which resulted in validation code $cardMoveCode");
    }

    $this->currentRoundCards[$playerId] = $card;
    $this->currentHandCards[$playerId]->removeCard($card);
    if (count($this->currentRoundCards) === 1) {
      $this->currentRoundSuit = Card::getCardSuit(reset($this->currentRoundCards));
    }
  }

  private function nextPlayer($i) {
    return (++$i < self::N_OF_PLAYERS) ? $i : 0;
  }

  /**
   * Called at the end of a round: prepare for the next round, i.e. count points, determine the next player,
   * set the appropriate game state.
   * <p>
   * Note: Do not clear $this->currentRoundCards here but empty it at the start of {@link playTillHuman()}. This way,
   * the Displayer class can still access and display the cards of the round to the human.
   *
   * @return int the ID of the player who has to start the next round; null if the hand has been completed.
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
    if ($this->currentHandCards[0]->isEmpty()) { // End of hand
      $this->state = $this->endCurrentHand();
      return null;
    } else {
      $this->state = GameState::ROUND_END;
      return $nextStarter;
    }
  }

  private function endCurrentHand() {
    if (max($this->currentHandPoints) === 26) {
      foreach ($this->currentHandPoints as &$playerPoints) {
        $playerPoints = $playerPoints === 26 ? 0 : 26;
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
    $this->state = GameState::HAND_START;
    $this->distributeCards();

    ++$this->handNumber;
    $this->currentHandPoints  = array();
    for ($i = 0; $i < self::N_OF_PLAYERS; ++$i) {
      $this->currentHandPoints[] = 0;
    }

    $this->currentRoundSuit = Card::CLUBS;
    $this->heartsPlayed = false;
    $this->currentRoundCards = array();
    $this->currentRoundStarter = $this->findTwoOfClubsOwner();
    $this->needTwoOfClubs = true;
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
    return $this->currentHandCards[self::HUMAN_ID];
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
  function getNeedTwoOfClubs() {
    return $this->needTwoOfClubs;
  }

  function getDebugValues() {
    return [
      'state' => $this->state,
      'needTwoOfClubs' => $this->needTwoOfClubs ? 'true' : 'false',
      'currentRoundStarter' => $this->currentRoundStarter + 1, // Note: 1-based player index!
      'heartsPlayed' => $this->heartsPlayed ? 'true' : 'false',
      'currentHandPoints' => implode(', ', $this->currentHandPoints)
    ];
  }

  /**
   * @return CardContainer[]
   */
  function getCurrentHandCards() {
    return $this->currentHandCards;
  }
}
