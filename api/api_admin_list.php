<?php
/* ADMIN LIST - SUPER ADMIN ONLY - ADMIN hanya bisa melihat profilnya sendiri */
$app->get('/admin(/:idAdmin)', function ($idAdmin = '') use ($app) {
	$get		= $app->request->get();
	$headers	= $app->request->headers;
	
	/* DB Connect */
	require_once 'mysql.php';
	$db = connect_db();

	/* Parameter */
	$token		= ($headers['token']) ? $headers['token']:"";						// token
	$draw		= ( isset($get['draw']) ) ? $get['draw']:"0";						// draw
	$offset		= ( isset($get['offset']) ) ? $get['offset']:"0";					// offset
	$offset		= ( isset($get['start']) ) ? $get['start']:$offset;					// start
	$limit		= ( isset($get['limit']) ) ? $get['limit']:"10";					// limit
	$limit		= ( isset($get['length']) ) ? $get['length']:$limit;				// length
	$colIndex	= ( isset($get['order'][0]['column']) ) ? $get['order'][0]['column']:"0";	// order_column
	$colDir		= ( isset($get['order'][0]['dir']) ) ? $get['order'][0]['dir']:"";			// order_dir	
	$colDir		= ( isset($get['order_dir']) ) ? $get['order_dir']:$colDir;					// order_dir
	$colName	= ( isset($get['columns'][$colIndex]['data']) ) ? $get['columns'][$colIndex]['data']:"";	// column_name
	$colName	= ( isset($get['order_name']) ) ? $get['order_name']:$colName;				// column_name
	$search		= "";
	//$search		= ( isset($get['search']) ) ? mysqli_real_escape_string($db, $get['search']):"";	// search
	$search		= ( isset($get['search']['value']) ) ? mysqli_real_escape_string($db, $get['search']['value']):$search;	// search
	$orderBy	= ($colName) ? $colName." ".$colDir:"a.id DESC";			// orderBy		
	
	/* harus login dulu */
	if (!checkTokenId($token, 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized', 'token'=>$token, 'id'=>checkTokenId($token, 'admin')));

	} else {
		$idAdmin	= ( !$idAdmin && !in_array(checkTokenRole($token, 'admin'), array('super_admin')) ) ? checkTokenId($token, 'admin'):$idAdmin;
		$idAdmin	= ( !in_array(checkTokenRole($token, 'admin'), array('super_admin')) ) ? checkTokenId($token, 'admin'):$idAdmin;

		$whereId		= ($idAdmin) ? " AND (a.id = '".$idAdmin."')":"";
		$whereSearch	= ($search) ? " AND (a.name LIKE '%".$search."%' OR a.email LIKE '%".$search."%')":"";
		
		$sql		= "SELECT a.id, a.name, a.email, r.name AS role, r.code AS role_code
						FROM ".DB_PREFIX."admin a
						LEFT JOIN ".DB_PREFIX."admin_role ar ON ar.id_admin = a.id
						LEFT JOIN ".DB_PREFIX."role r ON r.id = ar.id_role
						WHERE a.id != '0' ".$whereId.$whereSearch." 
						ORDER BY ".$orderBy;
		$sqlLimit	= " LIMIT ".$offset.", ".$limit;
		$result		= $db->query($sql.$sqlLimit);
		$resultAll	= $db->query($sql);

		/* DEBUG */
		//echo json_encode(array('status'=>'DEBUG', 'error'=>$db->error, 'sql'=>preg_replace("/\s+/", " ", $sql))); exit;

		if ($result->num_rows == 0) {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Admin Not Found', 'error_code'=>'admin_not_found', 'sql'=>preg_replace("/\s+/", " ", $sql)));
		
		} else {
			$data	= array();
			while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
				$data[] = $row;
			}

			//echo json_encode(array('status'=>'SUCCESS', 'offset'=>$offset, 'limit'=>$limit, 'data_total'=>$resultAll->num_rows, 'data'=>$data, 'sql'=>preg_replace("/\s+/", " ", $sql)));
			echo json_encode(array('status'=>'SUCCESS', 'draw'=>intval($draw), 'iTotalDisplayRecords'=>$resultAll->num_rows, 'iTotalRecords'=>$result->num_rows, 'offset'=>$offset, 'limit'=>$limit, 'data_total'=>$resultAll->num_rows, 'data'=>$data, 'order_name'=>$colName, 'order_dir'=>$colDir, 'search'=>$search));
		}
	}
});

$app->options('/admin(/:idAdmin)', function ($idAdmin = '') use ($app) {
});

/***************************************************/