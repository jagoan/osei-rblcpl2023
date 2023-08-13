<?php
/* LOGIN */
$app->post('/login', function () use ($app) {
	$req		= $app->request;
	$post		= $app->request->post();

	/* Parameter */
	$ip				= ($req->getIp()) ? $req->getIp():"";							// RemoteAddr
	$browser		= ($req->getUserAgent()) ? $req->getUserAgent():"";				// UserAgent
	$idUser			= ( isset($post['id_user']) ) ? $post['id_user']:"";			// id_user
	$employeeId		= ( isset($post['employee_id']) ) ? $post['employee_id']:"";	// employee_id

	require_once 'mysql.php';
	$db = connect_db();		
	
	if (!$idUser || !$employeeId) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Empty Field', 'error_code'=>'empty_field'));

	} else {
		/* Cek User */
		$sql	= "SELECT id, first_name, last_name, departement
					FROM ".DB_PREFIX."user
					WHERE id = '".$idUser."' AND employee_id = '".$employeeId."'";
		$result	= $db->query($sql);

		/* DEBUG */
		//echo json_encode(array('status'=>'DEBUG', 'error'=>$db->error, 'sql'=>preg_replace("/\s+/", " ", $sql))); exit;

		/* User Not Found - Login ERROR */
		if ($result->num_rows == 0) {
			//echo json_encode(array('status'=>'ERROR', 'message'=>'User Not Found', 'error_code'=>'user_not_found', 'sql'=>preg_replace("/\s+/", " ", $sql)));
			echo json_encode(array('status'=>'ERROR', 'message'=>'User Not Found', 'error_code'=>'user_not_found'));

		/* Login SUCCESS */
		} else {
			while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
				$data[] = $row;
			}

			/* score */
			$sqlScore		= "SELECT a.id AS id_activity, a.activity_round, a.name AS game_name, IF(s.duration IS NOT NULL, s.duration, '') AS duration
								FROM ".DB_PREFIX."activity a
								LEFT JOIN (
									SELECT id_activity, duration FROM ".DB_PREFIX."score WHERE id_user = '".$idUser."'
								) s ON s.id_activity = a.id
								ORDER BY a.activity_round ASC";
			$resultScore	= $db->query($sqlScore);
			
			$dataScore	= array();
			while ($row = $resultScore->fetch_array(MYSQLI_ASSOC)) {
				$dataScore[] = $row;
			}
			
			/* Generate Token */
			$role	= 'user';
			$token	= generateToken($data[0]['id'], $browser, $ip, $role);
			if ($token) {
				echo json_encode(array('status'=>'SUCCESS', 'token'=>$token, 'first_name'=>$data[0]['first_name'], 'last_name'=>$data[0]['last_name'], 'departement'=>$data[0]['departement'], 'score'=>$dataScore));

			} else {
				echo json_encode(array('status'=>'ERROR', 'message'=>'Token Error', 'error_code'=>'token_error'));
			}
		}
	}
});

$app->options('/login', function () use ($app) {
});

/***************************************************/

/* LOGOUT */
$app->post('/logout', function () use ($app) {
	$headers	= $app->request->headers;

	/* Parameter */
	$token	= ($headers['token']) ? $headers['token']:"";	// token

	require_once 'mysql.php';
	$db = connect_db();		
	
	if (!checkTokenId($token)) {
		//echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
		echo json_encode(array('status'=>'SUCCESS', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));

	} else {
		$sql = "UPDATE ".DB_PREFIX."login SET is_active = '0' WHERE id_user = '".checkTokenId($token)."' AND is_developer != '1'";
		$db->query($sql);
		echo json_encode(array('status'=>'SUCCESS'));
	}
});

$app->options('/logout', function () use ($app) {
});

/***************************************************/

/* TERRITORY LIST */
$app->get('/user/territory', function () use ($app) {
	$get		= $app->request->get();
	$headers	= $app->request->headers;
	
	/* DB Connect */
	require_once 'mysql.php';
	$db = connect_db();

	/* Parameter */
	$token		= ($headers['token']) ? $headers['token']:"";			// token
	
	
	$sql	= "SELECT id, name, slug
				FROM ".DB_PREFIX."ref_territory
				ORDER BY name ASC";
	$result	= $db->query($sql);

	/* DEBUG */
	//echo json_encode(array('status'=>'DEBUG', 'error'=>$db->error, 'sql'=>preg_replace("/\s+/", " ", $sql))); exit;

	if ($result->num_rows == 0) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Territory Not Found', 'error_code'=>'territory_not_found', 'sql'=>preg_replace("/\s+/", " ", $sql)));
	
	} else {
		$data	= array();
		while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
			$data[] = $row;
		}

		//echo json_encode(array('status'=>'SUCCESS', 'offset'=>$offset, 'limit'=>$limit, 'data_total'=>$resultAll->num_rows, 'data'=>$data, 'sql'=>preg_replace("/\s+/", " ", $sql)));
		echo json_encode(array('status'=>'SUCCESS', 'data_total'=>$result->num_rows, 'data'=>$data));
	}
});

$app->options('/user/territory', function () use ($app) {
});

/***************************************************/

/* NAME & DEPARTEMENT LIST - FILTER BY TERRITORY */
$app->get('/user/name/:territory', function ($territory) use ($app) {
	$get		= $app->request->get();
	$headers	= $app->request->headers;
	
	/* DB Connect */
	require_once 'mysql.php';
	$db = connect_db();

	/* Parameter */
	$token		= ($headers['token']) ? $headers['token']:"";			// token
	
	
	$sql	= "SELECT id, first_name, last_name, departement
				FROM ".DB_PREFIX."user
				WHERE REPLACE(territory, ' ', '') = '".$territory."'
				ORDER BY first_name ASC";
	$result	= $db->query($sql);

	/* DEBUG */
	//echo json_encode(array('status'=>'DEBUG', 'error'=>$db->error, 'sql'=>preg_replace("/\s+/", " ", $sql))); exit;

	if ($result->num_rows == 0) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'User Not Found', 'error_code'=>'user_not_found', 'sql'=>preg_replace("/\s+/", " ", $sql)));
	
	} else {
		$data	= array();
		while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
			$data[] = $row;
		}

		//echo json_encode(array('status'=>'SUCCESS', 'offset'=>$offset, 'limit'=>$limit, 'data_total'=>$resultAll->num_rows, 'data'=>$data, 'sql'=>preg_replace("/\s+/", " ", $sql)));
		echo json_encode(array('status'=>'SUCCESS', 'data_total'=>$result->num_rows, 'data'=>$data));
	}
});

$app->options('/user/name/:territory', function ($territory) use ($app) {
});

/***************************************************/