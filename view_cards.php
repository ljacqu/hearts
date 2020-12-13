<?php
/**
 * Displays the cards in the current session's game for debug purposes.
 * @author ljacqu
 */
error_reporting(E_ALL);
session_start();
require 'functions.php';
require 'Game.php';
require 'Player.php';
require 'Displayer.php';

if (isset($_SESSION['game'])) {
  $game = unserialize($_SESSION['game']);
  if (!($game instanceof Game)) {
    session_destroy();
    die('Could not load the game! <a href="index.php">Please create a new one.</a>');
  }
} else {
  die('Did not find any game. <a href="index.php">Please create a new one.</a>');
}

echo <<<HTML
<link rel="stylesheet" type="text/css" href="style.css" />
HTML;

$displayer = new Displayer($game);
$players = $game->getPlayers();
foreach ($players as $key => $player) {
  echo '<h1>Player ' . ($key + 1) . '</h1>';
  echo '<table class="cards"><tr>';
  $cards = array_flatten($player->getCards());

  foreach ($cards as $card) {
    $html_data = $displayer->getHtmlCardDetails($card);
    echo <<<HTML
<td class="{$html_data['color']}">
 {$html_data['suit']}
 <br />{$html_data['number']}
</td>
HTML;
  }
  echo "</tr></table>\n";
}


function array_flatten($card_list) {
  $flat_arr = [];
  foreach ($card_list as $suit => $cards_of_suit) {
    $flat_arr = array_merge($flat_arr, array_map(function ($entry) use ($suit) {
      return $suit . $entry;
    }, $cards_of_suit));
  }
  return $flat_arr;
}