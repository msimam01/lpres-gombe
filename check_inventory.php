<?php
require 'vendor/autoload.php';

$db = new PDO('sqlite:C:\Users\HomePC\lpres-gombe\database\database.sqlite');

$results = $db->query("SELECT wp_id, post_type, status, classification, in_migration_scope FROM rows WHERE post_type = 'post' AND classification = 'team_member' AND in_migration_scope = 'yes'")->fetchAll(PDO::FETCH_ASSOC);
echo "Team member records with correct classification:\n";
foreach ($results as $row) {
    echo "  WP ID: " . $row['wp_id'] . ", status: " . $row['status'] . ", classification: " . $row['classification'] . ", in_migration_scope: " . $row['in_migration_scope'] . "\n";
}

$total = count($results);
echo "\nTotal eligible team members: $total\n";

// Also check what classifications exist
$all = $db->query("SELECT DISTINCT classification FROM rows WHERE post_type = 'post'")->fetchAll(PDO::FETCH_COLUMN);
echo "All classifications for posts: " . implode(', ', $all) . "\n";

// Status distribution
$byStatus = $db->query("SELECT status, COUNT(*) as cnt FROM rows WHERE post_type = 'post' GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
echo "Status distribution: \n";
foreach ($byStatus as $row) {
    echo "  " . $row['status'] . ": " . $row['cnt'] . "\n";
}