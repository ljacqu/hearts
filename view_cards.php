<?php
/**
 * Displays the cards in the current session's game for debug purposes.
 */
error_reporting(E_ALL);
session_start();
require 'Card.php';
require 'CardContainer.php';
require 'Game.php';
require 'IPlayer.php';
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
$currentHandCards = $game->getCurrentHandCards();
foreach ($currentHandCards as $key => $playerCards) {
  echo '<h1>Player ' . ($key + 1) . '</h1>';
  echo '<table class="cards"><tr>';
  $cards = collectCardsAsCardCodes($playerCards);

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

if (isset($_GET['debug'])) {
  $gameDebug = $game->getDebugValues();

  echo "<h2>Internal state</h2>
    State: {$gameDebug['state']}
    <br />Hearts played: {$gameDebug['heartsPlayed']}
    <br />Round starter: {$gameDebug['currentRoundStarter']} (1-based)
    <br />Current hand points: {$gameDebug['currentHandPoints']}
    <br />Need 2&clubs;: {$gameDebug['needTwoOfClubs']}";
}

/**
 * @param $cardContainer CardContainer
 * @return string[]
 */
function collectCardsAsCardCodes($cardContainer) {
  $result = [];
  foreach ($cardContainer->getCards() as $suit => $ranks) {
    $result = array_merge($result, array_map(function ($rank) use ($suit) {
      return $suit . $rank;
    }, $ranks));
  }
  return $result;
}