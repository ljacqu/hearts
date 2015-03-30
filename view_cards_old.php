<?php
error_reporting(E_ALL);
$SESSID = $_COOKIE['sessid'];

if(strpos($SESSID, '/') !== false){
	die('Tampering!');
}

if(!file_exists('./data/'.$SESSID)) die('File not found!');
require('./data/'.$SESSID);

error_reporting(0);

$count = 0;
for($i = 0; $i < 4; $i++){
	$count += count($player_cards[1][$i]);
}
?>
<link rel="stylesheet" type="text/css" href="style.css" />
<style type="text/css">
<!--
td {
	width: <?php
echo floor(100/$count); ?>%;
}
h3 {
	text-align: center;
}
h4 { font-size: 15pt; }
table.tabl td { border: 1px solid #000; padding: 3px; }
table.tabl { border-collapse: collapse; }
-->
</style>
<?php
$language = array(0 => '&clubs;', 1 => '&diams;', 2 => '&spades;', 3 => '&hearts;');
for($i = 1; $i < 5; $i++){
	echo '<h3>Player '.$i.'</h3><table style="width: 90%; margin-left: 50px auto"><tr>';
	for($u = 0; $u < 4; $u++){
		if(count($player_cards[$i][$u])){
			if($u % 2 == 0){
				$color = '000';
			}
			else{
				$color = '900';
			}
			foreach($player_cards[$i][$u] as $card){
				if($card > 11){
					$color .= '; background-color: #fcc';
					if($u == 2){
						$color = str_replace('#fcc', '#aaf', $color);
					}
				}
				echo '<td class="cardend" style="color: #'.$color.'">'.$language[$u].'<br />';
				echo card2name($card).'</td>';
			}
		}
	}
	echo '</tr></table>';
}

function card2name($input){
	switch($input){
		case 11: return 'J';
		case 12: return 'Q';
		case 13: return 'K';
		case 14: return '<b>A</b>';
		default: return $input;
	}
}

echo '<table class="tabl" style="margin-left: auto; margin-right: auto">
 <tr><td>$temp_cards</td><td>$hearts_played</td><td>$points</td></tr>
 <tr><td><pre>'; print_r($temp_cards); echo '</td><td><pre>';
	if($hearts_played){
		echo '<span style="color: #0f0">TRUE</span>';
	}
	else{
		echo '<span style="color: #f00">FALSE</span>';
	}
echo '</td><td><pre>'; print_r($points); 
echo '</td></tr><tr><td colspan="3">';
highlight_string(file_get_contents('./data/'.$SESSID));
echo '</td></tr></table>';