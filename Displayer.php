<?php

/**
 * Display the game to the user.
 * @author ljacqu
 */
class Displayer {
	
	/** 
	 * The Game instance the object should output info for
	 * @var Game
	 */
	private $game;
	
	function __construct(Game $game) {
		$this->game = $game;
	}
	
	function draw($centerMessage, $playable, $hasContinuation=true) {
		$tags = $this->prepareActiveCards();
		$tags['card_table'] = $this->prepareCardPane($playable);
		$tags['table_center'] = $centerMessage;
		$tags['hand_nr'] = $this->game->getHandNumber();
		if (!$playable && $hasContinuation) {
			$tags['table_center'] .= "\r<form action=\"{$_SERVER['PHP_SELF']}\" method=\"post\">
				<input type=\"submit\" value=\"Continue\" />
			</form>";
		}
		
		$htmlTemplate = file_get_contents('body.html');
		echo $this->replaceTags($htmlTemplate, $tags);
	}
	
	/**
	 * Generate the HTML for the card bar, displaying all the player's cards.
	 * @param bool $playable Whether the cards are playable or not (submitable)
	 * @return string The generated HTML for the output page
	 */
	function prepareCardPane($playable) {
		$htmlTemplate = $playable
				? file_get_contents('cardentryplayable.html')
				: file_get_contents('cardentry.html');
		$cards = $this->game->getHumanCards();
		
		$output = '';
		foreach ($cards as $suit => $cardsOfSuit) {
			foreach ($cardsOfSuit as $card) {
				$output .= "\r" . $this->prepareCardCell($htmlTemplate, $suit.$card);
			}
		}
		return $output;
	}
	
	private function prepareCardCell($htmlTemplate, $card) {
		$htmlSuit   = $this->getHtmlCardSuit($card);
		$htmlNumber = $this->getHtmlCardNumber($card);
		$tags = array();
		$tags['color']  = $this->getHtmlCardColor($card);
		$tags['card']   = $htmlSuit . '<br />' . $htmlNumber;
		$tags['formaction'] = $_SERVER['PHP_SELF'];
		$tags['cardcode'] = $card;
		return $this->replaceTags($htmlTemplate, $tags);
	}
	
	private function replaceTags($html, $tags) {
		foreach ($tags as $name => $value) {
			$html = str_replace('{'.$name.'}', $value, $html);
		}
		return $html;
	}
	
	/**
	 * Define the tags for the board showing the current round
	 * @return The generated tag entries as array
	 */
	function prepareActiveCards() {
		$tags = array();
		$activeCards = $this->game->getCurrentRoundCards();
		for ($i = 0; $i < Game::N_OF_PLAYERS; ++$i) {
			$key = 'player' . ($i+1) . '_card';
			if (!isset($activeCards[$i])) {
				$tags[$key] = '&nbsp;';
			} else {
				$card = $activeCards[$i];
				$color  = $this->getHtmlCardColor($card);
				$suit   = $this->getHtmlCardSuit($card);
				$number = $this->getHtmlCardNumber($card);
				$tags[$key] = "<span class=\"{$color}\">{$suit}<br />{$number}</span>";
			}
		}
		return $tags;
	}
	
	
	function roundEndMessage($nextPlayer) {
		if ($nextPlayer === null) {
			$pointsTable = $this->generatePointsTable();
			if ($this->game->getState() == Game::GAME_END) {
				$message = 'End of game!<br />' . $pointsTable;
				$hasContinuation = false;
			} else {
				$message = 'End of hand.<br />' . $pointsTable;
				$hasContinuation = true;
			}
			$this->draw($message, false, $hasContinuation);
		} else if ($nextPlayer == Game::HUMAN_ID) {
			$this->draw('You start.', false);
		} else {
			$this->draw('Player ' . ($nextPlayer+1) . ' starts.', false);
		}
	}
	
	function handleCardError($code) {
		switch ($code) {
			case Game::MOVE_BAD_SUIT:
				$message = 'Wrong suit!'; break;
			case Game::MOVE_NO_HEARTS:
				$message = 'You cannot play hearts yet!'; break;
			case Game::MOVE_BAD_CARD:
				$message = 'Please play a card.'; break;
			default:
				throw new Exception('Unknown error code ' . htmlspecialchars($code));
		}
		$this->draw($message, true);
	}
	
	private function generatePointsTable() {
		$points = $this->game->getPoints();
		$sum = array_fill(0, Game::N_OF_PLAYERS, 0);
		
		$output = "<table class=\"points\">\r <tr>";
		for ($i = 0; $i < Game::N_OF_PLAYERS; ++$i) {
			if ($i == Game::HUMAN_ID) {
				$output .= '<th>You</th>';
			} else {
				$output .= '<th> ' . ($i+1) . ' </th>';
			}
		}
		$output .= '</tr>';

		foreach ($points as $entries) {
			$output .= "\r <tr>";
			foreach ($entries as $player => $entry) {
				$output .= "<td>$entry</td>";
				$sum[$player] += $entry;
			}
			$output .= '</tr>';
		}

		$output .= "\r <tr class=\"sum\">";
		for ($i = 0; $i < Game::N_OF_PLAYERS; ++$i) {
			$output .= '<td>' . $sum[$i] . '</td>';
		}
		$output .= "</tr>\r</table>";
		return $output;
	}
	
	private function getHtmlCardSuit($card) {
		$suit = substr($card, 0, 1);
		switch ($suit) {
			case Game::CLUBS:    return '&clubs;';
			case Game::DIAMONDS: return '&diams;';
			case Game::SPADES:   return '&spades;';
			case Game::HEARTS:   return '&hearts;';
			default: throw new Exception("Unknown suit: " . htmlspecialchars($suit));
		}
	}
	
	private function getHtmlCardNumber($card) {
		$number = substr($card, 1);
		switch ($number) {
			case Game::JACK:  return 'J';
			case Game::QUEEN: return 'Q';
			case Game::KING:  return 'K';
			case Game::ACE:   return 'A';
			default: return $number;
		}
	}
	
	private function getHtmlCardColor($card) {
		$suit = substr($card, 0, 1);
		return ($suit == Game::DIAMONDS || $suit == Game::HEARTS)
			   ? 'red' : 'black';
	}
	
	
}
