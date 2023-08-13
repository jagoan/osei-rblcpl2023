<?php
require_once 'vendor/autoload.php';
use Shuchkin\SimpleXLS;
use Shuchkin\SimpleXLSX;

/* CHECK USER FILE */
$app->get('/user/file', function () use ($app) {
	$get		= $app->request->get();
	$headers	= $app->request->headers;
	
	/* DB Connect */
	require_once 'mysql.php';
	$db = connect_db();

	/* Parameter */
	$token		= ($headers['token']) ? $headers['token']:"";						// token
	
	/* harus login dulu */
	if (!checkTokenId($token, 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized', 'token'=>$token, 'id'=>checkTokenId($token, 'admin')));

	} else {
		$sql	= "SELECT filename, filetype, update_time FROM ".DB_PREFIX."user_file ORDER BY id ASC";
		$result	= $db->query($sql);

		/* DEBUG */
		//echo json_encode(array('status'=>'DEBUG', 'error'=>$db->error, 'sql'=>preg_replace("/\s+/", " ", $sql))); exit;

		if ($result->num_rows == 0) {
			echo json_encode(array('status'=>'ERROR', 'message'=>'File Not Found', 'error_code'=>'file_not_found', 'sql'=>preg_replace("/\s+/", " ", $sql)));
		
		} else {
			$data	= array();
			while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
				$data[] = $row;
			}

			//echo json_encode(array('status'=>'SUCCESS', 'offset'=>$offset, 'limit'=>$limit, 'data_total'=>$resultAll->num_rows, 'data'=>$data, 'sql'=>preg_replace("/\s+/", " ", $sql)));
			echo json_encode(array('status'=>'SUCCESS', 'data'=>$data));
		}
	}
});

$app->options('/user/file', function () use ($app) {
});

/***************************************************/

/* UPDATE USER FILE */
$app->post('/user/file', function () use ($app) {
	$post		= $app->request->post();
	$headers	= $app->request->headers;

	/* Parameter */
	$token		= ($headers['token']) ? $headers['token']:"";					// token
	$deleteFile	= ( isset($post['delete_file']) ) ? $post['delete_file']:"";	// delete_file
	$now		= date('Y-m-d H:i:s');											// now

	$deleteFile	= ( $deleteFile == 'yes' ) ? $deleteFile:"";	

	require_once 'mysql.php';
	$db = connect_db();

	/* check if event exist & get file name */
	$sql	= "SELECT id, filename FROM ".DB_PREFIX."user_file ORDER BY id ASC";
	$result	= $db->query($sql);

	/* hanya untuk admin*/
	if (!checkTokenId($token, 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
	
	} else if ($result->num_rows == 0) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'File Not Found', 'error_code'=>'file_not_found'));
	
	} else {
		while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
			$data[] = $row;
		}

		/* existing file */
		$user	= $data[0]['filename'];

		/* delete existing file */
		$isDeleted	= "no";
		if ($deleteFile) {
			if ($user && file_exists("../media/".$user)) {
				unlink("../media/".$user);
			}

			/* Update DB */
			$sql = "UPDATE ".DB_PREFIX."user_file SET filename = '', update_time = '".$now."' WHERE id = '".$data[0]['id']."'";
			$db->query($sql);

			if ($db->affected_rows > 0) {
				$isDeleted	= "yes";
			}
		}

		/* upload file */
		$isUploaded	= "no";
		if ( isset($_FILES['file']) ) {
			$tmp       	= $_FILES['file']['tmp_name'];
			$size      	= $_FILES['file']['size'];
			$error		= $_FILES['file']['error'];
			$filename	= strtolower($_FILES['file']['name']);
			$filename	= str_replace(' ', '_', $filename);
			$ext      	= pathinfo($filename, PATHINFO_EXTENSION);
			
			$isInvalidExt	= "no";
			if ($ext && $error == 0 && ($ext == "xls" || $ext == "xlsx")) {
				$fileUser	= "user_list-".randomString(15).".".$ext;
				//$fileUser	= "user_list.".$ext;
				//$fileUser	= $filename;
				move_uploaded_file($tmp, "../media/".$fileUser);

				/* delete existing file */
				if ($user && file_exists("../media/".$user)) {
					unlink("../media/".$user);
				}

				/* Update Event */
				$sql = "UPDATE ".DB_PREFIX."user_file SET filename = '".$fileUser."', filetype = '".$ext."' WHERE id = '".$data[0]['id']."'";
				$db->query($sql);

				if ($db->affected_rows > 0) {
					$isUploaded	= "yes";
				}

			} else {
				$isInvalidExt	= "yes";
			}
		}

		if ($isInvalidExt == "yes") {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Invalid File Type (XLS or XLSX only)', 'error_code'=>'invalid_filetype'));

		} else if ($isDeleted == "yes" || $isUploaded == "yes") {
			logActivity(checkTokenId($token, 'admin'), 'update user file', $data[0]['id'], 'user_file', 'admin');
			echo json_encode(array('status'=>'SUCCESS'));

		} else {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Update Error', 'error_code'=>'update_error'));
		}
	}
});

$app->options('/user/file', function () use ($app) {
});

/***************************************************/

/* PARSE USER FILE */
$app->post('/user/file/parse', function () use ($app) {
	$post		= $app->request->post();
	$headers	= $app->request->headers;

	/* Parameter */
	$token		= ($headers['token']) ? $headers['token']:"";					// token
	$now		= date('Y-m-d H:i:s');											// now

	require_once 'mysql.php';
	$db = connect_db();

	/* check if event exist & get file name */
	$sqlFile	= "SELECT id, filename, filetype 
					FROM ".DB_PREFIX."user_file 
					WHERE filename != '' AND filetype IN ('xls', 'xlsx')
					ORDER BY id ASC";
	$resultFile	= $db->query($sqlFile);

	/* hanya untuk admin*/
	if (!checkTokenId($token, 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
	
	} else if ($resultFile->num_rows == 0) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'File Not Found', 'error_code'=>'file_not_found'));
	
	} else {
		$dataFile = array();
		while ($row = $resultFile->fetch_array(MYSQLI_ASSOC)) {
			$dataFile[] = $row;
		}

		/* existing file */
		$filename	= $dataFile[0]['filename'];
		$filetype	= $dataFile[0]['filetype'];

		/* registered user */
		$sqlUser	= "SELECT employee_id FROM ".DB_PREFIX."user";
		$resultUser	= $db->query($sqlUser);

		$dataUser = array();
		while ($row = $resultUser->fetch_array(MYSQLI_ASSOC)) {
			$dataUser[] = $row['employee_id'];
		}

		if ($filetype == 'xls') {
			if ($xls = SimpleXLS::parse('../media/'.$filename)) {
				// Produce array keys from the array values of 1st array element
				$header_values = $parseUser = $newUser = array();
			
				foreach ($xls->rows() as $k => $r) {
					if ($k === 0) {
						$header_values = $r;
						continue;
					}
					$parseUser[] = array_combine($header_values, $r);
				}
				
				/*
				Array
				(
					[0] => Array
						(
							[ISBN] => 618260307
							[title] => The Hobbit
							[author] => J. R. R. Tolkien
							[publisher] => Houghton Mifflin
							[ctry] => USA
						)
			
					[1] => Array
						(
							[ISBN] => 908606664
							[title] => Slinky Malinki
							[author] => Lynley Dodd
							[publisher] => Mallinson Rendel
							[ctry] => NZ
						)
			
				)
				*/

				$newUserCount = 0;
				for ($i = 0; $i < count($parseUser); $i++) {
					if (!in_array(trim($parseUser[$i]['employee_id']), $dataUser)) {
						$newUser[$newUserCount]	= $parseUser[$i];
						
						/* escape string */
						$firstName		= mysqli_real_escape_string($db, trim($parseUser[$i]['first_name']));
						$lastName		= mysqli_real_escape_string($db, trim($parseUser[$i]['last_name']));
						$departement	= mysqli_real_escape_string($db, trim($parseUser[$i]['departement']));
						$employeeId		= mysqli_real_escape_string($db, trim($parseUser[$i]['employee_id']));
						$territory		= mysqli_real_escape_string($db, trim($parseUser[$i]['territory']));

						$sql = "INSERT INTO ".DB_PREFIX."user (first_name, last_name, departement, employee_id, territory, update_time) VALUES ('".$firstName."', '".$lastName."', '".$departement."', '".$employeeId."', '".$territory."', '".$now."')";
						$db->query($sql);
						$newUserCount++;
					}
				}				

				echo json_encode(array('status'=>'SUCCESS', 'user_parsed'=>count($parseUser), 'user_registered'=>count($dataUser), 'user_new'=>count($newUser), 'data'=>$newUser));

			} else {
				echo SimpleXLS::parseError();
			}

		} else if ($filetype == 'xlsx') {
			if ($xlsx = SimpleXLSX::parse('../media/'.$filename)) {
				// Produce array keys from the array values of 1st array element
				$header_values = $parseUser = $newUser = array();
				
				foreach ($xlsx->rows() as $k => $r) {
					if ($k === 0) {
						$header_values = $r;
						continue;
					}
					$parseUser[] = array_combine($header_values, $r);
				}

				$newUserCount = 0;
				for ($i = 0; $i < count($parseUser); $i++) {
					if (!in_array(trim($parseUser[$i]['employee_id']), $dataUser)) {
						$newUser[$newUserCount]	= $parseUser[$i];
						
						/* escape string */
						$firstName		= mysqli_real_escape_string($db, trim($parseUser[$i]['first_name']));
						$lastName		= mysqli_real_escape_string($db, trim($parseUser[$i]['last_name']));
						$departement	= mysqli_real_escape_string($db, trim($parseUser[$i]['departement']));
						$employeeId		= mysqli_real_escape_string($db, trim($parseUser[$i]['employee_id']));
						$territory		= mysqli_real_escape_string($db, trim($parseUser[$i]['territory']));
						
						$sql = "INSERT INTO ".DB_PREFIX."user (first_name, last_name, departement, employee_id, territory, update_time) VALUES ('".$firstName."', '".$lastName."', '".$departement."', '".$employeeId."', '".$territory."', '".$now."')";
						$db->query($sql);
						$newUserCount++;
					}
				}				

				echo json_encode(array('status'=>'SUCCESS', 'user_parsed'=>count($parseUser), 'user_registered'=>count($dataUser), 'user_new'=>count($newUser), 'data'=>$newUser));

			} else {
				echo SimpleXLSX::parseError();
			}

		} else {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Invalid File Type (XLS or XLSX only)', 'error_code'=>'invalid_filetype'));
		}
	}
});

$app->options('/user/file/parse', function () use ($app) {
});

/***************************************************/

/* DELETE USER DEBUG - ADMIN ONLY */
$app->delete('/userDebug', function () use ($app) {
	$headers	= $app->request->headers;

	/* Parameter */
	$token	= ($headers['token']) ? $headers['token']:"";

	require_once 'mysql.php';
	$db = connect_db();

	$sql	= "SELECT id, employee_id FROM ".DB_PREFIX."user WHERE id > '100'";
	$result	= $db->query($sql);

	/* hanya untuk admin */
	if (!checkTokenId($token, 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
	
	} else if ($result->num_rows == 0) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'User Not Found', 'error_code'=>'user_not_found'));
	
	} else {
		$sqlDel = "DELETE FROM ".DB_PREFIX."user WHERE id > '100'";
		$db->query($sqlDel);

		if ($db->affected_rows > 0) {
			logActivity(checkTokenId($token, 'admin'), 'delete user debug', '', 'user', 'admin');
			echo json_encode(array('status'=>'SUCCESS'));
		
		} else {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Delete Error', 'error_code'=>'delete_error'));
		}
	}
});

$app->options('/userDebug', function () use ($app) {
});

/***************************************************/

/* DELETE ALL USER DEBUG - ADMIN ONLY */
$app->delete('/userDebug/all', function () use ($app) {
	$headers	= $app->request->headers;

	/* Parameter */
	$token	= ($headers['token']) ? $headers['token']:"";

	require_once 'mysql.php';
	$db = connect_db();

	$sql	= "SELECT id, employee_id FROM ".DB_PREFIX."user";
	$result	= $db->query($sql);

	/* hanya untuk admin */
	if (!checkTokenId($token, 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
	
	} else if ($result->num_rows == 0) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'User Not Found', 'error_code'=>'user_not_found'));
	
	} else {
		$sqlDel = "DELETE FROM ".DB_PREFIX."user";
		$db->query($sqlDel);

		if ($db->affected_rows > 0) {
			logActivity(checkTokenId($token, 'admin'), 'delete all user debug', '', 'user', 'admin');
			echo json_encode(array('status'=>'SUCCESS'));
		
		} else {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Delete Error', 'error_code'=>'delete_error'));
		}
	}
});

$app->options('/userDebug/all', function () use ($app) {
});

/***************************************************/