<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


date_default_timezone_set('Asia/Tashkent');

// Session sozlamalari
$session_lifetime = 86400; // 1 kun (24 soat)
session_set_cookie_params([
    'lifetime' => $session_lifetime,
    'path' => '/',
    'domain' => '',            // Avtomatik domen
    'secure' => true,          // HTTPS talab qilinadi
    'httponly' => true,        // JavaScript orqali o‘qib bo‘lmaydi
    'samesite' => 'Lax'        // CSRF himoyasi uchun
]);

session_start();

// Har safar cookie muddatini uzaytirish
if (isset($_COOKIE[session_name()])) {
    setcookie(
        session_name(),
        $_COOKIE[session_name()],
        time() + $session_lifetime,
        '/',
        '',      // domain ('' bo‘lsa hozirgi domen uchun)
        true,    // secure: faqat HTTPS
        true     // httponly
    );
}

// MySQL ulanish
$conn = new mysqli("localhost", "root", "@izzatbek00", "izzatbek");

// Ulanishda xatolikni tekshirish
if ($conn->connect_error) {
    die("Ulanishda xatolik: " . $conn->connect_error);
}

// Timezone MySQL uchun
$conn->query("SET time_zone = '+05:00'");

// MySQL charset'ni UTF-8 ga sozlash (uzbek tilidagi belgilar uchun muhim!)
$conn->set_charset("utf8mb4");


// =====================
// TOKEN ORQALI SESSIYANI TIKLASH
// =====================
if (!isset($_SESSION['user']) && isset($_COOKIE['token'])) {
    $token = $_COOKIE['token'];

    $stmt = $conn->prepare("SELECT users.*, user_tokens.id as token_id, user_tokens.expires_at FROM user_tokens
                            JOIN users ON users.id = user_tokens.user_id
                            WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        if (strtotime($user['expires_at']) > time()) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'], // Agar bo‘lsa
                'token_id' => $user['token_id']
            ];

            // Token muddati yangilanadi
            $newExpiry = date('Y-m-d H:i:s', time() + $session_lifetime);
            $stmt = $conn->prepare("UPDATE user_tokens SET expires_at = ? WHERE id = ?");
            $stmt->bind_param("si", $newExpiry, $user['token_id']);
            $stmt->execute();

            // Cookie muddati yangilanadi
            setcookie("token", $token, time() + $session_lifetime, "/", "", true, true);
        } else {
            // Eskirgan tokenni o‘chiramiz
            $stmt = $conn->prepare("DELETE FROM user_tokens WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            setcookie("token", "", time() - 3600, "/");
            header("Location: login.php");
            exit;
        }
    } else {
        header("Location: login.php");
        exit;
    }
}

?>
