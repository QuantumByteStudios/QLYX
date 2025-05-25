<!DOCTYPE html>
<html lang="en">

<?php
require_once 'QLYX/qlyx.php';
require_once 'db-connect.php';

$qlyx = new QLYX($pdo);
$qlyx->track();
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QLYX - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/styles/atom-one-dark.min.css"
        rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/languages/php.min.js"></script>
    <script>
        hljs.highlightAll();
    </script>
</head>

<body>
    <div class="container py-5">
        <h1 class="mb-4">
            <a class="text-decoration-none" href="https://github.com/QuantumByteStudios/QLYX">QLYX Analytics</a>
        </h1>

        <p>
            Welcome to the QLYX home page. <strong>This page automatically tracks each visit</strong> and logs the data
            for statistical analysis.
            You can view detailed analytics and trends by visiting the <a class="text-decoration-none"
                href="QLYX/">dashboard</a>.
        </p>

        <p>
            To enable visit tracking on any page, simply include the <code>QLYX/qlyx.php</code> file and invoke the
            <code>$qlyx->track()</code> method after establishing a database connection.
        </p>

        <h6 class="mt-4">How to Integrate Visit Tracking</h6>
        <pre><code class="language-php"><?php echo htmlspecialchars('<?php
require_once "QLYX/qlyx.php";
require_once "db-connect.php";

$qlyx = new QLYX($pdo);
$qlyx->track();
?>'); ?></code></pre>

        <p>
            This code snippet initializes the QLYX tracking system. Make sure to replace the database connection
            parameters with your own.
        </p>

        <h6 class="mt-4">Database Connection</h6>
        <p>
            Please check the <code>db-connect.php</code> file for the database connection. Ensure you have the correct
            database credentials to connect successfully.
        </p>
    </div>
</body>

<footer class="footer mt-auto pb-5">
    <div class="container bg-white text-muted text-center p-3">
        <small class="m-0 text-uppercase">
            &copy; <?php echo date('Y'); ?> Quantum Byte Studios. All rights reserved.
        </small>
    </div>
</footer>


</html>