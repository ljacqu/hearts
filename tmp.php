<?php

$nTries = 12000;
$goodCards = 3;
$totalCards = 6;


// -------------- Internal
$cardsPerPlayer = $totalCards / 3;
$combos = [];
$cByPlayer = [];

function isSuitableCardNumber($n) {
  $n = (string) $n;
  if (strpos($n, '0') !== false) {
    return false;
  }
  $len = strlen($n);
  while (!empty($n)) {
//    var_dump('  ' . $n);
    $n = str_replace($n[0], '', $n);
    $newLen = strlen($n);
    if ($len - $newLen > 1) {
      return false;
    }
    $len = $newLen;
  }
  return true;
}
for (//$i = 123456;
   $i = pow(10, $totalCards-1);
  $i <= min(999999999999, pow(10, $totalCards) - 1); ++$i) {
//  var_dump($i);
  if (isSuitableCardNumber($i)) {
    $combo = (string) $i;
    $cByPlayer[] = [
      substr($combo, 0, $cardsPerPlayer),
      substr($combo, $cardsPerPlayer, $cardsPerPlayer),
      substr($combo, $cardsPerPlayer * 2)
    ];
  }

  if (($i & 0b10000) === 0b10000) {
    mt_srand(microtime(true));
    srand(make_seed());
  }
}

/*
for ($i = 0; $i < $nTries; ++$i) {
  $numbers = range(1, $totalCards);
  if (($i & 0b10000) === 0b10000) {
    mt_srand(microtime(true));
    srand(make_seed());
  }

  shuffle($numbers);

  $combo = implode('', $numbers);
  if (!in_array($combo, $combos, true)) {
    $combos[] = $combo;
    $cByPlayer[] = [
      substr($combo, 0, $cardsPerPlayer),
      substr($combo, $cardsPerPlayer, $cardsPerPlayer),
      substr($combo, $cardsPerPlayer * 2)
    ];
  }
}*/

$goodFor1 = 0;
$badFor1 = 0;
$goodFor2 = 0;
$badFor2 = 0;
foreach ($cByPlayer as $cardsPlayer) {
  if (containsAny($cardsPlayer[0], range(1, $goodCards))) {
    ++$goodFor1;
  } else {
    ++$badFor1;
  }
  if (containsAny($cardsPlayer[1], range(1, $goodCards))) {
    ++$goodFor2;
  } else {
    ++$badFor2;
  }
}
var_dump($goodFor1, $badFor1, $badFor1 / ($badFor1 + $goodFor1));
var_dump($goodFor2, $badFor2);

function containsAny($haystack, $needles) {
  foreach ($needles as $needle) {
    if (strpos($haystack, (string) $needle) !== false) {
      return true;
    }
  }
  return false;
}

function make_seed()
{
  list($usec, $sec) = explode(' ', microtime());
  return $sec + $usec * 1000000;
}
//exit;


sort($combos);
echo count($combos) . '<hr />';
echo implode('<br />', $combos);


exit;
// Type of players to test against each other
$options = [
  ['s', GameOptions::STANDARD],
  ['a', GameOptions::ADVANCED],
  ['c', GameOptions::CARD_COUNTING]
];

$totalOptions = count($options);
$number = isset($_SESSION['eval_number']) ? $_SESSION['eval_number'] : 0;
$nextNumber = $number + 1;
echo '<h2>' . $number . '</h2>';

if ($number >= pow($totalOptions, 4)) {
  echo 'Got number: ' . $number . '. Done with all combinations!';
  exit;
}

$playerTypes = [];
$name = '';
for ($i = 3; $i >= 0; --$i) {
  $value = (int)floor($number / pow($totalOptions, $i));
  $number -= $value * pow($totalOptions, $i);

  $name = $options[$value][0] . $name;
  $playerTypes[$i] = $options[$value][1];
}
ksort($playerTypes);
