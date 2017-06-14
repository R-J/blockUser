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

/**
 * Make 'blockUser.UseDropDownButton' option in settings and tell users that
 * having a prominent blocking option might look hostile...
 */
class BlockUserPlugin extends Gdn_Plugin {

    /** @var array Blocked Users IDs */
    protected static $blockedUserIDs = [];

    public $blockUserModel;

    /**
     * Get blocked users from UserMeta.
     *
     * @return void.
     */
    public function __construct() {
        // Guests cannot block anyone.
        if (!Gdn::session()->isValid()) {
            return;
        }
        // $this->blockedUserIDs = (new BlockUserModel)->getBlockedUsers(Gdn::session()->UserID);
    }

    public function setup() {
        $this->structure();
    }

    public function structure() {
        Gdn::structure()->table('BlockUser')
            ->column('BlockingUserID', 'int(11)', false)
            ->column('BlockedUserID', 'int(11)', false)
            ->column('BlockPrivateMessages', 'tinyint(1)', 1)
            ->column('BlockNotifications', 'tinyint(1)', 1)
            ->column('BlockDiscussions', 'tinyint(1)', 1)
            ->column('BlockComments', 'tinyint(1)', 1)
            ->column('BlockActivities', 'tinyint(1)', 1)
            ->column('DisallowProfileVisits', 'tinyint(1)', 1)
            ->column('Comment', 'text', '')
            ->set();
    }

    public function profileController_afterAddSideMenu_handler($sender, $args) {
        // Get a reference to the menu that we like to extend.
        $menu = &$args['SideMenu'];
        $menu->addLink(
            'Options',
            sprite('SpBlockUser').' '.t('Block Users'),
            'profile/blockuser',
            ['Garden.SignIn.Allow']
        );
    }

   /**
     * Add button to profile.
     *
     * @param ProfileController $sender Instance of the calling object.
     *
     * @return void.
     */
   public function profileController_beforeProfileOptions_handler($sender) {
        $sessionUserID = Gdn::session()->UserID;
        $profileUserID = $sender->User->UserID;

        // Exit if this is the visitors own profile or visitor is guest.
        if ($profileUserID == $sessionUserID || $sessionUserID < 1) {
            return;
        }

        if (in_array($profileUserID, $this->blockedUserIDs)) {
            $action = 'Unblock';
        } else {
            $action = 'Block';
        }

        $text = trim(sprite('Sp'.$action).' '.t($action));
        $url = '/plugin/blockuser/'.strtolower($action)."/{$profileUserID}/".Gdn::session()->transientKey();

        if (c('blockUser.UseDropDownButton', true)) {
            // Enhance messge button on profile with a second option
            $sender->EventArguments['MemberOptions'][] = [
                'Text' => $text,
                'Url' => $url,
                'CssClass' => "{$action}UserButton Hijack"
            ];
        } else {
            // Add some styling.
            echo '<style>.BlockUserButton,.UnblockUserButton{margin-right:4px}</style>';
            // Show button on profile.
            echo anchor(
                $text,
                $url,
                ['class' => "NavButton {$action}Button Hijack"]
            );
        }
    }

    /**
     *
     * @param PluginController $sender Instance of the calling class.
     *
     * @return bool|string False on failure "blocked"|"unblocked" on success.
     */
    public function profileController_blockUser_create($sender) {
        $sender->permission('Garden.SignIn.Allow');

        $sender->getUserInfo('', '', Gdn::session()->UserID, false);
        $sender->editMode(true);

        // Set the breadcrumbs.
        $sender->setData(
            'Breadcrumbs',
            [
                ['Name' => t('Profile'), 'Url' => '/profile'],
                ['Name' => t('Block Users'), 'Url' => '/profile/blockuser']
            ]
        );

        $this->blockUserModel = new BlockUserModel();
        $sender->Form = new Gdn_Form();
        $sender->Form->setModel($this->blockUserModel);

        if (count($sender->RequestArgs) != 3) {
            $this->controller_index($sender);
            return;
        }

        switch ($sender->RequestArgs[0]) {
            case 'add':
                $this->controller_add($sender, $sender->RequestArgs);
                break;
            case 'edit':
                $this->controller_edit($sender, $sender->RequestArgs);
                break;
            case 'delete':
                $this->controller_delete($sender, $sender->RequestArgs);
                break;
            default:
                throw notFoundException();
        }
    }

    /**
     * [controller_index description]
     * @param  [type] $sender [description]
     * @return [type]         [description]
     */
    public function controller_index($sender) {
        $sender->setData('Title', t('Block Users'));

        $sender->setData(
            'BlockedUsers',
            $this->blockUserModel->getWhere(
                ['BlockingUserID' => Gdn::session()->UserID]
            )
        );
        $sender->render('index', '', 'plugins/blockUser');
    }

    /**
     * [controller_add description]
     * @param  [type] $sender [description]
     * @param  [type] $args   [description]
     * @return [type]         [description]
     */
    public function controller_add($sender, $args) {
        $this->controller_edit($sender, $args);
    }

    /**
     * [controller_edit description]
     * @param  [type] $sender [description]
     * @param  [type] $args   [description]
     * @return [type]         [description]
     */
    public function controller_edit($sender, $args) {
        list($action, $userName, $transientKey) = $args;
        // Check authorization.
        if (!Gdn::session()->validateTransientKey($transientKey)) {
            throw permissionException();
        }

        $userID = Gdn::session()->UserID;

        $sender->Form->setData(
            $this->blockUserModel->getByBlockedUserName(
                $userName,
                $userID
            )
        );

decho($blockedUserInfo);
        if (strtolower($action) == 'edit') {
            $title = 'Edit Blocked User "%s"';
        } else {
            $title = 'Block "%s"';
        }
        $sender->setData('Title', sprintf($title, $userName));

        // Set the breadcrumbs.
        $sender->setData(
            'Breadcrumbs',
            [
                ['Name' => t('Profile'), 'Url' => '/profile'],
                ['Name' => t('Block Users'), 'Url' => '/profile/blockuser']
            ]
        );
        // Form submission handling.
        if ($sender->Form->authenticatedPostBack()) {
decho($sender->Form->formValues());
            $sender->informMessage(t("Your changes have been saved."));
        }

        $sender->render('edit', '', 'plugins/blockUser');
    }
}
