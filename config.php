<?php
$conn = new mysqli('localhost', 'root', '', 'UmmisTracking');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>