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
$category_name = $data['categoryName'] ?? null;

if (empty($category_name)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Lütfen geçerli bir kategori adı sağlayın."
    ]);
    exit;
}

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

// Kategori ID'sini bulmak için sorgu
$sql_category = "SELECT id FROM categories WHERE LOWER(name) = LOWER(?)";
$stmt_category = $conn->prepare($sql_category);

if ($stmt_category) {
    $stmt_category->bind_param("s", $category_name);
    $stmt_category->execute();
    $result_category = $stmt_category->get_result();

    if ($result_category->num_rows > 0) {
        $category = $result_category->fetch_assoc();
        $category_id = $category['id'];
    } else {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "Kategori bulunamadı."
        ]);
        $stmt_category->close();
        $conn->close();
        exit;
    }
    $stmt_category->close();
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Kategori sorgusu sırasında hata oluştu: " . $conn->error
    ]);
    $conn->close();
    exit;
}

// Postları getirmek için sorgu
$sql_posts = "
    SELECT u.username, u.name, p.body, p.created_at
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.category_id = ?
    ORDER BY p.created_at DESC
";

$stmt_posts = $conn->prepare($sql_posts);

if ($stmt_posts) {
    $stmt_posts->bind_param("i", $category_id);
    $stmt_posts->execute();
    $result_posts = $stmt_posts->get_result();

    $posts = [];
    while ($row = $result_posts->fetch_assoc()) {
        $posts[] = [
            "username" => $row['username'],
            "name" => $row['name'],
            "body" => $row['body'],
            "created_at" => $row['created_at']
        ];
    }

    if (!empty($posts)) {
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $posts
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "Bu kategoriye ait gönderi bulunamadı."
        ]);
    }

    $stmt_posts->close();
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Gönderi sorgusu sırasında hata oluştu: " . $conn->error
    ]);
}

// Veritabanı bağlantısını kapat
$conn->close();
?>
