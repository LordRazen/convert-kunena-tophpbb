<?php

/**
 * UserMigration
 * 
 * Joomla Kunena ID is stored in phpbb field "user_lastpost_time" 
 * since the IDs of the users are changed 
 * and need to be stored somewhere temporary.
 */

namespace Src\Migration;

use PDOException;
use Src\Utils\Utils;

abstract class UserMigration
{
    const JOB = 'user';

    public static function start()
    {
        Utils::writeToLog('Start User Migration', false, true);

        # Check Tables
        $joomla_users = Utils::getKunenaTable('users');
        $kunena_users = Utils::getKunenaTable('kunena_users');
        $phpbb_users = Utils::getPhpBBTable('users');
        $phpbb_config = Utils::getPhpBBTable('config');

        # Get Avatar Salt for phpbb
        try {
            if (!$phpbb_avatar_salt = $GLOBALS["phpbbDB"]->get(
                $phpbb_config,
                "config_value",
                ["config_name" => "avatar_salt"]
            )) throw new PDOException();
            Utils::writeToLog("Fetched Phpbb Avatar Salt from Config Table");
        } catch (PDOException) {
            Utils::writeToLog("Count not get PhpBB Avatar Salt from Config Table!");
            die();
        }

        # Get Joomla / Kunena Users

        # Add Users to PHPBB
        $users = $GLOBALS["kunenaDB"]->select(
            $joomla_users,
            ['[>]' . $kunena_users => ['id' => 'userid']],
            [
                # Joomla Data
                $joomla_users . '.id',
                $joomla_users . '.username',
                $joomla_users . '.email',
                $joomla_users . '.registerDate',
                $joomla_users . '.lastvisitDate',

                # Kunena Data
                $kunena_users . '.posts',
                $kunena_users . '.avatar',
                $kunena_users . '.birthdate',
            ],
            [
                "id[>]" => $GLOBALS["migrationConfig"]["last_user"],
                "LIMIT" => $GLOBALS["migrationConfig"]["migrations_at_once"]
            ]
        );

        # No more Users
        if (empty($users)) {
            Utils::writeToLog("User Migration finished!", false, true);
            $GLOBALS["migrationConfig"]["job"] = TopicMigration::JOB;
            return;
        }

        # Match Users from certain kunena IDs to phpbb IDs
        $matchUsers = json_decode($GLOBALS["migrationConfig"]["match_user_kunenaId_phpbbId"], true);

        foreach ($users as $user) :
            # Update last edited user in config
            $GLOBALS["migrationConfig"]["last_user"] = $user["id"];

            # Dates
            $registration = strtotime($user["registerDate"]);
            $lastVisit = strtotime($user["lastvisitDate"]);
            $birthday = strtotime($user["birthdate"]);

            # Avatar
            if (!empty($user["avatar"])) {
                # The avatars should be in this folder of your Joomla installations. Adjust if needed
                $avatarPath = $GLOBALS["migrationConfig"]["joomla_url"] . 'media/kunena/avatars/' . $user["avatar"];
                $avatarHeader = @get_headers($avatarPath);
                if (!$avatarHeader || $avatarHeader[0] == 'HTTP/1.1 404 Not Found') {
                    Utils::writeToLog("User should have an avatar but file was not found: " . $user["username"], true, true);
                } else {
                    # Avatar found
                    $avatarFormat = substr($user["avatar"], strrpos($user["avatar"], '.') + 1);
                    $avatarFilenameDB = $user["id"] . '_' . time() . '.' . $avatarFormat;
                    $avatarFilenameLocal = $phpbb_avatar_salt . '_' . $user["id"] . '.' . $avatarFormat;
                    copy($avatarPath, DIR_AVATARS . $avatarFilenameLocal);
                    list($width, $height) = getimagesize(DIR_AVATARS . $avatarFilenameLocal);
                }
            } else {
                $avatarFilenameDB = ''; # Reset Avatar Filename
            }

            # Build UserData
            $userData = [
                'user_regdate' => $registration,
                'username' => $user["username"],
                'username_clean' => Utils::utf8CleanString($user["username"]),
                # TODO: Store the ID in the user_lastpost_time for later change!
                'user_lastpost_time' => $user["id"],
                'user_email' => $user["email"],
                'user_birthday' => ($birthday > 0) ? $birthday : '',
                'user_lastvisit' => max($lastVisit, $registration),
                'user_posts' => $user["posts"],
                'user_form_salt' => Utils::generateRandomString(),
                'user_avatar' => (isset($avatarFilenameDB)) ? $avatarFilenameDB : '',
                'user_avatar_type' => 'avatar.driver.upload',
                'user_avatar_width' => (isset($width)) ? $width : '',
                'user_avatar_height' => (isset($height)) ? $height : '',
                'user_new' => 0,
                'user_allow_massemail' => 0,
            ];

            if (array_key_exists((int) $user["id"], $matchUsers)) {
                # Match UserData to existing PHPBB User
                $matchID = $matchUsers[$user["id"]];

                Utils::writeToLog("Match User Data from Joomla Kunena User ID: " . $user["id"] . "/" . $user["username"] . " to phpBB ID " . $matchID);

                # Update some additional Data
                unset($userData["username"]);
                unset($userData["username_clean"]);
                unset($userData["user_email"]);
                unset($userData["user_form_salt"]);

                $userData["user_id"] = $matchID;
                if (!empty($userData["user_avatar"])) {
                    $userData["user_avatar"] = str_replace(
                        $user["id"] . '_',
                        $matchID . '_',
                        $userData["user_avatar"]
                    );
                }
                try {
                    $GLOBALS["phpbbDB"]->update(
                        $phpbb_users,
                        $userData,
                        ["user_id" => $matchID]
                    );
                } catch (PDOException $e) {
                    Utils::writeToLog('Error in User Migration: ', false, true);
                    Utils::writeToLog($e->getMessage(), false, true);
                }
            } else {
                # Insert new User
                try {
                    $GLOBALS["phpbbDB"]->insert($phpbb_users, $userData);
                } catch (PDOException $e) {
                    Utils::writeToLog('Error in User Migration: ', false, true);
                    Utils::writeToLog($e->getMessage(), false, true);
                }
            }
        endforeach;

        Utils::writeToLog('End User Migration', false, true);
        Utils::writeToLog('=====', false, true);
    }
}
