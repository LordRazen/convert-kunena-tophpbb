<?php

/**
 * Topic
 */

namespace Src\ForumModels;

abstract class Topic
{
    /**
     * Insert Topic
     * 
     * @param array $topic
     */
    public static function insertTopic(array $topic): void
    {
        var_dump($topic);



        $GLOBALS["database"]->insert("phpbb_topics", [
            'topic_id' => (int) $topic["id"],
            'forum_id' => (int) $topic["category_id"],
            // 'icon_id' => $topic[""],
            // 'topic_attachment' => $topic[""],
            // 'topic_reported' => $topic[""],
            // 'topic_title' => $topic["subject"],
            // 'topic_poster' => $topic[""],
            // 'topic_time' => $topic["first_post_time"],
            // 'topic_time_limit' => $topic[""],
            // 'topic_views' => $topic[""],
            // 'topic_status' => $topic[""],
            // 'topic_type' => $topic[""],
            // 'topic_first_post_id' => $topic[""],
            // 'topic_first_poster_name' => $topic[""],
            // 'topic_first_poster_colour' => $topic[""],
            // 'topic_last_post_id' => $topic[""],
            // 'topic_last_poster_id' => $topic[""],
            // 'topic_last_poster_name' => $topic[""],
            // 'topic_last_poster_colour' => $topic[""],
            // 'topic_last_post_subject' => $topic[""],
            // 'topic_last_post_time' => $topic[""],
            // 'topic_last_view_time' => $topic[""],
            // 'topic_moved_id' => $topic[""],
            // 'topic_bumped' => $topic[""],
            // 'topic_bumper' => $topic[""],
            // 'poll_title' => $topic[""],
            // 'poll_start' => $topic[""],
            // 'poll_length' => $topic[""],
            // 'poll_max_options' => $topic[""],
            // 'poll_last_vote' => $topic[""],
            // 'poll_vote_change' => $topic[""],
            'topic_visibility' => 1,
            // 'topic_delete_time' => $topic[""],
            // 'topic_delete_reason' => $topic[""],
            // 'topic_delete_user' => $topic[""],
            // 'topic_posts_approved' => $topic[""],
            // 'topic_posts_unapproved' => $topic[""],
            // 'topic_posts_softdeleted' => $topic[""],
        ]);
    }
}
