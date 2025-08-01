<?php
function handleProfileImageUpload($userId, $conn) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image']) && isset($userId)) {
        
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            $_SESSION['error'] = "CSRF token noto'g'ri yoki mavjud emas.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
        
        $file = $_FILES['profile_image'];
        
        // File upload xatolarini tekshirish
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'Fayl hajmi juda katta.',
                UPLOAD_ERR_FORM_SIZE => 'Fayl hajmi form limitidan oshib ketdi.',
                UPLOAD_ERR_PARTIAL => 'Fayl to\'liq yuklanmadi.',
                UPLOAD_ERR_NO_FILE => 'Hech qanday fayl tanlanmadi.',
                UPLOAD_ERR_NO_TMP_DIR => 'Vaqtinchalik papka topilmadi.',
                UPLOAD_ERR_CANT_WRITE => 'Faylni diskga yozib bo\'lmadi.',
                UPLOAD_ERR_EXTENSION => 'Fayl yuklash extension tomonidan to\'xtatildi.'
            ];
            $_SESSION['error'] = $errorMessages[$file['error']] ?? 'Noma\'lum xatolik yuz berdi.';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
        
        // Fayl mavjudligini tekshirish
        if (!is_uploaded_file($file['tmp_name'])) {
            $_SESSION['error'] = "Fayl to'g'ri yuklanmadi.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
        
        // Fayl hajmini tekshirish (3MB)
        $maxSize = 3 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            $_SESSION['error'] = "Rasm hajmi 3 MB dan oshmasligi kerak.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg'];
        if (in_array($_SESSION['user']['role'], ['admin', 'creator'])) {
            $allowedExtensions[] = 'gif';
        }
        if (!in_array($extension, $allowedExtensions)) {
            $_SESSION['error'] = "Faqat .jpg formatidagi rasm yuklashingiz mumkin err." . $user['role'];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        // MIME type tekshirish (mobile uchun yaxshilangan)
        $allowedMimes = ['image/jpeg', 'image/jpg'];
        if (in_array($_SESSION['user']['role'], ['admin', 'creator'])) {
            $allowedMimes[] = 'image/gif';
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        if (!in_array($mimeType, $allowedMimes)) {
            $_SESSION['error'] = "Faqat JPG formatidagi rasm yuklashingiz mumkin.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
        
        // Rasm ekanligini tekshirish
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            $_SESSION['error'] = "Yuklangan fayl rasm emas.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        $uploadDir = __DIR__ . "/../profile_image/";
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                $_SESSION['error'] = "Upload papkasini yaratib bo'lmadi.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        }

        $fileName = $userId . "." . $extension;
        $filePath = $uploadDir . $fileName;
        
        // Eski faylni o'chirish
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // Fayl ruxsatlarini o'rnatish
            chmod($filePath, 0644);
            
            $relativePath = "/profile_image/" . $fileName;
            $stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
            $stmt->bind_param("si", $relativePath, $userId);
            
            if (!$stmt->execute()) {
                $_SESSION['error'] = "Ma'lumotlar bazasini yangilab bo'lmadi.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
            $stmt->close();

            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $_SESSION['error'] = "Faylni saqlashda xatolik. Qayta urinib ko'ring.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}
?>
