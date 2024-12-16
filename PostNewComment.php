<?php
// Hata raporlamayı etkinleştir
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// JSON yanıtlar için başlık ayarla
header('Content-Type: application/json');

// config.php dosyasını dahil et
require __DIR__ . '/../config.php';

// JSON verisini alma ve çözme
$data = json_decode(file_get_contents('php://input'), true);

// Gelen verileri kontrol et
$loginUser = $data['loginUser'] ?? $_POST['loginUser'] ?? null;
$postId = $data['postId'] ?? $_POST['postId'] ?? null;
$commentContent = $data['commentContent'] ?? $_POST['commentContent'] ?? null;

if (empty($loginUser) || empty($commentContent) || trim($commentContent) === '' || empty($postId)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Lütfen kullanıcı adı ve içeriği giriniz!"
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

if ($stmt1) {
    $stmt1->bind_param("s", $loginUser);
    $stmt1->execute();
    $stmt1->bind_result($userId);
    $stmt1->fetch();
    $stmt1->close();

    if (!isset($userId)) {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "Kullanıcı bulunamadı!"
        ]);
        exit;
    }

    // postId'nin var olup olmadığını kontrol et
    $sqlCheckPost = "SELECT id FROM posts WHERE id = ?";
    $stmtCheckPost = $conn->prepare($sqlCheckPost);

    if ($stmtCheckPost) {
        $stmtCheckPost->bind_param("i", $postId);
        $stmtCheckPost->execute();
        $stmtCheckPost->bind_result($existingPostId);
        $stmtCheckPost->fetch();
        $stmtCheckPost->close();

        if (!isset($existingPostId)) {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "Post bulunamadı!"
            ]);
            exit;
        }

        // Yorum kaydet
        $sql2 = "INSERT INTO comments (user_id, post_id, content) VALUES (?, ?, ?)";
        $stmt2 = $conn->prepare($sql2);

        if ($stmt2) {
            $stmt2->bind_param("iis", $userId, $postId, $commentContent);
            if ($stmt2->execute()) {
                http_response_code(201); // Created
                echo json_encode([
                    "status" => "success",
                    "message" => "Yorum başarıyla oluşturuldu!"
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    "status" => "error",
                    "message" => "Yorum oluşturma sırasında hata oluştu: " . $conn->error
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
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Post kontrolü sırasında bir hata oluştu: " . $conn->error
        ]);
    }
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
