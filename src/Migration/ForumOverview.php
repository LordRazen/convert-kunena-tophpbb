<?php

/**
 * ForumOverview
 * 
 * Perform this multiple times so the postcount is passed from lower forum hierarchies to the higher ones
 * 
 * forum_depth is needed to count the post and topic count from the lowest to the highest level correctly
 */

namespace Src\Migration;

use Src\Utils\Utils;

abstract class ForumOverview
{
    const JOB = 'overview';

    public static function start()
    {
        Utils::writeToLog('Start Forum Overview Migration', false, true);

        # Check Tables
        $phpbb_forums = Utils::getPhpBBTable('forums');
        $phpbb_topics = Utils::getPhpBBTable('topics');

        # Clean Forum Post Data if any
        $GLOBALS["phpbbDB"]->update(
            Utils::getPhpBBTable('forums'),
            [
                'forum_last_post_id' => '',
                'forum_last_poster_id' => '',
                'forum_last_post_subject' => '',
                'forum_last_post_time' => '',
                'forum_last_poster_name' => '',
                'forum_last_poster_colour' => '',
                'forum_posts_approved' => '',
                'forum_topics_approved' => '',
            ]
        );

        $forums = $GLOBALS["phpbbDB"]->select(
            $phpbb_forums,
            "*"
        );

        # Check every forum
        for ($i = 1; $i <= $GLOBALS["migrationConfig"]["forum_depth"]; $i++) :
            echo '<h1>Iteration '  . $i . '</h1>';
            foreach ($forums as $forum) :
                $forumId = (int) $forum["forum_id"];
                // var_dump($forumId);

                ### LATEST THREADS IN SUBFORUMS AND FORUM ITSSELF

                # Get SubForum with Latest Topic
                $subForumWithLatestTopic = $GLOBALS["phpbbDB"]->select(
                    $phpbb_forums,
                    "*",
                    [
                        "parent_id" => $forumId,
                        "ORDER" => ["forum_last_post_time" => "DESC"],
                        "LIMIT" => 1
                    ],
                );
                if (!empty($subForumWithLatestTopic)) $subForumWithLatestTopic = $subForumWithLatestTopic[0];
                // var_dump($subForumWithLatestTopic);

                # Get Latest Topic in the Forum
                $latestTopic = $GLOBALS["phpbbDB"]->select(
                    $phpbb_topics,
                    "*",
                    [
                        "forum_id" => $forumId,
                        "ORDER" => ["topic_last_post_time" => "DESC"],
                        "LIMIT" => 1
                    ],
                );
                if (!empty($latestTopic)) $latestTopic = $latestTopic[0];
                // var_dump($latestTopic);

                $forumData = [];
                $lastPostInForum = isset($latestTopic["topic_last_post_time"])
                    ? (int) $latestTopic["topic_last_post_time"] : 0;
                $lastPostInSubForum = isset($subForumWithLatestTopic["forum_last_post_time"])
                    ? (int) $subForumWithLatestTopic["forum_last_post_time"] : 0;

                if ($lastPostInForum > $lastPostInSubForum || !empty($latestTopic)) {
                    $forumData = [
                        'forum_last_post_id' => (int) $latestTopic["topic_last_post_id"],
                        'forum_last_poster_id' =>  (int) $latestTopic["topic_last_poster_id"],
                        'forum_last_post_subject' => $latestTopic["topic_last_post_subject"],
                        'forum_last_post_time' => (int) $latestTopic["topic_last_post_time"],
                        'forum_last_poster_name' => $latestTopic["topic_last_poster_name"],
                        'forum_last_poster_colour' => $latestTopic["topic_last_poster_colour"],
                    ];
                } else if ($lastPostInForum < $lastPostInSubForum) {
                    $forumData = [
                        'forum_last_post_id' => (int) $subForumWithLatestTopic["forum_last_post_id"],
                        'forum_last_poster_id' => (int) $subForumWithLatestTopic["forum_last_poster_id"],
                        'forum_last_post_subject' => $subForumWithLatestTopic["forum_last_post_subject"],
                        'forum_last_post_time' => (int) $subForumWithLatestTopic["forum_last_post_time"],
                        'forum_last_poster_name' => $subForumWithLatestTopic["forum_last_poster_name"],
                        'forum_last_poster_colour' => $subForumWithLatestTopic["forum_last_poster_colour"],
                    ];
                }

                ### TOPIC AND POST COUNT IN SUBFORUMS AND FORUM ITSSELF

                # Count all Posts in Topics of the forum
                $forumData["forum_posts_approved"] = (int) $GLOBALS["phpbbDB"]->sum(
                    $phpbb_topics,
                    "topic_posts_approved",
                    ["forum_id" => $forumId],
                );

                # Count all Topics in the forum
                $forumData["forum_topics_approved"] = (int) $GLOBALS["phpbbDB"]->count(
                    $phpbb_topics,
                    ["forum_id" => $forumId],
                );

                var_dump($forumData);

                # Apply data to forum
                $GLOBALS["phpbbDB"]->update($phpbb_forums, $forumData, ["forum_id" => $forumId]);

                echo '<hr>';
            endforeach;
        endfor;

        // $GLOBALS["migrationConfig"]["job"] = '';

        Utils::writeToLog("Forum Overview Migration finished!", false, true);
        Utils::writeToLog('=====', false, true);
    }
}
