<?php
/*
 * Runs a game of Hearts without a human player. Used to evaluate different player implementations.
 */

error_reporting(E_ALL);
session_start();

$start = microtime(true);

require 'functions.php';
require 'Card.php';
require 'CardContainer.php';
require 'GameState.php';
require './player/Player.php';
require './player/StandardPlayer.php';
require './player/AdvancedPlayer.php';
require './player/CardCountingPlayer.php';
require './player/HumanPlayer.php';

require 'GameOptions.php';
require 'Game.php';
require 'Displayer.php';

unset($game);

// Stop game if requested
if (isset($_GET['stop_game'])) {
  $_SESSION['game'] = null;
  session_destroy();
}

// Load or create game
if (isset($_SESSION['game'])) {
  $game = unserialize($_SESSION['game']);
  if (!($game instanceof Game)) {
    session_destroy();
    throw new Exception('Could not load the game! Please reload.');
  }
}

// CONFIGURATIONS
// --------------------
$get_options_from_session = false;
$hand_to_inspect = -4; // set to negative number to disable
$max_round = 14;

// --------------------
// END CONFIGURATIONS

if ($get_options_from_session) {
  // Type of players to test against each other
  $options = [
    ['s', GameOptions::STANDARD],
    ['a', GameOptions::ADVANCED],
    ['c', GameOptions::CARD_COUNTING]
  ];

  $totalOptions = count($options);
  $number = isset($_SESSION['eval_number']) ? $_SESSION['eval_number'] : 0;
  $nextNumber = $number + 1;
  echo '<h2>' . $number . '</h2>';

  if ($number >= pow($totalOptions, 4)) {
    echo 'Got number: ' . $number . '. Done with all combinations!';
    exit;
  }

  $playerTypes = [];
  $name = '';
  for ($i = 3; $i >= 0; --$i) {
    $value = (int)floor($number / pow($totalOptions, $i));
    $number -= $value * pow($totalOptions, $i);

    $name = $options[$value][0] . $name;
    $playerTypes[$i] = $options[$value][1];
  }
  ksort($playerTypes);

  $_SESSION['eval_number'] = $nextNumber;
} else {
  $name = 'manual eval';
  $playerTypes = [
    GameOptions::CARD_COUNTING,
    GameOptions::ADVANCED,
    GameOptions::ADVANCED,
    GameOptions::ADVANCED
  ];
}

$game = isset($game) ? $game : new Game(GameOptions::createForPlayerEvaluation($name, $playerTypes));

$displayer = new Displayer($game);
$state = $game->getState();

if ($state === GameState::HAND_START) {
  $game->startNewHand();
  $start_player = $game->getCurrentRoundStarter();
  $nextRoundStarter = $game->playAllPlayers();


  while ($game->getHandNumber() !== $hand_to_inspect && ($game->getState() === GameState::ROUND_END
         || ($game->getState() === GameState::HAND_START && $game->getHandNumber() <= $max_round && $game->getHandNumber() !== $hand_to_inspect))) {
    $nextRoundStarter = $game->playAllPlayers();
    if ($nextRoundStarter === null) {
      $game->startNewHand();
      $nextRoundStarter = $game->getCurrentRoundStarter();
    }
    if ($game->getHandNumber() === $hand_to_inspect) { break; }
  }

  if ($game->getHandNumber() !== $hand_to_inspect) {
    $displayer->roundEndMessage(null);
  } else {
    $nxt = $game->playAllPlayers();
    $displayer->roundEndMessage($nxt);
    save_game_to_session($game);
  }

}
else if ($state === GameState::AWAITING_HUMAN) {
  throw new Exception('Should never be in "awaiting human" state');
}
else if ($state === GameState::ROUND_END) {
  $nextStarter = $game->playAllPlayers();
  $displayer->draw('Hand goes to player ' . ($nextStarter + 1), false);
  save_game_to_session($game);
}
else if ($state === GameState::GAME_END) {
  throw new Exception('Should never be in "game end" state');
}
else {
  var_dump($game->getState());
  throw new Exception("Encountered unknown game state");
}

$end = microtime(true);
$gen_time = round($end - $start, 3);
echo '<p class="footer">
 Page generated in ' . $gen_time . '&nbsp;s
 <br /><a href="?stop_game">Stop game?</a>
</p>
';

if ($get_options_from_session) {
  echo '<meta http-equiv="Refresh" content="0"/>';
}
?>

 </body>
</html>