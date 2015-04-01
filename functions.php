<?php

function save_game_to_session(Game $game) {
	$_SESSION['game'] = serialize($game);
}

function post_is_valid_card_format($index) {
	return isset($_POST[$index])
	 && is_scalar($_POST[$index])
	 && preg_match('/^[0-9]{2,3}$/', $_POST[$index]);
}