<?php
require_once 'config.php';
function connect_db() {
	$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
	return $connection;
}
?>