<?php

/**
 * A player in the hearts game.
 */
interface IPlayer {

  // TODO might make sense to pass in an immutable card container?

  /**
   * Called when the player is expected to start the hand, i.e. must return the two of clubs.
   *
   * @return string code for the two of clubs
   */
  function startHand();

  /**
   * Called when the player has to provide a card to start a new round.
   *
   * @param boolean $heartsPlayed true if hearts can be used to start the new round, false otherwise
   * @return string code of the card to play
   */
  function startRound($heartsPlayed);

  /**
   * Called when the player has to provide a card for an ongoing round (i.e. the player did not start the round).
   *
   * @param int $suit the suit of the current hand ({@link Card} constant)
   * @param string[] $playedCards played cards by player id
   * @return string code of the card this player wants to play
   */
  function playInRound($suit, array $playedCards);

  /**
   * Notifies the player of the round on completion.
   *
   * @param int $suit the suit the hand was in ({@link Card} constant)
   * @param string[] $playedCards all played cards (key is player id)
   */
  function processRound($suit, array $playedCards);

  /**
   * Takes the given cards for the start of a new round. Cards are expected to be valid and unique.
   *
   * @param string[] $cards the cards belonging to the user for a new hand
   */
  function processCardsForNewHand(array $cards);

}