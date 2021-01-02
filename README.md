Hearts in PHP
=============
A PHP 5 implementation of the card game [Hearts](https://en.wikipedia.org/wiki/Hearts).
In a round, the player of the largest card in the hand's suit takes the lot and 
has to start the next round. Cards of the Hearts suit cost you one point each; 
the queen of spades gives you 13 points. You want to get as few points as 
possible.

This implementation does not require players to pass cards among each other in 
the beginning. If a player collects all 26 points in a hand, he won't get any 
points and all other players will receive 26 points. The game ends once a 
player has reached 100 points.

States
------
The `Game` class maintains a `$state` to keep track of what actions it
expects. The table below shows the transitions from state to state.

| old v / new > | HAND_START | AWAITING_HUMAN | ROUND_END | GAME_END |
| ------------- | ---------- | -------------- | --------- | -------- |
| **HAND_START** | — | Continue button | — | — |
| **AWAITING_HUMAN** | valid card + no more cards in hand | — | valid card + more cards in hand | valid card + no more cards in hand + someone has >= 100 pts | — |
| **ROUND_END** | No one with >= 100 points | — | — | — |
| **GAME_END** | — | — | — | — |
