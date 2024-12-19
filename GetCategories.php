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

// En çok kullanılan kategorileri sorgula
$sql = "
    SELECT c.name, COUNT(p.category_id) AS usage_count
    FROM categories c
    LEFT JOIN posts p ON c.id = p.category_id
    GROUP BY c.id, c.name
    ORDER BY usage_count DESC
    LIMIT 10
";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = [
            "name" => $row["name"],
            "usage_count" => (int) $row["usage_count"]
        ];
    }

    echo json_encode([
        "status" => "success",
        "data" => $categories
    ]);
} else {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Kategori bulunamadı."
    ]);
}

// Veritabanı bağlantısını kapat
$conn->close();
?>
