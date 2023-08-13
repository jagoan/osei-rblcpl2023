<?php
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* RESET PASSWORD */
$app->post('/admin/password/reset', function () use ($app) {
	$post = $app->request->post();

	/* Parameter */
	$email	= ( isset($post['email']) ) ? $post['email']:"";	// email
	$now	= date('Y-m-d H:i:s');								// now

	require_once 'mysql.php';
	$db = connect_db();

	$result	= $db->query("SELECT id, name  FROM ".DB_PREFIX."admin WHERE email = '".$email."'");
	
	/* data utama harus diisi */
	if (!$email) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Empty Field', 'error_code'=>'empty_field', 'post'=>$post));
	
	} else if ($result->num_rows == 0) {
		echo json_encode(array('status'=>'ERROR', 'message'=>EMAIL_NOT_REGISTERED, 'error_code'=>'email_not_registered'));
	
	} else {
		while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
			$data[] = $row;
		}		
		
		$passwordTemp	= strtolower(randomString(8));
		$sql = "UPDATE ".DB_PREFIX."admin SET password_temp = '".password_hash($passwordTemp, PASSWORD_BCRYPT)."', update_time = '".$now."' WHERE email = '".$email."'";

		if ($db->query($sql) === TRUE) {
			/* SEND EMAIL */
			$userPassword	= 'Password: '.$passwordTemp;

			$body	= '<html>
	<title>'.SITE_NAME.'</title>
	<meta charset="utf-8"/>
	<meta name="viewport" content="width=device-width, initial-scale=1"/>
	<body style="margin:0; font-family:Arial; font-size:13px; line-height:1.4; color:#000;">

		<div style="max-width:700px; margin:0;">
			<div style="margin:20px 10px 0; padding:10px 0;">
				<p>Password Anda telah berhasil direset. Anda dapat login dengan menggunakan password berikut:</p>
				<p>'.nl2br($userPassword).'</p>
				<p>Abaikan email ini jika Anda tidak bermaksud mereset password dan silakan login dengan menggunakan password sebelumnya.</p>
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
				$mail->AddAddress($email, $data[0]['name']);			// Add a recipient

				// Content
				$mail->isHTML(true);                                  // Set email format to HTML
				$mail->Subject	= "[".SITE_NAME."] Password Baru Anda";
				$mail->Body		= $body;
				$mail->AltBody	= "Password Anda telah berhasil direset. Anda dapat login dengan menggunakan password berikut:\n\n".$userPassword."\n\nAbaikan email ini jika Anda tidak bermaksud mereset password dan silakan login dengan menggunakan password sebelumnya.";

				if ($mail->send()) {
					logActivity($data[0]['id'], 'reset password', $data[0]['id'], 'admin', 'admin');
					echo json_encode(array('status'=>'SUCCESS', 'email'=>$email));
				} else {
					echo json_encode(array('status'=>'ERROR', 'message'=>'Mailer Error: '.$mail->ErrorInfo, 'error_code'=>'email_not_send'));
				}				
				
			} catch (Exception $e) {
				echo json_encode(array('status'=>'ERROR', 'message'=>'Mailer Error: '.$mail->ErrorInfo, 'error_code'=>'email_not_send_exception'));
			}

		} else {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Reset Error', 'error_code'=>'reset_error'));
		}
	}
});

$app->options('/admin/password/reset', function () use ($app) {
});

/***************************************************/

/* UPDATE PASSWORD */
$app->post('/admin/password/update',	function () use ($app) {
	$post		= $app->request->post();
	$headers	= $app->request->headers;

	/* Parameter */
	$token				= ($headers['token']) ? $headers['token']:"";							// token
	$passwordCurrent	= ( isset($post['password_current']) ) ? $post['password_current']:"";	// password saat ini
	$password			= ( isset($post['password_new']) ) ? $post['password_new']:"";			// password baru
	$password2			= ( isset($post['password2']) ) ? $post['password2']:$password;			// password baru

	require_once 'mysql.php';
	$db = connect_db();

	if (!checkTokenId($token, 'admin')) {
		echo json_encode(array('status'=>'ERROR', 'message'=>'Not Authorized', 'error_code'=>'not_authorized'));
	
	//} else if ($password != $password2) {
	//	echo json_encode(array('status'=>'ERROR', 'message'=>'New Password Not Match', 'error_code'=>'new_password_not_match'));
	
	} else {
		$result	= $db->query("SELECT password, password_temp FROM ".DB_PREFIX."admin WHERE id = '".checkTokenId($token, 'admin')."'");
		$data	= array();
		while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
			$data[] = $row;
		}

		if (password_verify($passwordCurrent, $data[0]['password']) || password_verify($passwordCurrent, $data[0]['password_temp'])) {
			$sql = "UPDATE ".DB_PREFIX."admin SET password = '".password_hash($password, PASSWORD_BCRYPT)."', password_temp = '' WHERE id = '".checkTokenId($token, 'admin')."'";
			
			if ($db->query($sql) === TRUE) {	
				logActivity(checkTokenId($token, 'admin'), 'update password', checkTokenId($token), 'admin', 'admin');
				echo json_encode(array('status'=>'SUCCESS'));

			} else {
				echo json_encode(array('status'=>'ERROR', 'message'=>'Update Error', 'error_code'=>'update_error'));
			}

		} else {
			echo json_encode(array('status'=>'ERROR', 'message'=>'Invalid Current Password', 'error_code'=>'invalid_current_password', 'id_user'=>checkTokenId($token, 'admin'), 'data'=>$data, 'password_current'=>$passwordCurrent));
		}
	}
});

$app->options('/admin/password/update',	function () use ($app) {
});

/***************************************************/

