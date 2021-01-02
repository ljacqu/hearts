<?php

/**
 * Container for a set of cards. Offers utilities for easier querying and manipulation.
 * Cards can be removed from a container but not added. A new card container can be constructed for each hand.
 */
class CardContainer {

  /**
   * @var int[][] The card values by suit (constants from {@link Card}).
   *              The values in the subarray by suit are sorted.
   */
  private $cards = [];

  /**
   * Creates a card container with the given cards.
   *
   * @param string[] $cardCodes the codes (suit + rank) of the cards this container should contain
   */
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

  /**
   * Removes the given card from the container. Throws an exception if the card is not present.
   *
   * @param string $cardCode the composed card code (suit + rank)
   */
  function removeCard($cardCode) {
    $suit = Card::getCardSuit($cardCode);
    $rank = Card::getCardRank($cardCode);

    $index = array_search($rank, $this->cards[$suit], true);
    if ($index === false) {
      throw new Exception("Did not find card with suit=$suit and rank=$rank");
    }
    unset($this->cards[$suit][$index]);
  }

  /**
   * Returns whether the container has a card for any of the given suits.
   *
   * @param int ...$suits the suits to process (constants from Card)
   * @return boolean true if there is a card for any of the suits, false otherwise
   */
  function hasCardForSuit(...$suits) {
    foreach ($suits as $suit) {
      if (!empty($this->cards[$suit])) {
        return true;
      }
    }
    return false;
  }

  /**
   * Returns the smallest available rank for the given suit.
   *
   * @param int $suit the suit to look for (Card constant)
   * @return int|false the smallest present rank with the given suit, false if there are no cards for the given suit
   */
  function getMinCardForSuit($suit) {
    return reset($this->cards[$suit]);
  }

  /**
   * Returns the largest available rank for the given suit.
   *
   * @param int $suit the suit to look for (Card constant)
   * @return int the greatest present rank with the given suit
   */
  function getMaxCardForSuit($suit) {
    return end($this->cards[$suit]);
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

  /**
   * Returns the internal representation of the cards. Key of the first dimension array is the suit. The second-level
   * array contains the values sorted by value.
   *
   * @return int[][] the ranks of the cards by suit
   */
  function getCards() {
    return $this->cards;
  }

  /**
   * Specifies whether there aren't any cards in the container.
   *
   * @return boolean true if the container is empty, false if there is at least one card
   */
  function isEmpty() {
    foreach ($this->cards as $cardList) {
      if (!empty($cardList)) {
        return false;
      }
    }
    return true;
  }
}