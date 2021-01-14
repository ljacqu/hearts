<?php

/**
 * Advanced player implementation that keeps track of every card that has been played.
 */
class CardCountingPlayer implements Player {

  /** @var int This player's ID. */
  private $playerId;

  /** @var CardContainer Container with all unknown cards. */
  private $allCards;

  /** @var int[][] Player IDs presumed to have the given suit. Key of the first dimension is the suit. */
  private $playersBySuit;

  /** @var int[] Number of rounds with the given suit. */
  private $roundsBySuit;

  /** @var boolean Whether the queen of spades has been played. */
  private $queenOfSpadesPlayed;

  /** @var boolean Whether hearts has been played at least once in the current hand. */
  private $heartsPlayed;

  function __construct($playerId) {
    $this->playerId = $playerId;
  }

  function processCardsForNewHand($playerCards) {
    $this->roundsBySuit = array_fill_keys(Card::getAllSuits(), 0);
    $this->queenOfSpadesPlayed = false;
    $this->heartsPlayed = false;

    $allCards = [];
    foreach (Card::getAllSuits() as $suit) {
      foreach (range(2, Card::ACE) as $rank) {
        if (!$playerCards->hasCard($suit . $rank)) {
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
    ++$this->roundsBySuit[$suit];
    if (!$this->queenOfSpadesPlayed) {
      $this->queenOfSpadesPlayed = in_array(Card::SPADES . Card::QUEEN, $playedCards);
    }
    if (!$this->heartsPlayed) {
      foreach ($playedCards as $card) {
        if (Card::getCardSuit($card) === Card::HEARTS) {
          $this->heartsPlayed = true;
          break;
        }
      }
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

    $cardWithIntentionalBigRank = $this->takeRoundIntentionally($suit, $biggestPlayed, $playerCards, $cardsInRound);
    if ($cardWithIntentionalBigRank
        && $this->isNotUnwantedLargeSpadesCard($suit, $cardWithIntentionalBigRank, $playerCards)) {
      return $cardWithIntentionalBigRank;
    }

    $biggestPossible = 0;
    foreach ($playerCards->getCards()[$suit] as $card) {
      if ($card < $biggestPlayed) {
        $biggestPossible = $card;
      } else {
        break; // cards are sorted, once $card > $biggestPlayed, we're done
      }
    }

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
   * Checks that the given card is not a spades card that might force the player to take the queen of spades.
   * This method is used when the player wants to take a round intentionally to ensure that he does not take
   * the points of the queen of spades.
   *
   * @param int $suit the suit of the round
   * @param string $card the code of the card the player wants to play (same suit as the suit param)
   * @param CardContainer $playerCards the player's cards
   * @return boolean true if is card that is OK to play (to take the round intentionally), false otherwise
   */
  private function isNotUnwantedLargeSpadesCard($suit, $card, $playerCards) {
    if ($suit !== Card::SPADES || $this->queenOfSpadesPlayed) {
      return true;
    }
    $rank = Card::getCardRank($card);
    return $rank < Card::QUEEN || ($rank !== Card::QUEEN && $playerCards->hasCard(Card::SPADES . Card::QUEEN));
  }

  /**
   * @param int $suit
   * @param int $biggestPlayed
   * @param CardContainer $playerCards
   * @param string[] $cardsInRound
   * @return string|null
   */
  private function takeRoundIntentionally($suit, $biggestPlayed, $playerCards, array $cardsInRound) {
    if ($suit === Card::HEARTS
        || count($playerCards->getCards()[$suit]) < 2
        || $playerCards->getMaxCardForSuit($suit) < $biggestPlayed) {
      return null;
    }

    $isLastCardInRound = count($cardsInRound) === Game::N_OF_PLAYERS - 1;
    if ($this->roundsBySuit[$suit] >= 2 && !$isLastCardInRound) {
      return null;
    }

    foreach ($cardsInRound as $card) {
      // Don't intentionally take a round if it has a card with points
      if (Card::getCardSuit($card) === Card::HEARTS || $card === Card::SPADES . Card::QUEEN) {
        return null;
      }
    }

    // Update the unaccounted cards with the ones we see in the round for better accuracy
    $allUnaccountedCards = $this->allCards->createCopy();
    foreach ($cardsInRound as $card) {
      $allUnaccountedCards->removeCard($card);
    }
    $playersBySuit = [];
    foreach ($this->playersBySuit as $inspectedSuit => $playerIds) {
      $leftUnaccountedCardsInSuit = count($allUnaccountedCards->getCards()[$inspectedSuit]);
      $playersBySuit[$inspectedSuit] = min(count($playerIds), $leftUnaccountedCardsInSuit);
    }

    // Too risky to take intentionally if not all players have the suit
    if (!$isLastCardInRound && $playersBySuit[$suit] < Game::N_OF_PLAYERS - 1) {
      return null;
    }

    // Check how well our cards in the current suit perform
    $playerCardStatistics = [];
    foreach ($playerCards->getCards() as $inspectedSuit => $playerCardsBySuit) {
      foreach ($playerCardsBySuit as $rank) {
        if (!$this->isPotentiallyPlayableCard($inspectedSuit, $rank)) {
          continue;
        }


        $unaccountedCardsBelow = 0;
        $unaccountedCardsAbove = 0;
        foreach ($allUnaccountedCards->getCards()[$inspectedSuit] as $unaccountedRank) {
          if ($unaccountedRank < $rank) {
            ++$unaccountedCardsBelow;
          } else {
            ++$unaccountedCardsAbove;
          }
        }
        $playerCardStatistics[$inspectedSuit . $rank] = [$unaccountedCardsBelow, $unaccountedCardsAbove];
      }
    }

    $nBadCards = 0;
    $nGoodCards = 0;
    $nGoodCardsInSuit = 0;
    foreach ($playerCardStatistics as $card => $statistics) {
      if ($statistics[1] === 0) {
        $nBadCards += 1; // No cards above this one: bad
      } else if ($statistics[0] === 0) {
        $nGoodCards += 1; // No cards below this one (and at least one card above): very good
        if (Card::getCardSuit($card) === $suit) {
          $nGoodCardsInSuit += 1;
        }
      } else {
        if ($statistics[0] >= $playersBySuit[$suit] || $statistics[1] < 2) {
          $nBadCards += 1;
        } else {
          $nGoodCards += 0.5; // Benefit of the doubt???
          if (Card::getCardSuit($card) === $suit) {
            $nGoodCardsInSuit += 0.5;
          }
        }
      }
    }


    if ($isLastCardInRound) {
      $thresholdLast = $this->roundsBySuit[$suit] < 2 ? 0.5 : 1;
      if ($nGoodCards < $thresholdLast) {
        return null;
      }
    } else { // is not last card -- i.e. more risky
      $allPresumedToHaveSuit = $playersBySuit[$suit] === Game::N_OF_PLAYERS - 1;
      $thresholdMiddle = $this->roundsBySuit[$suit] === 0 ? 1 : 2.5;
      if (!$allPresumedToHaveSuit || $nGoodCards < $thresholdMiddle) {
        return null;
      }
    }

    // Idea: if we have good cards in the same suit, no need to get rid of it hastily.
    $thresholdGoodCardsSuit = $this->roundsBySuit[$suit] === 0 ? 2.5 : 1.5;
    if ($nGoodCardsInSuit >= $thresholdGoodCardsSuit) {
      return null;
    }

    $maxCardForSuit = $playerCards->getMaxCardForSuit($suit);
    $minCardForSuit = $playerCards->getMinCardForSuit($suit);
    return $suit . $maxCardForSuit;
  }

  private function isPotentiallyPlayableCard($suit, $rank) {
    if ($suit === Card::SPADES && !$this->queenOfSpadesPlayed && $rank >= Card::QUEEN) {
      return false;
    } else if ($suit === Card::HEARTS && !$this->heartsPlayed) {
      return false;
    }
    return true;
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

    $unaccountedCards = $this->allCards->createCopy();
    foreach ($cardsInHand as $card) {
      $unaccountedCards->removeCard($card);
    }

    $weightByCard = [];
    foreach ($playerCards->getCards() as $suit => $cards) {
      if (!empty($cards)) {
        $min = reset($cards);
        $max = end($cards);
        $totalUnaccountedCards = count($unaccountedCards->getCards()[$suit]);

        if ($totalUnaccountedCards === 0) {
          // Artificial value to make sure the calculation doesn't go ballistic because the min available card is the
          // largest as well. At the same time, set a value a bit larger than 0 since it would be more interesting to
          // get rid of this one than a 100% playable card.
          $weightByCard[$suit . $max] = 0.1;
          continue;
        }

        $factorMiddleCards = 0;
        foreach ($cards as $rank) {
          if ($rank !== $min && $rank !== $max) {
            // How many cards are still around that are bigger than the current card?
            $numberOfCardsAbove = $unaccountedCards->countNumberOfCardsAboveRank($suit, $rank);
            $factorMiddleCards += pow($numberOfCardsAbove / $totalUnaccountedCards, 2);
          }
        }
        $factorMiddleCards = min($factorMiddleCards, 1);

        $probabilityLastCardSmaller =
          $unaccountedCards->countNumberOfCardsAboveRank($suit, $max) / $totalUnaccountedCards;
        $probabilityFirstCardLargest =
          $unaccountedCards->countNumberOfCardsBelowRank($suit, $min) / $totalUnaccountedCards;

        // If last card is also smallest of all available, make factor -1 instead of 0 to make suit less important
        $lastCardFactor = $probabilityLastCardSmaller === 1 ? -1 : pow(1 - $probabilityLastCardSmaller, 0.6);

        $weight = $lastCardFactor
          + pow($probabilityFirstCardLargest, 0.75)
          - ($factorMiddleCards * 0.75);

        $weightByCard[$suit . $max] = $weight;
      }
    }

    arsort($weightByCard);
    reset($weightByCard);
    return key($weightByCard);
  }

  /**
   * Returns the max available rank from the given player cards, avoiding the queen of spades.
   *
   * @param int $suit the suit
   * @param CardContainer $playerCards the cards to search in
   * @return int highest available rank
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
   * Returns the minimum available rank, avoiding the queen of spades if possible.
   *
   * @param int $suit the suit
   * @param CardContainer $playerCards the cards to search in
   * @return int lowest available rank
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
      // Special case: if we know only king and/or ace of spades is left and we have the queen, we play the queen
      if ($playerCards->hasCard(Card::SPADES . Card::QUEEN) && $this->isOnlyMissingSpadesCardAboveQueen()) {
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
      // Small score boost for cards that simply can't be beaten
      return 10 + $numberOfBiggerCards;
    }
    if ($numberOfSmallerCards < count($this->playersBySuit)) {
      // Very small score boost if it's kind of likely that we won't take
      return 1 + $numberOfBiggerCards;
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