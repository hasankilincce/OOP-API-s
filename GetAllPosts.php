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

// SQL sorgusu: Tüm postları getirir
$sql = "
    SELECT u.username, p.body, p.created_at
    FROM posts p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC;
";

$result = $conn->query($sql);

// Sonuçları kontrol et ve JSON olarak döndür
if ($result && $result->num_rows > 0) {
    $posts = [];
    while ($row = $result->fetch_assoc()) {
        $posts[] = [
            "username" => $row['username'],
            "created_at" => $row['created_at'],
            "post" => $row['body']
        ];
    }
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "data" => $posts
    ]);
} else {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Hiç bir gönderi bulunamadı."
    ]);
}

// Veritabanı bağlantısını kapat
$conn->close();
?>
