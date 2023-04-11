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
                "id[>]" => $GLOBALS["migrationConfig"]["last_topic"],
                "LIMIT" => $GLOBALS["migrationConfig"]["migrations_at_once"]
            ]
        );

        # No more Topics
        if (empty($topics)) {
            Utils::writeToLog("Topic Migration finished!", false, true);
            $GLOBALS["migrationConfig"]["job"] = ForumOverview::JOB;
            return;
        }

        # Foreach Topic:
        foreach ($topics as $topic) :
            echo '<hr>';
            # Update last edited topic in config
            $GLOBALS["migrationConfig"]["last_topic"] = $topic["id"];

            # Hold 2 = Deleted, Hold 3 => unknown 
            if ((int) $topic["hold"] == 2) {
                // Utils::writeToLog('Topic is deleted, Hold: 2, TopicId: ' . $topic["id"]);
                continue;
            }
            if ((int) $topic["hold"] == 3) {
                // Utils::writeToLog('Topic is deleted, Hold: 3, TopicId: ' . $topic["id"]);
                continue;
            }

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
                ["thread" => (int) $topic["id"], "hold" => 0]
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
                $starterUserId = self::findUser(
                    $topic["first_post_userid"],
                    $topic["first_post_guest_name"]
                );
                // Utils::writeToLog("UserID (starter) found!", true);
            } catch (Exception) {
                $starterUserId = 1;
                Utils::writeToLog("UserID (starter) not found in phpBB. "
                    . "Topic ID: " . $topic["id"]
                    . ", Topic Title: " . $topic["subject"]
                    . ", Poster: " . $topic["first_post_guest_name"], true);
            }

            $lastPosterId = 0;
            try {
                $lastPosterId = self::findUser(
                    end($topicMessages)["userid"],
                    end($topicMessages)["name"]
                );
                // Utils::writeToLog("UserID (last) found!", true);
            } catch (Exception) {
                $lastPosterId = 1;
                Utils::writeToLog("UserID (last) not found in phpBB. "
                    . "Topic ID: " . $topic["id"]
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
                    // Utils::writeToLog("UserID found!", true);
                } catch (Exception) {
                    $newUserId = 1;
                    Utils::writeToLog("UserID not found in phpBB. "
                        . "Topic ID: " . $message["thread"]
                        . ", Message ID: " . $message["id"]
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
                    // TODO: Changed from filename_real to filename. Filename_real is probably the name with it was attached to the post while the filename is the one on the serer.
                    $filePath = $GLOBALS["migrationConfig"]["joomla_url"] . $attachement["folder"] . '/' . $attachement["filename"];
                    $fileHeader = @get_headers($filePath);
                    if (!$fileHeader || $fileHeader[0] == 'HTTP/1.1 404 Not Found') {
                        Utils::writeToLog("Attachment not found: " . $filePath, false, true);
                        continue;
                    }

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
                    Utils::writeToLog('Attachements: File downloaded and saved as ' . $physical_filename . ' / ' . $filePath, true);

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
                        Utils::writeToLog('Attachements: Error in Attachment Migration: ', false, true);
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
        $phpbb_users = $GLOBALS["migrationConfig"]["phpbb_prefix"] . 'users';

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
     * https://area51.phpbb.com/docs/dev/3.3.x/extensions/tutorial_parsing_text.html
     * https://area51.phpbb.com/docs/dev/3.3.x/extensions/tutorial_parsing_text.html
     *
     * @param  string $message
     * @return string
     */
    private static function convertMessage(string $message): string
    {
        # Check for Joomla-stored images, replace the link and copy them
        $joomlaRegex = '#\](https:\/\/minecraft-heads\.com\/images\/.*?)\[#';
        $joomlaUrl = "https://minecraft-heads.com/images/";
        $joomlaUrlReplace = "/images/";

        // var_dump($message);
        preg_match_all($joomlaRegex, $message, $matches);
        if (!empty($matches[1])) :
            foreach ($matches[1] as $match) :
                # Copy the file to images
                $fileContent = @file_get_contents($match);

                if ($fileContent === false) {
                    Utils::writeToLog("INSERTED: Image not found: " . $match);
                    continue;
                }

                $filePath = str_replace($joomlaUrl, $joomlaUrlReplace, $match);
                if (!file_exists(dirname(DIR_WORK . $filePath))) {
                    mkdir(dirname(DIR_WORK . $filePath), 0777, true);
                }
                var_dump(DIR_WORK . $filePath);
                file_put_contents(DIR_WORK . $filePath, $fileContent);
                Utils::writeToLog("INSERTED: Image found and stored: " . $match);
            endforeach;
        endif;
        $message = str_replace($joomlaUrl, $joomlaUrlReplace, $message);

        # Remove included attachment links since the ids wont be correct anymore: 
        $message = preg_replace(("#(\[attachment=[0-9]+\].*?\[\/attachment\])#"), "", $message);

        # Replace Lists
        $message = str_replace("[ul]", "[list]", $message);
        $message = str_replace("[/ul]", "[/list]", $message);
        $message = str_replace("[li]", "[*]", $message);
        $message = str_replace("[/li]", "", $message);

        # Replace colors (standard ones only)
        $message = str_replace("[color=black]", "[color=#000000]", $message);
        $message = str_replace("[color=orange]", "[color=#FFA500]", $message);
        $message = str_replace("[color=red]", "[color=#FF0000]", $message);
        $message = str_replace("[color=blue]", "[color=#0000FF]", $message);
        $message = str_replace("[color=purple]", "[color=#800080]", $message);
        $message = str_replace("[color=green]", "[color=#008000]", $message);
        $message = str_replace("[color=white]", "[color=#FFFFFF]", $message);
        $message = str_replace("[color=gray]", "[color=#808080]", $message);

        # Replace Sizes
        $message = str_replace("[size=1]", "[size=50]", $message);
        $message = str_replace("[size=2]", "[size=85]", $message);
        $message = str_replace("[size=3]", "[size=85]", $message);
        $message = str_replace("[size=4]", "[size=100]", $message);
        $message = str_replace("[size=5]", "[size=150]", $message);
        $message = str_replace("[size=6]", "[size=200]", $message);

        # Replace Emojis
        $message = str_replace("B)", "8-)", $message);
        $message = str_replace(":cheer:", ":D", $message);
        $message = str_replace(":angry:", ":x", $message);
        $message = str_replace(":unsure:", ":?", $message);
        $message = str_replace(":ohmy:", ":o", $message);
        $message = str_replace(":huh:", ":?", $message);
        $message = str_replace(":dry:", ":?", $message);
        $message = str_replace(":lol:", ":D", $message);
        $message = str_replace(":sick:", ":oops:", $message);
        $message = str_replace(":silly:", ":lol:", $message);
        $message = str_replace(":blink:", ":shock:", $message);
        $message = str_replace(":blush:", ":oops:", $message);
        $message = str_replace(":kiss:", "", $message);
        $message = str_replace(":woohoo:", ":P", $message);
        $message = str_replace(":side:", "", $message);
        $message = str_replace(":S", ":oops:", $message);
        $message = str_replace(":evil:", ":twisted:", $message);
        $message = str_replace(":whistle:", "", $message);
        $message = str_replace(":pinch:", "", $message);



        $uid = $bitfield = $options = ''; // will be modified by generate_text_for_storage
        $allow_bbcode = $allow_urls = $allow_smilies = true;
        // var_dump($message);
        generate_text_for_storage($message, $uid, $bitfield, $options, $allow_bbcode, $allow_urls, $allow_smilies);
        // var_dump($message);

        // echo '$uid:';
        // var_dump($uid);

        // echo '$bitfield:';
        // var_dump($bitfield);

        // echo '$options:';
        // var_dump($options);

        // echo '$message:';
        // var_dump($message);

        /**
         * OLD
         */
        // # URLS
        // # TODO: Turned of since there're native urls
        // // $pattern = '/\b((?:https?:\/\/|www\.)\S+)\b/';
        // // $replacement = '<URL url="$1">$1</URL>';
        // // $message = preg_replace($pattern, $replacement, $message);

        // # Fix new lines => SHOULD NOT BE NEEDED! (\r\n is visible if you edit something in the database...)
        // $message = preg_replace("/[\n\r]/", "\r\n", $message);

        // # Replace [searchplz]
        // $message = str_replace("[searchplz]", "<SEARCHPLZ>[searchplz]</SEARCHPLZ>", $message);

        // # Replace [commandblock]
        // $message = str_replace("[commandblock]", "<COMMANDBLOCK>[commandblock]</COMMANDBLOCK>", $message);

        // # Replace [commonissues]
        // $message = str_replace("[commonissues]", "<COMMONISSUES>[commonissues]</COMMONISSUES>", $message);

        // # Replace Strike Stuff
        // $message = str_replace("[strike]", "<STRIKE><s>[strike]</s>", $message);
        // $message = str_replace("[/strike]", "<e>[/strike]</e></STRIKE>", $message);

        // # Replace GC Stuff
        // $message = str_replace("[gc]", "<GC><s>[gc]</s>", $message);
        // $message = str_replace("[/gc]", "<e>[/gc]</e></GC>", $message);

        // # Replace Username Stuff
        // $message = str_replace("[username]", "<USERNAME><s>[username]</s>", $message);
        // $message = str_replace("[/username]", "<e>[/username]</e></USERNAME>", $message);


        // # Replace B Stuff
        // $message = str_replace("[b]", "<B><s>[b]</s>", $message);
        // $message = str_replace("[/b]", "<e>[/b]</e></B>", $message);

        // # Replace U Stuff
        // $message = str_replace("[i]", "<I><s>[i]</s>", $message);
        // $message = str_replace("[/i]", "<e>[/i]</e></I>", $message);

        // # Replace I Stuff
        // $message = str_replace("[u]", "<U><s>[u]</s>", $message);
        // $message = str_replace("[/u]", "<e>[/u]</e></U>", $message);

        // # Surround with <r>
        // $message = '<r>' . $message . '</r>';

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
        $phpbb_users = $GLOBALS["migrationConfig"]["phpbb_prefix"] . 'users';
        $color = $GLOBALS["phpbbDB"]->get(
            $phpbb_users,
            "user_colour",
            ["user_id" => $userId]
        );
        return $color;
    }
}
