<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        die("Unauthorized");
    }

    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        http_response_code(400);
        die("CSRF detected");
    }

    $id = intval($_POST['id']);

    $stmt = $conn->prepare("DELETE FROM School_Reviews WHERE Review_ID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

header("Location: index.php");
exit();
?>