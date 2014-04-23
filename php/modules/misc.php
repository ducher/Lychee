<?php

/**
 * @name		Misc Module
 * @author		Philipp Maurer
 * @author		Tobias Reich
 * @copyright	2014 by Philipp Maurer, Tobias Reich
 */

if (!defined('LYCHEE')) exit('Error: Direct access is not allowed!');

function openGraphHeader($photoID) {

	global $database;

	$photoID = mysqli_real_escape_string($database, $photoID);

	$result	= $database->query("SELECT title, description, url FROM lychee_photos WHERE id = '$photoID';");
	$row	= $result->fetch_object();

	$parseUrl	= parse_url("http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
	$picture	= $parseUrl['scheme']."://".$parseUrl['host'].$parseUrl['path']."/../uploads/big/".$row->url;

	$return = '<!-- General Meta Data -->';
	$return .= '<meta name="title" content="'.$row->title.'" />';
	$return .= '<meta name="description" content="'.$row->description.' - via Lychee" />';
	$return .= '<link rel="image_src" type="image/jpeg" href="'.$picture.'" />';

	$return .= '<!-- Twitter Meta Data -->';
	$return .= '<meta name="twitter:card" content="photo">';
	$return .= '<meta name="twitter:title" content="'.$row->title.'">';
	$return .= '<meta name="twitter:image:src" content="'.$picture.'">';

	$return .= '<!-- Facebook Meta Data -->';
	$return .= '<meta property="og:title" content="'.$row->title.'">';
	$return .= '<meta property="og:image" content="'.$picture.'">';

	return $return;

}

function search($term) {

	global $database, $settings;

	$return['albums'] = '';

	// Photos
	$result = $database->query("SELECT id, title, tags, sysdate, public, star, album, thumbUrl FROM lychee_photos WHERE title like '%$term%' OR description like '%$term%' OR tags like '%$term%';");
	while($row = $result->fetch_assoc()) {
		$return['photos'][$row['id']]				= $row;
		$return['photos'][$row['id']]['sysdate']	= date('d F Y', strtotime($row['sysdate']));
	}

	// Albums
	$result = $database->query("SELECT id, title, public, sysdate, password FROM lychee_albums WHERE title like '%$term%' OR description like '%$term%';");
	$i		= 0;
	while($row = $result->fetch_object()) {

		// Info
		$return['albums'][$row->id]['id']		= $row->id;
		$return['albums'][$row->id]['title']	= $row->title;
		$return['albums'][$row->id]['public']	= $row->public;
		$return['albums'][$row->id]['sysdate']	= date('F Y', strtotime($row->sysdate));
		$return['albums'][$row->id]['password']	= ($row->password=='' ? false : true);

		// Thumbs
		$result2	= $database->query("SELECT thumbUrl FROM lychee_photos WHERE album = '" . $row->id . "' " . $settings['sorting'] . " LIMIT 0, 3;");
		$k			= 0;
		while($row2 = $result2->fetch_object()){
			$return['albums'][$row->id]["thumb$k"] = $row2->thumbUrl;
			$k++;
		}

		$i++;

	}

	return $return;

}

function update($version = '') {

	global $database, $configVersion;

	// Albums
	if(!$database->query("SELECT `description` FROM `lychee_albums` LIMIT 1;"))	$database->query("ALTER TABLE `lychee_albums` ADD `description` VARCHAR( 1000 ) NULL DEFAULT ''"); // v2.0
	if($database->query("SELECT `password` FROM `lychee_albums` LIMIT 1;"))		$database->query("ALTER TABLE `lychee_albums` CHANGE `password` `password` VARCHAR( 100 ) NULL DEFAULT ''"); // v2.0

	// Photos
	if($database->query("SELECT `description` FROM `lychee_photos` LIMIT 1;"))	$database->query("ALTER TABLE `lychee_photos` CHANGE `description` `description` VARCHAR( 1000 ) NULL DEFAULT ''"); // v2.0
	if(!$database->query("SELECT `tags` FROM `lychee_photos` LIMIT 1;"))		$database->query("ALTER TABLE `lychee_photos` ADD `tags` VARCHAR( 1000 ) NULL DEFAULT ''"); // v2.1
	$database->query("UPDATE `lychee_photos` SET url = replace(url, 'uploads/big/', ''), thumbUrl = replace(thumbUrl, 'uploads/thumb/', '')");

	// Settings
	$database->query("ALTER TABLE `lychee_settings` CHANGE `value` `value` VARCHAR( 200 ) NULL DEFAULT ''"); // v2.1.1
	$result = $database->query("SELECT `key` FROM `lychee_settings` WHERE `key` = 'dropboxKey' LIMIT 1;");
	if ($result->num_rows===0) $database->query("INSERT INTO `lychee_settings` (`key`, `value`) VALUES ('dropboxKey', '')"); // v2.1

	// Config
	if ($version!==''&&$configVersion!==$version) {
		$data = file_get_contents('../data/config.php');
		$data = preg_replace('/\$configVersion = \'[\w. ]*\';/', "\$configVersion = '$version';", $data);
		if (file_put_contents('../data/config.php', $data)===false) return 'Error: Could not save updated config!';
	}

	return true;

}

function fastimagecopyresampled (&$dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h, $quality = 3) {
  // Plug-and-Play fastimagecopyresampled function replaces much slower imagecopyresampled.
  // Just include this function and change all "imagecopyresampled" references to "fastimagecopyresampled".
  // Typically from 30 to 60 times faster when reducing high resolution images down to thumbnail size using the default quality setting.
  // Author: Tim Eckel - Date: 09/07/07 - Version: 1.1 - Project: FreeRingers.net - Freely distributable - These comments must remain.
  //
  // Optional "quality" parameter (defaults is 3). Fractional values are allowed, for example 1.5. Must be greater than zero.
  // Between 0 and 1 = Fast, but mosaic results, closer to 0 increases the mosaic effect.
  // 1 = Up to 350 times faster. Poor results, looks very similar to imagecopyresized.
  // 2 = Up to 95 times faster.  Images appear a little sharp, some prefer this over a quality of 3.
  // 3 = Up to 60 times faster.  Will give high quality smooth results very close to imagecopyresampled, just faster.
  // 4 = Up to 25 times faster.  Almost identical to imagecopyresampled for most images.
  // 5 = No speedup. Just uses imagecopyresampled, no advantage over imagecopyresampled.

  if (empty($src_image) || empty($dst_image) || $quality <= 0) { return false; }
  if ($quality < 5 && (($dst_w * $quality) < $src_w || ($dst_h * $quality) < $src_h)) {
    $temp = imagecreatetruecolor ($dst_w * $quality + 1, $dst_h * $quality + 1);
    imagecopyresized ($temp, $src_image, 0, 0, $src_x, $src_y, $dst_w * $quality + 1, $dst_h * $quality + 1, $src_w, $src_h);
    imagecopyresampled ($dst_image, $temp, $dst_x, $dst_y, 0, 0, $dst_w, $dst_h, $dst_w * $quality, $dst_h * $quality);
    imagedestroy ($temp);
  } else imagecopyresampled ($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);
  return true;
}

?>
