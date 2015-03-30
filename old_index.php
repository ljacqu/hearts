<?php
/* *****************
 * PHP Hearts game
 * by ljacqu
 * ***************** */

error_reporting(E_ALL);
$start_time = microtime(true);

$language = array(0 => '&clubs;', 1 => '&diams;', 2 => '&spades;', 3 => '&hearts;');

// --------------
// See if $SESSID is set.
// --------------
if (isset($_POST['SESSID']) && preg_match('/^[a-z0-9]+$/', $_POST['SESSID'])) { 
	$SESSID = $_POST['SESSID'].'.txt';

	if (!file_exists('./data/'.$SESSID)) {
		unset($SESSID);
	}
}

// --------------
// Create new game
// --------------
if (isset($_POST['new_game'])) {
	$SESSID = uniqid().'.txt';
	while(file_exists('./data/'.$SESSID)){
		$SESSID = uniqid().'.txt';
	}

	$points = array(1 => 0,
					2 => 0,
					3 => 0,
					4 => 0 );

	$step = 1;
	
	store_var('points', true);
	store_var('step');
}
else if (!isset($SESSID)) {
	// Offer to create a new game if we don't have any session.
	echo '<form action="'.$_SERVER['PHP_SELF'].'" method="post">
<input type="submit" style="margin: auto" value="Create new game" name="new_game" />
</form>';
exit;
}

// Cookie is used for view_cards.php
@setcookie('sessid', $SESSID, time()+9999);

// --------------
// Start HTML output
// --------------
echo '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
 <head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title>PHP Hearts</title>
	<link rel="stylesheet" type="text/css" href="style.css" />
 </head>
 <body>';


// --------------
// Starting steps
// --------------
$pl_card  = array();
$twoClubs = false;
require('./data/'.$SESSID);

echo '<h1>Hearts &middot; Round #'.$step.'</h1>';

if(!isset($temp_cards)){
	$temp_cards = array();
	$pl_card = array();
}

if(!isset($heartsPlayed)){
	$heartsPlayed = 0;
}


// --------------
// Give out cards if $player_cards is not set
// --------------
if(!isset($player_cards)){
	echo 'Giving out cards...';
	give_cards();
	$roundPoints = array(1 => 0,
						 2 => 0,
						 3 => 0,
						 4 => 0 );

	store_var('player_cards');
	store_var('points');
	store_var('roundPoints');
	store_var('step');
	store_var('heartsPlayed');
	$substep = 2;

}
else{
	// --------------
	// Substeps: Telling us what code to run below
	// --------------

	if(!isset($_POST['card_choice'])){
		if(isset($_POST['action']) && $_POST['action'] == 'player_start'){ // another player starts the hand
			$substep = 0; # <-- someone plays, and it's not player #1
		}
		else if(isset($_POST['card'])){
			$substep = 1; # <-- human input a card; it needs to be processed
		}
		else if(!isset($_POST['action']) && !isset($pl_card)){
			$substep = 2; # <-- begin round
		}
		else{
			$substep = 0;
		}
	}
}


// --------------
// Beginning of the round
// Check who's got the 2 of clubs (02)
// --------------
if ($substep == 2) {
	$played  = false;
	$twoClubs = true;

	if(isset($player_cards[1][0]) && in_array(2, $player_cards[1][0])){
		$message = 'Must start with the 2 of clubs';
		$played = true;
	}

	if (!$played) {
		for ($i = 2; $i < 5; $i++) {
			if(isset($player_cards[$i][0]) && in_array(2, $player_cards[$i][0])){
				$message = 'Player '.$i.' starts';
				$game_continue = true;
				break;
			}
		}
	}
	store_var('twoClubs');
}
else if($substep == 0){
	// --------------
	// AI players play
	// --------------

	// Start of round: find player who plays two of clubs
	if($twoClubs){
		for($i = 2; $i < 5; $i++){
			if(count($player_cards[$i][0]) > 0){
				foreach($player_cards[$i][0] as $key => $card){ // $key gets used below!
					if($card == 2){
						$start_player = $i;
						break 2;
					}
				}
			}
		}
		if(!isset($start_player) || preg_match('/^[^1-4]{1}$/', $start_player)){
			print_r($player_cards); die('Problem with $start_player');
		}

		// Save the player's choice
		$pl_card[$start_player] = '&clubs;<br />2';
		$temp_cards[$start_player] = '02';
		unset($player_cards[$i][0][$key]); // <-- remove card from his cards
		$twoClubs = false;

		// Other AIs play.
		$start_player = next_player($start_player);
		while($start_player != false && $start_player < 5 && $start_player != 1){
			respond_play($start_player);
			$start_player = next_player($start_player);
		}

		$message = 'Your turn';
		$played = 1;
	}
	else{ // Not the start of the round!
		if(isset($suit)){
			unset($suit);
		}
		if($player_start != 1){
			start_play($player_start);
			$next_player = next_player($player_start);
			while($next_player != false && $next_player < 5 && $next_player != 1){
				respond_play($next_player);
				$next_player = next_player($next_player); // <-- that line looks great
			}
			$message = 'Your turn';
			$played = 1;
		}
		else{
			$message = 'You start';
			$played = 1;
		}
	}

	// Store the new game changes in the file.
	store_var('pl_card', 1);
	$array = array('temp_cards', 'points', 'roundPoints', 'step', 'player_cards', 'twoClubs', 'heartsPlayed');
	foreach($array as $item){
		store_var($item);
	}
	if(isset($suit)){
		store_var('suit');
	}
}
else if($substep == 1){
	// --------------
	// Player has chosen a card
	// --------------
	$user_card = $_POST['card'];

	// Step 1: Validate the card choice
	// Find the suit
	if(!isset($suit) && count($pl_card) > 0){
		for($card_key = 2; $card_key < 5; $card_key++){
			if(isset($temp_cards[$card_key])){
				break;
			}
		}
		$suit = $temp_cards[$card_key]{0};
	}
	else if(!isset($suit)){
		if($twoClubs){
			$suit = '0';
		}
		else{ //at this point, the player is starting, so any suit is fine.
				// Check for Hearts below. 
			$suit = $user_card{0};
		}
	}
	do{
		if($twoClubs && $user_card != '02'){
			$message = '<span style="color: #900">Must start with the 2 of clubs</span>';
			$twoClubs = true;
			$played = true;
			break;
		}
		elseif($twoClubs){ $twoClubs = false; }

		if($user_card{0} !== $suit && count($player_cards[1][$suit]) > 0){
			$message = '<span style="color: #900">Invalid suit</span>';
			$played = true;
			break;
		}
		if($user_card{0} == 3 && count($temp_cards) == 0 && !$heartsPlayed){
			$myc = $player_cards[1];
			if(count($myc[0]) > 0 || count($myc[1]) > 0 || count($myc[2]) > 0){
				$message = '<span style="color: #900">Hearts have not been played yet!</span>';
				$played = true;
				break;
			}
			$heartsPlayed = true;
		}
		if(!$heartsPlayed && $user_card{0} == 3){
			$heartsPlayed = true;
		}
		if(!in_array(substr($user_card, 1), $player_cards[1][$user_card{0}])){
			die('You do not have this card!');
		}

		// At this point, card is a valid choice.
		// Step 2: Register the decision, let all AIs play.
		// & remove the card from player's array
		$temp_cards[1] = $user_card;
		$pl_card[1] = $language[$user_card{0}].'<br />'.card2name(substr($user_card, 1));
		foreach($player_cards[1][$user_card{0}] as $key => $value){
			if($value == substr($user_card, 1)){
				unset($player_cards[1][$user_card{0}][$key]);
				break;
			}
		}

		if(count($temp_cards) != 4){
			$player = 2;
			while($player != false && $player != 5 && $player != 1){
				respond_play($player);
				$player = next_player($player);
			}
		}

		// Step 3: Find out who takes the cards
		$high = 0;
		foreach($temp_cards as $player => $card){
			if(substr($card, 1) > $high && $card{0} == $suit){
				$high = substr($card, 1);
				$takingPlayer = $player;
			}
		}

		// Add points where necessary
		$newPoints = 0;
		foreach($temp_cards as $key => $card){
			if($card{0} == 3){
				$newPoints++;
				$heartsPlayed = 1;
			}
			else if($card == 212){ // S12
				$newPoints += 13;
			}
		}

		$roundPoints[$takingPlayer] += $newPoints;
		store_var('roundPoints', 1);
		store_var('points');

		// Determine if there are still cards or if it's the end of the round.
		$stillCards = false;
		for($i = 0; $i < 4; $i++){
			if(isset($player_cards[1][$i]) && count($player_cards[1][$i]) > 0){
				$stillCards = true;
				break;
			}
		}

		// If it's the end of the round, add $roundPoints to $points
		// and display the points
		if(!$stillCards){
			$oldPoints = $points;

			natsort($roundPoints);
			if(end($roundPoints) == 26){
				$luckyPlayer = key($roundPoints);
				for($i = 1; $i <= 4; $i++){
					if($i != $luckyPlayer){
						$points[$i] += 26;
						$roundPoints[$i] = 26; // The correction is needed because we use $roundPoints (see below)
					}
					else{
						$roundPoints[$i] = 0;
					}
				}
			}
			else{
				for($i = 1; $i <= 4; $i++){
					$points[$i] += $roundPoints[$i];
				}
			}

			natsort($points);
			$lowest = current($points);
			$showPoints = $points;

			foreach($points as $key => $value){
				if($value == $lowest){
					$showPoints[$key] = '<span style="color: #090">'.$value.'</span>';
				}
				if($value >= 100){ // end of game
					$eog = 1;
				}
			}

			// Produce the addition output for each player
			// $oldPoints + $roundPoints = $points
			for($i = 1; $i <= 4; $i++){
				$showPoints[$i] = '<p style="font-size: 0.85em; line-height: -1px; margin: 0; padding: 0">&nbsp;'.$oldPoints[$i].'<br />+'.$roundPoints[$i].'</p>'.$showPoints[$i];
			}

			if(isset($eog)){
				$count_place = 1;
				foreach($points as $user => $score){
					if($user != 1){ $count_place++; }
					else{ break; }
				}
				switch ($count_place){
					case 1: $count_place .= 'st'; break;
					case 2: $count_place .= 'nd'; break;
					case 3: $count_place .= 'rd'; break;
					case 4: $count_place .= 'th'; break;
					default: die('Error with $count_place:'.var_dump($count_place));
				}
				$sub_msg = 'Game over: '.$count_place.' place';
			}
			else $sub_msg = 'Points';

			$message = '<table style="margin-left: auto; margin-right: auto">
 <tr>
	<td colspan="4">'.$sub_msg.'</td>
 </tr>
 <tr style="text-align: center">
	<td>You</td>
	<td>P2</td>
	<td>P3</td>
	<td>P4</td>
 </tr>
 <tr style="text-align: center">
	<td>'.$showPoints[1].'</td>
	<td>'.$showPoints[2].'</td>
	<td>'.$showPoints[3].'</td>
	<td>'.$showPoints[4].'</td>
 </tr>
</table>';

			// Not the end of the game yet, so prepare for a new round!
			if(!isset($eog)){
				$game_continue = 1;
				$twoClubs = 1;
				$step++;
				$end = 1;
				$heartsPlayed = 0;
			}
			else @unlink('./data/'.$SESSID);
		}
		else{
			// If it's not the end of the round yet, say who has to start
			if($takingPlayer != 1){
				$message = 'Player #'.$takingPlayer.' starts.';
			}
			else{
				$message = 'You start.';
			}
			$game_continue = 1;
		}

		$player_start = $takingPlayer;
		store_var('points', true);
		if($stillCards){
			store_var('player_cards');
		}
		store_var('roundPoints');
		store_var('step');
		store_var('player_start');
	} while(0);
	store_var('heartsPlayed');
	store_var('twoClubs');
}

// --------------
// Show output (players' cards, game message)
// --------------

// Variable fixing & adding the <Continue> button to $message
if(!isset($pl_card)){ $pl_card = array(); }
if(!isset($twoClubs)) $twoClubs = 0;

if(isset($message)){
	if(isset($game_continue) && $game_continue){
		$message .= '<br /><form action="'.$_SERVER['PHP_SELF'].'" method="post">
 <input type="hidden" name="SESSID" value="'.substr($SESSID, 0, -4).'" />
 <input type="hidden" name="twoClubs" value="'.$twoClubs.'" />
 <input type="hidden" name="action" value="player_start" />
 <input type="submit" value="Continue" />
</form>';
	}
}
else if(!isset($message) || !$message){  // This should never appear!
	$message = '<span style="color: #600">&hearts; PHP Hearts &hearts;</span>';
}

// The main output
echo '<table style="width: 650px; margin-left: auto; margin-right: auto">
	<tr>
	   <td> </td>
	   <td class="cardbox">'.echo_pl(3).'</td>
	   <td> </td>
	</tr>
	<tr>
	   <td style="width: 33%" class="cardbox">'.echo_pl(2).'</td>
	   <td style="text-align: center; font-size: 12pt; width: 33%">'.$message.'</td>
	   <td style="width: 33%" class="cardbox">'.echo_pl(4).'</td>
	</tr>
	<tr>
	   <td> </td>
	   <td class="cardbox">'.echo_pl(1).'</td>
	   <td> </td>
	</tr>';

// Show the player's cards if it's not the end of the round
if(!isset($end)){
	echo '<tr><td colspan="3" style="text-align: center; padding-top: 7px"><i>Your cards</i>';
	players_cards();
	echo '</td></tr>';
}
echo '</table>';



// #####################
// End main flow control
//
// Function definitions below
// #####################

// --------------
// echo_pl(): Outputs the card a player has played in a readable format
// --------------
function echo_pl($i){
	global $pl_card, $points;
	$begin = '<span class="plyrname">Player '.$i.' &nbsp; </span><br />'."\r";
	if($i == 1){ $begin = str_replace('Player 1', 'You', $begin); }
	if(isset($pl_card[$i])){
		if(strpos($pl_card[$i], 'diams') !== false || strpos($pl_card[$i], 'hearts') !== false){
			return $begin.'<span style="color: #600">'.$pl_card[$i].'</span>';
		}
		else{
			return $begin.$pl_card[$i];
		}
	}
	else{
		return $begin.'&nbsp;<br />&nbsp;';
	}
}

// --------------
// give_cards(): Give out cards randomly to the four players
// --------------
function give_cards(){
	global $player_cards;
	
	// Set $gaia_cards to contain all cards.
	$gaia_cards = array();
	for($i = 0; $i <= 3; $i++){
		for($j = 2; $j <= 14; $j++){
			$gaia_cards[] = (string) $i.$j;
		}
	}

	// Define the array structure for $player_cards
	// Avoids errors if a player doesn't get any cards of a certain suit
	$player_cards = array();
	for($i = 1; $i <= 4; $i++){
		$player_cards[$i] = array(	0 => array(),
									1 => array(),
									2 => array(),
									3 => array()  );
	}
	
	// Give 13 random cards to the first three players
	for($i = 1; $i <= 3; $i++){
		$new_card_key = array_rand($gaia_cards, 13);
		
		foreach($new_card_key as $key){
			$cur_card = $gaia_cards[$key];
			unset($gaia_cards[$key]);
			
			$cur_suit   = substr($cur_card, 0, 1);
			$cur_number = substr($cur_card, 1);
			
			$player_cards[$i][$cur_suit][] = $cur_number;
		}
	}

	// Player 4 gets the rest of the cards
	foreach($gaia_cards as $value){
		$cur_suit   = substr($value, 0, 1);
		$cur_number = substr($value, 1);
		
		$player_cards[4][$cur_suit][] = $cur_number;
	}

	// Sort the cards properly. The code relies on this!
	for($i = 1; $i <= 4; $i++){
		for($u = 0; $u <= 3; $u++){
			natsort($player_cards[$i][$u]);
		}
	}
}

// --------------
// players_cards(): Outputs the [human] player's cards at the bottom of the page
// --------------
function players_cards(){
	global $player_cards, $SESSID, $language, $played, $twoClubs;
	$cards = $player_cards[1];

	echo '<table class="cards"><tr>';
	for($i = 0; $i <= 3; $i++){
		$currentSuit = $cards[$i];

		// Suit 1 and 3 (diamond & heart) are red
		if($i % 2 == 0){
			$color = '#000';
		}
		else{
			$color = '#600';
		}

		if (count($currentSuit) > 0) {
			foreach ($currentSuit as $card) {
				if ($i == 2 && ($card == 12 || $card == 13 || $card == 14) ) {
					$color = '#000; background-color: #fdd'; // highlight Q, K and A of spades red
				}
				echo '<td class="cardend" style="color: '.$color.';">'.$language[$i].'<br />'.card2name($card);

				if (!isset($played)) {
					$played = false;
				}
				if ($played) {
					if(!isset($twoClubs)){
						$twoClubs = false;
					}
					echo '<form action="'.$_SERVER['PHP_SELF'].'" method="post">
<input type="hidden" name="SESSID" value="'.substr($SESSID, 0, -4).'" />
<input type="hidden" value="'.$i.$card.'" name="card" />
<input type="submit" value="Play" style="font-size: 8pt" /></form>'."</td>\r";
				}
			}
		}
	}
}

// --------------
// card2name(): Output the name of the card (e.g. J instead of 11)
// --------------
function card2name($i){
	switch($i){
		case 11: return 'J';
		case 12: return 'Q';
		case 13: return 'K';
		case 14: return '<b>A</b>';
		default: return $i;
	}
}

// --------------
// next_player(): Return the number of the next player that has to play
// return false if all have played.
// --------------
function next_player($input) {
	global $pl_card;
	$input++;
	if( $input == 5) { $input = 1; }

	if (isset($pl_card[$input]) && $input < 5) {
		return false;
	}
	else if ($input > 5) {
		die('$input error:'.$input);
	}
	else {
		return $input;
	}
}

// --------------
// respond_play(): Computer player plays a card (not the starting one, though)
// --------------
function respond_play($user) {
	global $player_cards, $pl_card, $temp_cards, $language, $suit;
	$cards = $player_cards[$user];

	if (count($temp_cards) <= 0) { die('Invalid use of respond_play(); user:'.$user); }
	else if (count($temp_cards) > 4) { die('Too many <i>$temp_cards</i> elements'); }

	// Find the suit
	if (!isset($suit)) {
		for ($i = 2; $i < 5; $i++) {
			if (isset($temp_cards[$i])) {
				$suit = $temp_cards[$i]{0};
				break;
			}
		}
	}

	// Now: choose the card
	if (count($cards[$suit]) > 0) { // Player has cards in the suit
		if (count($cards[$suit]) == 1) {
			$key = key($cards[$suit]);
			$value = current($cards[$suit]);

			$temp_cards[$user] = $suit.$value;
			$pl_card[$user] = $language[$suit].'<br />'.card2name($value);
			unset($player_cards[$user][$suit][$key]);
			return NULL;
		}
		else { // A few potential cards
			if($suit == 2){ // Special Spades stuff
				if(in_array('12', $cards[2])){
					if(in_array('213', $temp_cards) || in_array('214', $temp_cards)){
						$pl_card[$user] = '&spades;<br />Q';
						$temp_cards[$user] = 212;
						foreach($cards[2] as $key => $value){
							if($value == 12){
								unset($player_cards[$user][2][$key]);
								return NULL;
							}
						}
					}
				}
				else if(count($pl_card) == 3 && (in_array('13', $cards[2]) || in_array('14', $cards[2]))){
					if(in_array('14', $cards[2])){ $card_choice = 14; }
					else{ $card_choice = 13; }
					if(!in_array('12', $temp_cards)){
						$temp_cards[$user] = '2'.$card_choice;
						$pl_card[$user] = '&spades;<br />'.card2name($card_choice);
						foreach($cards[2] as $key => $value){
							if($value == $card_choice){
								unset($player_cards[$user][2][$key]);
								return NULL;
							}
						}
					}
				}
			}

			// Find biggest possible card without taking the lot.
			$maximum = 0;
			foreach($temp_cards as $current_card){
				if($current_card{0} == $suit && substr($current_card, 1) > $maximum){
					$maximum = substr($current_card, 1);
				}
			}
			if($maximum == 0){ die('<b>Problem</b>: $suit:'.$suit.'; $maximum:'.$maximum); }

			foreach($cards[$suit] as $key => $card){
				if($card > $maximum){ break; }
				else{ $maxCard = $card; $cardKey = $key; }
			}

			if(isset($maxCard)){
				$pl_card[$user] = $language[$suit].'<br />'.card2name($maxCard);
				$temp_cards[$user] = $suit.$maxCard;
				unset($player_cards[$user][$suit][$cardKey]);
				return NULL;
			}
			else{ // No card is small enough!
				if(count($temp_cards) == 3){ // We'll take the cards anyway, so we get rid of the biggest!
					$value = end($cards[$suit]);
				}
				else{ // Not all have played. Let's hope someone else only has bigger ones
					$value = reset($cards[$suit]);
				}
				$key = key($cards[$suit]);

				$pl_card[$user] = $language[$suit].'<br />'.card2name($value);
				$temp_cards[$user] = $suit.$value;
				unset($player_cards[$user][$suit][$key]);
			}

			if (!isset($pl_card[$user])) { die('$pl_card[$user] should have been set! $user = '.$user); }
		}
	}
	else { // We don't have any cards in the suit.
		$card_choice = (string) get_worst_card($user);
		$card_suit = $card_choice{0};
		$card_choice = substr($card_choice, 1);

		foreach($player_cards[$user][$card_suit] as $key => $value){
			if($value == $card_choice){
				$pl_card[$user] = $language[$card_suit].'<br />'.card2name($card_choice);
				$temp_cards[$user] = $card_suit.$card_choice;
				unset($player_cards[$user][$card_suit][$key]);
				return NULL;
			}
		}

		echo 'Error! arrived at the end of respond_play()<br />';
		if(!isset($pl_card[$user])){ die('$pl_card[$user] should be set! $card_choice: '.$card_choice.'; $card_suit:
		'.$card_suit.'; $user = '.$user); }
	}
}

// --------------
// store_var(): Write a variable to the $SESSID file
// --------------
function store_var($var_name, $clear_contents = false){
	global $$var_name, $SESSID; // The double $ is not a typo
	if(!isset($$var_name)){
		die($var_name.' does not exist!');
	}

	if($clear_contents == true){
		$sessFile = fopen('./data/'.$SESSID, 'w');
	}
	else{ $sessFile = fopen('./data/'.$SESSID, 'a'); }

	fwrite($sessFile, '<?php $'.$var_name.' = '.var_export($$var_name, true).' ?>');
	fclose($sessFile);
}

// --------------
// get_worst_card(): Get the first card a user wants to get rid of
// --------------
function get_worst_card($user) {
	global $player_cards;
	$user_cards = $player_cards[$user];
	if(count($user_cards[2]) > 0){
		if(in_array('12', $user_cards[2])){ return '212'; }
		if(in_array('14', $user_cards[2])){ return '214'; }
		if(in_array('13', $user_cards[2])){ return '213'; }
	}

	if(isset($user_cards[3]) && count($user_cards[3]) > 0){
		$reverse = array_reverse($user_cards[3]);
		if($reverse[0] > 5){
			return '3'.$reverse[0];
		}
	}

	$cardMax = 0;
	for($i = 0; $i < 4; $i++){
		if(count($user_cards[$i]) > 0){
			foreach($user_cards[$i] as $card){
				if($card > $cardMax){
					$cardMax = $card;
					$cardCode = $i.$card;
				}
			}
		}
	}
	return $cardCode;
}

// --------------
// start_play(): Player starts the game (opposite of get_worst_card(), so to speak)
// --------------
function start_play($user) {
	global $player_cards, $pl_card, $temp_cards, $language, $heartsPlayed;
	$smallCard = 15;

	for($i = 0; $i < 4; $i++){
		if($i == 3 && (bool)$heartsPlayed == false){
			continue;
		}

		// Check out the first card of each suit and see if it's smaller
		if(isset($player_cards[$user][$i]) && count($player_cards[$user][$i]) > 0){
			$value = reset($player_cards[$user][$i]);
			if($value < $smallCard){
				$cardKey = key($player_cards[$user][$i]);
				$smallCard = $value;
				$cardCode = $i.$value;
			}
		}
	}
	if(isset($cardCode)){
		$pl_card[$user] = $language[substr($cardCode, 0, 1)].'<br />'.card2name($smallCard);
		$temp_cards[$user] = $cardCode;
		unset($player_cards[$user][substr($cardCode, 0, 1)][$cardKey]);
		return NULL;
	}

	if(!$heartsPlayed){
		$myc = $player_cards[$user];
		if(count($myc[0]) <= 0 && count($myc[1]) <= 0 && count($myc[2]) <= 0){
			$value = reset($myc[3]);
			
			foreach($myc[3] as $key => $value){
				$pl_card[$user] = '&hearts;<br />'.card2name($value);
				$temp_cards[$user] = '3'.$value;
				unset($player_cards[$user][3][$key]);
				break;
			}
		}
	}

	if(!isset($cardCode) && !isset($pl_card[$user])){
		echo $user.' : Error in start_play():<pre>';
		print_r($player_cards[$user]); exit;
	}
}


// --------------
// Footer: loading time & info
// --------------
$difference = microtime(1) - $start_time;
$difference = (string) $difference;

$precision = 0;
for($i = 0; $i < (strlen($difference)-1); $i++){
	if($difference[$i] == 0 || $difference[$i] == '.'){ 
		$precision++;
	}
	else{ $difference = round($difference, --$precision); break; }
}
?>

</body>
</html>