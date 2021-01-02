<?php

/**
 * Empty implementation used for the player spot that is the human. Ensures that the game doesn't
 * falsely ask this player implementation for a card since it should come from human input.
 */
class HumanPlayer implements Player {

  function startHand() {
    throw new Exception('Should never be called');
  }

  function startRound($heartsPlayed) {
    throw new Exception('Should never be called');
  }

  function playInRound($suit, array $playedCards) {
    throw new Exception('Should never be called');
  }

  function processRound($suit, array $playedCards) {
    // Nothing to do
  }

  function processCardsForNewHand(array $cards) {
    // Nothing to do
  }
}