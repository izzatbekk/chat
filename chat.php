<?php

require 'includes/config.php';
require 'includes/auth.php';
require 'includes/upload_handler.php';

// Profile image upload handling
handleProfileImageUpload($userId, $conn);
?>

<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
  <title>Chat v1.3</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="assets/css/chat.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container" style="padding-top: 4px; padding-bottom: 4px;">
 <div class="d-flex justify-content-between align-items-center mb-2" style="padding: 4px 8px;">
  <div id="profile-name" class="d-flex align-items-center" style="gap: 10px;">
    <!-- JavaScript bilan to'ldiriladi -->
  </div>
  
<!-- Online ko‘rsatkich -->
<div class="online-status" onclick="openOnlineUsersModal()">
  Online: <span id="online-count">0</span>
</div>
<!-- Modal oynasi -->
<div id="online-users-overlay" class="overlay" onclick="closeOnlineUsersModal()"></div>
<div id="online-users-modal" class="modal">
  <div class="modal-header">
    <strong>Online foydalanuvchilar:</strong>
    <button onclick="closeOnlineUsersModal()">✕</button>
  </div>
  <div id="online-users-list" class="user-list"></div>
</div>

  <a href="logout.php" class="btn btn-outline-danger btn-sm">Chiqish</a>
</div>

    <input type="hidden" id="reply-to" value="0">
    <input type="hidden" id="user-id" value="<?= $user['id'] ?>">
    
    <div id="connecting-indicator">
        Ulanmoqda..
    </div>

    <div id="chat-box" class="mb-3 border rounded"></div>
    <button id="scrollToBottomBtn" title="Eng oxiriga" onclick="scrollToBottom()"><i class="bi bi-arrow-down-circle-fill"></i></button>
    <div id="reply-preview" class="rounded"></div>
    <form id="chat-form"> 
        <textarea id="message" class="form-control" placeholder="Xabar yozing..." rows="1" required></textarea>
        <button class="btn btn-primary" type="submit"><span>Yuborish</span></button> 
    </form>
</div>
  
<!-- Modal: markazda joylashgan -->
<div id="profile-popup">
  <h5 id="profile-info" class="d-flex align-items-center mb-3"></h5>
  <p class="mb-1"><strong>Username:</strong> <?= htmlspecialchars($user['username']) ?></p>
  <p class="mb-1"><strong>Status:</strong> <?= htmlspecialchars($user['role']) ?></p>
  <p class="mb-1"><strong>id:</strong> <?= $user['id'] ?></p>
  <div class="text-end mt-3">
    <button onclick="closeProfile()" class="btn btn-sm btn-outline-secondary">Yopish</button>
  </div>
</div>

<!-- Orqa fonni to'siq (overlay) -->
<div id="modal-overlay"></div>

<?php if (!empty($_SESSION['error'])): ?>
  <div id="errOverlay">
    <div id="errBox">
      <button id="errClose">&times;</button>
      <strong>Error:</strong>
      <div style="margin-top:10px;"><?= htmlspecialchars($_SESSION['error']) ?></div>
    </div>
  </div>
  <script>
    window.addEventListener('load', () => {
      const overlay = document.getElementById('errOverlay');
      const box = document.getElementById('errBox');
      const close = document.getElementById('errClose');

      if (overlay && box && close) {
        overlay.style.display = 'flex';
        close.onclick = () => overlay.style.display = 'none';
        overlay.onclick = e => { if (!box.contains(e.target)) overlay.style.display = 'none'; };
      }
    });
  </script>
<?php unset($_SESSION['error']); endif; ?>

<!-- User Profile Overlay -->
<div id="user-profile-overlay" onclick="closeUserProfilePopup()"></div>
<div id="user-profile-popup">
  <div id="user-profile-content"></div>
  <div style="position: absolute; bottom: 15px; right: 20px;">
    <button class="btn btn-sm btn-outline-secondary" onclick="closeUserProfilePopup()">Yopish</button>
  </div>
</div>

<script>

// PHP variables for JavaScript
const userId = <?= $user['id'] ?>;
const username = "<?= $user['username'] ?>";
const role = "<?= $user['role'] ?>";
const token = "<?= $wsToken ?>";
const csrfToken = "<?= $_SESSION['csrf_token'] ?? '' ?>";
</script>

<script src="assets/js/chat.js"></script>

</body>
</html>