<?php
// Hata raporlamayı etkinleştir
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// config.php dosyasını dahil et
require 'config.php';

// Veritabanı bağlantısını oluştur
$conn = new mysqli($servername, $username, $password, $dbname);

// Bağlantıyı kontrol et
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully <br><br>";

//if ($_POST['manager'] == 'getusers') {
    $sql = "SELECT username FROM users";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
      // Her satırın verilerini yazdır
      while($row = $result->fetch_assoc()) {
        echo "username: " . $row["username"]. "<br>";
      }
    } else {
      echo "0 results";
    }
//}
$conn->close();
?>
