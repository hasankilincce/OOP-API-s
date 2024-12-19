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

// Sizi takip eden kullanıcıları getir
$sqlFollowers = "
    SELECT u.username, u.name, u.pp_id, u.bio
    FROM follows f
    JOIN users u ON f.following_user_id = u.id
    WHERE f.followed_user_id = ?
";

$stmtFollowers = $conn->prepare($sqlFollowers);

if ($stmtFollowers) {
    $stmtFollowers->bind_param("i", $current_user_id);
    $stmtFollowers->execute();
    $resultFollowers = $stmtFollowers->get_result();

    $followers = [];
    while ($row = $resultFollowers->fetch_assoc()) {
        $followers[] = [
            "username" => $row['username'],
            "name" => $row['name'],
            "pp_id" => $row['pp_id'],
            "bio" => $row['bio']
        ];
    }

    // Takipçi sayısını hesapla
    $followersCount = count($followers);

    if ($followersCount > 0) {
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $followers       // Takipçi bilgileri
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "Sizi takip eden kullanıcı bulunamadı."
        ]);
    }
    $stmtFollowers->close();
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Takipçi sorgusu sırasında hata oluştu: " . $conn->error
    ]);
    exit;
}

// Veritabanı bağlantısını kapat
$conn->close();
?>
