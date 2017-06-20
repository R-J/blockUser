<?php

$PluginInfo['blockUser'] = [
    'Name' => 'Block Users',
    'Description' => 'Allows users to hide their content from other users',
    'Version' => '0.1',
    'RequiredApplications' => ['Vanilla' => '>=2.3'],
    'RequiredPlugins' => false,
    'SettingsPermission' => 'Garden.Settings.Manage',
    'SettingsUrl' => 'settings/blockuser',
    'MobileFriendly' => true,
    'HasLocale' => true,
    'Author' => 'Robin Jurinka',
    'AuthorUrl' => 'http://open.vanillaforums.com/profile/r_j',
    'License' => 'MIT'
];

include __DIR__.'/controllers/class.blockuserprofilecontroller.php';
include __DIR__.'/controllers/class.blockuserplugincontroller.php';
/**
 * Make 'blockUser.UseDropDownButton' option in settings and tell users that
 * having a prominent blocking option might look hostile...
 */
class BlockUserPlugin extends Gdn_Plugin {
    public $blockedUserInfo;

    public $blockedUserIDs;

    /**
     * Init db changes. Set sane config values if needed.
     *
     * @return void.
     */
    public function setup() {
        $this->structure();
    }

    /**
     * Add table for blocking information to db.
     *
     * @return void.
     */
    public function structure() {
        Gdn::structure()->table('BlockUser')
            ->primaryKey('BlockUserID')
            ->column('BlockingUserID', 'int(11)', false, 'index')
            ->column('BlockedUserID', 'int(11)', false)
            ->column('BlockPrivateMessages', 'tinyint(1)', 1)
            ->column('BlockNotifications', 'tinyint(1)', 1)
            ->column('BlockDiscussions', 'tinyint(1)', 1)
            ->column('BlockComments', 'tinyint(1)', 1)
            ->column('BlockActivities', 'tinyint(1)', 1)
            ->column('DisallowProfileVisits', 'tinyint(1)', 1)
            ->column('Comment', 'text', true)
            ->set();
    }

    public function assetModel_styleCss_handler($sender) {
        $sender->addCssFile('blockuser.css', 'plugins/blockUser');
    }

    public function base_BeforeDiscussionName_handler($sender, $args) {
        if (!$this->blockedUserIDs) {
            $this->blockUserModel = new BlockUserModel();
            $this->blockedUserInfo = $this->blockUserModel->getByBlockingUserID(Gdn::session()->UserID);
            $this->blockedUserIDs = array_column($this->blockedUserInfo, 'BlockedUserID');
        }

        $index = array_search(
            $args['Discussion']->InsertUserID,
            $this->blockedUserIDs
        );

        if (!$index || !$this->blockedUserInfo[$index]['BlockDiscussions']) {
            return;
        }
        $args['CssClass'] .= ' Ignored';
    }
}
