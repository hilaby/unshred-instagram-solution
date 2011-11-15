<?php
$imageResource = imagecreatefrompng('TokyoPanoramaShredded.png');

$imageWidth = imagesx($imageResource); 
$imageHeight = imagesy($imageResource);
$ySkips = 2; // how much I can tolerate in Y axes (10 is good but slow, bigger less accurate, but faster)
$minErrors = 30; // how much diff is considered a diff when comparing X axes (200 should be good)
$confidanceLevel = 20;
$redFix = 300; // red value fix

// estimating funciton
function getRelativeDiff($imageResource, $x1, $y1, $x2, $y2, $redFix = 0){
	$rgb1 = imagecolorsforindex($imageResource, imagecolorat($imageResource, $x1, $y1));
	$rgb2 = imagecolorsforindex($imageResource, imagecolorat($imageResource, $x2, $y2));
	$points = abs($rgb1['red'] - $rgb2['red'] - $redFix);
	$points += abs($rgb1['green'] - $rgb2['green']);
	$points += abs($rgb1['blue'] - $rgb2['blue']);
	return $points;
}

// ESTIMATE - how many segments do we find in the shredded image
$results = array();
for($x=0;$x<$imageWidth-1;$x++){
	$errorPoints = 0;
	for($y=0;$y<$imageHeight-1;$y=$y+$ySkips) {
			if(getRelativeDiff($imageResource, $x, $y, $x+1, $y) > $minErrors) { 
					$errorPoints++;
			}; 
	} 
	$results[$x] = $errorPoints;
}

arsort($results);
$results = array_slice($results, 0, $confidanceLevel, true);
ksort($results);
$lastKey=0;
$tmp = array();
foreach($results as $key=>$value){
	$diff = $key-$lastKey;
	if(!isset($tmp[$diff])){
			$tmp[$diff] = 1; 
	}else{
			$tmp[$diff]++;
	}
	$lastKey=$key;
}

arsort($tmp);

// this is the width estimated for each shredded segment
$estimatedAverageCutSize = key($tmp); 
// todo: need to reject the astimate if the number can't be devided equaly, and try to redo with a bigger $ySkips number
$estimatedSegments = ceil($imageWidth/$estimatedAverageCutSize); 

// Explode and GLUE everything together
$arrangments = array();
$errorPoints = array();
$connections = array();

for($x = 1; $x <= $estimatedSegments; $x++){
	for($y = 1; $y <= $estimatedSegments; $y++){
		if($y != $x){
			$xPixelToCheck = ($x * $estimatedAverageCutSize) - 1; 
			$yPixelToCheck = (($y-1) * $estimatedAverageCutSize) % $imageWidth;

			for($z=0;$z<$imageHeight;$z++) {
				$localPonts = getRelativeDiff($imageResource, $yPixelToCheck, $z, $xPixelToCheck, $z, $redFix);
				if(!isset($errorPoints[$x][$y])){
					$errorPoints[$x][$y] = 0;
				}
				$errorPoints[$x][$y] += $localPonts;
			}
		}
	}
	asort($errorPoints[$x]);
	$errorPoints[$x] = key($errorPoints[$x]);
}

// check the farthest run
$runs = array();
foreach($errorPoints as $key=>$value){
	$runs[$key] = array();
	$newKeyStartPoint = $key;
	array_push($runs[$key], $key);

	for($x = 0; $x < count($errorPoints); $x++){
		$right = $errorPoints[$newKeyStartPoint];
		if(in_array($right, $runs[$key])){
			break;
		}
		array_push($runs[$key], $right);
		$newKeyStartPoint = $right;
	}

	$runs[$key] = $runs[$key];
	$toSort[array_sum($runs[$key])] = $key;
}

krsort($toSort);
$newImagePart = 0;
$logestRun = $runs[$toSort[key($toSort)]];


$newImageResource = imagecreatetruecolor($imageWidth, $imageHeight);
foreach($logestRun as $parts){
	$eiacs = $estimatedAverageCutSize;
	imagecopy ( $newImageResource , $imageResource , $newImagePart*$eiacs , 0 , ($parts-1)*$eiacs  , 0 ,  $eiacs , $imageHeight);
	$newImagePart++;
}

header('Content-type: image/png');
imagepng($newImageResource);
imagedestroy($newImageResource);
imagedestroy($imageResource);


