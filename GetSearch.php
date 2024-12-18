<?php
// Hata raporlamayı etkinleştir
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// JSON yanıtlar için başlık ayarla
header('Content-Type: application/json');

// config.php dosyasını dahil et
require 'config.php';

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

// JSON gövdesini al
$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

// Gelen JSON'dan 'query' anahtarını kontrol et
$query = isset($data['query']) ? mb_strtolower($conn->real_escape_string($data['query'])) : '';
if (empty($query)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Sorgu kelimesi eksik."
    ]);
    exit;
}

// Kullanıcıları getir (follows tablosundaki takipçi sayılarına göre en yüksek 5)
$userSql = "
    SELECT u.username, u.name, u.pp_id , u.bio, COUNT(f.followed_user_id) AS follower_count
    FROM users u
    LEFT JOIN follows f ON u.id = f.followed_user_id
    WHERE LOWER(u.name) LIKE ? OR LOWER(u.username) LIKE ?
    GROUP BY u.id
    ORDER BY follower_count DESC
    LIMIT 5
";

$userStmt = $conn->prepare($userSql);
$likeQuery = $query . '%';
$userStmt->bind_param('ss', $likeQuery, $likeQuery);
$userStmt->execute();
$userResult = $userStmt->get_result();

$users = [];
while ($row = $userResult->fetch_assoc()) {
    $users[] = [
        "username" => $row['username'],
        "name" => $row['name'],
        "pp_id" => $row['pp_id'],
        "bio" => $row['bio'],
        "follower_count" => $row['follower_count']
    ];
}

// Kategorileri getir (posts tablosundaki kullanım sıklığına göre en yüksek 5)
$categorySql = "
    SELECT c.name, COUNT(p.category_id) AS usage_count
    FROM categories c
    LEFT JOIN posts p ON c.id = p.category_id
    WHERE LOWER(c.name) LIKE ?
    GROUP BY c.id
    ORDER BY usage_count DESC
    LIMIT 5
";

$categoryStmt = $conn->prepare($categorySql);
$categoryStmt->bind_param('s', $likeQuery);
$categoryStmt->execute();
$categoryResult = $categoryStmt->get_result();

$categories = [];
while ($row = $categoryResult->fetch_assoc()) {
    $categories[] = [
        "name" => $row['name'],
        "usage_count" => $row['usage_count']
    ];
}

// Sonuçları döndür
http_response_code(200);
echo json_encode([
    "status" => "success",
    "users" => $users,
    "categories" => $categories
]);

// Veritabanı bağlantısını kapat
$conn->close();
?>
