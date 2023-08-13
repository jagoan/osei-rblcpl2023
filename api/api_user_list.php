<?php
/* USER LIST - ADMIN ONLY - USER hanya bisa melihat profilnya sendiri */
$app->get('/user(/:idUser)', function ($idUser = '') use ($app) {
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
	$search		= ( isset($get['search']) ) ? mysqli_real_escape_string($db, $get['search']):"";	// search
	$search		= ( isset($get['search']['value']) ) ? mysqli_real_escape_string($db, $get['search']['value']):$search;	// search
	$orderBy	= ($colName) ? $colName." ".$colDir:"update_time DESC";			// orderBy	
	$now		= date('Y-m-d H:i:s');		
	
	/* harus login dulu */
	if (!checkTokenId($token, 'admin') && !checkTokenId($token)) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized', 'token'=>$token, 'id_user'=>checkTokenId($token), 'id_admin'=>checkTokenId($token, 'admin')));

	} else {
		$idUser	= ( !$idUser ) ? checkTokenId($token):$idUser;
		$idUser	= ( !checkTokenId($token, 'admin') ) ? checkTokenId($token):$idUser;

		$whereId		= ($idUser) ? " AND (id = '".$idUser."')":"";
		$whereSearch	= ($search) ? " AND (first_name LIKE '%".$search."%' OR last_name LIKE '%".$search."%' OR departement LIKE '%".$search."%' OR employee_id LIKE '%".$search."%' OR territory LIKE '%".$search."%')":"";
		
		$sql		= "SELECT id, first_name, last_name, departement, employee_id, territory,
						update_time, DATE_FORMAT(update_time, '%e %b %Y, %H:%i') AS update_time_string
						FROM ".DB_PREFIX."user
						WHERE id != '0' ".$whereId.$whereSearch." 
						ORDER BY ".$orderBy;
		$sqlLimit	= " LIMIT ".$offset.", ".$limit;
		$result		= $db->query($sql.$sqlLimit);
		$resultAll	= $db->query($sql);

		/* DEBUG */
		//echo json_encode(array('status'=>'DEBUG', 'error'=>$db->error, 'sql'=>preg_replace("/\s+/", " ", $sql))); exit;

		if ($result->num_rows == 0) {
			echo json_encode(array('status'=>'ERROR', 'message'=>'User Not Found', 'error_code'=>'user_not_found', 'sql'=>preg_replace("/\s+/", " ", $sql), 'draw'=>intval($draw), 'iTotalDisplayRecords'=>$resultAll->num_rows, 'iTotalRecords'=>$result->num_rows, 'offset'=>$offset, 'limit'=>$limit, 'data_total'=>$resultAll->num_rows, 'data'=>array(), 'order_name'=>$colName, 'order_dir'=>$colDir, 'search'=>$search));
		
		} else {
			$data	= array();
			while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
				$data[] = $row;
			}

			for ($i = 0; $i < count($data); $i++) {
				/* score */
				$sqlScore		= "SELECT a.id AS id_activity, a.activity_round, a.name AS game_name, a.start_time, a.end_time, a.status, IF(s.duration IS NOT NULL, s.duration, '') AS duration
									FROM ".DB_PREFIX."activity a
									LEFT JOIN (
										SELECT id_activity, duration FROM ".DB_PREFIX."score WHERE id_user = '".$data[$i]['id']."'
									) s ON s.id_activity = a.id
									ORDER BY a.activity_round ASC";
				$resultScore	= $db->query($sqlScore);
				
				$dataScore	= array();
				while ($row = $resultScore->fetch_array(MYSQLI_ASSOC)) {
					$dataScore[] = $row;
				}

				for ($s = 0; $s < count($dataScore); $s++) {
					$activityStatus	= $dataScore[$s]['status'];
					if ($dataScore[$s]['start_time'] > $now || $dataScore[$s]['end_time'] < $now) {
						$activityStatus	= "disable";
					}
					$dataScore[$s]['status']	= ($dataScore[$s]['status'] == 'disable') ? $dataScore[$s]['status']:$activityStatus;
				}

				$data[$i]['score']	= $dataScore;	
			}

			//echo json_encode(array('status'=>'SUCCESS', 'offset'=>$offset, 'limit'=>$limit, 'data_total'=>$resultAll->num_rows, 'data'=>$data, 'sql'=>preg_replace("/\s+/", " ", $sql)));
			echo json_encode(array('status'=>'SUCCESS', 'draw'=>intval($draw), 'iTotalDisplayRecords'=>$resultAll->num_rows, 'iTotalRecords'=>$result->num_rows, 'offset'=>$offset, 'limit'=>$limit, 'data_total'=>$resultAll->num_rows, 'data'=>$data, 'order_name'=>$colName, 'order_dir'=>$colDir, 'search'=>$search));
		}
	}
});

$app->options('/user(/:idUser)', function ($idUser = '') use ($app) {
});

/***************************************************/