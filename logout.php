<?php
require 'db.php';

if (isset($_COOKIE['token'])) {
    $token = $_COOKIE['token'];

    // Bazadagi user_tokens jadvalidan o‘chiramiz
    $stmt = $conn->prepare("DELETE FROM user_tokens WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();

    // Cookie ni muddati tugatamiz
    setcookie("token", "", time() - 3600, "/", "", true, true);
}

session_destroy();

$msg = '';
if (isset($_GET['blocked'])) {
  $msg = "⛔ Siz admin tomonidan bloklandingiz.";
}

header("Refresh: 3; url=index.php");
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Chiqish</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex justify-content-center align-items-center vh-100 bg-light">
  <div class="text-center">
    <h4><?= $msg ?: "Chiqish muvaffaqiyatli amalga oshirildi." ?></h4>
    <p class="text-muted">3 soniya ichida login sahifasiga yo‘naltirilasiz...</p>
  </div>
</body>
</html>
