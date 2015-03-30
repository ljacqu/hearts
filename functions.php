<?php

function save_game_to_file(Game $game, $id) {
	if (!is_valid_game_id($id)) {
		die('Invalid game ID: ' . htmlspecialchars($id));
	}
	
	$open = fopen(id_to_filename($id), 'w');
	fwrite($open, '<?php $game = ' . var_export($game, true) . ';');
	fclose($open);
}

function is_valid_game_id($id) {
	return is_scalar($id) && preg_match('/^[a-z0-9]+$/i', $id);
}

function game_exists($id) {
	return file_exists(id_to_filename($id));
}

function id_to_filename($id) {
	return './data/' . $id . '.php';
}

function create_new_game_id() {
	$sessid = uniqid();
	while (game_exists($sessid)) {
		$sessid = uniqid();
	}
	return $sessid;
}

function post_is_valid_card_format($index) {
	return isset($_POST[$index])
	 && is_scalar($_POST[$index])
	 && preg_match('/^[0-9]{2,3}$/', $_POST[$index]);
}