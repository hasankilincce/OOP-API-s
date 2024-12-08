<?php
// Hata raporlamayı etkinleştir
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// config.php dosyasını dahil et
require 'config.php';

// Kullanıcıdan gelen veriler
$loginUser = $_POST["loginUser"];
$loginPass = $_POST["loginPass"];

// Veritabanı bağlantısı oluştur
$conn = new mysqli($servername, $username, $password, $dbname);

// Bağlantı kontrolü
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Kullanıcı adını kontrol et
$sql = "SELECT username FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $loginUser);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  echo "Bu kullanıcı adı zaten alınmış";
} else {
  //echo "Kullanıcı oluşturuluyor";

  // Kullanıcıyı veritabanına ekle (şifre hash'lenmeden kaydediliyor)
  $sql2 = "INSERT INTO users (username, password) VALUES (?, ?)";
  $stmt2 = $conn->prepare($sql2);
  $stmt2->bind_param("ss", $loginUser, $loginPass);
  
  if ($stmt2->execute()) {
    echo "Success";
  } else {
    echo "Hata: " . $sql2 . "<br>" . $conn->error;
  }
}

$conn->close();
?>
