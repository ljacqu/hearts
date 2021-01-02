<?php
/**
 * Contains the data of a player in the game.
 */
class Player implements IPlayer {

  /** @var CardContainer The cards of this player. */
  private $cards;

  /** @var boolean Keeps track of whether the queen of spades has been played in the current hand. */
  private $queenOfSpadesPlayed;

  function startHand() {
    $twoOfClubs = Card::CLUBS . 2;
    $this->cards->removeCard($twoOfClubs);
    return $twoOfClubs;
  }

  function startRound($heartsPlayed) {
    $card = $this->selectCardForRoundStart($heartsPlayed);
    $this->cards->removeCard($card);
    return $card;
  }

  function playInRound($suit, array $playedCards) {
    if ($this->cards->hasCardForSuit($suit)) {
      $card = $this->selectBestSuitCard($playedCards, $suit);
    } else {
      $card = $this->getWorstCard();
    }
    $this->cards->removeCard($card);
    return $card;
  }

  function processRound($suit, array $playedCards) {
    if (!$this->queenOfSpadesPlayed) {
      $this->queenOfSpadesPlayed = in_array(Card::SPADES . Card::QUEEN, $playedCards);
    }
  }

  function processCardsForNewHand(array $cards) {
    $this->cards = new CardContainer($cards);
    $this->queenOfSpadesPlayed = false;
  }

  /**
   * Finds the best same-suit card to play in a round and removes it from the
   * player's card list. Helper method of playCard().
   *
   * @param string[] $playedCards the played cards
   * @param int $suit the suit of the round
   * @return string the card to play
   */
  private function selectBestSuitCard($playedCards, $suit) {
    // Handle special cases with Spades.
    if ($suit === Card::SPADES && !$this->queenOfSpadesPlayed) {
      if ($this->cards->hasCard(Card::SPADES . Card::QUEEN)
          && (in_array(Card::SPADES . Card::KING, $playedCards)
              || in_array(Card::SPADES . Card::ACE,  $playedCards))) {
        return Card::SPADES . Card::QUEEN;
      }
      else if (count($playedCards) === Game::N_OF_PLAYERS - 1
               && $this->cards->getMaxCardForSuit(Card::SPADES) >= Card::KING
               && !in_array(Card::SPADES . Card::QUEEN, $playedCards)) {
        return Card::SPADES . $this->cards->getMaxCardForSuit(Card::SPADES);
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
    foreach ($this->cards->getCards()[$suit] as $card) {
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
        return $suit . $this->cards->getMaxCardForSuit($suit);
      } else {
        // Let's hope someone else will have a bigger card
        return $suit . $this->cards->getMinCardForSuit($suit);
      }
    }
  }

  private function getWorstCard() {
    if (!$this->queenOfSpadesPlayed && $this->cards->hasCardForSuit(Card::SPADES)) {
      $card = false;
      if ($this->cards->hasCard(Card::SPADES . Card::QUEEN)) {
        $card = Card::SPADES . Card::QUEEN;
      } else if ($this->cards->hasCard(Card::SPADES . Card::ACE)) {
        $card = Card::SPADES . Card::ACE;
      } else if ($this->cards->hasCard(Card::SPADES . Card::KING)) {
        $card = Card::SPADES . Card::KING;
      }
      if ($card) {
        return $card;
      }
    }

    $max = 0;
    $maxSuit = 0;
    foreach ($this->cards->getCards() as $suit => $cardsOfSuit) {
      $value = end($cardsOfSuit);
      if ($value > $max) {
        $max = $value;
        $maxSuit = $suit;
      }
    }
    return $maxSuit . $max;
  }

  /**
   * Chooses a card to start a new round.
   *
   * @param boolean $heartsPlayed whether Hearts have already been played
   * @return string card ID the player wants to use
   */
  private function selectCardForRoundStart($heartsPlayed) {
    $minCard = Card::ACE + 1;
    $minCardSuit = -1;

    foreach ($this->cards->getCards() as $suit => $cardsOfSuit) {
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

    if ($this->cards->hasCardForSuit(Card::HEARTS)) {
      return Card::HEARTS . $this->cards->getMinCardForSuit(Card::HEARTS);
    } else {
      var_dump($this->cards);
      throw new Exception('Error in startRound; did not expect empty card list');
    }
  }
}
