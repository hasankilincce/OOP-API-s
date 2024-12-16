<?php
// Hata raporlamayı etkinleştir
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// JSON yanıtlar için başlık ayarla
header('Content-Type: application/json');

// config.php dosyasını dahil et
require __DIR__ . '/../config.php';

// Gelen veriyi JSON olarak al
$input = json_decode(file_get_contents('php://input'), true);

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

// Uygulamadan gelen değerleri al
$katsayi = isset($_GET['katsayi']) ? (int)$_GET['katsayi'] : 0;
$category_name = isset($input['categoryName']) ? $input['categoryName'] : null;
$loginUser = isset($input['loginUser']) ? $input['loginUser'] : null;

if (!$loginUser || !$category_name) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "loginUser veya categoryName parametresi eksik."
    ]);
    exit;
}

// Katsayıya göre limitleri ayarla
$alt_limit = ($katsayi * 50);
$ust_limit = 50; // Her sayfada 50 gönderi getir

// Login kullanıcı için user_id'yi al
$user_id_query = "SELECT id FROM users WHERE username = ?";
$user_stmt = $conn->prepare($user_id_query);
if ($user_stmt === false) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "SQL sorgusu hazırlanamıyor: " . $conn->error
    ]);
    exit;
}

$user_stmt->bind_param("s", $loginUser);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "loginUser ile eşleşen kullanıcı bulunamadı."
    ]);
    exit;
}

$user_row = $user_result->fetch_assoc();
$login_user_id = $user_row['id'];

// Kategori adından ID'yi al
$category_query = "SELECT id FROM categories WHERE LOWER(name) = LOWER(?)";
$category_stmt = $conn->prepare($category_query);
if ($category_stmt === false) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Kategori sorgusu sırasında hata oluştu: " . $conn->error
    ]);
    exit;
}

$category_stmt->bind_param("s", $category_name);
$category_stmt->execute();
$category_result = $category_stmt->get_result();

if ($category_result->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Kategori bulunamadı."
    ]);
    exit;
}

$category_row = $category_result->fetch_assoc();
$category_id = $category_row['id'];

// SQL sorgusu: Belirli aralıktaki postları getirir, beğeni sayılarını ve isLiked durumunu ekler
$sql = "
    SELECT 
        p.id AS post_id, 
        u.username, 
        u.name, 
        p.body, 
        p.created_at, 
        COALESCE(COUNT(l.id), 0) AS likes_count,
        CASE 
            WHEN EXISTS (
                SELECT 1 
                FROM likes l2 
                WHERE l2.post_id = p.id AND l2.user_id = ?
            ) 
            THEN true ELSE false 
        END AS isLiked
    FROM posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN likes l ON p.id = l.post_id
    WHERE p.category_id = ?
    GROUP BY p.id, u.username, u.name, p.body, p.created_at
    ORDER BY p.created_at DESC
    LIMIT ?, ?;
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "SQL sorgusu hazırlanamıyor: " . $conn->error
    ]);
    exit;
}

// Parametreleri bağla ve sorguyu çalıştır
$stmt->bind_param("iiii", $login_user_id, $category_id, $alt_limit, $ust_limit);
$stmt->execute();
$result = $stmt->get_result();

function formatTimeDifference($datetime) {
    $now = new DateTime();
    $postTime = new DateTime($datetime);
    $interval = $now->diff($postTime);

    if ($interval->y > 0 || $interval->m > 0 || $interval->d > 1) {
        // Tarihi "14 Aralık 2024" gibi dön
        return $postTime->format('d F Y');
    } elseif ($interval->d === 1) {
        return 'yesterday';
    } elseif ($interval->h >= 1) {
        return $interval->h . ' hours ago';
    } elseif ($interval->i >= 1) {
        return $interval->i . ' minutes ago';
    } else {
        return 'just now';
    }
}

// Sonuçları kontrol et ve JSON olarak döndür
if ($result && $result->num_rows > 0) {
    $posts = [];
    while ($row = $result->fetch_assoc()) {
        $posts[] = [
            "post_id" => $row['post_id'],
            "username" => $row['username'],
            "name" => $row['name'],
            "created_at" => formatTimeDifference($row['created_at']),
            "text" => $row['body'],
            "likes_count" => (int)$row['likes_count'],
            "isLiked" => (bool)$row['isLiked']
        ];
    }
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "data" => $posts
    ]);
} else {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Hiç bir gönderi bulunamadı."
    ]);
}

// Veritabanı bağlantısını kapat
$stmt->close();
$conn->close();
?>
