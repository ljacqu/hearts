<?php

/**
 * More advanced player implementation: takes into consideration when other players are out of cards for certain suits,
 * and considers his own cards more nuanced (e.g. doesn't blindly try to get rid of his biggest cards but also considers
 * what his other cards in that suit are).
 */
class AdvancedPlayer implements Player {

  /** @var int The player ID of this player. */
  private $playerId;

  /**
   * @var int[][] Keeps track which players (presumably) have cards in a certain suit. The key of the first dimension
   * is the suit constant. The second dimension is the collection of player IDs. Player IDs are removed when it is
   * apparent that they do not have any card in the given suit.
   */
  private $playersBySuit;

  /** @var boolean Whether the queen of spades has been played. */
  private $queenOfSpadesPlayed;

  /**
   * Constructor.
   *
   * @param int $playerId this player's ID
   */
  function __construct($playerId) {
    $this->playerId = $playerId;
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
    return $this->selectWorstCard($playerCards);
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
    }
  }

  function processCardsForNewHand(array $cards) {
    $this->queenOfSpadesPlayed = false;

    $allOtherPlayers = range(0, Game::N_OF_PLAYERS - 1);
    unset($allOtherPlayers[$this->playerId]);

    $playersBySuit = [];
    foreach (Card::getAllSuits() as $suit) {
      $playersBySuit[$suit] = $allOtherPlayers;
    }
    $this->playersBySuit = $playersBySuit;
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
      if (empty($missingPlayers)) {
        // We're going to take the cards, so let's get rid of the biggest
        return $suit . $playerCards->getMaxCardForSuit($suit);
      } else {
        // Let's hope someone else will have a bigger card
        return $suit . $playerCards->getMinCardForSuit($suit);
      }
    }
  }

  /**
   * Selects the worst card. Used when the player can play a card from a different suit.
   *
   * @param CardContainer $playerCards the player's cards
   * @return string code of the card to play
   */
  private function selectWorstCard($playerCards) {
    // Get rid of spades cards if queen of spades is still around
    if (!$this->queenOfSpadesPlayed && $playerCards->hasCardForSuit(Card::SPADES)) {
      if ($playerCards->hasCard(Card::SPADES . Card::QUEEN)) {
        return Card::SPADES . Card::QUEEN;
      } else if ($playerCards->hasCard(Card::SPADES . Card::ACE)) {
        return Card::SPADES . Card::ACE;
      } else if ($playerCards->hasCard(Card::SPADES . Card::KING)) {
        return Card::SPADES . Card::KING;
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
    $minCardBySuit = [];
    foreach (Card::getAllSuits() as $suit) {
      if ($playerCards->hasCardForSuit($suit)) {
        // Special case for Queen of spades: would be better to play the King
        $minCardBySuit[$suit] = $this->getMinCardAvoidingQueenOfSpades($suit, $playerCards, $heartsPlayed);
      }
    }

    $weightsByCardCode = [];
    foreach ($minCardBySuit as $suit => $rank) {
      $nOfPlayers = count($this->playersBySuit[$suit]) ?: -20;
      $value = $rank === Card::ACE ? -20 : Card::ACE - $rank;
      $weight = $nOfPlayers + $value + $this->getPenaltyForCard($suit, $rank, $heartsPlayed);
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

      // Taking the ace of spades instead of the queen actually makes sense when hearts haven't been played yet:
      // hopefully another player is out of spades cards and will play hearts, allowing the player to then use a good
      // Hearts cards he hasn't been able to play until now. I guess otherwise better to stick with the queen in hopes
      // that someone else only has the king left for spades?
      // Note that these cards are anyway heavily penalized in their score so this is only relevant if the user has to
      // start a round and only has really bad cards!
      if ($playerCards->hasCard(Card::SPADES . Card::ACE)
          && !$heartsPlayed && $playerCards->hasCardForSuit(Card::HEARTS)) {
        return Card::ACE;
      }
    }
    return $minRank;
  }
}