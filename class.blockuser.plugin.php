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
    public $blockUserModel;

    public $blockedUserInfo;

    public $blockedUserIDs;

    /**
     * Init db changes. Set sane config values if needed.
     *
     * @return void.
     */
    public function setup() {
        touchConfig('blockUser.ForceAnnouncements', true);
        touchConfig('blockUser.ForceStaffMessages', true);
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
     * "Ignore" discussions by adding a css class.
     *
     * @param BaseController $sender Instance of the calling class.
     * @param Mixed $args Instance of the calling class.
     *
     * @return void.
     */
    public function base_BeforeDiscussionName_handler($sender, $args) {
        if (!$this->blockedUserIDs) {
            $this->blockUserModel = new BlockUserModel();
            $this->blockedUserInfo = $this->blockUserModel->getBlocked(Gdn::session()->UserID);
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

    /**
     * "Ignore" comments by adding a css class.
     *
     * @param BaseController $sender Instance of the calling class.
     * @param Mixed $args Instance of the calling class.
     *
     * @return void.
     */
    public function base_beforeCommentDisplay_handler($sender, $args) {
        if (!$this->blockedUserIDs) {
            $this->blockUserModel = new BlockUserModel();
            $this->blockedUserInfo = $this->blockUserModel->getBlocked(Gdn::session()->UserID);
            $this->blockedUserIDs = array_column($this->blockedUserInfo, 'BlockedUserID');
        }

        $index = array_search(
            $args['Comment']->InsertUserID,
            $this->blockedUserIDs
        );

        if (!$index || !$this->blockedUserInfo[$index]['BlockComments']) {
            return;
        }
        $args['CssClass'] .= ' Ignored';
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
        if (!$this->blockedUserIDs) {
            $this->blockUserModel = new BlockUserModel();
            $this->blockedUserInfo = $this->blockUserModel->getBlocked(Gdn::session()->UserID);
            $this->blockedUserIDs = array_column($this->blockedUserInfo, 'BlockedUserID');
        }

        $index = array_search(
            $args['Activity']->InsertUserID,
            $this->blockedUserIDs
        );

        if (!$index || !$this->blockedUserInfo[$index]['BlockActivities']) {
            return;
        }
        $args['CssClass'] .= ' Ignored';
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
        $blockingUserInfo = (new BlockUserModel)->getBlocking(Gdn::session()->UserID);
        foreach ($blockingUserInfo as $user) {
            if (
                in_array($user['BlockingUserID'], $args['Recipients']) &&
                $user['BlockPrivateMessages'] == true
            ) {
                $sender->Form->addError(
                    sprintf(
                        t('You cannot sent messages to %s. This user is ignoring you.'),
                        $user['Name']
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
        $blockingUserInfo = (new BlockUserModel)->getBlocking(Gdn::session()->UserID);
        foreach ($blockingUserInfo as $user) {
            if (
                in_array(
                    $user['BlockingUserID'],
                    $args['FormPostValues']['RecipientUserID']
                ) &&
                $user['BlockPrivateMessages'] == true
            ) {
                $sender->Validation->addValidationResult(
                    'RecipientUserID',
                    sprintf(
                        t('You cannot sent messages to %s. This user is ignoring you.'),
                        $user['Name']
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
        $result = (new BlockUserModel())->isBlocking(
            $args['Activity']['NotifyUserID'],
            $args['Activity']['ActivityUserID'],
            ['BlockNotifications']
        );

        if($result == true) {
            // This prevents further processing.
            $args['Handled'] = true;
        }
    }
}
