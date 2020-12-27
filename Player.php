<?php
/**
 * Contains the data of a player in the game.
 */
class Player {

  /** int[][] The cards the player has */
  private $cards;

  function __construct() {
    $this->emptyCardList();
  }

  function getCards() {
    return $this->cards;
  }

  function hasEmptyCardList() {
    foreach ($this->cards as $list) {
      if (!empty($list)) return false;
    }
    return true;
  }

  /**
   * Returns the card to play in the round.
   *
   * @param int $suit The suit of the current round (constant from Game)
   * @param int[] $playedCards array of played cards, where the key corresponds to the
   *  player ID and the entry is the suit number and the value, e.g.
   *  $played_cards[1] = 211 means player 1 played J of spades.
   * @param boolean $heartsPlayed whether hearts can be used to start a round
   * @return string the card the player wants to play
   */
  function playCard($suit, array $playedCards, $heartsPlayed) {
    if (count($playedCards) == 0) {
      $card = $this->startRound($heartsPlayed);
    } else if (!empty($this->cards[$suit])) {
      $card = $this->selectBestSuitCard($playedCards, $suit);
    } else {
      $card = $this->getWorstCard();
    }
    $this->removeCard($card);
    return $card;
  }

  /**
   * Takes the given cards for the start of a new round. Cards are expected to be valid and unique.
   *
   * @param $cards string[] the cards belonging to the user for a new round
   */
  function setCardsForNewRound($cards) {
    $this->emptyCardList();
    foreach ($cards as $card) {
      $suit   = Card::getCardSuit($card);
      $number = Card::getCardRank($card);
      if (!isset($this->cards[$suit])) {
        throw new Exception('Illegal suit ' . htmlspecialchars($suit) . '!');
      }
      $this->cards[$suit][] = $number;
    }
    $this->sortCards();
  }

  /**
   * Returns if the player has the given card.
   *
   * @param $card string card to check
   * @return bool true if player has the specified card, false otherwise
   */
  function hasCard($card) {
    $suit   = Card::getCardSuit($card);
    $number = Card::getCardRank($card);
    return isset($this->cards[$suit]) && in_array($number, $this->cards[$suit]);
  }

  function hasCardsForSuit($suit) {
    return isset($this->cards[$suit]) && count($this->cards[$suit]) > 0;
  }

  /**
   * Resets the card list (empties all cards from list)
   */
  private function emptyCardList() {
    $this->cards = [
      Card::CLUBS    => [],
      Card::DIAMONDS => [],
      Card::SPADES   => [],
      Card::HEARTS   => []
    ];
  }

  /**
   * Sorts the player's card list.
   */
  private function sortCards() {
    foreach ($this->cards as &$cardsInSuit) {
      natsort($cardsInSuit);
    }
  }

  /**
   * Finds the best same-suit card to play in a round and removes it from the
   * player's card list. Helper method of playCard().
   *
   * @param $playedCards string[] the played cards
   * @param $suit int the suit of the round
   * @return string the card to play
   */
  private function selectBestSuitCard($playedCards, $suit) {
    // Handle special cases with Spades.
    if ($suit == Card::SPADES) {
      if (in_array(Card::QUEEN, $this->cards[Card::SPADES])
          && (in_array(Card::SPADES . Card::KING, $playedCards)
              || in_array(Card::SPADES . Card::ACE,  $playedCards))) {
        return Card::SPADES . Card::QUEEN;
      }
      else if (count($playedCards) === Game::N_OF_PLAYERS - 1
               && end($this->cards[Card::SPADES]) >= Card::KING
               && !in_array(Card::SPADES . Card::QUEEN, $playedCards)) {
        return Card::SPADES . end($this->cards[Card::SPADES]);
      }
    }

    // Find the biggest card of the current suit
    $biggestPlayed = 0;
    foreach ($playedCards as $card) {
      if (Card::getCardSuit($card) === $suit && Card::getCardRank($card) > $biggestPlayed) {
        $biggestPlayed = Card::getCardRank($card);
      }
    }

    $biggestPossible = 0;
    foreach ($this->cards[$suit] as $card) {
      if ($card < $biggestPlayed) {
        $biggestPossible = $card;
      } else {
        break; // cards are sorted, once $card > $biggestPlayed, we're done
      }
    }

    if ($biggestPossible != 0) {
      return $suit . $biggestPossible;
    } else {
      // No card is small enough...
      if (count($playedCards) === Game::N_OF_PLAYERS - 1) {
        // We're going to take the cards, so let's get rid of the biggest
        return $suit . end($this->cards[$suit]);
      } else {
        // Let's hope someone else will have a bigger card
        return $suit . reset($this->cards[$suit]);
      }
    }
  }

  private function getWorstCard() {
    // TODO: Check that queen of spades was not played
    if (!empty($this->cards[Card::SPADES])) {
      $card = false;
      if (in_array(Card::QUEEN, $this->cards[Card::SPADES])) {
        $card = Card::SPADES . Card::QUEEN;
      } else if (in_array(Card::ACE, $this->cards[Card::SPADES])) {
        $card = Card::SPADES . Card::ACE;
      } else if (in_array(Card::KING, $this->cards[Card::SPADES])) {
        $card = Card::SPADES . Card::KING;
      } else if (end($this->cards[Card::SPADES]) > 7) {
        $card = Card::SPADES . end($this->cards[Card::SPADES]); // TODO: What does this do?
      }
      if ($card) {
        return $card;
      }
    }

    $max = 0;
    $maxSuit = 0;
    foreach ($this->cards as $suit => $cardsOfSuit) {
      $value = end($cardsOfSuit);
      if ($value > $max) {
        $max = $value;
        $maxSuit = $suit;
      }
    }
    return $maxSuit . $max;
  }

  /**
   * Choose card to start a new round
   * @param bool $heartsPlayed Whether Hearts have already been played
   * @param bool $spadesQueenPlayed Whether the Queen of Spades has been
   *  played yet.
   * @return string Card ID the player wants to use
   */
  private function startRound($heartsPlayed) {
    $minCard = Card::ACE + 1;
    $minCardSuit = -1;

    foreach ($this->cards as $suit => $cardsOfSuit) {
      if (($suit === Card::HEARTS && !$heartsPlayed) || empty($cardsOfSuit)) {
        continue;
      }
      $minSuitValue = reset($cardsOfSuit);
      if ($minSuitValue < $minCard) {
        $minCard = $minSuitValue;
        $minCardSuit = $suit;
      }
    }

    if ($minCardSuit != -1) {
      return $minCardSuit . $minCard;
    }

    if (count($this->cards[Card::HEARTS]) > 1) {
      $minCard = reset($this->cards[Card::HEARTS]);
      return Card::HEARTS . $minCard;
    } else {
      var_dump($this->cards);
      throw new Exception('Error in startRound; did not expect empty card list');
    }
  }

  /**
   * Removes the given card from the player, if available.
   *
   * @param string $card the composed card code (suit + rank)
   * @return boolean true if card was removed, false if the player did not have it
   */
  function removeCard($card) {
    $suit  = Card::getCardSuit($card);
    $value = Card::getCardRank($card);

    foreach ($this->cards[$suit] as $key => $card) {
      if ($card == $value) {
        unset($this->cards[$suit][$key]);
        return true;
      }
    }
    return false;
  }
}
