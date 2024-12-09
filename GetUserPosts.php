<?php
// Hata raporlamayı etkinleştir
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// JSON yanıtlar için başlık ayarla
header('Content-Type: application/json');

// config.php dosyasını dahil et
require __DIR__ . '/../config.php';

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

// Gelen `loginUser` değerini kontrol et
$loginUser = $data['loginUser'] ?? null;

if (!$loginUser) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Lütfen geçerli bir kullanıcı adı sağlayın."
    ]);
    exit;
}

// Kullanıcının ID'sini alma sorgusu
$sql_user = "SELECT id FROM users WHERE username = ?";
$stmt_user = $conn->prepare($sql_user);

if ($stmt_user) {
    $stmt_user->bind_param("s", $loginUser);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();

    if ($result_user->num_rows > 0) {
        $user = $result_user->fetch_assoc();
        $user_id = $user['id'];

        // Kullanıcının ve gönderiyi paylaşan kişilerin bilgilerini alma sorgusu
        $sql_posts = "
            SELECT u.username, u.name, p.body, p.created_at 
            FROM posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.user_id = ?
            ORDER BY p.created_at DESC
        ";
        $stmt_posts = $conn->prepare($sql_posts);

        if ($stmt_posts) {
            $stmt_posts->bind_param("i", $user_id);
            $stmt_posts->execute();
            $result_posts = $stmt_posts->get_result();

            if ($result_posts->num_rows > 0) {
                $posts = [];
                while ($row = $result_posts->fetch_assoc()) {
                    $posts[] = [
                        "username" => $row['username'],
                        "name" => $row['name'],
                        "body" => $row['body'],
                        "created_at" => $row['created_at']
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
                    "message" => "Bu kullanıcıya ait gönderi bulunamadı."
                ]);
            }
            $stmt_posts->close();
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Gönderiler sorgusu hazırlanırken hata oluştu: " . $conn->error
            ]);
        }
    } else {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "Kullanıcı bulunamadı."
        ]);
    }
    $stmt_user->close();
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Kullanıcı sorgusu hazırlanırken hata oluştu: " . $conn->error
    ]);
}

// Veritabanı bağlantısını kapat
$conn->close();
?>
