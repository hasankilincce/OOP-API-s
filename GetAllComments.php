<?php
// Hata raporlamayı etkinleştir
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// JSON yanıtlar için başlık ayarla
header('Content-Type: application/json');

// config.php dosyasını dahil et
require __DIR__ . '/../config.php';

// Gelen veriyi JSON olarak al
$input = json_decode(file_get_contents('php://input'), true);

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

// Gelen `katsayi` ve `postId` parametrelerini al
$katsayi = isset($_GET['katsayi']) ? (int)$_GET['katsayi'] : 0;
$postId = isset($input['postId']) ? $input['postId'] : null;

// `postId` eksikse hata döndür
if (!$postId) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "postId parametresi eksik."
    ]);
    exit;
}

// Katsayıya göre limitleri ayarla
$alt_limit = $katsayi * 10;
$ust_limit = $alt_limit + 10; // Her sayfada 10 gönderi getir

// SQL sorgusunu hazırla
$sql = "
    SELECT 
        c.id AS comment_id, 
        u.username, 
        u.name,
        u.pp_id,
        c.content, 
        c.created_at
    FROM 
        comments c
    JOIN 
        users u ON c.user_id = u.id
    WHERE 
        c.post_id = ?
    ORDER BY 
        c.created_at DESC
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
$stmt->bind_param("iii", $postId, $alt_limit, $ust_limit);
$stmt->execute();
$result = $stmt->get_result();

// Zaman farkını formatlayan yardımcı fonksiyon
function formatTimeDifference($datetime) {
    $now = new DateTime();
    $postTime = new DateTime($datetime);
    $interval = $now->diff($postTime);

    if ($interval->y > 0 || $interval->m > 0 || $interval->d > 1) {
        // Tarihi "14 Aralık 2024" gibi döndür
        return $postTime->format('d F Y');
    } elseif ($interval->d === 1) {
        return 'yesterday';
    } elseif ($interval->h >= 1) {
        return $interval->h . ' hours ago';
    } elseif ($interval->i >= 1) {
        return $interval->i . ' minutes ago';
    } else {
        return 'just now';
    }
}

// Sonuçları kontrol et ve JSON olarak döndür
if ($result && $result->num_rows > 0) {
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = [
            "comment_id" => $row['comment_id'],
            "username" => $row['username'],
            "pp_id" => $row['pp_id'],
            "name" => $row['name'],
            "content" => $row['content'],
            "created_at" => formatTimeDifference($row['created_at'])
        ];
    }
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "data" => $comments
    ]);
} else {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Hiç bir yorum bulunamadı."
    ]);
}

// Veritabanı bağlantısını kapat
$stmt->close();
$conn->close();
?>
