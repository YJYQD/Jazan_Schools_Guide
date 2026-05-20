<?php
session_start();
session_unset();
session_destroy(); // تدمير الجلسة لأسباب أمنية (Exercise 11)
header("Location: index.php");
exit();
?>