<?php
/* SUBMIT SCORE */
$app->post('/score', function () use ($app) {
	$post		= $app->request->post();
	$headers	= $app->request->headers;

	/* Parameter */
	$token		= ($headers['token']) ? $headers['token']:"";					// token
	$idActivity	= ( isset($post['id_activity']) ) ? $post['id_activity']:"";	// id_activity
	$duration	= ( isset($post['duration']) ) ? $post['duration']:"0";			// duration
	$now		= date('Y-m-d H:i:s');											// now	

	require_once 'mysql.php';
	$db = connect_db();	

	/* hanya untuk user */
	if (!checkTokenId($token)) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
	
	/* data utama harus diisi */
	} else if (!$idActivity) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Empty Field', 'error_code'=>'empty_field', 'post'=>$post));
	
	} else {
		$sqlScore		= "SELECT id, duration FROM ".DB_PREFIX."score WHERE id_activity = '".$idActivity."' AND id_user = '".checkTokenId($token)."'";
		$resultScore	= $db->query($sqlScore);

		$dataScore	= array();
		while ($row = $resultScore->fetch_array(MYSQLI_ASSOC)) {
			$dataScore[] = $row;
		}

		if ($resultScore->num_rows != 0 && $dataScore[0]['duration'] > 0) {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Duplicate Score', 'error_code'=>'duplicate_score'));
		
		} else {			
			$sql = ($duration > 0) ? "UPDATE ".DB_PREFIX."score SET duration = '".$duration."' WHERE id_activity = '".$idActivity."' AND id_user = '".checkTokenId($token)."'":"INSERT INTO ".DB_PREFIX."score (id_activity, id_user, duration, update_time) VALUES ('".$idActivity."', '".checkTokenId($token)."', '".$duration."', '".$now."')";
			$db->query($sql);

			if ($db->affected_rows > 0) {
				$idScore = ($duration > 0) ? $dataScore[0]['id']:$db->insert_id;
				logActivity(checkTokenId($token), 'submit score: '.$duration, $idScore, 'score');	
				echo json_encode(array('status'=>'SUCCESS', 'id_activity'=>$idActivity, 'id_user'=>checkTokenId($token), 'duration'=>$duration));	

			} else {
				echo json_encode(array('status'=>'ERROR', 'message'=>'Submit Score Error', 'error_code'=>'submit_score_error', 'sql'=>preg_replace("/\s+/", " ", $sql)));
			}
		}
	}
});

$app->options('/score', function () use ($app) {
});

/***************************************************/

/* LEADERBOARD */
$app->get('/leaderboard', function () use ($app) {
	$get		= $app->request->get();
	$headers	= $app->request->headers;
	
	/* DB Connect */
	require_once 'mysql.php';
	$db = connect_db();

	/* Parameter */
	$token		= ($headers['token']) ? $headers['token']:"";				// token
	$limit		= ( isset($get['limit']) ) ? $get['limit']:"10";			// limit
	$now		= date('Y-m-d H:i:s');			
	
	/* harus login dulu */
	// if (!checkTokenId($token, 'admin') && !checkTokenId($token)) {
	// 	echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized', 'token'=>$token, 'id_user'=>checkTokenId($token), 'id_admin'=>checkTokenId($token, 'admin')));

	// } else {
		/* territory */
		$sqlTerritory		= "SELECT name FROM ".DB_PREFIX."ref_territory ORDER BY name ASC";
		$resultTerritory	= $db->query($sqlTerritory);

		$dataTerritory	= array();
		while ($row = $resultTerritory->fetch_array(MYSQLI_ASSOC)) {
			$dataTerritory[] = $row;
		}

		/* round */
		$sqlRound		= "SELECT DISTINCT(activity_round) FROM ".DB_PREFIX."activity";
		$resultRound	= $db->query($sqlRound);
		
		$dataRound	= array();
		while ($row = $resultRound->fetch_array(MYSQLI_ASSOC)) {
			$dataRound[] = $row;
		}

		$data	= array();
		$i = 0;		
			
		for ($r = 0; $r < count($dataRound); $r++) {
			$sqlActivity	= "SELECT id FROM ".DB_PREFIX."activity WHERE activity_round = '".$dataRound[$r]['activity_round']."' AND start_time <= '".$now."'";
			$resultActivity	= $db->query($sqlActivity);

			$dataActivity	= array();
			while ($row = $resultActivity->fetch_array(MYSQLI_ASSOC)) {
				$dataActivity[] = $row['id'];
			}
			$idActivityCount	= count($dataActivity);
			$idActivityList		= (count($dataActivity) > 0) ? implode(',', $dataActivity):"";			

			for ($t = 0; $t < count($dataTerritory); $t++) {
				$sqlUser	= "SELECT id FROM ".DB_PREFIX."user WHERE territory = '".$dataTerritory[$t]['name']."'";
				$resultUser	= $db->query($sqlUser);
	
				$dataUser	= array();
				while ($row = $resultUser->fetch_array(MYSQLI_ASSOC)) {
					$dataUser[] = $row['id'];
				}
				$idUserList		= (count($dataUser) > 0) ? implode(',', $dataUser):"";
								
				if ($idUserList && $idActivityList) {
    				$sqlScore		= "SELECT s.id_user, u.first_name, u.last_name, u.departement, s.total_duration
    									FROM (
    										SELECT s.total_duration, s.count_score, s.id_user
    										FROM (
    											SELECT SUM(duration) AS total_duration, COUNT(id) AS count_score, id_user
    											FROM ".DB_PREFIX."score
    											WHERE id_activity IN (".$idActivityList.")
    											AND id_user IN (".$idUserList.")
												AND duration > 0
    											GROUP BY id_user
    										) s
    										WHERE s.count_score = '".$idActivityCount."'
    										ORDER BY s.total_duration ASC
    										LIMIT 0, ".$limit."
    									) s
    									LEFT JOIN ".DB_PREFIX."user u ON u.id = s.id_user";
    				$resultScore	= $db->query($sqlScore);
    
    				$dataScore	    = array();
    				$dataScoreUser  = array();
    				while ($row = $resultScore->fetch_array(MYSQLI_ASSOC)) {
    					$dataScore[] 		= $row;
    					$dataScoreUser[] 	= $row['id_user'];
    				}
    				$idUserScoreCount	= count($dataScoreUser);
    				$idUserScoreList	= (count($dataScoreUser) > 0) ? implode(',', $dataScoreUser):"";
    
    				for ($s = 0; $s < count($dataScore); $s++) {
    					$sqlScoreActivity		= "SELECT s.id_activity, a.name AS activity_name, s.duration
    												FROM (													
    													SELECT s.duration, s.id_user, s.id_activity
    													FROM ".DB_PREFIX."score s 
    													WHERE s.id_activity IN (".$idActivityList.")
    													AND s.id_user = '".$dataScore[$s]['id_user']."'													
    												) s
    												LEFT JOIN ".DB_PREFIX."activity a ON a.id = s.id_activity";
    					$resultScoreActivity	= $db->query($sqlScoreActivity);
    
    					$dataScoreActivity	= array();
    					while ($row = $resultScoreActivity->fetch_array(MYSQLI_ASSOC)) {
    						$dataScoreActivity[] = $row;
    					}
    
    					$dataScore[$s]['duration_activity']	= $dataScoreActivity;					
    				}
    				
				} else {
				    $idUserScoreList    = "";
				    $dataScore          = array();
				}

				$data[$i]	= array('territory'=>$dataTerritory[$t]['name'], 'round'=>$dataRound[$r]['activity_round'], 'count_activity'=>$idActivityCount, 'activity_list'=>$idActivityList, 'count_user'=>$idUserScoreCount, 'user_list'=>$idUserScoreList, 'score'=>$dataScore);	
				$i++;
			}			
		}

		//echo json_encode(array('status'=>'SUCCESS', 'data_total'=>count($data), 'data'=>$data, 'limit'=>$limit, 'sql'=>preg_replace("/\s+/", " ", $sqlScore)));
		echo json_encode(array('status'=>'SUCCESS', 'data_total'=>count($data), 'data'=>$data, 'limit'=>$limit));
	// }
});

$app->options('/leaderboard', function () use ($app) {
});

/***************************************************/

/* DELETE ALL SCORE - ADMIN ONLY */
$app->delete('/score/all', function () use ($app) {
	$headers	= $app->request->headers;

	/* Parameter */
	$token	= ($headers['token']) ? $headers['token']:"";

	require_once 'mysql.php';
	$db = connect_db();

	$sql	= "SELECT id FROM ".DB_PREFIX."score";
	$result	= $db->query($sql);

	/* hanya untuk admin */
	if (!checkTokenId($token, 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
	
	} else if ($result->num_rows == 0) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Score Not Found', 'error_code'=>'score_not_found'));
	
	} else {
		while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
			$data[] = $row;
		}

		$sqlDel = "DELETE FROM ".DB_PREFIX."score";
		$db->query($sqlDel);

		if ($db->affected_rows > 0) {
			logActivity(checkTokenId($token, 'admin'), 'delete score all', '0', 'score', 'admin');
			echo json_encode(array('status'=>'SUCCESS'));
		
		} else {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Delete Error', 'error_code'=>'delete_error'));
		}
	}
});

$app->options('/score/all', function () use ($app) {
});

/***************************************************/

/* DELETE SCORE PER ACTIVITY PER USER (UNBLOCK USER) - ADMIN ONLY */
$app->delete('/score/:idActivity/:idUser', function ($idActivity, $idUser) use ($app) {
	$headers	= $app->request->headers;

	/* Parameter */
	$token	= ($headers['token']) ? $headers['token']:"";

	require_once 'mysql.php';
	$db = connect_db();

	$sql	= "SELECT id FROM ".DB_PREFIX."score WHERE id_activity = '".$idActivity."' AND id_user = '".$idUser."' AND duration = '0'";
	$result	= $db->query($sql);

	/* hanya untuk admin */
	if (!checkTokenId($token, 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
	
	} else if ($result->num_rows == 0) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Score Not Found', 'error_code'=>'score_not_found'));
	
	} else {
		while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
			$data[] = $row;
		}

		$sqlDel = "DELETE FROM ".DB_PREFIX."score WHERE id_activity = '".$idActivity."' AND id_user = '".$idUser."' AND duration = '0'";
		$db->query($sqlDel);

		if ($db->affected_rows > 0) {
			logActivity(checkTokenId($token, 'admin'), 'delete score (unblock) - id_user: '.$idUser.' - id_activity: '.$idActivity, $data[0]['id'], 'score', 'admin');
			echo json_encode(array('status'=>'SUCCESS'));
		
		} else {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Delete Error', 'error_code'=>'delete_error'));
		}
	}
});

$app->options('/score/:idActivity/:idUser', function ($idActivity, $idUser) use ($app) {
});

/***************************************************/

/* SUBMIT SCORE DEBUG */
$app->post('/scoreDebug', function () use ($app) {
	$post		= $app->request->post();
	$headers	= $app->request->headers;

	/* Parameter */
	$token		= ($headers['token']) ? $headers['token']:"";					// token
	$now		= date('Y-m-d H:i:s');											// now	

	require_once 'mysql.php';
	$db = connect_db();

	/* hanya untuk user */
	if (!checkTokenId($token, 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
	
	} else {
		$sql	= "SELECT id FROM ".DB_PREFIX."activity";
		$result	= $db->query($sql);

		$data	= array();
		while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
			$data[] = $row;
		}

		for ($u = 1; $u <= 100; $u++) {
			$sqlValues	= "";
			for ($i = 0; $i < count($data); $i++) {
				$sqlValues	.= ($i != 0) ? ", ":"";
				$sqlValues	.= "('".$data[$i]['id']."', '".$u."', '".rand(600000, 900000)."', '".$now."')";
			}
			$sql = "INSERT INTO ".DB_PREFIX."score (id_activity, id_user, duration, update_time) VALUES ".$sqlValues;
			$db->query($sql);
		}

		echo json_encode(array('status'=>'SUCCESS'));
	}
});

$app->options('/scoreDebug', function () use ($app) {
});

/***************************************************/