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

// include __DIR__.'/class.blockuserprofilecontroller.php';
/**
 * Make 'blockUser.UseDropDownButton' option in settings and tell users that
 * having a prominent blocking option might look hostile...
 */
class BlockUserPlugin extends Gdn_Plugin {

    /** @var array Blocked Users IDs */
    protected static $blockedUserIDs = [];

    protected static $blockUserProfileController;

    protected $blockingUserID;

    protected $blockedUser;

    public $blockUserModel;

    public function setup() {
        $this->structure();
    }

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

    public function profileController_afterAddSideMenu_handler($sender, $args) {
        (new BlockUserProfileController())->addEditMenuEntry($sender);
    }

   /**
     * Add button to profile.
     *
     * @param ProfileController $sender Instance of the calling object.
     *
     * @return void.
     */
   public function profileController_beforeProfileOptions_handler($sender) {
        (new BlockUserProfileController())->addBlockUserButton($sender);
    }

    /**
     *
     * @param PluginController $sender Instance of the calling class.
     *
     * @return bool|string False on failure "blocked"|"unblocked" on success.
     */
    public function profileController_blockUser_create($sender) {
        (new BlockUserProfileController())->simpleDispatch($sender);
    }

    public function pluginController_blockUser_create($sender) {
        $sender->permission('Garden.SignIn.Allow');

        $this->blockUserModel = new BlockUserModel();
        $sender->Form = new Gdn_Form();
        $sender->Form->setModel($this->blockUserModel);

        if (count($sender->RequestArgs) != 2) {
            return false;
        }

        list($action, $targetUserName, $transientKey) = $sender->RequestArgs;
        // Check if request is valid.
        if (!Gdn::session()->validateTransientKey($transientKey)) {
            throw permissionException();
        }
        // Get info about the user.
        $this->blockedUser = Gdn::userModel()->getByUsername($blockedUserName);


        switch ($action) {
            case 'add':
                $this->controller_add($sender);
                break;
            case 'edit':
                $this->controller_edit($sender);
                break;
            case 'delete':
                $this->controller_delete($sender);
                break;
            default:
                $this->controller_index($sender);
        }

    }
}
