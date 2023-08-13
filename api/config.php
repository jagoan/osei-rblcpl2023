<?php
//date_default_timezone_set("UTC");
date_default_timezone_set("Asia/Jakarta");


define('DB_HOST','localhost');
define('DB_USER','jagoanin_eventqiu');
define('DB_PASS','3v3ntQiu!');
define('DB_NAME','jagoanin_gameqiu');
define('DB_PREFIX','qiu_');

define('SITE_NAME','Gameqiu');		
define('HOST','jagoan.info');			// domain name. NO http, NO www
define('SERVER_KEY','JGWN-GAMEQIU');
define('MAIN_URL', 'https://gameqiu.jagoan.info/');
define('MEDIA_URL', 'https://gameqiu.jagoan.info/media/');

define('USER_VALIDATION_URL', MAIN_URL.'validation');

define('EMAIL_DEVELOPER','doddi.sudartha@gmail.com');	// Bcc
define('IS_SMTP','yes');								// yes OR no

define('SMTP_HOST','mail.jagoan.info');
define('SMTP_USER','noreply@jagoan.info');
define('SMTP_PASS','jgwnN0reply!');
define('SMTP_PORT','465');
define('SMTP_SECURITY','ssl');

// define('SMTP_HOST','mail.smtp2go.com');
// define('SMTP_USER','noreply@jagoan.info');
// define('SMTP_PASS','N0replyJGWN!');
// define('SMTP_PORT','80');
// define('SMTP_SECURITY','tls');


//error_reporting(E_ALL & ~E_NOTICE);
error_reporting(E_ALL ^ E_DEPRECATED);
?>