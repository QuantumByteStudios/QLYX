<?php

// Configuration for both environments
$DB_DATABASE_PROD = '';         // Remote/Production DB name
$DB_USERNAME_PROD = '';         // Remote/Production DB username
$DB_PASSWORD_PROD = '';         // Remote/Production DB password
$DB_DATABASE_LOCAL = 'qlyx_local'; // Local DB name

// Determine environment based on hostname
$isLocalhost = ($_SERVER['SERVER_NAME'] === 'localhost');

// Set connection parameters accordingly
$SERVER_NAME = 'localhost'; // Assuming DB is on the same server in both cases
$USERNAME    = $isLocalhost ? 'root' : $DB_USERNAME_PROD;
$PASSWORD    = $isLocalhost ? ''     : $DB_PASSWORD_PROD;
$DATABASE    = $isLocalhost ? $DB_DATABASE_LOCAL : $DB_DATABASE_PROD;

try {
	// Establish PDO connection
	$pdo = new PDO(
		"mysql:host={$SERVER_NAME};dbname={$DATABASE};charset=utf8mb4",
		$USERNAME,
		$PASSWORD,
		[
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Throw exceptions on error
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Safe default fetch mode
		]
	);
} catch (PDOException $e) {
	// Output error in development; log it in production
	die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}
