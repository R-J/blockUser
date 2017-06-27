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
/**
 * todo:
 * UX: (Live-) reload
 * Bug: workaround for AddPeopleModule... (JS? Or module override?)
 * 
 * Make 'blockUser.UseDropDownButton' option in settings and tell users that
 * having a prominent blocking option might look hostile...
 */
class BlockUserPlugin extends Gdn_Plugin {
    /** @var mixed Info about users blocked by session user */
    public $blockedUserInfo = null;
    /** @var mixed The IDs of all users that are blocked by session user */
    public $blockedUserIDs = null;
    /** @var mixed Info about users blocking session user */
    public $blockingUserInfo = null;
    /** @var mixed The IDs of all users who are blocking the session user */
    public $blockingUserIDs = null;

    /**
     * Init db changes. Set sane config values if needed.
     *
     * @return void.
     */
    public function setup() {
        touchConfig('blockUser.ForceAnnouncements', true);
        touchConfig('blockUser.ForceStaffMessages', true);
        saveToConfig('blockUser.ForceStaffMessages', false);
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
            ->column('DisallowWallPosts', 'tinyint(1)', 1)
            ->column('Comment', 'text', true)
            ->set();
    }

    /**
     * Simple settings page.
     *
     * Setting "ForceAnnouncements" will make announced discussions always
     * visible.
     * "ForceStaffMessages" will allow mods and admins to write messages even
     * to those users who are ignoring them.
     *
     * @param SettingsController $sender Instance of the calling class.
     * @param Mixed $args Instance of the calling class.
     *
     * @return void.
     */
    public function settingsController_blockUser_create($sender, $args) {
        $sender->permission('Garden.Settings.Manage');

        $sender->addSideMenu('dashboard/settings/plugins');
        $sender->setData('Title', t('Block User Settings'));

        $configurationModule = new ConfigurationModule($sender);
        $configurationModule->initialize(
            [
                'blockUser.ForceAnnouncements' => [
                    'Control' => 'CheckBox',
                    'Description' => 'Announcements cannot be ignored',
                    'Default' => true
                ],
                'blockUser.ForceStaffMessages' => [
                    'Control' => 'CheckBox',
                    'Description' => 'Allow staff to write conversation messages even if they are ignored',
                    'Default' => true
                ]
            ]
        );
        $configurationModule->renderAll();
    }

    /**
     * Add css file.
     *
     * "Ignoring" is most of the time achieved by hiding content from
     * being displayed.
     *
     * @param AssetModel $sender Instance of the calling class.
     *
     * @return void.
     */
    public function assetModel_styleCss_handler($sender) {
        $sender->addCssFile('blockuser.css', 'plugins/blockUser');
    }

    /**
     * "Ignore" a discussion by adding a css class.
     *
     * @param DiscussionController $sender Instance of the calling class.
     * @param mixed $args Instance of the calling class.
     *
     * @return void.
     */
    public function discussionController_beforeDiscussionDisplay_handler($sender, $args) {
        $this->base_beforeDiscussionName_handler($sender, $args);
    }

    /**
     * "Ignore" discussions by adding a css class.
     *
     * @param BaseController $sender Instance of the calling class.
     * @param Mixed $args Instance of the calling class.
     *
     * @return void.
     */
    public function base_beforeDiscussionName_handler($sender, $args) {
        // Stop if this is an announcement and ignoring announcements have been blocked.
        if ($args['Discussion']->Announce && c('blockUser.ForceAnnouncements', true)) {
            return;
        }
        if (!$this->isBlocking($args['Discussion']->InsertUserID, 'Discussions')) {
            return;
        }
        $args['CssClass'] .= ' BlockedContent';
    }

    /**
     * "Ignore" comments by adding a css class.
     *
     * @param BaseController $sender Instance of the calling class.
     * @param Mixed $args Instance of the calling class.
     *
     * @return void.
     */
    public function base_beforeCommentDisplay_handler($sender, $args) {
        if (!$this->isBlocking($args['Comment']->InsertUserID, 'Comments')) {
            return;
        }
        $args['CssClass'] .= ' BlockedContent';
    }

    /**
     * "Ignore" activity entries by adding a css class.
     *
     * @param BaseController $sender Instance of the calling class.
     * @param Mixed $args Instance of the calling class.
     *
     * @return void.
     */
    public function base_beforeActivity_handler($sender, $args) {
        if (!$this->isBlocking($args['Activity']->InsertUserID, 'Activities')) {
            return;
        }
        $args['CssClass'] .= ' BlockedContent';
    }

    /**
     * Block activity comments.
     *
     * Since there is a lack of an event which would allow changing the
     * CssClass, an activity comment must be removed. This is slightly
     * inconsisten with how the other posts are hidden.
     *
     * @param ActivityController $sender Instance of the calling class.
     * @param mixed $args EventArguments.
     *
     * @return void.
     */
    public function activityController_afterMeta_handler($sender, $args) {
        foreach ($args['Activity']->Comments as $key => $comment) {
            if ($this->isBlocking($comment['InsertUserID'], 'Activities')) {
                unset($args['Activity']->Comments[$key]);
            }
        }
    }

    /**
     * "Ignore" PMs by disallowing ignored users to address them to me.
     *
     * @param MessagesController $sender Instance of the calling class.
     * @param Mixed $args Instance of the calling class.
     *
     * @return void.
     */
    public function messagesController_beforeAddConversation_handler($sender, $args) {
        // Check if staff cannot be ignored and user is staff.
        if (
            c('blockUser.ForceStaffMessages', true) &&
            $this->isStaffUser(Gdn::session()->UserID)
        ) {
            return;
        }
        foreach ($args['Recipients'] as $recipientID) {
            $blockingUser = $this->isBlocked($recipientID, 'PrivateMessages');
            if ($blockingUser) {
                // Set error because user does't want to be addressed.
                $sender->Form->addError(
                    sprintf(
                        t('You cannot start a conversation with %s. This user is ignoring you.'),
                        $blockingUser['Name']
                    ),
                    'RecipientUserID'
                );
            }
        }
    }

    /**
     * "Ignore" PMs by disallowing ignored users to address them to me.
     *
     * @param ConversationMessageModel $sender Instance of the calling class.
     * @param Mixed $args Instance of the calling class.
     *
     * @return void.
     */
    public function conversationMessageModel_beforeSaveValidation_handler($sender, $args) {
        // Check if staff cannot be ignored and user is staff.
        if (
            c('blockUser.ForceStaffMessages', true) &&
            $this->isStaffUser(Gdn::session()->UserID)
        ) {
            return;
        }

        foreach ($args['FormPostValues']['RecipientUserID'] as $recipientID) {
            $blockingUser = $this->isBlocked($recipientID, 'PrivateMessages');
            if ($blockingUser) {
                $sender->Validation->addValidationResult(
                    'RecipientUserID',
                    sprintf(
                        t('You cannot sent messages to %s. This user is ignoring you.'),
                        $blockingUser['Name']
                    )
                );
            }
        }
    }

    /**
     * Block notifications from ignored users actions.
     *
     * This will prevent users from getting notifications e.g. if an ignored
     * users mentions them.
     *
     * @param ActivityModel $sender Instance of the calling class.
     * @param Mixed $args Event arguments.
     *
     * @return void.
     */
    public function activityModel_beforeSave_handler($sender, $args) {
        if ($this->isBlocking($args['Activity']['ActivityUserID'], 'Notifications')) {
            // This prevents further processing.
            $args['Handled'] = true;
        }
    }

    /**
     * Helper function to decide whether a user can be blocked.
     *
     * @param integer $userID The userID.
     *
     * @return boolean Whether the user is an admin or a mod.
     */
    private function isStaffUser($userID) {
        $userRoles = Gdn::userModel()->getRoles(Gdn::session()->UserID);
        foreach ($userRoles as $role) {
            if (
                $role['Type'] == RoleModel::TYPE_ADMINISTRATOR ||
                $role['Type'] == RoleModel::TYPE_MODERATOR
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Function to find out if session user blocks user for a specific action.
     *
     * @param  [type]  $blockedUserID [description]
     * @param  [type]  $action        [description]
     * @return boolean                [description]
     */
    public function isBlocking($blockedUserID, $action) {
        if ($this->blockedUserIDs === null) {
            $this->blockedUserInfo = (new BlockUserModel())->getBlocked(Gdn::session()->UserID);
            $this->blockedUserIDs = array_flip(
                array_column(
                    $this->blockedUserInfo,
                    'BlockedUserID'
                )
            );
        }
        if (!isset($this->blockedUserInfo[$this->blockedUserIDs[$blockedUserID]])) {
            return false;
        }
        return $this->blockedUserInfo[$this->blockedUserIDs[$blockedUserID]];
    }

    /**
     * Check if session user is blocked by another user.
     *
     * @param  [type]  $blockedUserID [description]
     * @param  [type]  $action        [description]
     * @return boolean                [description]
     */
    public function isBlocked($blockingUserID, $action) {
        if ($this->blockingUserIDs === null) {
            $this->blockingUserInfo = (new BlockUserModel())->getBlocking(Gdn::session()->UserID);
            $this->blockingUserIDs = array_flip(
                array_column(
                    $this->blockingUserInfo,
                    'BlockingUserID'
                )
            );
        }
        if (!isset($this->blockingUserInfo[$this->blockingUserIDs[$blockingUserID]])) {
            return false;
        }
        return $this->blockingUserInfo[$this->blockingUserIDs[$blockingUserID]];
    }
}
