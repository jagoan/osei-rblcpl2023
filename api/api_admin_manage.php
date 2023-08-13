<?php
/* ADD ADMIN - SUPER ADMIN ONLY */
$app->post('/admin/add', function () use ($app) {
	$post		= $app->request->post();
	$headers	= $app->request->headers;

	/* Parameter */
	$token		= ($headers['token']) ? $headers['token']:"";					// token
	$email		= ( isset($post['email']) ) ? $post['email']:"";				// email
	$password	= ( isset($post['password']) ) ? $post['password']:"";			// password
	$name		= ( isset($post['name']) ) ? $post['name']:"Administrator";		// name
	$now		= date('Y-m-d H:i:s');											// now

	require_once 'mysql.php';
	$db = connect_db();

	/* hanya untuk super_admin */
	if (!in_array(checkTokenRole($token), array('super_admin'))) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized', 'role'=>checkTokenRole($token), 'id_admin'=>checkTokenId($token, 'admin')));
	
	/* data utama harus diisi */
	} else if (!$email || !$password || !$name) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Empty Field', 'error_code'=>'empty_field', 'post'=>$post));
	
	/* cek duplikat email */
	} else if ($email && !checkDuplicateEmail($email, '', 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Email Taken', 'error_code'=>'email_taken'));

	} else {			
		/* New Admin */
		$sql = "INSERT INTO ".DB_PREFIX."admin (password_temp, email, name, register_time, update_time) VALUES ('".password_hash($password, PASSWORD_BCRYPT)."', '".$email."', '".$name."', '".$now."', '".$now."')";
		
		if ($db->query($sql) === TRUE) {
			$idAdmin = $db->insert_id;
			
			$sql = "INSERT INTO ".DB_PREFIX."admin_role (id_admin, id_role) VALUES ('".$idAdmin."', '2')";

			if ($db->query($sql) === TRUE) {
				logActivity(checkTokenId($token, 'admin'), 'add admin', $idAdmin, 'admin', 'admin');
				echo json_encode(array('status'=>'SUCCESS', 'id'=>$idAdmin));
			
			} else {
				echo json_encode(array('status'=>'ERROR', 'message'=>'Add Admin Role Error', 'error_code'=>'add_admin_role_error'));
			}
		
		} else {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Add Admin Error', 'error_code'=>'add_admin_error'));
		}
	}
});

$app->options('/admin/add', function () use ($app) {
});

/***************************************************/

/* UPDATE ADMIN */
$app->post('/admin/update', function () use ($app) {
	$post		= $app->request->post();
	$headers	= $app->request->headers;

	/* Parameter */
	$token		= ($headers['token']) ? $headers['token']:"";							// token
	$idAdmin	= ( isset($post['id_admin']) ) ? $post['id_admin']:"";					// id_admin
	$name		= ( isset($post['name']) ) ? $post['name']:"Administrator";				// name
	$email		= ( isset($post['email']) ) ? $post['email']:"";						// email
	$password	= ( isset($post['password']) ) ? $post['password']:"";					// password
	$now		= date('Y-m-d H:i:s');													// now

	$idAdmin	= ( !in_array(checkTokenRole($token), array('super_admin')) ) ? checkTokenId($token, 'admin'):$idAdmin;
	
	require_once 'mysql.php';
	$db = connect_db();

	/* hanya untuk super_admin & admin*/
	if (!checkTokenId($token, 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
	
	/* data utama harus diisi */
	} else if (!$name) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Empty Field', 'error_code'=>'empty_field', 'post'=>$post));
	
	/* cek duplikat email */
	} else if ($email && !checkDuplicateEmail($email, $idAdmin, 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Email Taken', 'error_code'=>'email_taken'));

	} else {			
		/* Update Admin - jika super admin, maka diupdate */
		$updateEmail	= (in_array(checkTokenRole($token), array('super_admin')) && $email) ? ", email = '".$email."'":"";
		$updatePassword	= (in_array(checkTokenRole($token), array('super_admin')) && $password) ? ", password = '".password_hash($password, PASSWORD_BCRYPT)."'":"";

		$sql = "UPDATE ".DB_PREFIX."admin SET name = '".$name."', update_time = '".$now."' ".$updateEmail.$updatePassword." WHERE id = '".$idAdmin."'";
		
		if ($db->query($sql) === TRUE) {
			logActivity(checkTokenId($token, 'admin'), 'update admin', $idAdmin, 'admin', 'admin');
			echo json_encode(array('status'=>'SUCCESS', 'id'=>$idAdmin, 'name'=>$name));
		
		} else {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Update Admin Error', 'error_code'=>'update_admin_error'));
		}
	}
});

$app->options('/admin/update', function () use ($app) {
});

/***************************************************/

/* DELETE ADMIN - SUPER ADMIN ONLY */
$app->delete('/admin/:idAdmin', function ($idAdmin) use ($app) {
	$headers	= $app->request->headers;

	/* Parameter */
	$token	= ($headers['token']) ? $headers['token']:"";

	require_once 'mysql.php';
	$db = connect_db();

	$sql	= "SELECT a.id, a.name, r.code AS role
				FROM ".DB_PREFIX."admin a
				LEFT JOIN ".DB_PREFIX."admin_role ar ON ar.id_admin = a.id
				LEFT JOIN ".DB_PREFIX."role r ON r.id = ar.id_role
				WHERE a.id = '".$idAdmin."'";
	$result	= $db->query($sql);

	/* hanya untuk super_admin */
	if (!in_array(checkTokenRole($token), array('super_admin'))) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
	
	} else if ($result->num_rows == 0) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Admin Not Found', 'error_code'=>'admin_not_found'));
	
	} else {
		while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
			$data[] = $row;
		}

		if ($data[0]['role'] == 'super_admin') {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Cannot Delete Super Admin', 'error_code'=>'cannot_delete_super_admin')); exit;
		
		} else {
			$sqlDel = "DELETE FROM ".DB_PREFIX."admin WHERE id = '".$idAdmin."'";
			if ($db->query($sqlDel) === TRUE) {
				logActivity(checkTokenId($token, 'admin'), 'delete admin - '.$data[0]['name'], $idAdmin, 'admin', 'admin');
				echo json_encode(array('status'=>'SUCCESS'));
			
			} else {
				echo json_encode(array('status'=>'ERROR', 'message'=>'Delete Error', 'error_code'=>'delete_error'));
			}
		}
	}
});

$app->options('/admin/:idAdmin', function ($idAdmin) use ($app) {
});

/***************************************************/

