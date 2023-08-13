<?php
/* SUBMIT SCORE */
$app->post('/score', function () use ($app) {
	$post		= $app->request->post();
	$headers	= $app->request->headers;

	/* Parameter */
	$token		= ($headers['token']) ? $headers['token']:"";				// token
	$idGame		= ( isset($post['id_game']) ) ? $post['id_game']:"";		// id_game
	$name		= ( isset($post['name']) ) ? $post['name']:"";				// name
	$score		= ( isset($post['score']) ) ? $post['score']:"";			// score
	$duration	= ( isset($post['duration']) ) ? $post['duration']:"";		// duration
	$now		= date('Y-m-d H:i:s');										// now	

	require_once 'mysql.php';
	$db = connect_db();

	/* hanya untuk user */
	//if (!checkTokenId($token)) {
	//	echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
	
	/* data utama harus diisi */
	//} else if ( !$idLogo ) {
	if (!$idGame || !$name) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Empty Field', 'error_code'=>'empty_field', 'post'=>$post));
	
	} else {
		$sql = "INSERT INTO ".DB_PREFIX."game_score (id_game, name, score, duration, update_time) VALUES ('".$idGame."', '".$name."', '".$score."', '".$duration."', '".$now."')";
		$db->query($sql);

		if ($db->affected_rows > 0) {
			$idScore = $db->insert_id;
			logActivity('', 'submit score', $idScore, 'score');	
			echo json_encode(array('status'=>'SUCCESS', 'id_game'=>$idGame, 'name'=>$name, 'score'=>$score, 'duration'=>$duration));	

		} else {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Submit Score Error', 'error_code'=>'submit_score_error', 'sql'=>preg_replace("/\s+/", " ", $sql))); exit;
		}
	}
});

$app->options('/score', function () use ($app) {
});

/***************************************************/

/* LEADERBOARD */
$app->get('/leaderboard(/:idGame)', function ($idGame = '') use ($app) {
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
	$colName	= ( isset($get['order_name']) ) ? $get['order_name']:$colName;								// column_name
	$search		= "";
	//$search		= ( isset($get['search']) ) ? mysqli_real_escape_string($db, $get['search']):"";			// search
	$search		= ( isset($get['search']['value']) ) ? mysqli_real_escape_string($db, $get['search']['value']):$search;	// search
	$orderBy	= ($colName) ? $colName." ".$colDir:"id DESC";					// orderBy		
	
	/* harus login dulu */
	//if (!checkTokenId($token, 'admin') && !checkTokenId($token)) {
	//	echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized', 'token'=>$token, 'id_user'=>checkTokenId($token), 'id_admin'=>checkTokenId($token, 'admin')));

	//} else {
		$whereGame		= ($idGame) ? " AND (id_game = '".$idGame."')":"";
		$whereSearch	= ($search) ? " AND (name LIKE '%".$search."%')":"";
		
		$sql		= "SELECT id, id_game, name, score, duration
						FROM ".DB_PREFIX."game_score
						WHERE id != '0' ".$whereGame.$whereSearch." 
						ORDER BY ".$orderBy;
		$sqlLimit	= " LIMIT ".$offset.", ".$limit;
		$result		= $db->query($sql.$sqlLimit);
		$resultAll	= $db->query($sql);

		/* DEBUG */
		//echo json_encode(array('status'=>'DEBUG', 'error'=>$db->error, 'sql'=>preg_replace("/\s+/", " ", $sql))); exit;

		if ($result->num_rows == 0) {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Score Not Found', 'error_code'=>'score_not_found'));
		
		} else if (!$idGame) {
			echo json_encode(array('status'=>'ERROR', 'message'=>'ID Game Not Found', 'error_code'=>'id_game_not_found'));
		
		} else {
			$data	= array();
			while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
				$data[] = $row;
			}

			//echo json_encode(array('status'=>'SUCCESS', 'offset'=>$offset, 'limit'=>$limit, 'data_total'=>$resultAll->num_rows, 'data'=>$data, 'sql'=>preg_replace("/\s+/", " ", $sql)));
			echo json_encode(array('status'=>'SUCCESS', 'draw'=>intval($draw), 'iTotalDisplayRecords'=>$resultAll->num_rows, 'iTotalRecords'=>$result->num_rows, 'offset'=>$offset, 'limit'=>$limit, 'data_total'=>$resultAll->num_rows, 'data'=>$data, 'order_name'=>$colName, 'order_dir'=>$colDir, 'search'=>$search));
		}
	//}
});

$app->options('/leaderboard(/:idGame)', function ($idGame = '') use ($app) {
});

/***************************************************/