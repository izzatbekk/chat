<?php

require 'db.php';

// Foydalanuvchi sessiyasi tekshiruv
if (!isset($_SESSION['verify'])) {
  header("Location: register.php");
  exit;
}

$verify = $_SESSION['verify'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // 1. Qayta yuborish
  if (isset($_POST['resend'])) {
    $lastSent = $_SESSION['verify']['time'];
    $now = time();

    if ($now - $lastSent < 60) {
      $left = 60 - ($now - $lastSent);
      $error = "Kod qayta yuborilishi uchun $left soniya kuting.";
    } else {
      $new_code = rand(10000, 99999);

      // Eski kodni yangisi bilan almashtiramiz
      $_SESSION['verify']['code'] = $new_code;
      $_SESSION['verify']['time'] = $now;

      // Yangi email jo‘natamiz
      $to = $verify['email'];
      $subject = "WebSocket Chat - Yangi tasdiqlash kodi";
      $message = "Sizning yangi tasdiqlash kodingiz: $new_code";
      $headers = "From: no-reply@xasanov.uz";

      mail($to, $subject, $message, $headers);
      $success = "Yangi kod yuborildi. Iltimos, pochtangizni tekshiring.";
    }
  }

  // 2. Kodni tekshirish
  elseif (isset($_POST['code'])) {
    $enteredCode = trim($_POST['code']);
    $actualCode = $_SESSION['verify']['code'];

    if ($enteredCode == $actualCode) {
        
      // Foydalanuvchini bazaga yozamiz
     $stmt = $conn->prepare("INSERT INTO users (username, email, password, token, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssss", $verify['username'], $verify['email'], $verify['password'], $verify['token'], $verify['ip']);
    $stmt->execute();
    
    $user_id = $stmt->insert_id;
    $stmt->close();

      // Sessiyaga login qilamiz
      $_SESSION['user'] = [
        'id' => $user_id,
        'username' => $verify['username'],
        'token' => $verify['token'],
        'role' => 'user'
      ];

    $token = $verify['token'];
    $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 1 kun
    
    $stmt = $conn->prepare("INSERT INTO user_tokens (user_id, token, expires_at, user_agent, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $token, $expiresAt, $_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR']);
    $stmt->execute();
    
    setcookie("token", $token, time() + 86400, "/", "", true, true); // Secure va HttpOnly

      // Tasdiqlash sessiyasini o‘chirib tashlaymiz
      unset($_SESSION['verify']);

      header("Location: chat.php");
      exit;
    } else {
      $error = "Tasdiqlash kodi noto‘g‘ri kiritildi.";
    }
  }

  // 3. Bekor qilish
  elseif (isset($_POST['cancel'])) {
    unset($_SESSION['verify']);
    header("Location: register.php");
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
  <meta charset="UTF-8">
  <title>Email tasdiqlash - xasanov.uz</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Google Fonts & Bootstrap Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    * {
      font-family: 'Inter', sans-serif;
    }

    body {
      margin: 0;
      min-height: 100vh;
      background-color: #f5f6fa;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .auth-box {
      background: #ffffff;
      border: 1px solid #e0e0e0;
      border-radius: 12px;
      padding: 40px 30px;
      width: 100%;
      max-width: 400px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
    }

    .auth-box h4 {
      font-weight: 600;
      color: #202124;
      text-align: center;
    }

    .form-control {
      background-color: #fff;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      transition: 0.2s ease-in-out;
    }

    .form-control:focus {
      border-color: #229ed9;
      box-shadow: 0 0 0 0.15rem rgba(34, 158, 217, 0.25);
    }

    .btn-primary {
      background-color: #229ed9;
      border: none;
      border-radius: 8px;
      font-weight: 500;
    }

    .btn-primary:hover {
      background-color: #1d90c5;
    }

    .btn-outline-secondary,
    .btn-outline-danger {
      border-radius: 8px;
    }

    .small-muted {
      color: #6c757d;
      font-size: 0.9rem;
      margin-bottom: 1rem;
      text-align: center;
    }

    .dual-buttons {
      display: flex;
      gap: 10px;
    }
  </style>
</head>
<body>

  <div class="auth-box">
    <h4 class="mb-3">Email tasdiqlash</h4>
    <p class="small-muted">
      Tasdiqlash kodi <strong><?= htmlspecialchars($verify['email']) ?></strong> manziliga yuborilgan. Kodni olmagan bo‘lsangiz Spam jildini tekshiring.
    </p>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="text" class="form-control mb-3" name="code" placeholder="Tasdiqlash kodi" required>
      <button type="submit" class="btn btn-primary w-100 mb-2">Tasdiqlash</button>
    </form>

    <form method="POST" class="dual-buttons">
      <button type="submit" name="resend" class="btn btn-outline-secondary w-50">Qayta yuborish</button>
      <button type="submit" name="cancel" class="btn btn-outline-danger w-50">Bekor qilish</button>
    </form>
  </div>

</body>
</html>