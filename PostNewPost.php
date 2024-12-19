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
$postContent = $data['postContent'] ?? $_POST['postContent'] ?? null;

if (empty($loginUser) || empty($postContent || trim($postContent) === '')) {
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

    // Hashtag'leri ayıkla
    preg_match_all('/#([a-zA-Z0-9çÇğĞıİöÖşŞüÜ]+)/u', $postContent, $matches);
    $hashtags = $matches[1]; // Sadece hashtag isimleri alınır

    // Kategori ID'sini varsayılan olarak null yap
    $categoryId = null;

    if (!empty($hashtags)) {
        // İlk hashtag'i kullanarak kategori belirle
        $hashtag = strtolower($hashtags[0]); // Küçük harfe çevir

        // Kategori kontrolü
        $sqlCategoryCheck = "SELECT id FROM categories WHERE name = ?";
        $stmtCategoryCheck = $conn->prepare($sqlCategoryCheck);
        $stmtCategoryCheck->bind_param("s", $hashtag);
        $stmtCategoryCheck->execute();
        $stmtCategoryCheck->bind_result($categoryId);
        $stmtCategoryCheck->fetch();
        $stmtCategoryCheck->close();

        if (!isset($categoryId)) {
            // Kategori yoksa yeni oluştur
            $sqlCategoryInsert = "INSERT INTO categories (name) VALUES (?)";
            $stmtCategoryInsert = $conn->prepare($sqlCategoryInsert);
            $stmtCategoryInsert->bind_param("s", $hashtag);
            if ($stmtCategoryInsert->execute()) {
                $categoryId = $stmtCategoryInsert->insert_id; // Yeni kategori ID'sini al
            }
            $stmtCategoryInsert->close();
        }
    }

    // Gönderiyi kaydet
    $sql2 = "INSERT INTO posts (user_id, body, category_id) VALUES (?, ?, ?)";
    $stmt2 = $conn->prepare($sql2);

    if ($stmt2) {
        $stmt2->bind_param("isi", $userId, $postContent, $categoryId);
        if ($stmt2->execute()) {
            http_response_code(201); // Created
            echo json_encode([
                "status" => "success",
                "message" => "Gönderi başarıyla oluşturuldu!"
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Gönderi oluşturma sırasında hata oluştu: " . $conn->error
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
        "message" => "Sorgu hatası: " . $conn->error
    ]);
}

// Veritabanı bağlantısını kapat
$conn->close();
?>
