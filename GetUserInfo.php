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

// `loginUser` ve `targetUser` kontrolü
$loginUser = $data['loginUser'] ?? null;
$targetUser = $data['targetUser'] ?? null;

if (!$loginUser) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Geçerli bir kullanıcı adı sağlamalısınız."
    ]);
    exit;
}

if (!$targetUser) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Geçerli bir hedef kullanıcı adı sağlamalısınız."
    ]);
    exit;
}

// Kullanıcı bilgilerini bulma
$sqlUser = "SELECT id, name, bio, pp_id FROM users WHERE username = ?";
$stmtUser = $conn->prepare($sqlUser);

if ($stmtUser) {
    $stmtUser->bind_param("s", $loginUser);
    $stmtUser->execute();
    $resultUser = $stmtUser->get_result();

    if ($resultUser->num_rows > 0) {
        $user = $resultUser->fetch_assoc();
        $current_user_id = $user['id'];
        $user_info = [
            "name" => $user['name'],
            "bio" => $user['bio'],
            "pp_id" => $user['pp_id']
        ];
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

// Hedef kullanıcı bilgilerini bulma
$sqlTargetUser = "SELECT id FROM users WHERE username = ?";
$stmtTargetUser = $conn->prepare($sqlTargetUser);

if ($stmtTargetUser) {
    $stmtTargetUser->bind_param("s", $targetUser);
    $stmtTargetUser->execute();
    $resultTargetUser = $stmtTargetUser->get_result();

    if ($resultTargetUser->num_rows > 0) {
        $target_user = $resultTargetUser->fetch_assoc();
        $target_user_id = $target_user['id'];
    } else {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "Hedef kullanıcı bulunamadı."
        ]);
        exit;
    }
    $stmtTargetUser->close();
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Hedef kullanıcı sorgusu sırasında hata oluştu: " . $conn->error
    ]);
    exit;
}

// LoginUser ve TargetUser eşit mi kontrolü
if ($loginUser === $targetUser) {
    $isFollowing = null;
} else {
    // LoginUser, TargetUser'ı takip ediyor mu kontrolü
    $sqlCheckFollow = "SELECT COUNT(*) AS is_following FROM follows WHERE following_user_id = ? AND followed_user_id = ?";
    $stmtCheckFollow = $conn->prepare($sqlCheckFollow);

    if ($stmtCheckFollow) {
        $stmtCheckFollow->bind_param("ii", $current_user_id, $target_user_id);
        $stmtCheckFollow->execute();
        $resultCheckFollow = $stmtCheckFollow->get_result();
        $isFollowing = $resultCheckFollow->fetch_assoc()['is_following'] > 0;
        $stmtCheckFollow->close();
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Takip durumu sorgusu sırasında hata oluştu: " . $conn->error
        ]);
        exit;
    }
}

// Takip edilen kullanıcı sayısını bulma
$sqlFollowingCount = "SELECT COUNT(*) AS following_count FROM follows WHERE following_user_id = ?";
$stmtFollowingCount = $conn->prepare($sqlFollowingCount);

if ($stmtFollowingCount) {
    $stmtFollowingCount->bind_param("i", $current_user_id);
    $stmtFollowingCount->execute();
    $resultFollowingCount = $stmtFollowingCount->get_result();
    $following_count = $resultFollowingCount->fetch_assoc()['following_count'];
    $stmtFollowingCount->close();
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Takip edilen kullanıcı sorgusu sırasında hata oluştu: " . $conn->error
    ]);
    exit;
}

// Takipçi sayısını bulma
$sqlFollowerCount = "SELECT COUNT(*) AS follower_count FROM follows WHERE followed_user_id = ?";
$stmtFollowerCount = $conn->prepare($sqlFollowerCount);

if ($stmtFollowerCount) {
    $stmtFollowerCount->bind_param("i", $current_user_id);
    $stmtFollowerCount->execute();
    $resultFollowerCount = $stmtFollowerCount->get_result();
    $follower_count = $resultFollowerCount->fetch_assoc()['follower_count'];
    $stmtFollowerCount->close();
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Takipçi sayısı sorgusu sırasında hata oluştu: " . $conn->error
    ]);
    exit;
}

// Sonuçları döndür
http_response_code(200);
echo json_encode([
    "status" => "success",
    "user_info" => $user_info,
    "following_count" => $following_count,
    "follower_count" => $follower_count,
    "is_following" => $isFollowing
]);

// Veritabanı bağlantısını kapat
$conn->close();
?>
