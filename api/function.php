<?php
require_once 'vendor/autoload.php';
use \Firebase\JWT\JWT;

/* F U N C T I O N */
/* GENERATE TOKEN */
function generateToken($id, $browser, $ip, $role = array(), $type = 'user') {
	$token	= randomString(20).$id;							// token
	$now	= date('Y-m-d H:i:s');							// now
	$idType	= ($type == 'admin') ? "id_admin":"id_user";	// user or admin

	/* Insert Login */
	require_once 'mysql.php';
	$db = connect_db();
	$sql	=	"INSERT INTO ".DB_PREFIX."login (".$idType.", token, browser, ip, login_time, update_time)
	VALUES ('".$id."', '".$token."', '".$browser."', '".$ip."', '".$now."', '".$now."')";
	$insert	= $db->query($sql);

	if ($insert) {
		$tokenData = [
			$idType		=> $id,
			'token'		=> $token,
			'role'		=> $role
		];
		$tokenJWT = JWT::encode($tokenData, SERVER_KEY);

		if (strpos($tokenJWT, "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9") == 0) {
			$tokenJWTArr	= explode(".", $tokenJWT);
			$tokenJWT		= implode(".", array_splice($tokenJWTArr, 1, 2));
		}
		return $tokenJWT;
	}
	return false;
}

/* GET ID USER / ID ADMIN FROM TOKEN */
function checkTokenId($tokenJWT, $type = 'user') {
	$now	= date('Y-m-d H:i:s');							// now
	$idType	= ($type == 'admin') ? "id_admin":"id_user";	// user or admin
	$logout	= 1;											// auto logout in hour

	if ($tokenJWT) {
		try {
			$tokenData = JWT::decode("eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9." .$tokenJWT, SERVER_KEY, array('HS256'));
			if ($tokenData) {
				$id		= $tokenData->$idType;
				$token	= $tokenData->token;

				/* Check User Login Token */
				require_once 'mysql.php';
				$db = connect_db();
				$sql	= "SELECT id FROM ".DB_PREFIX."login WHERE is_active = '1' AND ".$idType." = '".$id."' AND token = '".$token."'";
				$result	= $db->query($sql);

				if ($result->num_rows > 0) {
					/* Update Login Time */
					$sql = "UPDATE ".DB_PREFIX."login SET update_time = '".$now."' WHERE is_active = '1' AND ".$idType." = '".$id."' AND token = '".$token."'";
					$db->query($sql);

					/* Auto Logout */
					//$sql = "UPDATE rs_login SET is_active = '0' WHERE is_active = '1' AND time < DATE_ADD('".$now."', INTERVAL -".$logout." HOUR) AND is_developer != '1'";
					//$db->query($sql);
					return $id;
				} else {
					return false;
					//return $token;
				}
			}
		} catch (\Exception $e) {
			//Invalid token from header
			//return "error";
			return false;
		}
	} else {
		return false;
	}
}

/* GET ROLE FROM TOKEN - ADMIN ONLY */
function checkTokenRole($tokenJWT) {
	$now	= date('Y-m-d H:i:s');		// now

	if ($tokenJWT) {
		try {
			$tokenData = JWT::decode("eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9." .$tokenJWT, SERVER_KEY, array('HS256'));
			if ($tokenData) {
				$id		= $tokenData->id_admin;
				$token	= $tokenData->token;
				$role	= $tokenData->role;

				/* Check User Login Token */
				require_once 'mysql.php';
				$db = connect_db();
				$sql	= "SELECT id FROM ".DB_PREFIX."login WHERE is_active = '1' AND id_admin = '".$id."' AND token = '".$token."'";
				$result	= $db->query($sql);

				if ($role && $result->num_rows > 0) {
					/* Update Login Time */
					$sql = "UPDATE ".DB_PREFIX."login SET update_time = '".$now."' WHERE is_active = '1' AND id_admin = '".$id."' AND token = '".$token."'";
					$db->query($sql);

					return $role;
				} else {
					return false;
				}
			}
		} catch (\Exception $e) {
			//Invalid token from header
			//return "error";
			return false;
		}
	} else {
		return false;
	}
}

/* GENERATE 2 STEP CODE */
function generate2StepCode($username) {
	$code	= random_int(100000, 999999);
	$now	= date('Y-m-d H:i:s');		// now
	try {
		require_once 'mysql.php';
		$db = connect_db();
		$sql = "UPDATE ".DB_PREFIX."user SET login_code	= '".password_hash($code, PASSWORD_BCRYPT)."', update_time = '".$now."' WHERE username = '".$username."'";
		if ($db->query($sql) === TRUE) {
			return $code;
		} else {
			return false;
		}
	} catch (\Exception $e) {
		return false;
	}
}

/* CHECK DUPLICATE USERNAME */
function checkDuplicateUsername($username) {
	try {
		require_once 'mysql.php';
		$db = connect_db();
		$result = $db->query("SELECT id FROM ".DB_PREFIX."user WHERE username = '".$username."'");
		if ($result->num_rows == 0) {
			return true;
		} else {
			return false;
		}
	} catch (\Exception $e) {
		return false;
	}
}

/* CHECK DUPLICATE EMAIL */
function checkDuplicateEmail($email, $id = '', $type = 'user') {
	$where	= ($id) ? " AND id != '".$id."'":"";
	try {
		require_once 'mysql.php';
		$db = connect_db();
		$result = $db->query("SELECT id FROM ".DB_PREFIX.$type." WHERE (email = '".$email."' OR email_temp = '".$email."')".$where);
		if ($result->num_rows == 0) {
			return true;
		} else {
			return false;
		}
	} catch (\Exception $e) {
		return false;
	}
}

/* CHECK DUPLICATE DATA (GENERAL) */
function checkDuplicateData($table, $field, $value, $id = '', $id2 = '', $id_field2 = '', $id_field = 'id') {
	$where	= ($id) ? " AND ".$id_field." != '".$id."'":"";
	$where2	= ($id2) ? " AND ".$id_field2." = '".$id2."'":"";
	try {
		require_once 'mysql.php';
		$db = connect_db();
		$result	= $db->query("SELECT id FROM ".DB_PREFIX.$table." WHERE ".$field." = '".$value."'".$where.$where2);
		if ($result->num_rows == 0) {
			return true;
		} else {
			//return "SELECT id FROM ".DB_PREFIX.$table." WHERE ".$field." = '".$value."'".$where.$where2;
			return false;
		}
	} catch (\Exception $e) {
		//return "SELECT id FROM ".DB_PREFIX.$table." WHERE ".$field." = '".$value."'".$where.$where2;
		return false;
	}
}

/* GET USER ID FROM USERNAME */
function checkUsernameId($username) {
	if ($username) {
		try {
			require_once 'mysql.php';
			$db = connect_db();
			$result = $db->query("SELECT id FROM ".DB_PREFIX."admin WHERE username = '".$username."'");
			if ($result->num_rows > 0) {
				$data	= array();
				while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
					$data[] = $row;
				}
				return $data[0]['id'];
			} else {
				return false;
			}
		} catch (\Exception $e) {
			return false;
		}
	} else {
		return false;
	}
}

/* LOG ACTIVITY */
function logActivity($id, $activity, $table_id = '0', $table_name = '', $type = 'user') {
	$now	= date('Y-m-d H:i:s');							// now
	$idType	= ($type == 'admin') ? "id_admin":"id_user";	// user or admin

	/* Insert Activity */
	require_once 'mysql.php';
	$db = connect_db();
	$sql	=	"INSERT INTO ".DB_PREFIX."log (".$idType.", activity, table_id, table_name, log_time)
	VALUES ('".$id."', '".$activity."', '".$table_id."', '".$table_name."', '".$now."')";
	$db->query($sql);
	if ($db) {
		return true;
	} else {
		return false;
	}
}

/* GET IMAGE EXTENSION */
function mime2ext($mimeType) {
	if ("image/jpeg") {
		$ext = "jpg";
	} else if ("image/gif") {
		$ext = "gif";
	} else if ("image/png") {
		$ext = "png";
	} else {
		$ext = "";
	}
	return $ext;
}

/* CONVERT TABLE FIELD */
function convertTableField($table, $fieldSource, $valueSource, $fieldTarget='id') {
	if ($table && $fieldSource && $valueSource) {
		require_once 'mysql.php';
		$db = connect_db();

		$result = $db->query("SELECT ".$fieldTarget." FROM ".$table." WHERE ".$fieldSource." = '".$valueSource."'");
		if ($result->num_rows > 0) {
			$data	= array();
			while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
				$data[] = $row;
			}
			return $data[0][$fieldTarget];
			//return "SELECT ".$fieldTarget." FROM ".$table." WHERE ".$fieldSource." = '".$valueSource."'";

		} else {
			return "not_found";
		}

	} else {
		return "error";
	}
}


/* ----------------------------------------------------------- */

/* GENERAL */

/* Create Random String */
function randomString($length) {	// Create random string
	$randstr = "";
	for ($i=0; $i<$length; $i++) {
		$randnum = mt_rand(0,61);
		while ($randnum == 0 || $randnum == 24 || $randnum == 50) {
			$randnum = mt_rand(0,61);
		}

		if ($randnum < 10) {
			$randstr .= chr($randnum+48);
		} else if ($randnum < 36) {
			$randstr .= chr($randnum+55);
		} else {
			$randstr .= chr($randnum+61);
		}
	}
	return $randstr;
}

/* Create Thumbnail */
function createThumb($file_orig, $file_target, $width_target, $height_target) {
	$size = getimagesize($file_orig);

	// Content type
	header("Content-type: {$size['mime']}");

	// Get new dimensions
	//list($width_orig, $height_orig) = getimagesize($file_orig);
	$width_orig = $size[0];
	$height_orig = $size[1];

	$ratio_orig = $width_orig / $height_orig;

	$width_target	= ($width_orig < $width_target && $height_orig < $height_target) ? $width_orig:$width_target;
	$height_target	= ($width_orig < $width_target && $height_orig < $height_target) ? $height_orig:$height_target;

	if ($width_target / $height_target > $ratio_orig) {
		$width_target = $height_target * $ratio_orig;
	} else {
		$height_target = $width_target / $ratio_orig;
	}

	// Resample
	$image_target = imagecreatetruecolor($width_target, $height_target);
	if ($size['mime'] == "image/gif") { $image_orig = imagecreatefromgif($file_orig); }
	else if ($size['mime'] == "image/jpeg") { $image_orig = imagecreatefromjpeg($file_orig); }
	else if ($size['mime'] == "image/png") {
		$image_orig = imagecreatefrompng($file_orig);

		// integer representation of the color black (rgb: 0,0,0)
		$background = imagecolorallocate($image_target , 0, 0, 0);
        // removing the black from the placeholder
		imagecolortransparent($image_target, $background);

        // turning off alpha blending (to ensure alpha channel information
        // is preserved, rather than removed (blending with the rest of the
        // image in the form of black))
		imagealphablending($image_target, false);

        // turning on alpha channel information saving (to ensure the full range
        // of transparency is preserved)
		imagesavealpha($image_target, true);
	}
	imagecopyresampled($image_target, $image_orig, 0, 0, 0, 0, $width_target, $height_target, $width_orig, $height_orig);

	// Output
	if ($size['mime'] == "image/gif") { imagegif($image_target, $file_target); }
	else if ($size['mime'] == "image/jpeg") { imagejpeg($image_target, $file_target, 70); }
	else if ($size['mime'] == "image/png") { imagepng($image_target, $file_target); }

	// Free up memory
	imagedestroy($image_target);
}

/* Create Thumbnail with Background */
function createThumbBg($file_orig, $file_target, $width_target, $height_target) {
	$size = getimagesize($file_orig);

	// Content type
	header("Content-type: {$size['mime']}");

	// Get new dimensions
	//list($width_orig, $height_orig) = getimagesize($file_orig);
	$width_orig		= $size[0];
	$height_orig	= $size[1];

	$ratio_orig = $width_orig / $height_orig;

	if ($width_target / $height_target > $ratio_orig) {
		$width_temp	= $height_target * $ratio_orig;
		$height_temp	= $height_target;
		$off_y	= 0;
		$off_x	= ceil(($width_target - $width_temp) / 2);
	} else {
		$height_temp	= $width_target / $ratio_orig;
		$width_temp	= $width_target;
		$off_x	= 0;
		$off_y	= ceil(($height_target - $height_temp) / 2);
	}

	// Resample Temp
	$image_temp = imagecreatetruecolor($width_temp, $height_temp);
	if ($size['mime'] == "image/gif") { $image_orig = imagecreatefromgif($file_orig); }
	else if ($size['mime'] == "image/jpeg") { $image_orig = imagecreatefromjpeg($file_orig); }
	else if ($size['mime'] == "image/png") { $image_orig = imagecreatefrompng($file_orig); }
	//imagecopyresampled($image_temp, $image_orig, 0, 0, 0, 0, $width_temp, $height_temp, $width_orig, $height_orig);

	// Resample
	$image_target = imagecreatetruecolor($width_target, $height_target);
	$black				= imagecolorallocate($image_target, 0, 0, 0);
	imagefill($image_target, 0, 0, $black);
	imagecopyresampled($image_target, $image_orig, $off_x, $off_y, 0, 0, $width_temp, $height_temp, $width_orig, $height_orig);

	// Output
	if ($size['mime'] == "image/gif") { imagegif($image_target, $file_target); }
	else if ($size['mime'] == "image/jpeg") { imagejpeg($image_target, $file_target, 70); }
	else if ($size['mime'] == "image/png") { imagepng($image_target, $file_target); }

	// Free up memory
	imagedestroy($image_temp);
	imagedestroy($image_target);
}

/* Create Thumbnail & Crop */
function createThumbCrop($file_orig, $file_target, $width_target, $height_target)
{
	$size = getimagesize($file_orig);

	// Content type
	header("Content-type: {$size['mime']}");

	// Get new dimensions
	//list($width_orig, $height_orig) = getimagesize($file_orig);
	$width_orig = $size[0];
	$height_orig = $size[1];

	$ratio_orig = $width_orig / $height_orig;

	$width_final = $width_target;
	$height_final = $height_target;

	$src_x = 0;
	$src_y = 0;

	//$width_target = ($width_target > $width_orig) ? $width_orig:$width_target;
	//$height_target = ($height_target > $height_orig) ? $height_orig:$height_target;

	if ($width_target / $height_target < $ratio_orig) {
		$width_target = $height_target * $ratio_orig;
	} else {
		$height_target = $width_target / $ratio_orig;
	}

	$src_x = ($width_target == $width_final) ? $src_x:($width_target - $width_final) / 2;
	$src_y = ($height_target == $height_final) ? $src_y:($height_target - $height_final) / 2;

	// Resample
	$image_target = imagecreatetruecolor($width_target, $height_target);
	if ($size['mime'] == "image/gif") { $image_orig = imagecreatefromgif($file_orig); }
	else if ($size['mime'] == "image/jpeg") { $image_orig = imagecreatefromjpeg($file_orig); }
	else if ($size['mime'] == "image/png") { $image_orig = imagecreatefrompng($file_orig); }
	imagecopyresampled($image_target, $image_orig, 0, 0, 0, 0, $width_target, $height_target, $width_orig, $height_orig);

	$image_crop = imagecreatetruecolor($width_final, $height_final);
	imagecopyresampled($image_crop, $image_target, 0, 0, $src_x, $src_y, $width_target, $height_target, $width_target, $height_target);

	// Output
	if ($size['mime'] == "image/gif") { imagegif($image_crop, $file_target); }
	else if ($size['mime'] == "image/jpeg") { imagejpeg($image_crop, $file_target, 70); }
	else if ($size['mime'] == "image/png") { imagepng($image_crop, $file_target); }

	// Free up memory
	imagedestroy($image_target);
	imagedestroy($image_crop);
}


/* Crop Image */
function cropImage($file_orig, $file_target, $width_target, $height_target, $x, $y)
{
	$size = getimagesize($file_orig);

	// Content type
	header("Content-type: {$size['mime']}");

	// Crop
	if ($size['mime'] == "image/gif") { $image_orig = imagecreatefromgif($file_orig); }
	else if ($size['mime'] == "image/jpeg") { $image_orig = imagecreatefromjpeg($file_orig); }
	else if ($size['mime'] == "image/png") { $image_orig = imagecreatefrompng($file_orig); }
	$image_crop = imagecrop($image_orig, ['x' => $x, 'y' => $y, 'width' => $width_target, 'height' => $height_target]);

	// Output
	if ($image_crop !== FALSE) {
		if ($size['mime'] == "image/gif") { imagegif($image_crop, $file_target); }
		else if ($size['mime'] == "image/jpeg") { imagejpeg($image_crop, $file_target, 70); }
		else if ($size['mime'] == "image/png") { imagepng($image_crop, $file_target); }
	} else {
		if ($size['mime'] == "image/gif") { imagegif($image_orig, $file_target); }
		else if ($size['mime'] == "image/jpeg") { imagejpeg($image_orig, $file_target, 70); }
		else if ($size['mime'] == "image/png") { imagepng($image_orig, $file_target); }
	}
	// Free up memory
	imagedestroy($image_crop);
}

/* Compress Image (JPG) */
function compressImage($source_url, $destination_url, $quality) {
	$info = getimagesize($source_url);

	if ($info['mime'] == 'image/jpeg') $image = imagecreatefromjpeg($source_url);
	elseif ($info['mime'] == 'image/gif') $image = imagecreatefromgif($source_url);
	elseif ($info['mime'] == 'image/png') $image = imagecreatefrompng($source_url);

    //save file
	imagejpeg($image, $destination_url, $quality);

    //return destination file
	return $destination_url;
}


/* Random Text */
function RandText( $type = 'alnum', $length = 10 ){
	switch ( $type ) {
		case 'alnum':
		$pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		break;
		case 'alpha':
		$pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		break;
		case 'caps':
		$pool = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		break;
		case 'hexdec':
		$pool = '0123456789abcdef';
		break;
		case 'numeric':
		$pool = '0123456789';
		break;
		case 'nozero':
		$pool = '123456789';
		break;
		case 'distinct':
		$pool = '2345679ACDEFHJKLMNPRSTUVWXYZ';
		break;
		default:
		$pool = (string) $type;
		break;
	}

	$crypto_rand_secure = function ( $min, $max ) {
		$range = $max - $min;
		if ( $range < 0 ) return $min; // not so random...
		$log    = log( $range, 2 );
		$bytes  = (int) ( $log / 8 ) + 1; // length in bytes
		$bits   = (int) $log + 1; // length in bits
		$filter = (int) ( 1 << $bits ) - 1; // set all lower bits to 1
		do {
			$rnd = hexdec( bin2hex( openssl_random_pseudo_bytes( $bytes ) ) );
			$rnd = $rnd & $filter; // discard irrelevant bits
		} while ( $rnd >= $range );
		return $min + $rnd;
	};

	$token = "";
	$max   = strlen( $pool );
	for ( $i = 0; $i < $length; $i++ ) {
		$token .= $pool[$crypto_rand_secure( 0, $max )];
	}
	return $token;
}

/* Leading Zero */
function leadingZero($number,$length) {	// Add leading zero
	$leadingZero = $length - strlen($number);
	$newNumber	= "";
	for ($i=0; $i<$leadingZero; $i++) {
		$newNumber	.= "0";
	}
	$newNumber	.= $number;
	return $newNumber;
}

/* Array Super Unique - tidak sepenuhnya berfungsi */
function super_unique($array) {
	$result = array_map("unserialize", array_unique(array_map("serialize", $array)));

	foreach ($result as $key => $value) 	{
		if ( is_array($value) ) 		{
			$result[$key] = super_unique($value);
		}
	}
	return $result;
}


/* DATE */
function showOne($intDate) {   // Convert 2 digit ke 1 digit
	switch ($intDate) {
		case "01" : return "1"; break;
		case "02" : return "2"; break;
		case "03" : return "3"; break;
		case "04" : return "4"; break;
		case "05" : return "5"; break;
		case "06" : return "6"; break;
		case "07" : return "7"; break;
		case "08" : return "8"; break;
		case "09" : return "9"; break;
		default: return $intDate; break;
	}
}

function showTwo($intDate) {  // Convert 1 digit ke 2 digit
	switch ($intDate) {
		case 1 : return "01"; break;
		case 2 : return "02"; break;
		case 3 : return "03"; break;
		case 4 : return "04"; break;
		case 5 : return "05"; break;
		case 6 : return "06"; break;
		case 7 : return "07"; break;
		case 8 : return "08"; break;
		case 9 : return "09"; break;
		default: return $intDate; break;
	}
}

function showMonth($intMonth) {  // Convert integer bulan ke nama bulan
	switch ($intMonth) {
		case 1 : return "Januari"; break;
		case 2 : return "Februari"; break;
		case 3 : return "Maret"; break;
		case 4 : return "April"; break;
		case 5 : return "Mei"; break;
		case 6 : return "Juni"; break;
		case 7 : return "Juli"; break;
		case 8 : return "Agustus"; break;
		case 9 : return "September"; break;
		case 10 : return "Oktober"; break;
		case 11 : return "November"; break;
		case 12 : return "Desember"; break;
	}
}

function showMonthInt($Month) {  // Convert nama bulan ke integer bulan
	switch ($Month) {
		case "Januari" : return "01"; break;
		case "Februari" : return "02"; break;
		case "Maret" : return "03"; break;
		case "April" : return "04"; break;
		case "Mei" : return "05"; break;
		case "Juni" : return "06"; break;
		case "Juli" : return "07"; break;
		case "Agustus" : return "08"; break;
		case "September" : return "09"; break;
		case "Oktober" : return "10"; break;
		case "November" : return "11"; break;
		case "Desember" : return "12"; break;
	}
}

function showMonthThree($intMonth) {  // Convert integer bulan ke nama bulan
	switch ($intMonth) {
		case 1 : return "Jan"; break;
		case 2 : return "Feb"; break;
		case 3 : return "Mar"; break;
		case 4 : return "Apr"; break;
		case 5 : return "Mei"; break;
		case 6 : return "Jun"; break;
		case 7 : return "Jul"; break;
		case 8 : return "Agt"; break;
		case 9 : return "Sep"; break;
		case 10 : return "Okt"; break;
		case 11 : return "Nov"; break;
		case 12 : return "Des"; break;
	}
}

function showWeekday($yyyy_mm_dd) {  // Convert tanggal YYYY-MM-DD ke nama hari
	$date = explode("-",$yyyy_mm_dd);
	$timestamp = mktime(0,0,0,$date[1],$date[2],$date[0]);
	$getdate = getdate($timestamp);
	switch ($getdate["wday"]) {
		case 0 : return "Minggu"; break;
		case 1 : return "Senin"; break;
		case 2 : return "Selasa"; break;
		case 3 : return "Rabu"; break;
		case 4 : return "Kamis"; break;
		case 5 : return "Jumat"; break;
		case 6 : return "Sabtu"; break;
	}
}

function showTanggal($yyyy_mm_dd) {      // Convert tanggal YYYY-MM-DD ke format DD nama bulan YYYY
	$date = explode("-",$yyyy_mm_dd);
	$tanggal = showOne($date[2])." ".showMonth($date[1])." ".$date[0];

	return $tanggal;
}

function showTanggalThree($yyyy_mm_dd) {      // Convert tanggal YYYY-MM-DD ke format DD nama bulan YYYY
	$date = explode("-",$yyyy_mm_dd);
	$tanggal = showOne($date[2])." ".showMonthThree($date[1])." ".$date[0];

	return $tanggal;
}

function showBulanTahun($yyyy_mm_dd) {      // Convert tanggal YYYY-MM-DD ke format nama bulan YYYY
	$date = explode("-",$yyyy_mm_dd);
	$tanggal = showMonth($date[1])." ".$date[0];

	return $tanggal;
}
