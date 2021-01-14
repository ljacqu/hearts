<?php

/**
 * A player in the hearts game.
 */
interface Player {

  /**
   * Takes the given cards for the start of a new round. Cards are expected to be valid and unique.
   *
   * @param CardContainer $playerCards the cards belonging to the user for a new hand
   */
  function processCardsForNewHand($playerCards);

  /**
   * Called when the player is expected to start the hand, i.e. must return the two of clubs.
   *
   * @param CardContainer $playerCards a copy of this player's cards (for reference)
   * @return string code for the two of clubs
   */
  function startHand($playerCards);

  /**
   * Called when the player has to provide a card to start a new round.
   *
   * @param CardContainer $playerCards a copy of this player's cards (for reference)
   * @param boolean $heartsPlayed true if hearts can be used to start the new round, false otherwise
   * @return string code of the card to play
   */
  function startRound($playerCards, $heartsPlayed);

  /**
   * Called when the player has to provide a card for an ongoing round (i.e. the player did not start the round).
   *
   * @param CardContainer $playerCards a copy of this player's cards (for reference)
   * @param int $suit the suit of the current hand ({@link Card} constant)
   * @param string[] $playedCards played cards by player id
   * @return string code of the card this player wants to play
   */
  function playInRound($playerCards, $suit, array $playedCards);

  /**
   * Notifies the player of the round on completion.
   *
   * @param int $suit the suit the hand was in ({@link Card} constant)
   * @param string[] $playedCards all played cards (key is player id)
   */
  function processRound($suit, array $playedCards);

}