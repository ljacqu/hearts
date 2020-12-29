<?php

interface IPlayer {

  /**
   * Returns the card to play in the round.
   *
   * @param int $suit The suit of the current round (constant from Game)
   * @param int[] $playedCards array of played cards, where the key corresponds to the
   *  player ID and the entry is the suit number and the value, e.g.
   *  $played_cards[1] = 211 means player 1 played J of spades.
   * @param boolean $heartsPlayed whether hearts can be used to start a round
   * @param boolean $handStart true if two of clubs are expected
   * @return string the card the player wants to play
   */
  function playCard($suit, array $playedCards, $heartsPlayed, $handStart); // todo create param object

  /**
   * Takes the given cards for the start of a new round. Cards are expected to be valid and unique.
   *
   * @param string[] $cards the cards belonging to the user for a new hand
   */
  function processCardsForNewHand(array $cards);

}