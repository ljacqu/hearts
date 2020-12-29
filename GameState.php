<?php

/**
 * Holds constants to represent the state of a game.
 */
abstract class GameState {

  const HAND_START      = 0;
  const AWAITING_HUMAN  = 1;
  const ROUND_END       = 2;
  const GAME_END        = 3;

}