<?php

namespace Src\Utils;

use PDOException;

class Utils
{
    const LOG = 'log.txt';

    /**
     * Write to Log
     * 
     * @param   string  $message
     * @param   bool    $writeToLog
     * @param   bool    $important
     */
    public static function writeToLog(string $message, bool $writeToLog = false, bool $important = false): void
    {
        echo ($important)
            ? '=> ' . $message . '<br>'
            : '- ' . $message . '<br>';

        # Abort if message should not go to log
        if (!$writeToLog) return;

        $date = date("Y-m-d H:i:s");
        $logMessage = $date . ' - ';
        $logMessage .= $message . PHP_EOL;
        file_put_contents(DIR_WORK . SELF::LOG, $logMessage, FILE_APPEND);
    }

    /**
     * Build Kunena Table name and ensure it exists. Return the name
     *
     * @param  string $tablename
     * @return string
     */
    public static function getKunenaTable(string $tablename): string
    {
        $fullname = $GLOBALS["config"]["joomla_prefix"] . $tablename;
        try {
            $GLOBALS["kunenaDB"]->has($fullname, []);
            Utils::writeToLog("Table found: " . $fullname);
        } catch (PDOException) {
            Utils::writeToLog("Table not found, abort: " . $fullname);
            die();
        }
        return $fullname;
    }

    /**
     * Build Kunena Table name and ensure it exists. Return the name
     *
     * @param  string $tablename
     * @return string
     */
    public static function getPhpBBTable(string $tablename): string
    {
        $fullname = $GLOBALS["config"]["phpbb_prefix"] . $tablename;
        try {
            $GLOBALS["phpbbDB"]->has($fullname, []);
            Utils::writeToLog("Table found: " . $fullname);
        } catch (PDOException) {
            Utils::writeToLog("Table not found, abort: " . $fullname);
            die();
        }
        return $fullname;
    }

    /**
     * Clean String
     *
     * @param  string $string
     * @return string
     */
    public static function utf8CleanString(string $string): string
    {
        $string = mb_convert_encoding($string, 'UTF-8', mb_detect_encoding($string));
        $string = preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $string);
        return $string;
    }

    /**
     * Generate Random String
     *
     * @param  int $length
     */
    public static function generateRandomString($length = 16)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
