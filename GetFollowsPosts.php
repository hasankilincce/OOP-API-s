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

// Uygulamadan gelen katsayıyı alın
$katsayi = isset($_GET['katsayi']) ? (int)$_GET['katsayi'] : 0;
// Login kullanıcı adını alın
$loginUser = isset($input['loginUser']) ? $input['loginUser'] : null;

if (!$loginUser) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "loginUser parametresi eksik."
    ]);
    exit;
}

// Katsayıya göre limitleri ayarla
$alt_limit = $katsayi * 50;
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
    $user_stmt->close();
    $conn->close();
    exit;
}

$user_row = $user_result->fetch_assoc();
$login_user_id = $user_row['id'];
$user_stmt->close();

// Takip edilen kullanıcıların ID'lerini alın
$followed_ids_query = "SELECT followed_user_id FROM follows WHERE following_user_id = ?";
$followed_stmt = $conn->prepare($followed_ids_query);
if ($followed_stmt === false) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Takip edilen kullanıcılar sorgusu hazırlanamıyor: " . $conn->error
    ]);
    $conn->close();
    exit;
}

$followed_stmt->bind_param("i", $login_user_id);
$followed_stmt->execute();
$followed_result = $followed_stmt->get_result();

$followed_ids = [];
while ($row = $followed_result->fetch_assoc()) {
    $followed_ids[] = $row['followed_user_id'];
}
$followed_stmt->close();

if (empty($followed_ids)) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Takip edilen kullanıcılar bulunamadı."
    ]);
    $conn->close();
    exit;
}

// SQL sorgusu: Belirli aralıktaki postları getirir, beğeni sayılarını ve isLiked durumunu ekler
$placeholders = implode(',', array_fill(0, count($followed_ids), '?'));
$sql = "
    SELECT 
        p.id AS post_id, 
        u.username, 
        u.name, 
        u.pp_id,
        p.body, 
        p.created_at, 
        COALESCE(COUNT(DISTINCT c.id), 0) AS comments_count,
        COALESCE(COUNT(DISTINCT l.id), 0) AS likes_count,
        CASE WHEN EXISTS (
            SELECT 1 FROM likes l2 WHERE l2.post_id = p.id AND l2.user_id = ?
        ) THEN true ELSE false END AS isLiked
    FROM posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN comments c ON p.id = c.post_id -- Yorumlar için
    LEFT JOIN likes l ON p.id = l.post_id   -- Beğeniler için
    WHERE p.user_id IN ($placeholders)
    GROUP BY p.id, u.username, u.name, p.body, p.created_at, pp_id
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
    $conn->close();
    exit;
}

// Dinamik parametreleri bağla
$params = array_merge([$login_user_id], $followed_ids, [$alt_limit, $ust_limit]);
$types = str_repeat('i', count($params)); // Tüm parametreler tamsayı (integer)
$stmt->bind_param($types, ...$params);
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
            "pp_id" => $row['pp_id'],
            "name" => $row['name'],
            "created_at" => formatTimeDifference($row['created_at']),
            "text" => $row['body'],
            "likes_count" => (int)$row['likes_count'],
            "comments_count" => (int)$row['comments_count'],
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
