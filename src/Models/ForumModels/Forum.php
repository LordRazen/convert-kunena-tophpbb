<?php

/**
 * Forum
 */

namespace Src\ForumModels;

abstract class Forum
{
    /**
     * Insert Forum
     * 
     * @param array $forum
     */
    public static function insertForum(array $forum): void
    {
        // if ((int) $forum['parent_id'] !== 0) return;
        var_dump($forum);



        // $GLOBALS["database"]->insert("phpbb_forums", [
        //     'forum_id' => (int) $forum["id"],
        //     'parent_id' => (int) $forum["parent_id"],
        //     'left_id' => isset($forum["left_id"]) ?  $forum["left_id"] : 0,
        //     'right_id' => isset($forum["right_id"]) ? $forum["right_id"] : 0,
        //     'forum_name' => $forum['name'],
        //     'forum_desc' => $forum['description'],
        //     // 'category_id' => (int) $forum['catid'],
        //     // 'description' => $forum['description'],
        //     // 'entity_amount' => $forum['entity_amount'],
        //     // "hits" => $forum["hits"],
        //     // 'exclusive' => (int) $forum['exclusive'],
        //     // 'price' => (int) $forum['price'],
        //     // 'purchases' => (int) $forum['purchases'],
        //     // 'image' => self::updateImagePath($forum['image']),
        //     // 'image_preview' => self::updateImagePath($forum['preview_image']),
        //     // 'published' => (int) $forum['published'],
        //     // 'created_at' => $forum['created'],
        //     // 'updated_at' => $forum['modified'],
        //     // 'published_at' => $forum['publish_up'],
        //     // 'datapacks' => $newDatapackVersionString,
        // ]);
    }
}
