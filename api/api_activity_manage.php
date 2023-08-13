<?php
/* ADD / UPDATE ACTIVITY */
$app->post('/activity', function () use ($app) {
	$post		= $app->request->post();
	$headers	= $app->request->headers;

	/* Parameter */
	$token			= ($headers['token']) ? $headers['token']:"";								// token
	$idActivity		= ( isset($post['id_activity']) ) ? $post['id_activity']:"";				// id_activity
	$idGame			= ( isset($post['id_game']) ) ? $post['id_game']:"";						// id_game
	$activityRound	= ( isset($post['activity_round']) ) ? $post['activity_round']:"";			// activity_round
	$name			= ( isset($post['name']) ) ? $post['name']:"";								// name
	$startTime		= ( isset($post['start_time']) ) ? $post['start_time']:date('Y-m-d H:i:s');	// start_time
	$endTime		= ( isset($post['end_time']) ) ? $post['end_time']:date('Y-m-d H:i:s');		// end_time
	$now			= date('Y-m-d H:i:s');														// now

	require_once 'mysql.php';
	$db = connect_db();

	/* hanya untuk admin */
	if (!checkTokenId($token, 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
	
	/* data utama harus diisi */
	} else if (!$idGame || !$name || !$startTime || !$endTime) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Empty Field', 'error_code'=>'empty_field', 'post'=>$post));
	
	} else {	
		/* escape string */	
		$activityRound	= mysqli_real_escape_string($db, trim($activityRound));
		$name			= mysqli_real_escape_string($db, trim($name));

		if ($idActivity) {		
			/* Edit Activity - check if activity exist */
			$sql	= "SELECT id FROM ".DB_PREFIX."activity WHERE id = '".$idActivity."'";
			$result	= $db->query($sql);

			if ($result->num_rows == 0) {
				echo json_encode(array('status'=>'ERROR', 'message'=>'Activity Not Found', 'error_code'=>'activity_not_found'));
			
			} else {
				/* Update Activity */
				$sql = "UPDATE ".DB_PREFIX."activity SET id_game = '".$idGame."', activity_round = '".$activityRound."', name = '".$name."', start_time = '".$startTime."', end_time = '".$endTime."', update_time = '".$now."' WHERE id = '".$idActivity."'";
				$db->query($sql);

				if ($db->affected_rows > 0) {
					logActivity(checkTokenId($token, 'admin'), 'update activity', $idActivity, 'activity', 'admin');
					echo json_encode(array('status'=>'SUCCESS', 'id'=>$idActivity));
				
				} else {
					echo json_encode(array('status'=>'ERROR', 'message'=>'Update Error', 'error_code'=>'update_error', 'sql'=>$sql));
				}				
			}

		} else {
			/* New Activity */
			$sql = "INSERT INTO ".DB_PREFIX."activity (id_game, activity_round, name, start_time, end_time, update_time) VALUES ('".$idGame."', '".$activityRound."', '".$name."', '".$startTime."', '".$endTime."', '".$now."')";
			$db->query($sql);

			if ($db->affected_rows > 0) {
				$idActivity = $db->insert_id;	

				logActivity(checkTokenId($token, 'admin'), 'add new activity', $idActivity, 'activity', 'admin');
				echo json_encode(array('status'=>'SUCCESS', 'id'=>$idActivity));
			
			} else {
				echo json_encode(array('status'=>'ERROR', 'message'=>'Insert Error', 'error_code'=>'insert_error', 'sql'=>$sql));
			}
		}
	}
});

$app->options('/activity', function () use ($app) {
});

/***************************************************/

/* CHANGE ACTIVITY STATUS */
$app->post('/activity/status', function () use ($app) {
	$post		= $app->request->post();
	$headers	= $app->request->headers;

	/* Parameter */
	$token		= ($headers['token']) ? $headers['token']:"";								// token
	$idActivity	= ( isset($post['id_activity']) ) ? $post['id_activity']:"";				// id_activity
	$status		= ( isset($post['status']) ) ? $post['status']:"";							// status
	$now		= date('Y-m-d H:i:s');														// now

	require_once 'mysql.php';
	$db = connect_db();

	/* hanya untuk admin */
	if (!checkTokenId($token, 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
	
	/* data utama harus diisi */
	} else if (!$idActivity || !$status) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Empty Field', 'error_code'=>'empty_field', 'post'=>$post));
	
	} else {				
		/* Edit Activity - check if activity exist */
		$sql	= "SELECT id FROM ".DB_PREFIX."activity WHERE id = '".$idActivity."'";
		$result	= $db->query($sql);

		if ($result->num_rows == 0) {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Activity Not Found', 'error_code'=>'activity_not_found'));
		
		} else {
			/* Update Activity */
			$sql = "UPDATE ".DB_PREFIX."activity SET status = '".$status."', update_time = '".$now."' WHERE id = '".$idActivity."'";
			$db->query($sql);

			if ($db->affected_rows > 0) {
				logActivity(checkTokenId($token, 'admin'), 'change activity status', $idActivity, 'activity', 'admin');
				echo json_encode(array('status'=>'SUCCESS', 'id'=>$idActivity));
			
			} else {
				echo json_encode(array('status'=>'ERROR', 'message'=>'Update Error', 'error_code'=>'update_error', 'sql'=>$sql));
			}				
		}
	}
});

$app->options('/activity/status', function () use ($app) {
});

/***************************************************/

/* DELETE ACTIVITY - ADMIN ONLY */
$app->delete('/activity/:idActivity', function ($idActivity) use ($app) {
	$headers	= $app->request->headers;

	/* Parameter */
	$token	= ($headers['token']) ? $headers['token']:"";

	require_once 'mysql.php';
	$db = connect_db();

	$sql	= "SELECT id, name FROM ".DB_PREFIX."activity WHERE id = '".$idActivity."'";
	$result	= $db->query($sql);

	/* hanya untuk admin */
	if (!checkTokenId($token, 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
	
	} else if ($result->num_rows == 0) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Activity Not Found', 'error_code'=>'activity_not_found'));
	
	} else {
		while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
			$data[] = $row;
		}

		$sqlDel = "DELETE FROM ".DB_PREFIX."activity WHERE id = '".$idActivity."'";
		$db->query($sqlDel);

		if ($db->affected_rows > 0) {
			logActivity(checkTokenId($token, 'admin'), 'delete activity - '.$data[0]['name'], $idActivity, 'activity', 'admin');
			echo json_encode(array('status'=>'SUCCESS'));
		
		} else {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Delete Error', 'error_code'=>'delete_error'));
		}
	}
});

$app->options('/activity/:idActivity', function ($idActivity) use ($app) {
});

/***************************************************/