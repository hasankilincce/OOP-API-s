<?php
// Hata raporlamayı etkinleştir
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// JSON yanıtlar için başlık ayarla
header('Content-Type: application/json');

// config.php dosyasını dahil et
require __DIR__ . '/../config.php';

// Veritabanı bağlantısını oluştur
$conn = new mysqli($servername, $username, $password, $dbname);

// Bağlantıyı kontrol et
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Bağlantı başarısız: " . $conn->connect_error
    ]);
    exit;
}

// JSON verisini alma ve çözme
$data = json_decode(file_get_contents('php://input'), true);

// `loginUser` kontrolü
$loginUser = $data['loginUser'] ?? null;
if (!$loginUser) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Geçerli bir kullanıcı adı sağlamalısınız."
    ]);
    exit;
}

// Kullanıcı ID'sini bul
$sqlUser = "SELECT id FROM users WHERE username = ?";
$stmtUser = $conn->prepare($sqlUser);

if ($stmtUser) {
    $stmtUser->bind_param("s", $loginUser);
    $stmtUser->execute();
    $resultUser = $stmtUser->get_result();

    if ($resultUser->num_rows > 0) {
        $user = $resultUser->fetch_assoc();
        $current_user_id = $user['id'];
    } else {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "Kullanıcı bulunamadı."
        ]);
        exit;
    }
    $stmtUser->close();
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Kullanıcı sorgusu sırasında hata oluştu: " . $conn->error
    ]);
    exit;
}

// Takip edilen kullanıcıları bul
$sqlFollows = "SELECT followed_user_id FROM follows WHERE following_user_id = ?";
$stmtFollows = $conn->prepare($sqlFollows);

if ($stmtFollows) {
    $stmtFollows->bind_param("i", $current_user_id);
    $stmtFollows->execute();
    $resultFollows = $stmtFollows->get_result();

    $followed_ids = [];
    while ($row = $resultFollows->fetch_assoc()) {
        $followed_ids[] = $row['followed_user_id'];
    }
    $stmtFollows->close();
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Takip edilen kullanıcı sorgusu sırasında hata oluştu: " . $conn->error
    ]);
    exit;
}

if (empty($followed_ids)) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Hiçbir kullanıcıyı takip etmiyorsunuz."
    ]);
    exit;
}

// Takip edilen kullanıcıların gönderilerini al
$placeholders = implode(',', array_fill(0, count($followed_ids), '?'));
$sqlPosts = "
    SELECT u.username, p.body, p.created_at
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.user_id IN ($placeholders)
    ORDER BY p.created_at DESC
";
$stmtPosts = $conn->prepare($sqlPosts);

if ($stmtPosts) {
    $stmtPosts->bind_param(str_repeat('i', count($followed_ids)), ...$followed_ids);
    $stmtPosts->execute();
    $resultPosts = $stmtPosts->get_result();

    $posts = [];
    while ($row = $resultPosts->fetch_assoc()) {
        $posts[] = [
            "username" => $row['username'],
            "body" => $row['body'],
            "created_at" => $row['created_at']
        ];
    }
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "data" => $posts
    ]);
    $stmtPosts->close();
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Gönderiler sorgulanırken hata oluştu: " . $conn->error
    ]);
    exit;
}

// Veritabanı bağlantısını kapat
$conn->close();
?>
