<?php

/**
 * TopicMigration
 */

namespace Src\Migration;

use Exception;
use PDOException;
use Src\Utils\Utils;

abstract class TopicMigration
{
    const JOB = 'topic';

    public static function start()
    {
        Utils::writeToLog('Start Topic Migration', false, true);

        # Check Tables
        $kunena_topics = Utils::getKunenaTable('kunena_topics');
        $kunena_messages = Utils::getKunenaTable('kunena_messages');
        $kunena_messages_text = Utils::getKunenaTable('kunena_messages_text');
        $kunena_attachments = Utils::getKunenaTable('kunena_attachments');

        $phpbb_users = Utils::getPhpBBTable('users');
        $phpbb_topics = Utils::getPhpBBTable('topics');
        $phpbb_topics_posted = Utils::getPhpBBTable('topics_posted');
        $phpbb_posts = Utils::getPhpBBTable('posts');
        $phpbb_attachments = Utils::getPhpBBTable('attachments');

        # Get Topics
        $topics = $GLOBALS["kunenaDB"]->select(
            $kunena_topics,
            '*',
            [
                "id[>]" => $GLOBALS["config"]["last_topic"],
                "LIMIT" => $GLOBALS["config"]["migrations_at_once"]
            ]
        );

        # No more Topics
        if (empty($topics)) {
            Utils::writeToLog("Topic Migration finished!", false, true);
            $GLOBALS["config"]["job"] = ForumOverview::JOB;
            return;
        }

        # Foreach Topic:
        foreach ($topics as $topic) :
            # Update last edited topic in config
            $GLOBALS["config"]["last_topic"] = $topic["id"];

            # Hold 2 = Deleted, Hold 3 => unknown TODO
            if ((int) $topic["hold"] == 2) continue;


            # Get Messages of the thread
            # TODO: Aware of combined threads! 1151, 1152
            $topicMessages = $GLOBALS["kunenaDB"]->select(
                $kunena_messages,
                ['[>]' . $kunena_messages_text => ['id' => 'mesid']],
                [
                    # Kunena Message
                    $kunena_messages . '.id',
                    $kunena_messages . '.parent',
                    $kunena_messages . '.thread',
                    $kunena_messages . '.catid',
                    $kunena_messages . '.name',
                    $kunena_messages . '.userid',
                    $kunena_messages . '.email',
                    $kunena_messages . '.subject',
                    $kunena_messages . '.time',
                    $kunena_messages . '.locked',
                    $kunena_messages . '.hits',

                    # Kunena Message Text
                    $kunena_messages_text . '.mesid',
                    $kunena_messages_text . '.message',
                ],
                ["thread" => (int) $topic["id"]]
            );
            // var_dump($topicMessages);

            # Found topic without messages. Do not migrate and continue with next
            if (empty($topicMessages)) {
                // Utils::writeToLog('Found thread without posts. Posts might have been moved to different topic. Please verify in Kunena! ID: ' . $topic["id"] . ", title: " . $topic["subject"]);
                // var_dump($topic);
                continue;
            }

            # Get Thread Starter User (user_lastpost_time stored the old id before the migration)
            // var_dump($topic);
            $starterUserId = 0;
            try {
                $starterUserId = self::findUser($topic["first_post_userid"], $topic["first_post_guest_name"]);
            } catch (Exception) {
                $starterUserId = 1;
                Utils::writeToLog("Set Starter UserID to 1 / Anonymous since it was not found in phpBB. "
                    . "User might have been removed in Kunena! Topic ID: " . $topic["id"]
                    . ", Topic Title: " . $topic["subject"]
                    . ", Poster: " . $topic["last_post_guest_name"], true);
            }

            $lastPosterId = 0;
            try {
                $lastPosterId = self::findUser(
                    end($topicMessages)["userid"],
                    end($topicMessages)["name"]
                );
            } catch (Exception) {
                $lastPosterId = 1;
                Utils::writeToLog("Set Last UserID to 1 / Anonymous since it was not found in phpBB. "
                    . "User might have been removed in Kunena! Topic ID: " . $topic["id"]
                    . ", Topic Title: " . $topic["subject"]
                    . ", Poster: " . $topic["last_post_guest_name"], true);
            }
            $firstPosterColor = self::getUserColorFromId($starterUserId);
            $lastPosterColor = self::getUserColorFromId($lastPosterId);


            # Build Data
            $topicData = [
                'topic_id' => $topic["id"],
                'forum_id' => $topic["category_id"],
                'topic_title' => $topic["subject"],
                'topic_poster' => $starterUserId,
                'topic_time' => $topic["first_post_time"],
                'topic_views' => $topic["hits"],
                // 'topic_status' => $topic[""],
                'topic_first_post_id' => $topic["first_post_id"],
                'topic_first_poster_name' => $topic["first_post_guest_name"],
                'topic_last_post_id' => $topic["last_post_id"],
                'topic_last_poster_id' => $lastPosterId,
                'topic_last_poster_name' => $topic["last_post_guest_name"],
                'topic_last_post_time' => $topic["last_post_time"],
                'topic_posts_approved' => count($topicMessages),
                'topic_visibility' => 1,
                'topic_first_poster_colour' => $firstPosterColor,
                'topic_last_poster_colour' => $lastPosterColor,
                'topic_last_post_subject' => count($topicMessages) === 1
                    ? $topic["subject"] : "Re: " . $topic["subject"],
            ];
            $topicPosterData = [
                'user_id' => $starterUserId,
                'topic_id' => $topic["id"],
                'topic_posted' => 1,
            ];

            # Insert topic
            try {
                $GLOBALS["phpbbDB"]->insert($phpbb_topics, $topicData);
                $GLOBALS["phpbbDB"]->insert($phpbb_topics_posted, $topicPosterData);
            } catch (PDOException $e) {
                switch ($e->errorInfo[1]):
                    case 1062:
                        # Duplicate Entry. Potential error while migration if entries are tried to be resubmitted. 
                        # Can be more or less ignored
                        // Utils::writeToLog('Error in Topic Migration: Duplicate Topic');
                        continue 2;
                        break;
                    default:
                        var_dump($topicData);
                        var_dump($e);
                        Utils::writeToLog('Error in Topic Migration: ', false, true);
                        Utils::writeToLog($e->getMessage());
                endswitch;
                die();
            }

            # Attachment Count for Thread
            $topicAttachments = 0;

            # Foreach Message:
            foreach ($topicMessages as $message) :
                # Get (new) User ID (after Migration to phpbb)
                // var_dump($message);
                $newUserId = 0;
                try {
                    // var_dump($message);
                    $newUserId = self::findUser($message["userid"], $message["name"]);
                } catch (Exception) {
                    $newUserId = 1;
                    Utils::writeToLog("Set UserID to 1 / Anonymous since it was not found within the database. "
                        . "Check in Kunena! Message ID: " . $message["id"]
                        . ", TopicID: " . $message["thread"]
                        . ", Poster: " . $message["name"], true);
                }

                # Check if there're attachments
                $postattachments = 0;
                $attachments = $GLOBALS["kunenaDB"]->select(
                    $kunena_attachments,
                    '*',
                    ['mesid' => $message["id"]]
                );

                foreach ($attachments as $attachement) :
                    $filePath = $GLOBALS["config"]["joomla_url"] . $attachement["folder"] . '/' . $attachement["filename_real"];
                    $fileHeader = @get_headers($filePath);
                    if (!$fileHeader || $fileHeader[0] == 'HTTP/1.1 404 Not Found') {
                        Utils::writeToLog("Attachment not found: " . $filePath, false, true);
                    } else {
                        # Attachment found
                        $physical_filename = $newUserId . '_' . md5(file_get_contents($filePath));
                        copy($filePath, DIR_ATTACHMENTS . $physical_filename);
                        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

                        # Get the file mimetype
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $file_mimetype = finfo_file($finfo, DIR_ATTACHMENTS . $physical_filename);
                        finfo_close($finfo);

                        # Get the file size
                        $fileSize = filesize(DIR_ATTACHMENTS . $physical_filename);

                        unlink(DIR_ATTACHMENTS . $physical_filename);

                        # Read the file in binary mode
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $filePath);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $file_content = curl_exec($ch);
                        if ($file_content === false) {
                            die('Error downloading file');
                        }
                        if (file_put_contents(DIR_ATTACHMENTS . $physical_filename, $file_content) === false) {
                            die('Error creating local file');
                        }
                        echo 'File downloaded and saved as ';
                    }

                    $attachmentData = [
                        'attach_id' => $attachement["id"],
                        'post_msg_id' => $message["id"],
                        'topic_id' => $topic["id"],
                        'is_orphan' => 0,
                        'physical_filename' => $physical_filename,
                        'real_filename' => $attachement["filename_real"],
                        'extension' => $extension,
                        'mimetype' => $file_mimetype,
                        'filesize' => $fileSize,
                        'filetime' => $message["time"],
                        'thumbnail' => '',
                    ];
                    // var_dump($attachmentData);

                    # Insert attachment
                    try {
                        $GLOBALS["phpbbDB"]->insert($phpbb_attachments, $attachmentData);
                        $postattachments++;
                        $topicAttachments++;
                    } catch (PDOException $e) {
                        Utils::writeToLog('Error in Attachment Migration: ', false, true);
                        Utils::writeToLog($e->getMessage(), false, true);
                        var_dump($e->errorInfo);
                        var_dump($attachmentData);
                    }
                endforeach;

                $postData = [
                    'post_id' => $message["id"],
                    'topic_id' => $message["thread"],
                    'forum_id' => $message["catid"],
                    'poster_id' => $newUserId,
                    'post_time' => $message["time"],
                    'post_username' => $message["name"],
                    'post_subject' => $message["subject"],
                    'post_text' => self::convertMessage($message["message"]),
                    // 'post_attachment' => $message[""],
                    // 'post_postcount' => $message[""],
                    'post_visibility' => 1,
                    // 'post_delete_time' => $message[""],
                    'post_attachment' => $postattachments,
                ];

                # Insert posting
                try {
                    $GLOBALS["phpbbDB"]->insert($phpbb_posts, $postData);
                } catch (PDOException $e) {
                    Utils::writeToLog('Error in Topic Post Migration: ', false, true);
                    Utils::writeToLog($e->getMessage(), false, true);
                    var_dump($e->errorInfo);
                    var_dump($message);
                    var_dump($postData);
                }
            endforeach;

            # Update topic attachement count
            if ($topicAttachments > 0) {
                $GLOBALS["phpbbDB"]->update(
                    $phpbb_topics,
                    ['topic_attachment' => $topicAttachments,],
                    ['topic_id' => $topic["id"]]
                );
            }
            echo '<hr>';
        endforeach;

        Utils::writeToLog('End Topic Migration', false, true);
        Utils::writeToLog('=====', false, true);
    }

    /**
     * Find User by ID and by name if id is not found. 
     * Return 1 if user could not be found (1 = Anonymous in phpbb)
     *
     * @param  int    $id
     * @param  string $name
     * @return int
     * @throws Exception
     */
    private static function findUser(int $id, string $name = ''): int
    {
        $phpbb_users = $GLOBALS["config"]["phpbb_prefix"] . 'users';

        if ($id !== 0) {
            // Utils::writeToLog('SEARCH ID: ' . $id);
            # Search for the old ID stored in "user_lastpost_time"
            $userid = (int) $GLOBALS["phpbbDB"]->get(
                $phpbb_users,
                "user_id",
                ["user_lastpost_time" => $id]
            );
            // var_dump($userid);

            if (is_int($userid) && $userid > 0) return $userid;
        }

        // Utils::writeToLog('SEARCH NAME: ' . $name);
        $userid = (int) $GLOBALS["phpbbDB"]->get(
            $phpbb_users,
            "user_id",
            ["username" => $name]
        );
        // var_dump($userid);

        if (is_int($userid) && $userid > 0) return $userid;

        throw new Exception('User not found!');
    }

    /**
     * Convert Message
     *
     * @param  string $message
     * @return string
     */
    private static function convertMessage(string $message): string
    {
        # URLS
        # TODO: Turned of since there're native urls
        // $pattern = '/\b((?:https?:\/\/|www\.)\S+)\b/';
        // $replacement = '<URL url="$1">$1</URL>';
        // $message = preg_replace($pattern, $replacement, $message);

        # Surround with <t>
        $message = '<t>' . $message . '</t>';

        // var_dump($message);
        return $message;
    }

    /**
     * Get User Color
     *
     * @param  int    $userId
     * @return string
     */
    private static function getUserColorFromId(int $userId): string
    {
        $phpbb_users = $GLOBALS["config"]["phpbb_prefix"] . 'users';
        $color = $GLOBALS["phpbbDB"]->get(
            $phpbb_users,
            "user_colour",
            ["user_id" => $userId]
        );
        return $color;
    }
}
