<?php

/**
 * Forum Migration Root
 */

namespace Src\Forum;

use Exception;
use Src\Utils\Utils;

abstract class ForumMigration
{
    const MIGRATE = 'migrate';
    const ERROR = 'error';

    public static function migrate()
    {
        try {
            ForumUserMigration::start();


            // ForumCategoryMigration::start();
            // ForumTopicMigration::start();

        } catch (Exception $e) {
            Utils::writeToLog('==== ERROR! ====', 0, true);
            self::writeErrorToLog($e);

            $GLOBALS["config"]["job"] === self::ERROR;
        }
    }

    private static function writeErrorToLog($e)
    {
        Utils::writeToLog('', 0, true);

        Utils::writeToLog('Config:', 0, true);
        foreach ($GLOBALS["config"] as $key => $configEntry) :
            Utils::writeToLog($key . ": " . $configEntry, 1, true);
        endforeach;
        Utils::writeToLog('', 0, true);

        Utils::writeToLog('Error Details:', 0, true);
        Utils::writeToLog($e->getMessage(), 1, true);
        file_put_contents(DIR_WORK . 'log.txt', $e->getTraceAsString() . PHP_EOL, FILE_APPEND);

        Utils::writeToLog('', 0, true);
        Utils::writeToLog('==== ERROR END ====', 0, true);

        mail(
            "razen.mailer@gmail.com",
            "Migration Progress - ERROR!",
            "Found an error: ",
        );
    }
}
