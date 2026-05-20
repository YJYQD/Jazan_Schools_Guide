<?php
// Secure add_review handler
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

// CSRF check
$csrf = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf)) {
    header('Location: index.php?error=csrf');
    exit();
}

$school_id = intval($_POST['school_id'] ?? 0);
$comment_text = trim($_POST['comment_text'] ?? '');
$visitor = isset($_SESSION['username']) ? $_SESSION['username'] : trim($_POST['visitor_name'] ?? 'زائر');

// Basic validation & spam protections
if ($school_id <= 0 || $comment_text === '') {
    header('Location: index.php?error=invalid');
    exit();
}
$maxCommentLen = 1000;
$comment_text = mb_substr($comment_text, 0, $maxCommentLen);

// Simple anti-spam: limit one comment per 30s per user per school
if (isset($_SESSION['username'])) {
    $recentChk = $conn->prepare('SELECT Review_ID FROM School_Reviews WHERE School_ID = ? AND Visitor_Name = ? AND Review_Date > DATE_SUB(NOW(), INTERVAL 30 SECOND) LIMIT 1');
    $recentChk->bind_param('is', $school_id, $_SESSION['username']);
    $recentChk->execute();
    $recentRes = $recentChk->get_result();
    if ($recentRes && $recentRes->num_rows > 0) {
        header('Location: index.php?error=spam'); exit();
    }
    $recentChk->close();
}

// Verify school exists
$chk = $conn->prepare('SELECT School_ID FROM Schools WHERE School_ID = ?');
$chk->bind_param('i', $school_id);
$chk->execute();
$res = $chk->get_result();
if (!$res || $res->num_rows === 0) {
    header('Location: index.php?error=no_school');
    exit();
}
$chk->close();

$stmt = $conn->prepare('INSERT INTO School_Reviews (School_ID, Visitor_Name, Comment, Review_Date) VALUES (?, ?, ?, NOW())');
$stmt->bind_param('iss', $school_id, $visitor, $comment_text);
if ($stmt->execute()) {
    header('Location: index.php?status=comment_added');
} else {
    header('Location: index.php?error=save_failed');
}
$stmt->close();
exit();
?>