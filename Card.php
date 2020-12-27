<?php

/**
 * Contains card utilities.
 */
final class Card {

  // Constants used for the suits
  const CLUBS    = 0;
  const DIAMONDS = 1;
  const SPADES   = 2;
  const HEARTS   = 3;

  // Numerical values used for card ranks
  const JACK  = 11;
  const QUEEN = 12;
  const KING  = 13;
  const ACE   = 14;

  private function __construct() {
  }

  /**
   * Returns the points this card gives for the player that had to take it.
   *
   * @param $suit int the suit of the card (see {@link Card} constants)
   * @param $number int the card rank
   * @return int number of points given to the player
   */
  static function getPoints($suit, $number) {
    if ($suit === Card::HEARTS) {
      return 1;
    } else if ($suit === Card::SPADES && $number === Card::QUEEN) {
      return 13;
    }
    return 0;
  }

  /**
   * Returns the suit of the given card.
   *
   * @param $composedCardCode string composed card code (suit + rank)
   * @return int the suit
   */
  static function getCardSuit($composedCardCode) {
    return (int) substr($composedCardCode, 0, 1);
  }

  /**
   * Returns the rank (number) of the given card.
   *
   * @param $composedCardCode string composed card code (suit + rank)
   * @return int the rank
   */
  static function getCardRank($composedCardCode) {
    return (int) substr($composedCardCode, 1);
  }
}