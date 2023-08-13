<?php
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* Register 16 */
$app->post('/register16', function () use ($app) {
	$post		= $app->request->post();
	$headers	= $app->request->headers;

	/* Parameter */
	$token			= ($headers['token']) ? $headers['token']:"";					// token
	$idRegister		= ( isset($post['id_register']) ) ? $post['id_register']:"";	// id_register
	$isSubmit		= ( isset($post['is_submit']) ) ? $post['is_submit']:"";		// is_submit
	$isCorrect		= ( isset($post['is_correct']) ) ? $post['is_correct']:"";		// is_correct
	$now			= date('Y-m-d H:i:s');											// now

	require_once 'mysql.php';
	$db = connect_db();

	/* hanya untuk user */
	if (!checkTokenId($token)) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
	
	/* data utama harus diisi */
	} else if (!$isCorrect) {
		echo json_encode(array('status'=>'ERROR', 'message'=>IS_CORRECT_EMPTY, 'error_code'=>'is_correct_empty', 'post'=>$post));
	
	} else {
		/* check incomplete data */
		$sql	= "SELECT r.id, r.status, r.is_complete_01, r.is_complete_02, r.is_complete_03, r.is_complete_04, r.is_complete_05, r.is_complete_06, 
					r.is_complete_07, r.is_complete_08, r.is_complete_09, r.is_complete_10, r.is_complete_11, r.is_complete_12, r.is_complete_13,
					r.is_complete_14, r.is_complete_15, u.email, u.name
					FROM ".DB_PREFIX."register r
					LEFT JOIN ".DB_PREFIX."user u ON u.id = r.id_user
					WHERE r.id_user = '".checkTokenId($token)."'";
		$result	= $db->query($sql);
		
		if ($result->num_rows == 0) {
			$arrComplete	= array();
			$idRegister		= "";
		
		} else {
			$data	= array();
			while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
				$data[] = $row;
			}			
			
			$arrComplete	= array();
			if ($data[0]['is_complete_01'] != '1') { $arrComplete[] = "Personal Information"; }
			if ($data[0]['is_complete_03'] != '1') { $arrComplete[] = "Emergency Contact Information"; }
			if ($data[0]['is_complete_04'] != '1') { $arrComplete[] = "English Language Proviciency"; }
			if ($data[0]['is_complete_05'] != '1') { $arrComplete[] = "Educational Background"; }
			if ($data[0]['is_complete_06'] != '1') { $arrComplete[] = "Scholarship History"; }
			if ($data[0]['is_complete_07'] != '1') { $arrComplete[] = "Employment History"; }
			if ($data[0]['is_complete_11'] != '1') { $arrComplete[] = "Field of Study"; }
			if ($data[0]['is_complete_13'] != '1') { $arrComplete[] = "Essays"; }
			if ($data[0]['is_complete_14'] != '1') { $arrComplete[] = "Reference Summary"; }
			if ($data[0]['is_complete_15'] != '1') { $arrComplete[] = "Required Documents"; }					
				
			$idRegister		= $data[0]['id'];
		};
		
		/* DEBUG */
		//echo json_encode(array('status'=>'ERROR', 'arr_complete'=>$arrComplete, 'count_arr_complete'=>count($arrComplete))); exit;

		$errComplete	= (count($arrComplete) > 0) ? implode(", ",$arrComplete):"";	
		
		if ($isSubmit == '1' && $errComplete == '') {
			$email	= $data[0]['email'];
			$name	= $data[0]['name'];

			/* Update Register */
			$sql = "UPDATE ".DB_PREFIX."register SET update_time = '".$now."', status = 'submit' WHERE id = '".$idRegister."'";
			
			if ($db->query($sql) === TRUE) {	
				/* SEND EMAIL */
				$body	= '<html>
	<title>'.SITE_NAME.'</title>
	<meta charset="utf-8"/>
	<meta name="viewport" content="width=device-width, initial-scale=1"/>
	<body style="margin:0; font-family:Arial; font-size:13px; line-height:1.4; color:#000;">

		<div style="max-width:620px; margin:0;">
			<div style="margin:20px 10px 0; padding:10px 0;">
				<p>Halo,</p>
				<p>Pengajuan beasiswa telah berhasil terkirim dan akan segera diproses.</p>
				<p>Mohon menunggu informasi selanjutnya.</p>

				<p>Terima kasih.</p>
			</div>
		</div>

	</body>
</html>';
				$mail	= new PHPMailer();
				try {
					//Server settings
					if (strtolower(IS_SMTP) == 'yes') {
						$mail->IsSMTP();						// Set mailer to use SMTP
						$mail->Host			= SMTP_HOST;		// Specify main and backup SMTP servers
						$mail->SMTPAuth		= true;				// Enable SMTP authentication
						$mail->Username		= SMTP_USER;		// SMTP username
						$mail->Password		= SMTP_PASS;		// SMTP password
						$mail->SMTPSecure	= SMTP_SECURITY;	// Enable TLS encryption, `ssl` also accepted
						$mail->Port			= SMTP_PORT;		// TCP port to connect to
						$mail->SMTPDebug	= 0;
					}
					
					//Recipients
					$mail->SetFrom('noreply@'.HOST, SITE_NAME);
					$mail->AddReplyTo('noreply@'.HOST, 'noreply@'.HOST);			
					$mail->AddAddress($email, $name);						// Add a recipient
					
					// Content
					$mail->isHTML(true);									// Set email format to HTML
					$mail->Subject	= "[".SITE_NAME."] Pengajuan Beasiswa";
					$mail->Body		= $body;
					$mail->AltBody	= "Halo,\n\nPengajuan beasiswa telah berhasil terkirim dan akan segera diproses.\n\nMohon menunggu informasi selanjutnya.\n\nTerima kasih.";
					
					if ($mail->send()) {
						logActivity(checkTokenId($token), 'register submit', $idRegister, 'register');
						echo json_encode(array('status'=>'SUCCESS', 'id_register'=>$idRegister, 'do'=>'redirect'));
					
					} else {
						echo json_encode(array('status'=>'ERROR', 'message'=>'Mailer Error: '.$mail->ErrorInfo, 'error_code'=>'email_not_send'));
					}

				} catch (Exception $e) {
					echo json_encode(array('status'=>'ERROR', 'message'=>'Mailer Error: '.$mail->ErrorInfo, 'error_code'=>'email_not_send'));
				}

			} else {
				echo json_encode(array('status'=>'ERROR', 'message'=>'Register Error', 'error_code'=>'register_error'));
			}			

		} else if ($isSubmit == '1' && $errComplete != '') {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Please complete the following data: '.$errComplete, 'error_code'=>'incomplete_data', 'id_user'=>checkTokenId($token), 'is_submit'=>$isSubmit, 'err1'=>$errComplete));

		} else {
			//echo json_encode(array('status'=>'SUCCESS', 'id_register'=>$idRegister));
			echo json_encode(array('status'=>'ERROR', 'message'=>'Please complete the following data: '.$errComplete, 'error_code'=>'incomplete_data', 'id_user'=>checkTokenId($token), 'is_submit'=>$isSubmit, 'err2'=>$errComplete));
		}
	}
});

$app->options('/register16', function () use ($app) {
});

/***************************************************/