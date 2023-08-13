<?php
/* CONFIG LIST */
$app->get('/config(/:configName)', function ($configName = '') use ($app) {
	$get		= $app->request->get();
	$headers	= $app->request->headers;

	/* Parameter */
	$token		= ($headers['token']) ? $headers['token']:"";			// token
	$order		= ( isset($get['order']) ) ? $get['order']:"id ASC";	// order
	$order		= ($order == 'titleDesc') ? "config_title DESC":$order;	// order 
	$order		= ($order == 'titleAsc') ? "config_title ASC":$order;	// order 
	$order		= ($order == 'nameDesc') ? "config_name DESC":$order;	// order 
	$order		= ($order == 'nameAsc') ? "config_name ASC":$order;		// order

	require_once 'mysql.php';
	$db = connect_db();	
	
	/* harus login baik sebagai admin maupun sebagai user */
	//if (!checkTokenId($token, 'admin') && !checkTokenId($token)) {
	//	echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));

	//} else {
		$whereName		= ($configName) ? " AND (config_name = '".$configName."')":"";
		
		$sql		= "SELECT id, config_title, config_name, config_value
						FROM ".DB_PREFIX."config 
						WHERE id != '0' ".$whereName." 
						ORDER BY ".$order;
		$result		= $db->query($sql);
		//echo $sql; exit;
		if ($result->num_rows == 0) {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Config Not Found', 'error_code'=>'config_not_found', 'sql'=>preg_replace("/\s+/", " ", $sql)));
		
		} else {
			$data	= array();
			while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
				$data[] = $row;
			}

			$config	= array();
			for ($i = 0; $i < count($data); $i++) {
				$config[$data[$i]['config_name']]	= $data[$i]['config_value'];
			}
			//echo json_encode(array('status'=>'SUCCESS', 'data_total'=>$result->num_rows, 'data'=>$data, 'config'=>$config));
			echo json_encode(array('status'=>'SUCCESS', 'config'=>$config));
		}
	//}
});

$app->options('/config(/:configName)', function ($configName = '') use ($app) {
});

/***************************************************/

/* UPDATE CONFIG */
$app->post('/config', function () use ($app) {
	$post		= $app->request->post();
	$headers	= $app->request->headers;

	/* Parameter */
	$token			= ($headers['token']) ? $headers['token']:"";					// token
	$configName		= ( isset($post['config_name']) ) ? $post['config_name']:"";	// config_name (UPDATE)
	$configValue	= ( isset($post['config_value']) ) ? $post['config_value']:"";	// config_value
	$now			= date('Y-m-d H:i:s');		

	require_once 'mysql.php';
	$db = connect_db();

	/* Edit Config */
	$sql	= "SELECT id FROM ".DB_PREFIX."config WHERE config_name = '".$configName."'";
	$result	= $db->query($sql);

	/* harus login terlebih dahulu */
	if (!checkTokenId($token, 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));

	/* config_value wajib diisi */
	} else if (!$configName || !$configValue) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Empty Field', 'error_code'=>'empty_field'));

	/* config tidak ditemukan */
	} else if ($result->num_rows == 0) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Config Not Found', 'error_code'=>'config_not_found'));
	
	} else {
		$data	= array();
		while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
			$data[] = $row;
		}

		/* escape string */
		$configValue	= mysqli_real_escape_string($db, trim($configValue));

		/* Update Config */	
		$sql = "UPDATE ".DB_PREFIX."config SET config_value = '".$configValue."', update_time = '".$now."' WHERE config_name = '".$configName."'";
		$db->query($sql);

		if ($db->affected_rows > 0) {
			logActivity(checkTokenId($token, 'admin'), 'update config', $data[0]['id'], 'config', 'admin');
			echo json_encode(array('status'=>'SUCCESS', 'config_name'=>$configName, 'config_value'=>$configValue));
		
		} else {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Update Error', 'error_code'=>'update_error', 'sql'=>$sql));
		}	
	}		
});

$app->options('/config', function () use ($app) {
});

/***************************************************/