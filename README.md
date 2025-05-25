# QLYX Analytics

**QLYX** is a lightweight PHP-based website analytics tool designed to track and visualize user activity and trends with zero front-end integration.

## Getting Started

QLYX automatically tracks each visit to your page and stores the data for statistical analysis.

## View Analytics

Access the dashboard to view detailed analytics and visitor trends:

[QLYX Dashboard](QLYX/)

## Integration Guide

To enable visit tracking on any PHP page, simply include the QLYX script and initialize it as shown below:

```php
<?php
require_once "QLYX/qlyx.php";
require_once "db-connect.php";

$qlyx = new QLYX($pdo);
$qlyx->track();
?>
```

Place this code on any page where you want to enable tracking. Make sure to establish a working database connection before calling `$qlyx->track()`.

## Setup Requirements

Ensure your `db-connect.php` file contains valid database credentials. This file is used to create a PDO connection instance passed into QLYX.

**Example structure of `db-connect.php`:**

```php
<?php
// Configuration for both environments
$DB_DATABASE_PROD = ''; // Remote/Production DB name
$DB_USERNAME_PROD = ''; // Remote/Production DB username
$DB_PASSWORD_PROD = ''; // Remote/Production DB password
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
?>
```

## Project Structure

```
├── QLYX/
│   └── qlyx.php           # Main tracking logic
|   └── index.php          # Analytics dashboard
├── db-connect.php         # Database connection setup
├── index.php              # Landing page (tracks visits)            
```

## License

This project is licensed under the MIT License. See `LICENSE.md` for details.

## Author

Developed by QuantumByteStudios. Contributions and suggestions are welcome.  
For inquiries, email us at [contact@quantumbytestudios.in](mailto:contact@quantumbytestudios.in).
