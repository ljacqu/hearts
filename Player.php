<?php
/**
 * Symbolizes a player in the game
 * @author ljacqu
 */
class Player {
	
	/** int[][] The cards the player has */
	private $cards;

	function __construct() {
		$this->emptyCardList();
	}
	
	function getCards() {
		return $this->cards;
	}
	
	function hasEmptyCardList() {
		foreach ($this->cards as $list) {
			if (count($list) > 0) return false;
		}
		return true;
	}
	
	/**
	 * Returns the card to play in the round.
	 * @param int The suit of the current round (constant from Game)
	 * @param int[] Array of played cards, where the key corresponds to the
	 *  player ID and the entry is the suit number and the value, e.g.
	 *  $played_cards[1] = 211 means player 1 played Spades J.
	 */
	function playCard($suit, array $playedCards, $heartsPlayed, $spadesQueenPlayed = false) {
		if (count($playedCards) == 0) {
			return $this->startRound($heartsPlayed, $spadesQueenPlayed);
		} else if (count($this->cards[$suit]) == 1) {
			return $suit . array_pop($this->cards[$suit]);
		} else if (count($this->cards[$suit]) > 1) {
			return $this->selectBestSuitCard($playedCards, $suit);
		} else {
			return $this->getWorstCard($spadesQueenPlayed);
		}
	}
	
	
	/**
	 * Adds a card to the player list. It does not check whether the player
	 * already has the given card.
	 * @param string $card The card to add, represented by the numerical
	 *  constants given in the game class. First character is the suit number,
	 *  the remaining 1-2 characters the card number.
	 */
	function addCard($card) {
		$suit   = substr($card, 0, 1);
		$number = substr($card, 1);
		
		if (!isset($this->cards[$suit])) {
			throw new Exception('Illegal suit ' . htmlspecialchars($suit) . '!');
		}
		$this->cards[$suit][] = $number;
	}
	
	/**
	 * Returns if the player has the two of clubs (to start the hand)
	 * @return bool True if player has the two of clubs, false otherwise.
	 */	
	function hasCard($card) {
		$suit   = substr($card, 0, 1);
		$number = substr($card, 1);
		return isset($this->cards[$suit]) && in_array($number, $this->cards[$suit]);
	}
	
	function hasCardsForSuit($suit) {
		return isset($this->cards[$suit]) && count($this->cards[$suit]) > 0;
	}
	
	/**
	 * Resets the card list (empties all cards from list)
	 */
	function emptyCardList() {
		$this->cards = [
			0 => [],
			1 => [],
			2 => [],
			3 => []
		];
	}
	
	/**
	 * Sorts the player's card list if necessary.
	 */
	function sortCards() {
		for ($i = 0; $i <= Game::HEARTS; ++$i) {
			natsort($this->cards[$i]);
		}
	}
	
	/**
	 * Finds the best same-suit card to play in a round and removes it from the 
	 * player's card list. Helper method of playCard().
	 */
	private function selectBestSuitCard($playedCards, $suit) {
		// Handle special cases with Spades.
		if ($suit == Game::SPADES) {
			if (in_array(Game::QUEEN, $this->cards[Game::SPADES])
				&& (in_array(Game::SPADES . Game::KING, $playedCards) 
				 || in_array(Game::SPADES . Game::ACE,  $playedCards)))
			{
				$this->removeCard(Game::SPADES . Game::QUEEN);
				return Game::SPADES . Game::QUEEN;
			}
			else if (count($playedCards) == Game::N_OF_PLAYERS-1
				&& end($this->cards[Game::SPADES]) >= Game::KING)
			{
				$cardChoice = Game::SPADES . end($this->cards[Game::SPADES]);
				$this->removeCard($cardChoice);
				return $cardChoice;
			}
		}
		
		// Find the biggest card of the current suit
		$biggestPlayed = 0;
		foreach ($playedCards as $card) {
			if (substr($card, 0, 1) == $suit && substr($card, 1) > $biggestPlayed) {
				$biggestPlayed = substr($card, 1);
			}
		}
		
		$biggestPossible = 0;
		foreach ($this->cards[$suit] as $card) {
			if ($card < $biggestPlayed) {
				$biggestPossible = $card;
			} else {
				break; // cards are sorted, once $card > $biggestPlayed, we're done
			}
		}
		
		if ($biggestPossible != 0) {
			$this->removeCard($suit . $biggestPossible);
			return $suit . $biggestPossible;
		} else {
			// No card is small enough... 
			if (count($playedCards) == Game::N_OF_PLAYERS-1) {
				// We're going to take the cards, so let's get rid of the biggest
				return $suit . array_pop($this->cards[$suit]);
			} else {
				// Let's hope someone else will have a bigger card
				return $suit . array_shift($this->cards[$suit]);
				// TODO: No removeCard here?
			}
		}
	}
	
	private function getWorstCard($spadesQueenPlayed = false) {
		if (!$spadesQueenPlayed && count($this->cards[Game::SPADES]) > 0) {
			$card = 0;
			if (in_array(Game::QUEEN, $this->cards[Game::SPADES])) { 
				$card = Game::SPADES . Game::QUEEN;
			} else if (in_array(Game::ACE, $this->cards[Game::SPADES])) {
				$card = Game::SPADES . Game::ACE;
			} else if (in_array(Game::KING, $this->cards[Game::SPADES])) {
				$card = Game::SPADES . Game::KING;
			} else if (end($this->cards[Game::SPADES]) > 7) {
				$card = Game::SPADES . end($this->cards[Game::SPADES]); // TODO: check & refine
			}
			if ($card != 0) {
				$this->removeCard($card);
				return $card;
			}
		}
		
		$max = 0;
		$maxSuit = 0;
		foreach ($this->cards as $suit => $cardsOfSuit) {
			foreach ($cardsOfSuit as $value) { // TODO: Sorted property of cards!!
				if ($value > $max) {
					$max = $value;
					$maxSuit = $suit;
				}
			}
		}

		$this->removeCard($maxSuit . $max);
		return $maxSuit . $max;
	}
	
	/**
	 * Choose card to start a new round
	 * @param bool $heartsPlayed Whether Hearts have already been played
	 * @param bool $spadesQueenPlayed Whether the Queen of Spades has been
	 *  played yet.
	 * @return string Card ID the player wants to use
	 */
	private function startRound($heartsPlayed, $spadesQueenPlayed) {
		$minCard = 15;
		$minCardSuit = -1;
		
		for ($i = 0; $i < 4; ++$i) {
			if ($i == Game::HEARTS && !$heartsPlayed
				|| count($this->cards[$i]) == 0)
			{
				continue;
			}
			$minSuitValue = reset($this->cards[$i]);
			if ($minSuitValue < $minCard) {
				$minCard = $minSuitValue;
				$minCardSuit = $i;
			}
		}
		
		if ($minCardSuit != -1) {
			$this->removeCard($minCardSuit . $minCard);
			return $minCardSuit . $minCard;
		}
		
		if (count($this->cards[Game::HEARTS]) > 1) {
			$minCard = reset($this->cards[Game::HEARTS]);
			$this->removeCard(Game::HEARTS . $minCard);
			return Game::HEARTS . $minCard;
		} else {
			var_dump($this->cards);
			throw new Exception('Error in startRound; did not expect empty card list');
		}
	}
	
	/**
	 * 
	 * @param type $card
	 */
	function removeCard($card) {
		$suit  = substr($card, 0, 1);
		$value = substr($card, 1);
		
		foreach ($this->cards[$suit] as $key => $card) {
			if ($card == $value) {
				unset($this->cards[$suit][$key]);
				return true;
			}
		}
		return false;
	}
}
