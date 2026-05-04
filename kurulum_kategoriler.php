<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$categories = [
    ['name' => 'Araba', 'slug' => 'araba', 'description' => 'İkinci el ve sıfır arabalar'],
    ['name' => 'Ev', 'slug' => 'ev', 'description' => 'Kiralık ve satılık evler'],
    ['name' => 'Kıyafet', 'slug' => 'kiyafet', 'description' => 'Kadın, erkek ve çocuk giyim'],
    ['name' => 'Oto Parçası', 'slug' => 'oto-parcasi', 'description' => 'Araçlar için yedek parçalar'],
    ['name' => 'Motor Yağı', 'slug' => 'motor-yagi', 'description' => 'Her türlü motor yağı seçenekleri']
];

echo "Kategoriler ekleniyor...<br>\n";

foreach ($categories as $i => $cat) {
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO categories (name, slug, description, sort_order, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$cat['name'], $cat['slug'], $cat['description'], $i]);
        echo "Eklendi: " . $cat['name'] . "<br>\n";
    } catch (PDOException $e) {
        echo "Hata (" . $cat['name'] . "): " . $e->getMessage() . "<br>\n";
    }
}

echo "İşlem tamamlandı.\n";
