<?php
// Hata raporlamayı etkinleştir
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// JSON yanıtlar için başlık ayarla
header('Content-Type: application/json');

// config.php dosyasını dahil et
require 'config.php';

// POST ile gelen verileri kontrol et
if (!empty($_POST["loginUser"]) && !empty($_POST["loginPass"])) {
    // Kullanıcı verilerini temizle
    $loginUser = trim($_POST["loginUser"]);
    $loginPass = trim($_POST["loginPass"]);

    // Veritabanı bağlantısı oluştur
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Bağlantı kontrolü
    if ($conn->connect_error) {
        echo json_encode([
            "status" => "error",
            "message" => "Bağlantı başarısız: " . $conn->connect_error
        ]);
        exit;
    }

    // SQL sorgusunu prepared statement ile oluştur
    $sql = "SELECT password FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        // Kullanıcı adını sorguya bağla ve çalıştır
        $stmt->bind_param("s", $loginUser);
        $stmt->execute();
        $result = $stmt->get_result();

        // Sonuçları kontrol et
        if ($result->num_rows > 0) {
            // Veritabanından alınan şifreyi kontrol et
            $row = $result->fetch_assoc();
            if ($loginPass == $row["password"]) {
                echo json_encode([
                    "status" => "success",
                    "message" => "Giriş başarılı!"
                ]);
            } else {
                http_response_code(401);
                echo json_encode([
                    "status" => "error",
                    "message" => "Hatalı şifre!"
                ]);
            }
        } else {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "Böyle bir kullanıcı bulunamadı!"
            ]);
        }

        // Kaynakları serbest bırak
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
} else {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Lütfen kullanıcı adı ve şifre giriniz!"
    ]);
}
?>
