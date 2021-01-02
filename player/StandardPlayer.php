<?php
/**
 * Default implementation of a computer player.
 */
class StandardPlayer implements Player {

  /** @var boolean Keeps track of whether the queen of spades has been played in the current hand. */
  private $queenOfSpadesPlayed;

  function startHand($playerCards) {
    return Card::CLUBS . 2;
  }

  function startRound($playerCards, $heartsPlayed) {
    return $this->selectCardForRoundStart($playerCards, $heartsPlayed);
  }

  function playInRound($playerCards, $suit, array $playedCards) {
    if ($playerCards->hasCardForSuit($suit)) {
      return $this->selectBestSuitCard($playerCards, $suit, $playedCards);
    } else {
      return $this->getWorstCard($playerCards);
    }
  }

  function processRound($suit, array $playedCards) {
    if (!$this->queenOfSpadesPlayed) {
      $this->queenOfSpadesPlayed = in_array(Card::SPADES . Card::QUEEN, $playedCards);
    }
  }

  function processCardsForNewHand(array $cards) {
    $this->queenOfSpadesPlayed = false;
  }

  /**
   * Finds the best same-suit card to play in a round and removes it from the
   * player's card list. Helper method of playCard().
   *
   * @param CardContainer $playerCards this player's cards
   * @param int $suit the suit of the round
   * @param string[] $cardsInRound the played cards
   * @return string the card to play
   */
  private function selectBestSuitCard($playerCards, $suit, $cardsInRound) {
    // Handle special cases with Spades.
    if ($suit === Card::SPADES && !$this->queenOfSpadesPlayed) {
      if ($playerCards->hasCard(Card::SPADES . Card::QUEEN)
          && (in_array(Card::SPADES . Card::KING, $cardsInRound)
              || in_array(Card::SPADES . Card::ACE,  $cardsInRound))) {
        return Card::SPADES . Card::QUEEN;
      }
      else if (count($cardsInRound) === Game::N_OF_PLAYERS - 1
               && $playerCards->getMaxCardForSuit(Card::SPADES) >= Card::KING
               && !in_array(Card::SPADES . Card::QUEEN, $cardsInRound)) {
        return Card::SPADES . $playerCards->getMaxCardForSuit(Card::SPADES);
      }
    }

    // Find the biggest card of the current suit
    $biggestPlayed = 0;
    foreach ($cardsInRound as $card) {
      if (Card::getCardSuit($card) === $suit && Card::getCardRank($card) > $biggestPlayed) {
        $biggestPlayed = Card::getCardRank($card);
      }
    }

    $biggestPossible = 0;
    foreach ($playerCards->getCards()[$suit] as $card) {
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
      if (count($cardsInRound) === Game::N_OF_PLAYERS - 1) {
        // We're going to take the cards, so let's get rid of the biggest
        return $suit . $playerCards->getMaxCardForSuit($suit);
      } else {
        // Let's hope someone else will have a bigger card
        return $suit . $playerCards->getMinCardForSuit($suit);
      }
    }
  }

  /**
   * Returns the player's worst card. Used when the player does not have any card for the suit of the current round.
   *
   * @param CardContainer $playerCards this player's cards
   * @return string the code of the chosen card to play
   */
  private function getWorstCard($playerCards) {
    if (!$this->queenOfSpadesPlayed && $playerCards->hasCardForSuit(Card::SPADES)) {
      $card = false;
      if ($playerCards->hasCard(Card::SPADES . Card::QUEEN)) {
        $card = Card::SPADES . Card::QUEEN;
      } else if ($playerCards->hasCard(Card::SPADES . Card::ACE)) {
        $card = Card::SPADES . Card::ACE;
      } else if ($playerCards->hasCard(Card::SPADES . Card::KING)) {
        $card = Card::SPADES . Card::KING;
      }
      if ($card) {
        return $card;
      }
    }

    $max = 0;
    $maxSuit = 0;
    foreach ($playerCards->getCards() as $suit => $cardsOfSuit) {
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
   * @param CardContainer $playerCards this player's cards
   * @param boolean $heartsPlayed whether Hearts have already been played
   * @return string card ID the player wants to use
   */
  private function selectCardForRoundStart($playerCards, $heartsPlayed) {
    $minCard = Card::ACE + 1;
    $minCardSuit = -1;

    foreach ($playerCards->getCards() as $suit => $cardsOfSuit) {
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

    if ($playerCards->hasCardForSuit(Card::HEARTS)) {
      return Card::HEARTS . $playerCards->getMinCardForSuit(Card::HEARTS);
    } else {
      var_dump($playerCards);
      throw new Exception('Error in startRound; did not expect empty card list');
    }
  }
}
