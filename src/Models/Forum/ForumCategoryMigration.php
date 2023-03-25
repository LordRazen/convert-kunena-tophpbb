<?php

/**
 * Prepare ForumCategories
 */

namespace Src\Forum;

use Src\ForumModels\Forum;
use Src\Utils\Utils;

abstract class ForumCategoryMigration
{
    const FILE = 'jos341_kunena_categories.php';

    /**
     * Start Categories Preparations
     */
    public static function start(): bool
    {

        # Abort if no new file exists
        if (!file_exists(DIR_DATA_FORUM . self::FILE)) {
            Utils::writeToLog('No forum categories file found, abort', 1);
            return false;
        }

        # Found Tag file
        Utils::writeToLog('Found forum categories file!', 1);

        # Truncate table and rewrite all entries
        $GLOBALS["database"]->query("TRUNCATE `phpbb_forums`");

        include(DIR_DATA_FORUM . self::FILE);

        $tree = self::createTree($jos341_kunena_categories);
        self::buildTreeOutline($jos341_kunena_categories, $tree);

        foreach ($jos341_kunena_categories as $forum) :
            Forum::insertForum($forum);
        endforeach;

        // # Move to archive
        // Utils::moveToArchive(self::FILE, DIR_DATA_GUIDELINES_CSV);
        return true;
    }

    /**
     * Create Tree
     *
     * @param  array $forum
     */
    private static function createTree(array $jos341_kunena_categories): array
    {
        $tree = [];

        # Add Main Nodes
        foreach ($jos341_kunena_categories as $key => $forum) :
            if ((int) $forum["parent_id"] === 0) {
                $tree[$forum["id"]] = [];
                unset($jos341_kunena_categories[$key]);
            }
        endforeach;

        # Add First Level Sub
        foreach ($jos341_kunena_categories as $key => $forum) :
            $firstlevelID = (int) $forum["parent_id"];
            if (array_key_exists($firstlevelID, $tree)) {
                $tree[$firstlevelID][$forum["id"]] = [];
                unset($jos341_kunena_categories[$key]);
            }
        endforeach;

        # Add Second Level Sub
        foreach ($jos341_kunena_categories as $forum) :
            $parentId = (int) $forum["parent_id"];
            $topParentId = 0;
            foreach ($tree as $key => $subtree) :
                if (array_key_exists($parentId, $subtree)) {
                    $topParentId = $key;
                }
            endforeach;
            if ($topParentId === 0) {
                var_dump("ERROR WITH TOP CATEGORY!");
                die();
            }

            $tree[$topParentId][$parentId][$forum["id"]] = $forum["name"];
        endforeach;

        return $tree;
    }

    /**
     * Build Tree Outline
     *
     * @param  array $tree
     */
    private static function buildTreeOutline(array &$jos341_kunena_categories, array $tree)
    {
        var_dump($tree);
        // var_dump($jos341_kunena_categories);
        $counter = 1;
        foreach ($tree as $level1Id => $level1Branch) :
            # Set first key of branch to left_id
            self::setToMainArray($jos341_kunena_categories, $level1Id, "left_id", array_keys($level1Branch)[0]);

            # Iterate through level 2
            foreach ($level1Branch as $level2Id => $level2Branch) :

            endforeach;

        # 
        endforeach;















        // $GLOBALS['treeOutline'] = [];
        // $GLOBALS['treeOutlineCount'] = 1;

        // foreach ($tree as $branchId => $branch) :
        //     self::addtoTreeOutline($branchId);
        // // self::xxx($tree, $branch);
        // endforeach;

        // var_dump($GLOBALS['treeOutlineCount']);
    }
    /**
     * Undocumented function
     *
     * @param array $jos341_kunena_categories
     * @param  int    $forumId
     * @param  string $idType - right_id or left_id
     * @param  int    $id - of right and left id...
     */
    private static function setToMainArray(array &$jos341_kunena_categories, int $forumId, string $idType, int $id)
    {
        foreach ($jos341_kunena_categories as $key => $forum) :
            if ((int) $forum['id'] === $forumId) {
                var_dump('Update id: ' . $idType . ' = ' . $id);
                $jos341_kunena_categories[$key][$idType] = $id;
            }
        endforeach;
    }




    private static function xxx(array &$tree, array $branch)
    {
    }
    private static function addtoTreeOutline(int $id)
    {
        array_push($GLOBALS['treeOutline'], $id);
        $GLOBALS['treeOutlineCount']++;
    }
}
