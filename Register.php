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
$loginPass = $data['loginPass'] ?? $_POST['loginPass'] ?? null;
$loginName = $data['loginName'] ?? $_POST['loginName'] ?? null;

if (empty($loginUser) || empty($loginPass) || empty($loginName)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Lütfen kullanıcı adı, şifre ve adınızı giriniz!"
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

// Kullanıcı adını kontrol et
$sql = "SELECT username FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $loginUser);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        http_response_code(409); // Conflict
        echo json_encode([
            "status" => "error",
            "message" => "Bu kullanıcı adı zaten alınmış."
        ]);
    } else {
        // Şifreyi düz metin olarak kaydet
        $sql2 = "INSERT INTO users (username, name, password) VALUES (?, ?, ?)";
        $stmt2 = $conn->prepare($sql2);

        if ($stmt2) {
            $stmt2->bind_param("sss", $loginUser, $loginName, $loginPass);
            if ($stmt2->execute()) {
                http_response_code(201); // Created
                echo json_encode([
                    "status" => "success",
                    "message" => "Kullanıcı başarıyla oluşturuldu!"
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    "status" => "error",
                    "message" => "Kullanıcı kaydı sırasında hata oluştu: " . $conn->error
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
    }
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Sorgu hatası: " . $conn->error
    ]);
}

// Veritabanı bağlantısını kapat
$conn->close();
?>
