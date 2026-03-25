<?php
/**
 * Sophishticated - Seed SQL Generator
 *
 * Generates database/seed.sql with a proper bcrypt hash for the admin password.
 *
 * Usage:
 *   php generate_seed.php
 *
 * The generated seed.sql can then be imported:
 *   mysql -u root -p larreere_phish < seed.sql
 */

declare(strict_types=1);

$password = 'admin';
$hash     = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

if ($hash === false) {
    fwrite(STDERR, "Error: password_hash() failed.\n");
    exit(1);
}

$seedFile = __DIR__ . '/seed.sql';

// Read the current seed.sql template, replace the hash placeholder
$template = file_get_contents($seedFile);

if ($template === false) {
    fwrite(STDERR, "Error: could not read {$seedFile}.\n");
    exit(1);
}

// Replace any bcrypt hash (starting with $2y$12$) in the admin INSERT
$updated = preg_replace(
    '/\$2y\$12\$[A-Za-z0-9\.\/]{53}/',
    str_replace('$', '\\$', $hash),
    $template,
    1
);

if ($updated === null) {
    fwrite(STDERR, "Error: regex replacement failed.\n");
    exit(1);
}

// Unescape the dollar signs
$updated = str_replace('\\$', '$', $updated);

file_put_contents($seedFile, $updated);

echo "seed.sql generated successfully.\n";
echo "Admin password: {$password}\n";
echo "Bcrypt hash:    {$hash}\n";
