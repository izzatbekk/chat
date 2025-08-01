<?php
require 'vendor/autoload.php';
require 'db.php';

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Websocket;

Worker::$stdoutFile = '/var/log/ws_output.log';
Worker::$logFile = '/var/log/ws_error.log';

$context = [
    'ssl' => [
        'local_cert'  => '/etc/letsencrypt/live/xasanov.uz/fullchain.pem',
        'local_pk'    => '/etc/letsencrypt/live/xasanov.uz/privkey.pem',
        'verify_peer' => false
    ]
];

$ws = new Worker("websocket://0.0.0.0:2346", $context);
$ws->transport = 'ssl';
$ws->count = 1;
$clients = [];
$messageLog = [];

function safePrepare($conn, $query, $connection) {
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        $connection->close();
        return false;
    }
    return $stmt;
}

// 🆕 FUNKSIYA: Foydalanuvchining so'nggi faolligini yangilaydi
function updateLastActive($conn, $uid) {
    global $conn;
    $stmt = $conn->prepare("UPDATE users SET last_active = NOW() WHERE id = ?"); // SQL injectiondan xavfsiz
    if ($stmt) {
        $stmt->bind_param("i", $uid); // integer bog'lash
        $stmt->execute(); // bazani yangilash
    } else {
        error_log("Last active update failed: " . $conn->error); // xatoni logga yozish
    }
}


$ws->onConnect = function(TcpConnection $connection) use (&$clients, $conn) {
    $connection->onWebSocketConnect = function(TcpConnection $connection) use (&$clients, $conn) {
        try {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            parse_str(parse_url($uri, PHP_URL_QUERY), $query);
            $token = $query['token'] ?? null;

            if (!$token) {
                $connection->close();
                return;
            }

            $stmt = safePrepare($conn, "SELECT users.id, users.username, users.role, users.profile_image FROM user_tokens JOIN users ON users.id = user_tokens.user_id WHERE user_tokens.token = ? AND user_tokens.expires_at > NOW()", $connection);
            if (!$stmt) return;
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if (!$user || $user['role'] === 'block') {
                $connection->send(json_encode(['type' => 'blocked', 'reason' => !$user ? 'unauthorized' : 'admin']));
                $connection->close();
                return;
            }

            $connection->user = $user;
            $clients[$connection->id] = $connection;
            updateLastActive($conn, $user['id']);         // 🆕 yangi kod: faollikni saqlash
            broadcastOnlineUsers($clients);             // 🆕 yangi kod: online userlarni yuborish

            $res = $conn->query("SELECT m.id, m.message, m.user_id, u.username, u.role, u.profile_image, m.created_at, m.reply_to, (SELECT CONCAT(u2.username, ': ', m2.message, ':', u2.role) FROM messages m2 JOIN users u2 ON m2.user_id = u2.id WHERE m2.id = m.reply_to) AS reply_text FROM (SELECT * FROM messages ORDER BY id DESC LIMIT 1000) AS m JOIN users u ON m.user_id = u.id ORDER BY m.id ASC");

            $messages = [];
            while ($row = $res->fetch_assoc()) {
                $row['username'] = htmlspecialchars($row['username'], ENT_QUOTES);
                $row['role'] = htmlspecialchars($row['role'], ENT_QUOTES);
                $row['reply_text'] = $row['reply_text'] ?? '';
                $messages[] = $row;
            }

            $connection->send(json_encode(['type' => 'init', 'user' => $user, 'messages' => $messages]));
        } catch (Exception $e) {
            error_log("onConnect error: " . $e->getMessage());
            $connection->close();
        }
    };
};

$ws->onMessage = function(TcpConnection $connection, $data) use (&$clients, $conn, &$messageLog) {
    try {
        $payload = json_decode($data, true);
        $user = $connection->user;
        $connection->last_active = time();         // 🆕 yangi kod: har harakatda faollik yangilanishi
        broadcastOnlineUsers($clients);    // 🆕 yangi kod: har harakatda faollik yangilanishi

        if (!isset($payload['type'])) return;

        if ($payload['type'] === 'send') {
            $msg = $payload['message'];
            $uid = $user['id'];
            $reply = isset($payload['reply_to']) && is_numeric($payload['reply_to']) ? (int)$payload['reply_to'] : null;
            $created_at = date("Y-m-d H:i:s");

            $messageLog[$uid] = array_filter($messageLog[$uid] ?? [], fn($t) => time() - $t <= 10);
            $messageLog[$uid][] = time();

            if (count($messageLog[$uid]) >= 10) {
                $conn->query("UPDATE users SET role = 'block' WHERE id = $uid");
                $res = $conn->query("SELECT id FROM messages WHERE user_id = $uid");
                $msgIds = array_column($res->fetch_all(MYSQLI_ASSOC), 'id');

                foreach ($msgIds as $msgId) {
                    foreach ($clients as $client) {
                        $client->send(json_encode(['type' => 'delete', 'id' => $msgId]));
                    }
                }

                foreach ($clients as $client) {
                    if ($client->user['id'] == $uid) {
                        $client->send(json_encode(['type' => 'blocked', 'reason' => 'flood']));
                        $client->close();
                    }
                }
                return;
            }

            $stmt = $reply !== null ? $conn->prepare("INSERT INTO messages (user_id, message, created_at, reply_to) VALUES (?, ?, ?, ?)") : $conn->prepare("INSERT INTO messages (user_id, message, created_at) VALUES (?, ?, ?)");
            if (!$stmt) throw new Exception("Insert prepare failed: " . $conn->error);
            $reply !== null ? $stmt->bind_param("issi", $uid, $msg, $created_at, $reply) : $stmt->bind_param("iss", $uid, $msg, $created_at);
            $stmt->execute();
            $id = $stmt->insert_id;

            $reply_text = '';
            if ($reply !== null) {
                $r = $conn->query("SELECT u.username, m.message, u.role FROM messages m JOIN users u ON m.user_id = u.id WHERE m.id = $reply")->fetch_assoc();
                if ($r) {
                    $reply_text = htmlspecialchars("{$r['username']}: {$r['message']}:{$r['role']}", ENT_QUOTES);
                }
            }

            $message = [
                'id' => $id,
                'message' => $msg,
                'user_id' => $uid,
                'username' => htmlspecialchars($user['username'], ENT_QUOTES),
                'role' => htmlspecialchars($user['role'], ENT_QUOTES),
                'created_at' => $created_at,
                'reply_text' => $reply_text
            ];

            foreach ($clients as $client) {
                $client->send(json_encode(['type' => 'new', 'message' => $message]));
            }
            updateLastActive($conn, $user['id']);
        }
        elseif ($payload['type'] === 'delete') {
            $id = (int)$payload['id'];
            $res = $conn->query("SELECT user_id FROM messages WHERE id = $id");
            if ($row = $res->fetch_assoc()) {
                $owner_id = $row['user_id'];
                $isAdmin = in_array($user['role'], ['creator', 'admin']);
                if ($owner_id == $user['id'] || $isAdmin) {
                    $conn->query("DELETE FROM messages WHERE id = $id");
                    foreach ($clients as $client) {
                        $client->send(json_encode(['type' => 'delete', 'id' => $id]));
                    }
                }
            }
        }
        elseif ($payload['type'] === 'block') {
            $userIdToBlock = (int)$payload['user_id'];
            $conn->query("UPDATE users SET role = 'block' WHERE id = $userIdToBlock");
            $res = $conn->query("SELECT id FROM messages WHERE user_id = $userIdToBlock");
            $msgIds = array_column($res->fetch_all(MYSQLI_ASSOC), 'id');
            foreach ($msgIds as $msgId) {
                foreach ($clients as $client) {
                    $client->send(json_encode(['type' => 'delete', 'id' => $msgId]));
                }
            }
            foreach ($clients as $client) {
                if ($client->user['id'] == $userIdToBlock) {
                    $client->send(json_encode(['type' => 'blocked', 'reason' => 'admin']));
                    $client->close();
                }
            }
        }
    } catch (Exception $e) {
        error_log("onMessage error: " . $e->getMessage());
    }
};

$ws->onClose = function(TcpConnection $connection) use (&$clients) {
    unset($clients[$connection->id]);
    broadcastOnlineUsers($clients);
    if (isset($connection->user)) {
        updateLastActive($conn, $connection->user['id']); // izoh
    }
};

function broadcastOnlineUsers(&$clients) {
    global $conn; // //izoh: mysqli obyektini global olish

    $onlineUsers = [];

    foreach ($clients as $client) {
        if (isset($client->user)) {
            $userId = $client->user['id'];

            // //izoh: SQL so‘rovi tayyorlaymiz
            $stmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId); // //izoh: "i" — bu integer (id)
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            // //izoh: Agar profil rasmi bor bo‘lsa, o‘shani olamiz, yo‘q bo‘lsa default
            $profileImage = (!empty($row['profile_image']))
                ? $row['profile_image']
                : '/profile_image/default.jpg';

            $onlineUsers[] = [
                'id' => $userId,
                'username' => $client->user['username'],
                'role' => $client->user['role'],
                'profile_image' => $profileImage // //izoh: Frontendga yuboriladi
            ];
        }
    }

    // //izoh: JSON holatida yuboramiz
    $data = [
        'type' => 'online_users',
        'count' => count($onlineUsers),
        'users' => $onlineUsers
    ];

    foreach ($clients as $client) {
        $client->send(json_encode($data));
    }
}


Worker::runAll();
