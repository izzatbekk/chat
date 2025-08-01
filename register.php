<?php

require 'db.php';


if (isset($_SESSION['user'])) {
  header("Location: chat.php");
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username']);
  $email = trim($_POST['email']);
  $password = $_POST['password'];
  $ip = $_SERVER['REMOTE_ADDR'];

  // 1. IP limit tekshiruvi
  $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE ip_address = ? AND created_at >= NOW() - INTERVAL 1 DAY");
  $stmt->bind_param("s", $ip);
  $stmt->execute();
  $stmt->bind_result($ip_count);
  $stmt->fetch();
  $stmt->close();

  if ($ip_count >= 3) {
    $error = "Bu IP manzildan 24 soatda faqat 3 ta hisob ochish mumkin.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Email noto‘g‘ri kiritildi.";
  } elseif (!preg_match('/^[a-zA-Z][a-zA-Z0-9]{4,31}$/', $username)) {
    $error = "Login kamida 5 ta belgidan iborat bo‘lishi kerak.";
  } elseif (strlen($password) < 6) {
    $error = "Parol kamida 6 ta belgidan iborat bo‘lishi kerak.";
  } else {
    // Login mavjudmi?
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
      $error = "Bu login saytda mavjud, iltimos boshqa login kiriting.";
    } else {
      $stmt->close();

      // Email mavjudmi?
      $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
      $stmt->bind_param("s", $email);
      $stmt->execute();
      $stmt->store_result();

      if ($stmt->num_rows > 0) {
        $error = "Bu email saytda ro‘yxatdan o‘tgan, iltimos boshqa email kiriting.";
      } else {
        // Email noyob, davom etamiz
        $verify_code = rand(10000, 99999);
        $_SESSION['verify'] = [
          'username' => $username,
          'password' => password_hash($password, PASSWORD_DEFAULT),
          'email' => $email,
          'token' => bin2hex(random_bytes(32)),
          'ip' => $ip,
          'code' => $verify_code,
          'time' => time()
        ];

        // Kodni yuboramiz
        $to = $email;
        $subject = "WebSocket Chat - Tasdiqlash kodi";
        $message = "Sizning tasdiqlash kodingiz: $verify_code";
        $headers = "From: no-reply@xasanov.uz";

        mail($to, $subject, $message, $headers);

        header("Location: verify.php");
        exit;
      }
    }
    $stmt->close();
  }
}
?>

<!DOCTYPE html>
<html lang="uz">
<head>
  <meta charset="UTF-8">
  <title>Ro‘yxatdan o‘tish - xasanov.uz</title>
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

    .eye-toggle {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #9ca3af;
    }

    .eye-toggle:hover {
      color: #229ed9;
    }

    .valid-feedback-custom { color: #16a34a; font-size: 0.875rem; }
    .invalid-feedback-custom { color: #dc2626; font-size: 0.875rem; }

    a {
      color: #229ed9;
      text-decoration: none;
    }

    a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

  <div class="auth-box">
    <h3 class="text-center mb-4">Ro‘yxatdan o‘tish</h3>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off" novalidate>
      <div class="mb-3">
        <input name="username" class="form-control" placeholder="Login" required>
      </div>

      <div class="mb-3">
        <input name="email" type="email" class="form-control" placeholder="Email" required>
      </div>

      <div class="position-relative mb-3">
        <input name="password" type="password" id="password" class="form-control" placeholder="Parol" required>
        <i class="bi bi-eye-fill eye-toggle" onclick="togglePassword()"></i>
      </div>

      <div id="pass-feedback" class="mb-3 small"></div>

      <button class="btn btn-primary w-100 py-2">Tasdiqlash kodini yuborish</button>

      <p class="text-center mt-3 mb-0">
        Hisobingiz bormi? <a href="index.php">Kirish</a>
      </p>
    </form>
  </div>

  <script>
    const passInput = document.getElementById('password');
    const passFeedback = document.getElementById('pass-feedback');

    passInput.addEventListener('input', () => {
      const val = passInput.value;
      if (val.length >= 6) {
        passFeedback.textContent = "Parol to‘g‘ri kiritildi.";
        passFeedback.className = "valid-feedback-custom";
      } else {
        passFeedback.textContent = "Parol kamida 6 ta belgidan iborat bo‘lishi kerak.";
        passFeedback.className = "invalid-feedback-custom";
      }
    });

    function togglePassword() {
      const type = passInput.getAttribute("type") === "password" ? "text" : "password";
      passInput.setAttribute("type", type);
      const icon = document.querySelector(".eye-toggle");
      icon.classList.toggle("bi-eye-fill");
      icon.classList.toggle("bi-eye-slash-fill");
    }
  </script>
</body>
</html>