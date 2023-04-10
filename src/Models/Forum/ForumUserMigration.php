<?php

/**
 * Prepare Users
 */

namespace Src\Models\Forum;

use Src\ForumModels\Topic;
use Src\Utils\Utils;

abstract class ForumUserMigration
{
    const FILE = 'jos341_kunena_users.php';

    /**
     * Start User Preparations
     */
    public static function start(): bool
    {

        # Abort if no new file exists
        if (!file_exists(DIR_DATA_FORUM . self::FILE)) {
            Utils::writeToLog('No forum user file found, abort', 1);
            return false;
        }

        # Found file
        Utils::writeToLog('Found forum user file!', 1);

        # Truncate table and rewrite all entries
        $GLOBALS["database"]->query("TRUNCATE `phpbb_users`");

        # $jos341_kunena_users
        include(DIR_DATA_FORUM . self::FILE);
        $counter = 1;
        foreach ($jos341_kunena_users as $user) :

            var_dump($user);
            $counter++;
            if ($counter > 5) die();
        endforeach;

        // # Move to archive
        // Utils::moveToArchive(self::FILE, DIR_DATA_GUIDELINES_CSV);
        return true;
    }
}
