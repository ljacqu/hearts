<?php

class CardCountingPlayer implements Player {

  private $playerId;

  /** @var CardContainer Container with all unknown cards. */
  private $allCards;

  private $playersBySuit;

  /** @var boolean Whether the queen of spades has been played. */
  private $queenOfSpadesPlayed;

  function __construct($playerId) {
    $this->playerId = $playerId;
  }

  function processCardsForNewHand(array $cards) {
    $this->queenOfSpadesPlayed = false;

    $allCards = [];
    foreach (Card::getAllSuits() as $suit) {
      foreach (range(2, Card::ACE) as $rank) {
        if (!in_array($suit . $rank, $cards, true)) {
          $allCards[] = $suit . $rank;
        }
      }
    }
    $this->allCards = CardContainer::fromCardCodes($allCards);

    $allOtherPlayers = range(0, Game::N_OF_PLAYERS - 1);
    unset($allOtherPlayers[$this->playerId]);

    $playersBySuit = [];
    foreach (Card::getAllSuits() as $suit) {
      $playersBySuit[$suit] = $allOtherPlayers;
    }
    $this->playersBySuit = $playersBySuit;
  }

  function startHand($playerCards) {
    return Card::CLUBS . 2;
  }

  function startRound($playerCards, $heartsPlayed) {
    return $this->selectCardForRoundStart($playerCards, $heartsPlayed);
  }

  function playInRound($playerCards, $suit, array $playedCards) {
    if ($playerCards->hasCardForSuit($suit)) {
      return $this->selectSameSuitCard($suit, $playerCards, $playedCards);
    }
    return $this->selectWorstCard($playerCards, $playedCards);
  }

  function processRound($suit, array $playedCards) {
    if (!$this->queenOfSpadesPlayed) {
      $this->queenOfSpadesPlayed = in_array(Card::SPADES . Card::QUEEN, $playedCards);
    }

    foreach ($playedCards as $playerId => $card) {
      if (Card::getCardSuit($card) !== $suit) {
        if (($playerIdIndex = array_search($playerId, $this->playersBySuit[$suit])) !== false) {
          unset($this->playersBySuit[$suit][$playerIdIndex]);
        }
      }
      if ($playerId !== $this->playerId) {
         $this->allCards->removeCard($card);
      }
    }

    // After removal, if no more cards are left for a suit, it means no one has that suit anymore (logically)
    foreach (Card::getAllSuits() as $suit) {
      if (!$this->allCards->hasCardForSuit($suit)) {
        $this->playersBySuit[$suit] = [];
      }
    }
  }

  // --------------------
  // Play in round (not start)
  // --------------------

  /**
   * Selects a suitable card in the round's suit.
   *
   * @param int $suit the suit (Card constant) of the current round
   * @param CardContainer $playerCards this player's cards
   * @param string[] $cardsInRound the cards which were already played in this round
   * @return string the code of the card to play
   */
  private function selectSameSuitCard($suit, $playerCards, array $cardsInRound) {
    $allUnknownCards = $this->allCards->createCopy();
    foreach ($cardsInRound as $card) {
      $allUnknownCards->removeCard($card);
    }

    // Special cases around the queen of spades
    if ($suit === Card::SPADES && !$this->queenOfSpadesPlayed) {
      if ($playerCards->hasCard(Card::SPADES . Card::QUEEN)
          && (in_array(Card::SPADES . Card::KING, $cardsInRound)
              || in_array(Card::SPADES . Card::ACE, $cardsInRound))) {
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

    // todo here at some point decide to maybe take rounds when appropriate
    if ($biggestPossible !== 0) {
      return $suit . $biggestPossible;
    } else {
      // Find which players we expect to still play in the same suit
      $missingPlayers = [];
      for ($i = 0; $i < Game::N_OF_PLAYERS; ++$i) {
        if (!isset($cardsInRound[$i]) && $i !== $this->playerId && in_array($i, $this->playersBySuit[$suit])) {
          $missingPlayers[] = $i;
        }
      }

      // No card is small enough...
      if (empty($missingPlayers) || $this->getNumberOfCardsAbove($suit, $playerCards->getMinCardForSuit($suit)) === 0) {
        // We're going to take the cards, so let's get rid of the biggest
        return $suit . $this->getMaxRankAvoidingQueenOfSpades($suit, $playerCards);
      } else {
        // Let's hope someone else will have a bigger card
        return $suit . $this->getMinRankAvoidingQueenOfSpades($suit, $playerCards);
      }
    }
  }

  /**
   * Selects the worst card. Used when the player can play a card from a different suit.
   *
   * @param CardContainer $playerCards the player's cards
   * @param string[] $cardsInHand the cards which are in the hand so far
   * @return string code of the card to play
   */
  private function selectWorstCard($playerCards, $cardsInHand) {
    // Get rid of spades cards if queen of spades is still around
    if (!$this->queenOfSpadesPlayed && $playerCards->hasCardForSuit(Card::SPADES)) {
      if ($playerCards->hasCard(Card::SPADES . Card::QUEEN)) {
        return Card::SPADES . Card::QUEEN;
      }
      if (!in_array(Card::SPADES . Card::QUEEN, $cardsInHand)) {
        if ($playerCards->hasCard(Card::SPADES . Card::ACE)) {
          return Card::SPADES . Card::ACE;
        } else if ($playerCards->hasCard(Card::SPADES . Card::KING)) {
          return Card::SPADES . Card::KING;
        }
      }
    }

    $weightByCard = [];
    foreach ($playerCards->getCards() as $suit => $cards) {
      if (!empty($cards)) {
        $min = reset($cards);
        $max = end($cards);
        $total = count($cards);
        // The weights here were set by experimentation. Interesting to note that the computer will play ♠9 if he only
        // has the following cards in Hearts: 2, 3, 4, 6, 10, J, A. Will also play ♦10 if rest is ♥7, 8, Q, K.
        // Other good example: chooses ♥K out of ♦2♦Q ♠2♠10 ♥5♥6♥7♥10♥J♥K.
        // Still, the count seems to be weighted too much, especially if the player has cards J thru Ace, for example.
        $weightByCard[$suit . $max] = $max
          + pow(max($max - 10, 0), 1.4)
          + pow($min - 2, 1.2)
          - pow($total - 1, 1.2);
      }
    }

    arsort($weightByCard);
    reset($weightByCard);
    return key($weightByCard);
  }

  /**
   * @param int $suit
   * @param CardContainer $playerCards
   * @return string
   */
  private function getMaxRankAvoidingQueenOfSpades($suit, $playerCards) {
    $maxRank = $playerCards->getMaxCardForSuit($suit);
    if ($suit === Card::SPADES && $maxRank === Card::QUEEN) {
      $spadeCards = $playerCards->getCards()[$suit];
      end($spadeCards);
      prev($spadeCards);
      if (current($spadeCards) !== false) {
        return current($spadeCards);
      }
    }
    return $maxRank;
  }

  /**
   * @param int $suit
   * @param CardContainer $playerCards
   * @return string
   */
  private function getMinRankAvoidingQueenOfSpades($suit, $playerCards) {
    $minRank = $playerCards->getMinCardForSuit($suit);
    if ($suit === Card::SPADES && $minRank === Card::QUEEN) {
      $spadeCards = $playerCards->getCards()[$suit];
      reset($spadeCards);
      next($spadeCards);
      if (current($spadeCards) !== false) {
        return current($spadeCards);
      }
    }
    return $minRank;
  }

  // --------------------
  // Round start
  // --------------------

  /**
   * Returns a card to start a new round with.
   *
   * @param CardContainer $playerCards the player's cards
   * @param boolean $heartsPlayed true if hearts can be played, false otherwise
   * @return string code of the card to play
   */
  private function selectCardForRoundStart($playerCards, $heartsPlayed) {
    if (!$this->queenOfSpadesPlayed && $playerCards->hasCardForSuit(Card::SPADES)) {
      if ($playerCards->hasCard(Card::SPADES . Card::QUEEN) && $this->isOnlyMissingSpadesCardAboveQueen()) {
        // Special case: if we know only king and/or ace of spades is left and we have the queen, we play the queen
        return Card::SPADES . Card::QUEEN;
      }
      if ($this->isOnlyMissingSpadesQueen()) {
        // Likewise, if we know only the queen of spades is missing and we have something smaller, let's also play it
        $maxRankBeforeQueen = $this->getMaxCardRankBefore(Card::SPADES, $playerCards, Card::QUEEN);
        if ($maxRankBeforeQueen !== false) {
          return Card::SPADES . $maxRankBeforeQueen;
        }
      }
    }

    $minCardBySuit = [];
    foreach (Card::getAllSuits() as $suit) {
      if ($playerCards->hasCardForSuit($suit)) {
        $minCardBySuit[$suit] = $this->getMinCardAvoidingQueenOfSpades($suit, $playerCards, $heartsPlayed);
      }
    }

    $weightsByCardCode = [];
    foreach ($minCardBySuit as $suit => $rank) {
      $nOfPlayers = count($this->playersBySuit[$suit]) ?: -20;
      $factorFromOtherCards = $this->calculateFactorFromOtherCards($suit, $rank);
      $rankFactor = Card::ACE - $rank;
      $weight = $nOfPlayers + $factorFromOtherCards + $rankFactor + $this->getPenaltyForCard($suit, $rank, $heartsPlayed);
      $weightsByCardCode[$suit . $rank] = $weight;
      var_dump('weight[' . $suit . $rank . '] = ' . $weight . " = players=$nOfPlayers + rankFactor=$rankFactor 
        + factorFromOtherCards=$factorFromOtherCards + penalty="
          . $this->getPenaltyForCard($suit, $rank, $heartsPlayed));
    }

    arsort($weightsByCardCode);
    reset($weightsByCardCode);
    return key($weightsByCardCode);
  }

  private function getPenaltyForCard($suit, $rank, $heartsPlayed) {
    // Needs to be in sync with the values the weight calculation can produce. The correction for no played Hearts must
    // guarantee that the weight will be the lowest since hearts may only be played if it's the only suit left!
    if ($suit === Card::HEARTS && !$heartsPlayed) {
      return -1000;
    } else if (!$this->queenOfSpadesPlayed && $suit === Card::SPADES) {
      if ($rank === Card::QUEEN) {
        return -200;
      } else if ($rank >= Card::KING) {
        return -50;
      }
    }
    return 0;
  }

  private function calculateFactorFromOtherCards($suit, $rank) {
    $numberOfBiggerCards = $this->getNumberOfCardsAbove($suit, $rank);
    if ($numberOfBiggerCards === 0) {
      return -20;
    }
    $numberOfSmallerCards = $this->getNumberOfCardsBelow($suit, $rank);
    if ($numberOfSmallerCards === 0) {
      // Small score boost for cards that simply can't be taken anymore
      return 10 + $numberOfBiggerCards;
    }
    return $numberOfBiggerCards;
  }

  /**
   * Returns the smallest available card for the given suit, preferring the king and ace of spades over the queen
   * when appropriate.
   *
   * @param int $suit the suit to process (constant from {@link Card})
   * @param CardContainer $playerCards the player's cards
   * @param boolean $heartsPlayed true if hearts can be played, false otherwise
   * @return int smallest (or most suitable) rank in the given suit that the player has
   */
  private function getMinCardAvoidingQueenOfSpades($suit, $playerCards, $heartsPlayed) {
    $minRank = $playerCards->getMinCardForSuit($suit);
    if ($suit === Card::SPADES && $minRank === Card::QUEEN) {
      if ($playerCards->hasCard(Card::SPADES . Card::KING)) {
        return Card::KING;
      }

      // The idea behind this is commented in AdvancedPlayer
      if ($playerCards->hasCard(Card::SPADES . Card::ACE)
          && !$heartsPlayed && $playerCards->hasCardForSuit(Card::HEARTS)) {
        return Card::ACE;
      }
    }
    return $minRank;
  }

  /**
   * Returns the maximum available rank that is smaller than the given threshold.
   *
   * @param int $suit the suit ({@link Card} constant)
   * @param CardContainer $cardContainer the card container to look in
   * @param int $thresholdRank the rank this method may not exceed
   * @return int|false the maximum rank, or false if none is available
   */
  private function getMaxCardRankBefore($suit, $cardContainer, $thresholdRank) {
    $cards = $cardContainer->getCards()[$suit];
    $maxValue = false;
    foreach ($cards as $rank) {
      if ($rank < $thresholdRank) {
        $maxValue = $rank;
      } else {
        break; // Cards are sorted: if $rank no longer < $threshold, stop
      }
    }
    return $maxValue;
  }

  // ------------
  // Card tracking helper
  // ------------

  private function getNumberOfCardsAbove($suit, $rank) {
    $count = 0;
    foreach ($this->allCards->getCards()[$suit] as $card) {
      if ($card > $rank) {
        ++$count;
      }
    }
    return $count;
  }

  private function getNumberOfCardsBelow($suit, $rank) {
    $count = 0;
    foreach ($this->allCards->getCards()[$suit] as $card) {
      if ($card < $rank) {
        ++$count;
      } else {
        break; // Values are sorted: if not smaller, no entry will be afterwards
      }
    }
    return $count;
  }

  private function isOnlyMissingSpadesCardAboveQueen() {
    $spadeCards = $this->allCards->getCards()[Card::SPADES];
    if (!empty($spadeCards) && count($spadeCards) <= 2) {
      $card1 = reset($spadeCards);
      $card2 = next($spadeCards);
      return ($card1 === Card::KING || $card1 === Card::ACE)
          && ($card2 === Card::ACE  || $card2 === false);
    }
    return false;
  }

  private function isOnlyMissingSpadesQueen() {
    $spadeCards = $this->allCards->getCards()[Card::SPADES];
    return count($spadeCards) === 1 && reset($spadeCards) === Card::QUEEN;
  }
}