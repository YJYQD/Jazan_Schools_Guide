<?php
// post_comment.php has been deprecated. Use add_review.php which enforces CSRF and prepared statements.
http_response_code(410);
echo "This endpoint is removed. Use add_review.php instead.";
exit();
?>
