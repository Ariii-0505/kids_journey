<?php
$conn = new mysqli("localhost", "root", "", "");
if ($conn->connect_error) {
    die("Connection failed");
}
echo "MySQL connected!";
