<title>Kunena 5.x to PHPBB 3.x Migration - Cleanup for next Migration Start</title>
<?php

/**
 * Run this file if you would like to reset the migration:
 * 
 * - Delete all stored avatars
 * - Truncate several phpbb tables
 */

use Medoo\Medoo;
use Src\Migration\UserMigration;
use Src\Utils\Config;
use Src\Utils\Utils;

include(dirname(__FILE__) . "/include.php");

echo '<h3>Forum Migration: Joomla 3.10 + Kunena 5.X to phpBB 3.3 - Cleanup for next Migration Start</h3>';

# Read Config
$config = Config::read();

# Try Database Connection with Medoo Framework
try {
    $phpbbDB = new Medoo([
        'type' => $config['phpbb_db_type'],
        'host' => $config['phpbb_db_host'],
        'database' => $config['phpbb_db_database'],
        'username' => $config['phpbb_db_username'],
        'password' => $config['phpbb_db_password'],
        'charset' => 'utf8'
    ]);
    Utils::writeToLog('Connected to phpBB', true, true);
} catch (PDOException $e) {
    Utils::writeToLog('Could not establish Connection to PhpBB', true, true);
    die();
}

# Reset Config
$config['job'] = UserMigration::JOB;
$config['last_user'] = 0;
$config['last_topic'] = 0;

# Save Config
Config::save($config);

# Remove existing users of type 0 from DB (does not touch the google bots and admin from a fresh installation)
// $phpbbDB->delete(Utils::getPhpBBTable('users'), ["user_type" => 0]);

# Truncate several phpbb tables (not a read truncate, but delete all records)
$phpbbDB->delete(Utils::getPhpBBTable('topics'), []);
$phpbbDB->delete(Utils::getPhpBBTable('topics_posted'), []);
$phpbbDB->delete(Utils::getPhpBBTable('posts'), []);

# Remove avatars from a potential last migration
$files = glob(DIR_AVATARS . '/*');
foreach ($files as $file) {
    if (is_file($file)) unlink($file);
}

Utils::writeToLog("Fresh migration run prepared!", true, true);
