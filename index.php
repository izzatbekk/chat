<?php
require 'db.php';

if (isset($_SESSION['user'])) {
  header("Location: chat.php");
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username']);
  $password = $_POST['password'];

  $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $user = $stmt->get_result()->fetch_assoc();

  if ($user && password_verify($password, $user['password'])) {
    if ($user['role'] === 'block') {
        $error = "Siz admin tomonidan bloklangansiz. Bog'lanish uchun telegram manzil: <a href='https://t.me/root00uz'>Admin</a>";
    } else {
    
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 1 kun
    
    $stmt = $conn->prepare("INSERT INTO user_tokens (user_id, token, expires_at, user_agent, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user['id'], $token, $expiresAt, $_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR']);
    $stmt->execute();
    
    setcookie("token", $token, time() + 86400, "/", "", true, true); // Secure va HttpOnly
    
    $user['token'] = $token;
    $_SESSION['user'] = $user;
    header("Location: chat.php");
    exit;
    }
  } else {
    $error = "Login yoki parol noto‘g‘ri kiritildi.";
  }
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
  <meta charset="UTF-8">
  <title>Kirish - xasanov.uz</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">

  <!-- Bootstrap & Icons -->
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

    .auth-box h3 {
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

    a {
      color: #229ed9;
      text-decoration: none;
    }

    a:hover {
      text-decoration: underline;
    }

    .position-relative .eye-toggle {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #6c757d;
    }
  </style>
</head>
<body>

  <div class="auth-box">
    <h3 class="mb-4">Kirish</h3>

    <?php if($error): ?>
      <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="mb-3">
        <input class="form-control" name="username" placeholder="Login" required autocomplete="username">
      </div>

      <div class="mb-3 position-relative">
        <input class="form-control" name="password" type="password" placeholder="Parol" required autocomplete="current-password">
        <i class="bi bi-eye-fill eye-toggle" onclick="togglePassword(this)"></i>
      </div>

      <button class="btn btn-primary w-100 py-2">Kirish</button>
    </form>

    <p class="text-center mt-3 mb-0">
      Hisobingiz yo‘qmi? <a href="register.php">Ro‘yxatdan o‘tish</a>
    </p>
  </div>

  <script>
    function togglePassword(icon) {
      const input = icon.previousElementSibling;
      const isPassword = input.type === "password";
      input.type = isPassword ? "text" : "password";
      icon.classList.toggle("bi-eye-fill");
      icon.classList.toggle("bi-eye-slash-fill");
    }
  </script>
</body>
</html>