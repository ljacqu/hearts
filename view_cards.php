<?php
error_reporting(E_ALL);
require 'functions.php';

unset($sessid, $game);
if (isset($_COOKIE['sessid']) && is_valid_game_id($_COOKIE['sessid'])) {
	$sessid = $_COOKIE['sessid'];
} else {
	header('Location:index.php');
	exit;
}
if (!game_exists($sessid)) {
	setcookie('sessid', '', time()-9);
	die('No active gae found!');
}

require id_to_filename($sessid);
if (!isset($game) || !($game instanceof Game)) {
	die('Problem loading game!');
}

$total_cards = 1; // in case another player has one additional card
$human_cards = $game->getHumanCards();
foreach ($human_cards as $cardsOfSuit) {
	$total_cards += $cardsOfSuit;
}
?>
<link rel="stylesheet" type="text/css" href="style.css" />
<style type="text/css">
<!--
td {
	width: <?php
echo floor(100/$total_cards); ?>%;
}
h3 {
	text-align: center;
}
h4 { font-size: 15pt; }

-->
</style>
<?php
// TODO ...

highlight_file(id_to_filename($sessid));