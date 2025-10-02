<?php
// ==========================
// FILE: /sitemap.php - XML Sitemap Generator
// ==========================
header('Content-Type: application/xml; charset=utf-8');

require __DIR__.'/lib/db.php';

$base_url = (isset($_SERVER['HTTPS'])?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost');

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

// Homepage
echo '<url>';
echo '<loc>'.$base_url.'</loc>';
echo '<changefreq>daily</changefreq>';
echo '<priority>1.0</priority>';
echo '<lastmod>'.date('Y-m-d').'</lastmod>';
echo '</url>';

// Published content pages
$pages = q($db, "SELECT slug, updated_at FROM content_pages WHERE status='published' ORDER BY updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);

foreach($pages as $page) {
    echo '<url>';
    echo '<loc>'.$base_url.'/content/'.htmlspecialchars($page['slug']).'</loc>';
    echo '<changefreq>weekly</changefreq>';
    echo '<priority>0.8</priority>';
    echo '<lastmod>'.date('Y-m-d', strtotime($page['updated_at'])).'</lastmod>';
    echo '</url>';
}

echo '</urlset>';
?>