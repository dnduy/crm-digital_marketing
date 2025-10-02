<?php
// ==========================
// FILE: /robots.php - Dynamic Robots.txt
// ==========================
header('Content-Type: text/plain');

require __DIR__.'/lib/db.php';

$settings = q($db, "SELECT robots_txt FROM seo_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$base_url = (isset($_SERVER['HTTPS'])?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost');

if($settings && !empty($settings['robots_txt'])) {
    echo $settings['robots_txt'];
} else {
    // Default robots.txt
    echo "User-agent: *\n";
    echo "Disallow: /admin/\n";
    echo "Disallow: /?action=login\n";
    echo "Disallow: /?action=api\n";
    echo "\n";
    echo "Sitemap: " . $base_url . "/sitemap.xml\n";
}
?>