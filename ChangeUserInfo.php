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
$ppId = $data['ppId'] ?? $_POST['ppId'] ?? null;
$bioContent = $data['bioContent'] ?? $_POST['bioContent'] ?? null;

if (empty($loginUser)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Lütfen kullanıcı adı giriniz"
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

// Kullanıcı bilgilerini sorgula
$sql1 = "SELECT bio, pp_id FROM users WHERE username = ?";
$stmt1 = $conn->prepare($sql1);
if (!$stmt1) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Sorgu hatası: " . $conn->error
    ]);
    $conn->close();
    exit;
}

$stmt1->bind_param("s", $loginUser);
$stmt1->execute();
$result = $stmt1->get_result();
$userData = $result->fetch_assoc();

if (!$userData) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Kullanıcı bulunamadı"
    ]);
    $stmt1->close();
    $conn->close();
    exit;
}

// Mevcut veritabanı bilgileri
$currentBio = $userData['bio'];
$currentPpId = $userData['pp_id'];

// Güncellemeleri bağımsız kontrol et
$updateBio = false;
$updatePpId = false;

// Bio içeriğini kontrol et
if ($bioContent !== null) {
    if ($bioContent === "") {
        $currentBio = null; // Bio'yu temizle
        $updateBio = true;
    } elseif ($bioContent !== $currentBio) {
        $currentBio = $bioContent; // Yeni bio içeriği
        $updateBio = true;
    }
}

// ppId içeriğini kontrol et
if ($ppId !== null && $ppId != $currentPpId) {
    $currentPpId = $ppId; // Yeni ppId içeriği
    $updatePpId = true;
}

// Güncelleme sorgusu oluştur
if ($updateBio || $updatePpId) {
    $sql2 = "UPDATE users SET bio = ?, pp_id = ? WHERE username = ?";
    $stmt2 = $conn->prepare($sql2);
    if ($stmt2) {
        $stmt2->bind_param("sis", $currentBio, $currentPpId, $loginUser);
        if ($stmt2->execute()) {
            http_response_code(200); // OK
            echo json_encode([
                "status" => "success",
                "message" => "Profil başarıyla güncellendi!"
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Güncelleme sırasında hata oluştu: " . $stmt2->error
            ]);
        }
        $stmt2->close();
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Sorgu hatası: " . $conn->error
        ]);
    }
} else {
    http_response_code(200); // OK
    echo json_encode([
        "status" => "success",
        "message" => "Güncellemeye gerek yok, bilgiler zaten güncel."
    ]);
}

// Bağlantıyı kapat
$stmt1->close();
$conn->close();
?>
