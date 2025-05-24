<?php

$DB_DATABASE = "";
$DB_USERNAME = "";
$DB_PASSWORD = "";
$DB_DATABASE_LOCAL = "qlyx_local";

$getClient = $_SERVER['SERVER_NAME'];

if ($getClient == "localhost") {
	// Connect to database 
	// DEVELOPER ENVIRONMENT
	$SERVER_NAME = "localhost";
	$USERNAME = "root";
	$PASSWORD = "";
	$DATABASE = $DB_DATABASE_LOCAL;
} else {
	// Connect to database  
	// ACTUAL SERVER
	$SERVER_NAME = "localhost";
	$USERNAME = $DB_USERNAME;
	$PASSWORD = $DB_PASSWORD;
	$DATABASE = $DB_DATABASE;
}

$conn = mysqli_connect($SERVER_NAME, $USERNAME, $PASSWORD, $DATABASE);
if (!$conn) {
	die("DB-CONNECT CONNECTION ERROR: " . mysqli_connect_error());
}
