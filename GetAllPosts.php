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

// Uygulamadan gelen katsayıyı alın
$katsayi = isset($_GET['katsayi']) ? (int)$_GET['katsayi'] : 0;

// Katsayıya göre limitleri ayarla
$alt_limit = ($katsayi * 50) + 1;
$ust_limit = $katsayi > 0 ? $alt_limit + 49 : 50; // Katsayı 0 ise ilk 50 gönderiyi getir

// SQL sorgusu: Belirli aralıktaki postları getirir
$sql = "
    SELECT p.id, u.username, u.name, p.body, p.created_at
    FROM posts p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC
    LIMIT ?, ?;
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "SQL sorgusu hazırlanamıyor: " . $conn->error
    ]);
    exit;
}

// Parametreleri bağla ve sorguyu çalıştır
$stmt->bind_param("ii", $alt_limit, $ust_limit);
$stmt->execute();
$result = $stmt->get_result();

// Sonuçları kontrol et ve JSON olarak döndür
if ($result && $result->num_rows > 0) {
    $posts = [];
    while ($row = $result->fetch_assoc()) {
        $posts[] = [
            "post_id" => $row['id'],
            "username" => $row['username'],
            "name" => $row['name'],
            "created_at" => $row['created_at'],
            "text" => $row['body']
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
$stmt->close();
$conn->close();
?>
