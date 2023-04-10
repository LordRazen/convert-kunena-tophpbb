<?php

namespace Src\Utils;

use Src\Migration\UserMigration;

abstract class MigrationConfig
{
    const CONFIG = "config.txt";

    /**
     * Create new config file
     */
    private static function configArray()
    {
        return [
            # Local Installation of phpbb relative to the installation of the migration
            'phpbb_folder' => '/../../phpbb/phpbb-forum/',

            # Database
            'kunena_db_type' => 'mysql',
            'kunena_db_host' => 'localhost',
            'kunena_db_database' => 'name-of-kunena-db',
            'kunena_db_username' => '',
            'kunena_db_password' => '',
            'joomla_prefix' => 'jos_',
            'joomla_url' => 'https://my-website.com/',

            # Forum
            'phpbb_db_type' => 'mysql',
            'phpbb_db_host' => 'localhost',
            'phpbb_db_database' => 'name-of-phpbb-db',
            'phpbb_db_username' => '',
            'phpbb_db_password' => '',
            'phpbb_prefix' => 'phpbb_',

            # Misc
            'match_user_kunenaId_phpbbId' => json_encode(
                ['kunena_user_id' => 'phpbb_user_id', 'kunena_user_id2' => 'phpbb_user_id2']
            ),

            # Progress
            'job' => UserMigration::JOB,
            'migrations_at_once' => 2,
            'last_user' => 0,
            'last_topic' => 0,
            'last_attachement' => 0,
            'forum_depth' => 3, # Depth of the forum from highest to lowest forum
        ];
    }

    /**
     * Read Config
     */
    public static function read()
    {

        if (!file_exists(DIR_WORK . self::CONFIG)) {
            # Create new Config
            Utils::writeToLog('No Config found, create new config', 0);
            self::save(self::configArray());
        }

        # Read Config
        $migrationConfig = include DIR_WORK . self::CONFIG;

        # Update Config
        self::updateConfig($migrationConfig);

        return $migrationConfig;
    }

    /**
     * Update Config
     */
    private static function updateConfig(array &$migrationConfig)
    {
        $defaultConfig = self::configArray();
        foreach ($defaultConfig as $key => $defaultConfigEntry) :
            if (!array_key_exists($key, $migrationConfig)) {
                $migrationConfig[$key] = $defaultConfigEntry;
                Utils::writeToLog('Added to Config: ' . $key . ' => ' . $defaultConfigEntry, 1);
            }
        endforeach;
    }

    /**
     * Save config file
     */
    public static function save($migrationConfig)
    {
        $migrationConfig = var_export($migrationConfig, true);
        file_put_contents(DIR_WORK . self::CONFIG, "<?php return $migrationConfig ;");
    }
}
