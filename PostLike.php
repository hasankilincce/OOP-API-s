<?php
// Hata raporlamayı etkinleştir
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// JSON yanıtlar için başlık ayarla
header('Content-Type: application/json');

// config.php dosyasını dahil et
require 'config.php';

// JSON verisini alma ve çözme
$data = json_decode(file_get_contents('php://input'), true);

// Gelen verileri kontrol et
$loginUser = $data['loginUser'] ?? $_POST['loginUser'] ?? null;
$postId = $data['postId'] ?? $_POST['postId'] ?? null;

if (empty($loginUser) || empty($postId)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Lütfen kullanıcı adı ve gönderi ID'sini giriniz!"
    ]);
    exit;
}

// Veritabanı bağlantısını oluştur
$conn = new mysqli($servername, $username, $password, $dbname);

// Bağlantı kontrolü
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Bağlantı başarısız: " . $conn->connect_error
    ]);
    exit;
}

// Kullanıcı ID'sini al
$sql1 = "SELECT id FROM users WHERE username = ?";
$stmt1 = $conn->prepare($sql1);
$stmt1->bind_param("s", $loginUser);
$stmt1->execute();
$result1 = $stmt1->get_result();

if ($result1->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Kullanıcı bulunamadı!"
    ]);
    exit;
}

$userId = $result1->fetch_assoc()['id'];
$stmt1->close();

// Beğeni kontrolü
$sqlCheck = "SELECT id FROM likes WHERE user_id = ? AND post_id = ?";
$stmtCheck = $conn->prepare($sqlCheck);
$stmtCheck->bind_param("ii", $userId, $postId);
$stmtCheck->execute();
$resultCheck = $stmtCheck->get_result();

if ($resultCheck->num_rows > 0) {
    // Beğeni varsa sil
    $sqlDelete = "DELETE FROM likes WHERE user_id = ? AND post_id = ?";
    $stmtDelete = $conn->prepare($sqlDelete);
    $stmtDelete->bind_param("ii", $userId, $postId);

    if ($stmtDelete->execute()) {
        $message = "Beğeni kaldırıldı!";
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Beğeni kaldırma sırasında hata oluştu: " . $conn->error
        ]);
        exit;
    }
    $stmtDelete->close();
} else {
    // Beğeni yoksa ekle
    $sqlInsert = "INSERT INTO likes (user_id, post_id) VALUES (?, ?)";
    $stmtInsert = $conn->prepare($sqlInsert);
    $stmtInsert->bind_param("ii", $userId, $postId);

    if ($stmtInsert->execute()) {
        $message = "Beğeni eklendi!";
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Beğeni ekleme sırasında hata oluştu: " . $conn->error
        ]);
        exit;
    }
    $stmtInsert->close();
}
$stmtCheck->close();

// Beğeni sayısını al
$sqlCount = "SELECT COUNT(*) as likeCount FROM likes WHERE post_id = ?";
$stmtCount = $conn->prepare($sqlCount);
$stmtCount->bind_param("i", $postId);
$stmtCount->execute();
$resultCount = $stmtCount->get_result();
$likeCount = $resultCount->fetch_assoc()['likeCount'];
$stmtCount->close();

// Yanıtı döndür
http_response_code(200);
echo json_encode([
    "status" => "success",
    "message" => $message,
    "likeCount" => $likeCount
]);

// Veritabanı bağlantısını kapat
$conn->close();
?>
