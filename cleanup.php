<title>Kunena 5.x to PHPBB 3.x Migration - Cleanup for next Migration Start</title>
<?php

/**
 * Run this file if you would like to reset the migration:
 * 
 * - Delete all stored avatars
 * - Truncate several phpbb tables
 */

include(dirname(__FILE__) . "/include.php");

use Medoo\Medoo;
use Src\Migration\TopicMigration;
use Src\Migration\UserMigration;
use Src\Models\ForumModels\Forum;
use Src\Utils\MigrationConfig;
use Src\Utils\Utils;


echo '<h3>Forum Migration: Joomla 3.10 + Kunena 5.X to phpBB 3.3 - Cleanup for next Migration Start</h3>';

# Read Config
$migrationConfig = MigrationConfig::read();

# Try Database Connection with Medoo Framework
try {
    $phpbbDB = new Medoo([
        'type' => $migrationConfig['phpbb_db_type'],
        'host' => $migrationConfig['phpbb_db_host'],
        'database' => $migrationConfig['phpbb_db_database'],
        'username' => $migrationConfig['phpbb_db_username'],
        'password' => $migrationConfig['phpbb_db_password'],
        'charset' => 'utf8'
    ]);
    Utils::writeToLog('Connected to phpBB', true, true);
} catch (PDOException $e) {
    Utils::writeToLog('Could not establish Connection to PhpBB', true, true);
    die();
}

# Reset Config
$migrationConfig['job'] = TopicMigration::JOB; // TODO: UserMigration::JOB
$migrationConfig['last_user'] = 0;
$migrationConfig['last_topic'] = 0;

# Save Config
MigrationConfig::save($migrationConfig);

# Remove existing users of type 0 from DB (does not touch the google bots and admin from a fresh installation)
// $phpbbDB->delete(Utils::getPhpBBTable('users'), ["user_type" => 0]); // TODO: ENABLE

# Truncate several phpbb tables
$GLOBALS["phpbbDB"]->query("TRUNCATE `" . Utils::getPhpBBTable('topics') . "`");
$GLOBALS["phpbbDB"]->query("TRUNCATE `" . Utils::getPhpBBTable('topics_posted') . "`");
$GLOBALS["phpbbDB"]->query("TRUNCATE `" . Utils::getPhpBBTable('posts') . "`");
$GLOBALS["phpbbDB"]->query("TRUNCATE `" . Utils::getPhpBBTable('attachments') . "`");

# Remove avatars and attachments from a potential last migration
$files = glob(DIR_AVATARS . '/*');
foreach ($files as $file) {
    if (is_file($file)) unlink($file);
}
$attachments = glob(DIR_ATTACHMENTS . '/*');
foreach ($attachments as $file) {
    if (is_file($file)) unlink($file);
}

# Remove Log
if (is_file(DIR_WORK . Utils::LOG)) unlink(DIR_WORK . Utils::LOG);

# Remove Last Post / Thread info from Forums
Forum::removeLastPostInfo();

Utils::writeToLog("Fresh migration run prepared!", true, true);
