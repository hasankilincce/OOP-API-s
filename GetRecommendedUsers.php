<?php
// Hata raporlamayı etkinleştir
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// JSON yanıtlar için başlık ayarla
header('Content-Type: application/json');

// config.php dosyasını dahil et
require 'config.php';

// Gelen JSON verilerini al ve çöz
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// loginUser'ı kontrol et
$loginUser = $data['loginUser'] ?? null;

// Kullanıcı adı kontrolü
if (!$loginUser) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Kullanıcı adı gerekli."
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

// Kullanıcının ID'sini bul
$sql = "SELECT id FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $loginUser);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $loginUserId = $user['id'];

        // Ortak takipçileri olan kullanıcıları sorgula
        $sql = "
            SELECT u.username, u.name, u.pp_id, u.bio, COUNT(f1.following_user_id) AS mutual_followers_count
            FROM users u
            LEFT JOIN follows f1 ON u.id = f1.followed_user_id
            LEFT JOIN follows f2 ON f1.following_user_id = f2.followed_user_id
            WHERE u.id != ?
            AND u.id NOT IN (
                SELECT followed_user_id
                FROM follows
                WHERE following_user_id = ?
            )
            AND f2.following_user_id = ?
            GROUP BY u.id
            ORDER BY mutual_followers_count DESC
            LIMIT 5
        ";
        $stmt2 = $conn->prepare($sql);

        if ($stmt2) {
            $stmt2->bind_param("iii", $loginUserId, $loginUserId, $loginUserId);
            $stmt2->execute();
            $result2 = $stmt2->get_result();

            if ($result2 && $result2->num_rows > 0) {
                $nonFollowedUsers = [];
                while ($row = $result2->fetch_assoc()) {
                    $nonFollowedUsers[] = [
                        "username" => $row["username"],
                        "name" => $row["name"],
                        "bio" => $row["bio"],
                        "pp_id" => $row["pp_id"],
                        "mutual_followers_count" => $row["mutual_followers_count"]
                    ];
                }
            } if (5 > $result2->num_rows) {
                // Ortak takipçi yoksa rastgele kullanıcılar döndür
                $sql = "
                    SELECT username, name, pp_id , bio
                    FROM users 
                    WHERE id != ? 
                    ORDER BY RAND() 
                    LIMIT 5
                ";
                $stmt3 = $conn->prepare($sql);

                if ($stmt3) {
                    $stmt3->bind_param("i", $loginUserId);
                    $stmt3->execute();
                    $result3 = $stmt3->get_result();

                    if ($result3 && $result3->num_rows > 0) {
                        $randomUsers = [];
                        while ($row = $result3->fetch_assoc()) {
                            $randomUsers[] = [
                                "username" => $row["username"],
                                "name" => $row["name"],
                                "bio" => $row["bio"],
                                "pp_id" => $row["pp_id"],
                                "mutual_followers_count" => 0
                            ];
                        }
                    } else {
                        http_response_code(404);
                        echo json_encode([
                            "status" => "error",
                            "message" => "Hiç kullanıcı bulunamadı."
                        ]);
                    }

                    $stmt3->close();
                } else {
                    http_response_code(500);
                    echo json_encode([
                        "status" => "error",
                        "message" => "Rastgele kullanıcı sorgusunda hata: " . $conn->error
                    ]);
                }
            }
            
            echo json_encode([
            "status" => "success",
            "data" => $nonFollowedUsers + $randomUsers
        ]);

            $stmt2->close();
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Sorgu hatası: " . $conn->error
            ]);
        }
    } else {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "Kullanıcı bulunamadı."
        ]);
    }

    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Kullanıcı ID'si sorgusunda hata: " . $conn->error
    ]);
}

// Veritabanı bağlantısını kapat
$conn->close();
?>
