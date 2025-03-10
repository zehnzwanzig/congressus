<?php /*
	Copyright 2018 Cédric Levieux, Parti Pirate

	This file is part of Congressus.

    Congressus is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Congressus is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Congressus.  If not, see <http://www.gnu.org/licenses/>.
*/

require_once("config/database.php");
require_once("engine/utils/SessionUtils.php");

session_start();
$connection = openConnection();

if (isset($_REQUEST["userId"])) {
    $userId = intval($_REQUEST["userId"]);
}
else {
    $userId = SessionUtils::getUserId($_SESSION);
}

$galetteDatabase = "";

if (isset($config["galette"]["db"]) && $config["galette"]["db"]) {
    $galetteDatabase = $config["galette"]["db"];
    $galetteDatabase .= ".";
}

$queryBuilder = QueryFactory::getInstance($config["database"]["dialect"]);
$userSource = UserSourceFactory::getInstance($config["modules"]["usersource"]);
$userSource->selectQuery($queryBuilder, $config);


$queryBuilder->addSelect("gp" . ".picture");
$queryBuilder->addSelect("gp" . ".format");
$queryBuilder->join($galetteDatabase . "galette_pictures", "gp.id_adh = galette_adherents.id_adh", "gp", "left");


$queryBuilder->where("galette_adherents.id_adh = " . ":id_adh");
//$userSource->whereId($queryBuilder, $config, ":id_adh");
$args["id_adh"] = $userId;

$query = $queryBuilder->constructRequest();
$statement = $connection->prepare($query);
//echo showQuery($query, $args);

$statement->execute($args);
$results = $statement->fetchAll();

//print_r($results);

//echo count($results);

if (!count($results)) {
    header('Content-type: image/png');
    echo file_get_contents("assets/images/avatar-default.png");
    exit();
}

$user = $results[0];

if (!$user["format"]) {
    $dstfname = tempnam(sys_get_temp_dir(), 'DST');

    $letter = "";
    if ($user["pseudo_adh"]) {
        $letter = mb_strtoupper(mb_substr($user["pseudo_adh"], 0, 1));
    }
    else {
        $letter = mb_strtoupper(mb_substr($user["prenom_adh"], 0, 1));
    }

    $hash = md5($user["email_adh"], false);

    $r = hexdec(substr($hash, 0, 2));
    $g = hexdec(substr($hash, 2, 2));

    $b = 255 * 255 - $r * $r - $g * $g;
    $b = $b > 0 ? intval(sqrt($b)) : 0;

    $size = 128;

    $dst = ImageCreateTrueColor($size, $size);
    imagesavealpha($dst, true);
    $transColour = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefill($dst, 0, 0, $transColour);

    $diskColour = imagecolorallocatealpha($dst, $r, $g, $b, 0);
    imagefilledellipse($dst, intval($size / 2), intval($size / 2), $size - 1, $size - 1, $diskColour);
    $font = "assets/fonts/ubuntu-R.ttf";
    $bbox = imageftbbox(intval($size / 2) , 0, $font , $letter);

    $textColour = imagecolorallocatealpha($dst, 255, 255, 255, 0);
    imagefttext($dst, intval($size / 2), 0, intval($size / 2) - intval(($bbox[2] - $bbox[0]) / 2), intval($size / 2) - intval(($bbox[7] - $bbox[1]) / 2), $textColour, $font, $letter);
    $textColour = imagecolorallocatealpha($dst, $r, $g, $b, 100);
    imagefttext($dst, intval($size / 2), 0, intval($size / 2) - intval(($bbox[2] - $bbox[0]) / 2), intval($size / 2) - intval(($bbox[7] - $bbox[1]) / 2), $textColour, $font, $letter);

    Imagepng($dst, $dstfname);
    $user["picture"] = file_get_contents($dstfname);
}

function createThumbFromFile($srcFilePath, $dstFilePath, $maxWidth, $maxHeight, $forceDimensions){
    $size = getimagesize($srcFilePath);
    $width = $size[0];
    $height = $size[1];

    $xRatio = $maxWidth / $width;
    $yRatio = $maxHeight / $height;
    
    $offsetLeft = 0;
    $offsetTop = 0;

    if( ($width <= $maxWidth) && ($height <= $maxHeight)) {
        $thumbWidth = $width;
        $thumbHeight = $height;
        $offsetTop = $forceDimensions ? ceil(($maxHeight - $thumbHeight) / 2) : 0;
        $offsetLeft = $forceDimensions ? ceil(($maxWidth - $thumbWidth) / 2) : 0;
    }
    elseif (($xRatio * $height) < $maxHeight) {
        $thumbHeight = ceil($xRatio * $height);
        $thumbWidth = $maxWidth;
        $offsetTop = $forceDimensions ? ceil(($maxHeight - $thumbHeight) / 2) : 0;
    }
    else {
        $thumbWidth = ceil($yRatio * $width);
        $thumbHeight = $maxHeight;
        $offsetLeft = $forceDimensions ? ceil(($maxWidth - $thumbWidth) / 2) : 0;
    }

    if($size['mime'] == "image/jpeg"){
        $src = ImageCreateFromJpeg($srcFilePath);
        $dst = ImageCreateTrueColor($forceDimensions ? $maxWidth : $thumbWidth, $forceDimensions ? $maxHeight : $thumbHeight);
        imagesavealpha($dst, true);
        $transColour = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transColour);
        imagecopyresampled($dst, $src, $offsetLeft, $offsetTop, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
//        imageinterlace( $dst, true);
//        ImageJpeg($dst, $dstFilePath, 100);
        Imagepng($dst, $dstFilePath);
    } 
    else if ($size['mime'] == "image/png"){
        $src = ImageCreateFrompng($srcFilePath);
        $dst = ImageCreateTrueColor($forceDimensions ? $maxWidth : $thumbWidth, $forceDimensions ? $maxHeight : $thumbHeight);
        imagesavealpha($dst, true);
        $transColour = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transColour);
        imagecopyresampled($dst, $src, $offsetLeft, $offsetTop, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
        Imagepng($dst, $dstFilePath);
    } 
    else {
        $src = ImageCreateFromGif($srcFilePath);
        $dst = ImageCreateTrueColor($forceDimensions ? $maxWidth : $thumbWidth, $forceDimensions ? $maxHeight : $thumbHeight);
        imagesavealpha($dst, true);
        $transColour = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transColour);
        imagecopyresampled($dst, $src, $offsetLeft, $offsetTop, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
//        imagegif($dst, $dstFilePath);
        Imagepng($dst, $dstFilePath);
    }
}

$srcfname = tempnam(sys_get_temp_dir(), 'SRC');
$dstfname = tempnam(sys_get_temp_dir(), 'DST');

file_put_contents($srcfname, $user["picture"]);
createThumbFromFile($srcfname, $dstfname, 64, 64, true);

$content = file_get_contents($dstfname);

header('Content-type: image/png');
echo $content;