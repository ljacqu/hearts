<?php

/**
 * For evaluation purposes: contains multiple implementations and outputs whenever the player implementations
 * would choose a different card.
 */
class PlayerCombiner implements Player {

  /** @var int this player's ID (0-based). */
  private $playerId;

  /** @var Player[] the wrapped players. */
  private $players;

  function __construct($playerId) {
    $this->playerId = $playerId;

    $this->players = [
      new StandardPlayer(),
      new AdvancedPlayer($playerId),
      new CardCountingPlayer($playerId)
    ];
  }

  function startHand($playerCards) {
    $choices = array_map(function ($pl) use ($playerCards) {
      return $pl->startHand($playerCards);
    }, $this->players);
    $this->outputChoices('start hand', $choices);
    return end($choices);
  }

  function startRound($playerCards, $heartsPlayed) {
    $choices = array_map(function ($pl) use ($playerCards, $heartsPlayed) {
      return $pl->startRound($playerCards, $heartsPlayed);
    }, $this->players);
    $this->outputChoices('start round', $choices);
    return end($choices);
  }

  function playInRound($playerCards, $suit, array $playedCards) {
    $choices = array_map(function ($pl) use ($playerCards, $suit, $playedCards) {
      return $pl->playInRound($playerCards, $suit, $playedCards);
    }, $this->players);
    $this->outputChoices('in round', $choices);
    return end($choices);
  }

  function processRound($suit, array $playedCards) {
    foreach ($this->players as $player) {
      $player->processRound($suit, $playedCards);
    }
  }

  function processCardsForNewHand($playerCards) {
    foreach ($this->players as $player) {
      $player->processCardsForNewHand($playerCards);
    }
  }

  private function outputChoices($name, array $choices) {
    $firstChoice = (string) $choices[0];
    $hasDifferentChoice = false;
    foreach ($choices as $choice) {
      if ((string) $choice !== $firstChoice) {
        $hasDifferentChoice = true;
        break;
      }
    }

    if ($hasDifferentChoice) {
      $descriptions = [];
      foreach ($choices as $i => $choice) {
        $descriptions[] = get_class($this->players[$i]) . '=' . $this->toCard($choice);
      }

      $readableId = $this->playerId + 1;
      echo "<br />Player $readableId ($name): " . implode(', ', $descriptions);
    }
  }

  private function toCard($cardCode) {
    $suit = Card::getCardSuit($cardCode);
    $rank = Card::getCardRank($cardCode);

    switch ($suit) {
      case Card::CLUBS:    $suit = '♣'; break;
      case Card::DIAMONDS: $suit = '♦'; break;
      case Card::SPADES:   $suit = '♠'; break;
      case Card::HEARTS:   $suit = '♥'; break;
    }

    switch ($rank) {
      case Card::JACK:  $rank = 'J'; break;
      case Card::QUEEN: $rank = 'Q'; break;
      case Card::KING:  $rank = 'K'; break;
      case Card::ACE:   $rank = 'A'; break;
    }

    return "<b>" . $suit . $rank . '</b>';
  }
}