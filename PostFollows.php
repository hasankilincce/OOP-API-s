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
$followedUser = $data['followedUser'] ?? $_POST['followedUser'] ?? null;

if (empty($loginUser) || empty($followedUser)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Lütfen kullanıcı adı ve takip edilecek kullanıcıyı giriniz!"
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

// Kullanıcı ID'lerini bul
$sqlUser = "SELECT id FROM users WHERE username = ?";
$stmtUser = $conn->prepare($sqlUser);

if (!$stmtUser) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Kullanıcı sorgusu sırasında hata oluştu: " . $conn->error
    ]);
    exit;
}

// loginUser için ID
$stmtUser->bind_param("s", $loginUser);
$stmtUser->execute();
$stmtUser->bind_result($loginUserId);
$stmtUser->fetch();
$stmtUser->close();

if (!isset($loginUserId)) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Giriş yapan kullanıcı bulunamadı!"
    ]);
    exit;
}

// followedUser için ID
$stmtUser = $conn->prepare($sqlUser);
$stmtUser->bind_param("s", $followedUser);
$stmtUser->execute();
$stmtUser->bind_result($followedUserId);
$stmtUser->fetch();
$stmtUser->close();

if (!isset($followedUserId)) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Takip edilecek kullanıcı bulunamadı!"
    ]);
    exit;
}

// Daha önce takip edilmiş mi kontrol et
$sqlCheckFollow = "SELECT id FROM follows WHERE following_user_id = ? AND followed_user_id = ?";
$stmtCheck = $conn->prepare($sqlCheckFollow);

if ($stmtCheck) {
    $stmtCheck->bind_param("ii", $loginUserId, $followedUserId);
    $stmtCheck->execute();
    $stmtCheck->store_result();

    if ($stmtCheck->num_rows > 0) {
        // Takip varsa, takipten çıkar
        $stmtCheck->close();
        $sqlDelete = "DELETE FROM follows WHERE following_user_id = ? AND followed_user_id = ?";
        $stmtDelete = $conn->prepare($sqlDelete);
        $stmtDelete->bind_param("ii", $loginUserId, $followedUserId);

        if ($stmtDelete->execute()) {
            $message = "Takipten çıkıldı!";
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Takipten çıkma sırasında hata oluştu: " . $conn->error
            ]);
            exit;
        }
        $stmtDelete->close();
    } else {
        // Takip yoksa, takip et
        $stmtCheck->close();
        $sqlFollow = "INSERT INTO follows (following_user_id, followed_user_id, created_at) VALUES (?, ?, NOW() + INTERVAL 3 HOUR)";
        $stmtFollow = $conn->prepare($sqlFollow);
        $stmtFollow->bind_param("ii", $loginUserId, $followedUserId);

        if ($stmtFollow->execute()) {
            $message = "Takip edildi!";
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Takip işlemi sırasında hata oluştu: " . $conn->error
            ]);
            exit;
        }
        $stmtFollow->close();
    }
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Takip kontrolü sırasında hata oluştu: " . $conn->error
    ]);
    exit;
}

// Yanıtı döndür
http_response_code(200);
echo json_encode([
    "status" => "success",
    "message" => $message
]);

// Veritabanı bağlantısını kapat
$conn->close();
?>
