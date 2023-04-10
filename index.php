<title>Kunena 5.x to PHPBB 3.x Migration</title>
<?php
include(dirname(__FILE__) . "/include.php");

use Src\Utils\MigrationConfig;
use Medoo\Medoo;
use Src\Forum\ForumMigration;
use Src\Migration\ForumOverview;
use Src\Migration\TopicMigration;
use Src\Migration\UserMigration;
use Src\Utils\Utils;

echo '<h3>Forum Migration: Joomla 3.10 + Kunena 5.X to phpBB 3.3</h3>';
echo '<p><a href="cleanup.php" target="_blank">Start Cleanup for fresh migration run!</a></p>';

# Read Config
$migrationConfig = MigrationConfig::read();

# Try Database Connection with Medoo Framework
# https://medoo.in/
try {
    $kunenaDB = new Medoo([
        'type' => $migrationConfig['kunena_db_type'],
        'host' => $migrationConfig['kunena_db_host'],
        'database' => $migrationConfig['kunena_db_database'],
        'username' => $migrationConfig['kunena_db_username'],
        'password' => $migrationConfig['kunena_db_password'],
        'charset' => 'utf8'
    ]);
    Utils::writeToLog('Connected to Joomla / Kunena', false, true);
} catch (PDOException $e) {
    Utils::writeToLog('Could not establish Connection to Joomla / Kunena', false, true);
    die();
}

try {
    $phpbbDB = new Medoo([
        'type' => $migrationConfig['phpbb_db_type'],
        'host' => $migrationConfig['phpbb_db_host'],
        'database' => $migrationConfig['phpbb_db_database'],
        'username' => $migrationConfig['phpbb_db_username'],
        'password' => $migrationConfig['phpbb_db_password'],
        'charset' => 'utf8'
    ]);
    Utils::writeToLog('Connected to phpBB', false, true);
} catch (PDOException $e) {
    Utils::writeToLog('Could not establish Connection to PhpBB', false, true);
    die();
}

# INCLUDE PHPBB
define('IN_PHPBB', true);
$phpbb_root_path = dirname(__FILE__) . $migrationConfig['phpbb_folder'];
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);

# Different Migrations: Turn on one by one so the jobs dont take to long.
switch ($migrationConfig["job"]):
    case UserMigration::JOB:
        UserMigration::start();
        break;
    case TopicMigration::JOB:
        TopicMigration::start();
        break;
    case ForumOverview::JOB:
        ForumOverview::start();
        break;
    default:
        Utils::writeToLog('Migration completed!', true, true);
endswitch;

# Save Config
MigrationConfig::save($migrationConfig);
