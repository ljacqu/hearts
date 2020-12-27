<?php

/**
 * Holds constants to represent the state of a game.
 */
abstract class GameState {

  const HAND_START      = 0;
  const AWAITING_CLUBS  = 1;
  const AWAITING_HUMAN  = 2;
  const ROUND_END       = 3;
  const GAME_END        = 4;

}