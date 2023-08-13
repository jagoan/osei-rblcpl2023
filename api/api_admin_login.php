<?php
/* LOGIN */
$app->post('/admin/login', function () use ($app) {
	$req		= $app->request;
	$post		= $app->request->post();

	/* Parameter */
	$ip			= ($req->getIp()) ? $req->getIp():"";					// RemoteAddr
	$browser	= ($req->getUserAgent()) ? $req->getUserAgent():"";		// UserAgent
	$email		= ( isset($post['email']) ) ? $post['email']:"";		// email
	$password	= ( isset($post['password']) ) ? $post['password']:"";	// password

	require_once 'mysql.php';
	$db = connect_db();		
	
	if (!$email || !$password) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Empty Field', 'error_code'=>'empty_field'));

	} else {
		/* Cek Admin */
		$sql	= "SELECT a.id, a.name, a.email, a.email_temp, a.password, a.password_temp, r.code AS role
					FROM ".DB_PREFIX."admin a
					LEFT JOIN ".DB_PREFIX."admin_role ar ON ar.id_admin = a.id
					LEFT JOIN ".DB_PREFIX."role r ON r.id = ar.id_role
					WHERE a.email = '".$email."' OR a.email_temp = '".$email."'";
		$result	= $db->query($sql);
		if (!$result) {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Database Error', 'error_code'=>'db_error', 'error'=>$db->error, 'sql'=>preg_replace("/\s+/", " ", $sql)));
		}
		while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
			$data[] = $row;
		}

		/* Admin Not Found - Login ERROR */
		if ($result->num_rows == 0) {
			//echo json_encode(array('status'=>'ERROR', 'message'=>'Admin Not Found', 'error_code'=>'admin_not_found', 'sql'=>preg_replace("/\s+/", " ", $sql)));
			echo json_encode(array('status'=>'ERROR', 'message'=>'Admin Not Found', 'error_code'=>'admin_not_found'));

		/* Need Activation - Login ERROR */
		} else if ($data[0]['email'] == '') {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Need Activation', 'error_code'=>'need_activation'));

		/* Login SUCCESS */
		} else if ($data[0]['email'] == $email && (password_verify($password, $data[0]['password']) || password_verify($password, $data[0]['password_temp']))) {
			$role	= ($data[0]['role']) ? $data[0]['role']:'admin';
			
			/* Generate Token */
			$token	= generateToken($data[0]['id'], $browser, $ip, $role, 'admin');
			if ($token) {
				echo json_encode(array('status'=>'SUCCESS', 'token'=>$token, 'name'=>$data[0]['name'], 'role'=>$role));
			} else {
				echo json_encode(array('status'=>'ERROR', 'message'=>'Token Error', 'error_code'=>'token_error'));
			}
			
		/* Others - Login ERROR */
		} else {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Login Error', 'error_code'=>'login_error'));
		}
	}
});

$app->options('/admin/login', function () use ($app) {
});

/***************************************************/

/* LOGOUT */
$app->post('/admin/logout', function () use ($app) {
	$headers	= $app->request->headers;

	/* Parameter */
	$token	= ($headers['token']) ? $headers['token']:"";	// token

	require_once 'mysql.php';
	$db = connect_db();		
	
	if (!checkTokenId($token, 'admin')) {
		//echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
		echo json_encode(array('status'=>'SUCCESS', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));

	} else {
		$sql = "UPDATE ".DB_PREFIX."login SET is_active = '0' WHERE id_admin = '".checkTokenId($token)."' AND is_developer != '1'";
		$db->query($sql);
		echo json_encode(array('status'=>'SUCCESS'));
	}
});

$app->options('/admin/logout', function () use ($app) {
});

/***************************************************/

