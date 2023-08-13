<?php
/* ADD / UPDATE GAME */
$app->post('/game', function () use ($app) {
	$post		= $app->request->post();
	$headers	= $app->request->headers;

	/* Parameter */
	$token			= ($headers['token']) ? $headers['token']:"";					// token
	$idGame			= ( isset($post['id_game']) ) ? $post['id_game']:"";			// id_game
	$name			= ( isset($post['name']) ) ? $post['name']:"";					// name
	$gameType		= ( isset($post['game_type']) ) ? $post['game_type']:"";		// game_type
	$gameSetting	= ( isset($post['game_setting']) ) ? $post['game_setting']:"";	// game_setting
	$instruction	= ( isset($post['instruction']) ) ? $post['instruction']:"";	// instruction
	$now			= date('Y-m-d H:i:s');											// now

	require_once 'mysql.php';
	$db = connect_db();

	/* hanya untuk admin */
	if (!checkTokenId($token, 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
	
	/* data utama harus diisi */
	} else if (!$name || !$gameType || !$gameSetting || !$instruction) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Empty Field', 'error_code'=>'empty_field', 'post'=>$post));
	
	} else {
		/* escape string */	
		$name			= mysqli_real_escape_string($db, trim($name));
		$gameType		= mysqli_real_escape_string($db, trim($gameType));
		$instruction	= mysqli_real_escape_string($db, trim($instruction));

		if ($idGame) {		
			/* Edit Game - check if game exist */
			$sql	= "SELECT id FROM ".DB_PREFIX."game WHERE id = '".$idGame."'";
			$result	= $db->query($sql);

			if ($result->num_rows == 0) {
				echo json_encode(array('status'=>'ERROR', 'message'=>'Game Not Found', 'error_code'=>'game_not_found'));
			
			} else {
				/* Update Game */
				$sql = "UPDATE ".DB_PREFIX."game SET name = '".$name."', game_type = '".$gameType."', game_setting = '".$gameSetting."', instruction = '".$instruction."', update_time = '".$now."' WHERE id = '".$idGame."'";
				$db->query($sql);

				if ($db->affected_rows > 0) {
					logActivity(checkTokenId($token, 'admin'), 'update game', $idGame, 'game', 'admin');
					echo json_encode(array('status'=>'SUCCESS', 'id'=>$idGame));
				
				} else {
					echo json_encode(array('status'=>'ERROR', 'message'=>'Update Error', 'error_code'=>'update_error', 'sql'=>$sql));
				}				
			}

		} else {
			/* New Game */
			$sql = "INSERT INTO ".DB_PREFIX."game (name, game_type, game_setting, instruction, update_time) VALUES ('".$name."', '".$gameType."', '".$gameSetting."', '".$instruction."', '".$now."')";
			$db->query($sql);

			if ($db->affected_rows > 0) {
				$idGame = $db->insert_id;	

				logActivity(checkTokenId($token, 'admin'), 'add new game', $idGame, 'game', 'admin');
				echo json_encode(array('status'=>'SUCCESS', 'id'=>$idGame));
			
			} else {
				echo json_encode(array('status'=>'ERROR', 'message'=>'Insert Error', 'error_code'=>'insert_error', 'sql'=>$sql));
			}
		}
	}
});

$app->options('/game', function () use ($app) {
});

/***************************************************/

/* DELETE GAME - ADMIN ONLY */
$app->delete('/game/:idGame', function ($idGame) use ($app) {
	$headers	= $app->request->headers;

	/* Parameter */
	$token	= ($headers['token']) ? $headers['token']:"";

	require_once 'mysql.php';
	$db = connect_db();

	$sql	= "SELECT id, name FROM ".DB_PREFIX."game WHERE id = '".$idGame."'";
	$result	= $db->query($sql);

	/* hanya untuk admin */
	if (!checkTokenId($token, 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
	
	} else if ($result->num_rows == 0) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Game Not Found', 'error_code'=>'game_not_found'));
	
	} else {
		while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
			$data[] = $row;
		}

		$sqlDel = "DELETE FROM ".DB_PREFIX."game WHERE id = '".$idGame."'";
		$db->query($sqlDel);

		if ($db->affected_rows > 0) {
			logActivity(checkTokenId($token, 'admin'), 'delete game - '.$data[0]['name'], $idGame, 'game', 'admin');
			echo json_encode(array('status'=>'SUCCESS'));
		
		} else {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Delete Error', 'error_code'=>'delete_error'));
		}
	}
});

$app->options('/game/:idGame', function ($idGame) use ($app) {
});

/***************************************************/