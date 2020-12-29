<?php

class CardContainer {

  /** @var int[][] The card values by suit (constants from {@link Card}). */
  private $cards = [];

  function __construct($cardCodes) {
    $this->cards = [
      Card::CLUBS    => [],
      Card::DIAMONDS => [],
      Card::SPADES   => [],
      Card::HEARTS   => []
    ];

    foreach ($cardCodes as $code) {
      $suit = Card::getCardSuit($code);
      $this->cards[$suit][] = Card::getCardRank($code);
    }
    foreach ($this->cards as &$cardsInSuit) {
      natsort($cardsInSuit);
    }
  }

  function removeCard($cardCode) {
    $suit = Card::getCardSuit($cardCode);
    $rank = Card::getCardRank($cardCode);

    $index = array_search($rank, $this->cards[$suit], true);
    if ($index === false) {
      throw new Exception("Did not find card with suit=$suit and rank=$rank");
    }
    unset($this->cards[$suit][$index]);
  }

  function hasCardForSuit($suit) {
    return !empty($this->cards[$suit]);
  }

  /**
   * Returns whether the container has the specified card.
   *
   * @param $cardCode string code of the card to check
   * @return boolean true if player has the specified card, false otherwise
   */
  function hasCard($cardCode) {
    $suit = Card::getCardSuit($cardCode);
    $rank = Card::getCardRank($cardCode);
    $index = array_search($rank, $this->cards[$suit], true);
    return $index !== false;
  }

  function getCards() {
    return $this->cards;
  }

  function hasAnyCard() {
    foreach ($this->cards as $cardList) {
      if (!empty($cardList)) {
        return true;
      }
    }
    return false;
  }
}