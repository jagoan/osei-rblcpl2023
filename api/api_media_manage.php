<?php
/* ADD MEDIA */
$app->post('/media', function () use ($app) {
	$post		= $app->request->post();
	$headers	= $app->request->headers;

	/* Parameter */
	$token	= ($headers['token']) ? $headers['token']:"";	// token
	$now	= date('Y-m-d H:i:s');							// now

	require_once 'mysql.php';
	$db = connect_db();

	/* hanya untuk admin */
	if (!checkTokenId($token, 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
	
	/* data utama harus diisi */
	} else if (!isset($_FILES['media'])) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Empty Field', 'error_code'=>'empty_field', 'post'=>$post));
	
	} else {
		$tmp       	= $_FILES['media']['tmp_name'];
		$size      	= $_FILES['media']['size'];
		$error		= $_FILES['media']['error'];
		$filename	= strtolower($_FILES['media']['name']);
		$ext      	= pathinfo($filename, PATHINFO_EXTENSION);

		if ($ext && $error == 0 && ($ext == "jpg" || $ext == "png" || $ext == "jpeg" || $ext == "gif")) {
			$fileMedia	= "media-".randomString(15).".".$ext;
			createThumb($tmp, "../media/thumb-".$fileMedia, '300', '300');
			move_uploaded_file($tmp, "../media/".$fileMedia);

			/* Update Media DB */
			$sql = "INSERT INTO ".DB_PREFIX."media (media, update_time) VALUES ('".$fileMedia."', '".$now."')";
			$db->query($sql);

			if ($db->affected_rows > 0) {
				$idMedia = $db->insert_id;	

				logActivity(checkTokenId($token, 'admin'), 'add new media', $idMedia, 'media', 'admin');
				echo json_encode(array('status'=>'SUCCESS', 'id'=>$idMedia));
			
			} else {
				echo json_encode(array('status'=>'ERROR', 'message'=>'Insert Error', 'error_code'=>'insert_error', 'sql'=>$sql));
			}

		} else {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Invalid File (JPG, JPEG, PNG, GIF only)', 'error_code'=>'file_error'));
		}
	}
});

$app->options('/media', function () use ($app) {
});

/***************************************************/

/* DELETE MEDIA - ADMIN ONLY */
$app->delete('/media/:idMedia', function ($idMedia) use ($app) {
	$headers	= $app->request->headers;

	/* Parameter */
	$token	= ($headers['token']) ? $headers['token']:"";

	require_once 'mysql.php';
	$db = connect_db();

	$sql	= "SELECT id, media FROM ".DB_PREFIX."media WHERE id = '".$idMedia."'";
	$result	= $db->query($sql);

	/* hanya untuk admin */
	if (!checkTokenId($token, 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
	
	} else if ($result->num_rows == 0) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Media Not Found', 'error_code'=>'media_not_found'));
	
	} else {
		while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
			$data[] = $row;
		}

		/* delete existing file */
		$existingMedia	= $data[0]['media'];
		if ($existingMedia && file_exists("../media/thumb-".$existingMedia)) {
			unlink("../media/thumb-".$existingMedia);
		}
		if ($existingMedia && file_exists("../media/".$existingMedia)) {
			unlink("../media/".$existingMedia);
		}

		$sqlDel = "DELETE FROM ".DB_PREFIX."media WHERE id = '".$idMedia."'";
		$db->query($sqlDel);

		if ($db->affected_rows > 0) {
			logActivity(checkTokenId($token, 'admin'), 'delete media - '.$data[0]['media'], $idMedia, 'media', 'admin');
			echo json_encode(array('status'=>'SUCCESS'));
		
		} else {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Delete Error', 'error_code'=>'delete_error'));
		}
	}
});

$app->options('/media/:idMedia', function ($idMedia) use ($app) {
});

/***************************************************/