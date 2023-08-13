<?php
/* ADD / UPDATE USER */
$app->post('/user', function () use ($app) {
	$post		= $app->request->post();
	$headers	= $app->request->headers;

	/* Parameter */
	$token			= ($headers['token']) ? $headers['token']:"";					// token
	$idUser			= ( isset($post['id_user']) ) ? $post['id_user']:"";			// id_user
	$firstName		= ( isset($post['first_name']) ) ? $post['first_name']:"";		// first_name
	$lastName		= ( isset($post['last_name']) ) ? $post['last_name']:"";		// last_name
	$departement	= ( isset($post['departement']) ) ? $post['departement']:"";	// departement
	$employeeId		= ( isset($post['employee_id']) ) ? $post['employee_id']:"";	// employee_id
	$territory		= ( isset($post['territory']) ) ? $post['territory']:"";		// territory
	$now			= date('Y-m-d H:i:s');											// now

	require_once 'mysql.php';
	$db = connect_db();

	/* hanya untuk admin */
	if (!checkTokenId($token, 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
	
	/* data utama harus diisi */
	} else if (!$firstName || !$lastName || !$departement || !$employeeId || !$territory) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Empty Field', 'error_code'=>'empty_field', 'post'=>$post));
	
	/* cek duplikat employee_id */
	} else if ($employeeId && !checkDuplicateData('user', 'employee_id', $employeeId, $idUser)) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Duplicate Employee ID', 'error_code'=>'duplicate_employeeid'));

	} else {
		/* escape string */	
		$firstName		= mysqli_real_escape_string($db, trim($firstName));
		$lastName		= mysqli_real_escape_string($db, trim($lastName));
		$departement	= mysqli_real_escape_string($db, trim($departement));
		$employeeId		= mysqli_real_escape_string($db, trim($employeeId));
		$territory		= mysqli_real_escape_string($db, trim($territory));

		if ($idUser) {		
			/* Edit User - check if user exist */
			$sql	= "SELECT id FROM ".DB_PREFIX."user WHERE id = '".$idUser."'";
			$result	= $db->query($sql);

			if ($result->num_rows == 0) {
				echo json_encode(array('status'=>'ERROR', 'message'=>'User Not Found', 'error_code'=>'user_not_found'));
			
			} else {
				/* Update User */
				$sql = "UPDATE ".DB_PREFIX."user SET first_name = '".$firstName."', last_name = '".$lastName."', departement = '".$departement."', employee_id = '".$employeeId."', territory = '".$territory."', update_time = '".$now."' WHERE id = '".$idUser."'";
				$db->query($sql);

				if ($db->affected_rows > 0) {
					logActivity(checkTokenId($token, 'admin'), 'update user', $idUser, 'user', 'admin');
					echo json_encode(array('status'=>'SUCCESS', 'id'=>$idUser));
				
				} else {
					echo json_encode(array('status'=>'ERROR', 'message'=>'Update Error', 'error_code'=>'update_error', 'sql'=>$sql));
				}				
			}

		} else {
			/* New User */
			$sql = "INSERT INTO ".DB_PREFIX."user (first_name, last_name, departement, employee_id, territory, update_time) VALUES ('".$firstName."', '".$lastName."', '".$departement."', '".$employeeId."', '".$territory."', '".$now."')";
			$db->query($sql);

			if ($db->affected_rows > 0) {
				$idUser = $db->insert_id;	

				logActivity(checkTokenId($token, 'admin'), 'add new user', $idUser, 'user', 'admin');
				echo json_encode(array('status'=>'SUCCESS', 'id'=>$idUser));
			
			} else {
				echo json_encode(array('status'=>'ERROR', 'message'=>'Insert Error', 'error_code'=>'insert_error', 'sql'=>$sql));
			}
		}
	}
});

$app->options('/user', function () use ($app) {
});

/***************************************************/

/* DELETE USER - ADMIN ONLY */
$app->delete('/user/:idUser', function ($idUser) use ($app) {
	$headers	= $app->request->headers;

	/* Parameter */
	$token	= ($headers['token']) ? $headers['token']:"";

	require_once 'mysql.php';
	$db = connect_db();

	$sql	= "SELECT id, employee_id FROM ".DB_PREFIX."user WHERE id = '".$idUser."'";
	$result	= $db->query($sql);

	/* hanya untuk admin */
	if (!checkTokenId($token, 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
	
	} else if ($result->num_rows == 0) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'User Not Found', 'error_code'=>'user_not_found'));
	
	} else {
		while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
			$data[] = $row;
		}

		$sqlDel = "DELETE FROM ".DB_PREFIX."user WHERE id = '".$idUser."'";
		$db->query($sqlDel);

		if ($db->affected_rows > 0) {
			logActivity(checkTokenId($token, 'admin'), 'delete user - '.$data[0]['employee_id'], $idUser, 'user', 'admin');
			echo json_encode(array('status'=>'SUCCESS'));
		
		} else {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Delete Error', 'error_code'=>'delete_error'));
		}
	}
});

$app->options('/user/:idUser', function ($idUser) use ($app) {
});

/***************************************************/