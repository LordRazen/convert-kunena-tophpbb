<?php

/**
 * Prepare Topics
 */

namespace Src\Models\Forum;

use Src\Models\ForumModels\Topic;
use Src\Utils\Utils;

abstract class ForumTopicMigration
{
    const FILE = 'jos341_kunena_topics.php';

    /**
     * Start Topic Preparations
     */
    public static function start(): bool
    {

        # Abort if no new file exists
        if (!file_exists(DIR_DATA_FORUM . self::FILE)) {
            Utils::writeToLog('No forum topic file found, abort', 1);
            return false;
        }

        # Found Tag file
        Utils::writeToLog('Found forum topic file!', 1);

        # Truncate table and rewrite all entries
        $GLOBALS["database"]->query("TRUNCATE `phpbb_topics`");

        # $jos341_kunena_topics
        include(DIR_DATA_FORUM . self::FILE);
        $counter = 1;
        foreach ($jos341_kunena_topics as $topic) :
            Topic::insertTopic($topic);
            var_dump($topic);
            $counter++;
            if ($counter > 5) die();
        endforeach;

        // # Move to archive
        // Utils::moveToArchive(self::FILE, DIR_DATA_GUIDELINES_CSV);
        return true;
    }
}
