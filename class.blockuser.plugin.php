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
            ->column('BlockMessages', 'tinyint(1)', 1)
            ->column('BlockPosts', 'tinyint(1)', 1)
            ->column('DenyProfileview', 'tinyint(1)', 1)
            ->column('BlockNotifications', 'tinyint(1)', 1)
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

    public function profileController_blockUser_create($sender, $args) {
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

        $sender->setData('Title', t('Block Users'));

        // Form submission handling.
        if ($sender->Form->authenticatedPostBack()) {
            $sender->informMessage(t("Your changes have been saved."));
        }

        $sender->render('profile', '', 'plugins/blockUser');
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
     * Toggle userids in blocked user array.
     *
     * Needs TransientKey as a parameter to prevent accidential
     * blocking/unblocking (or malicious links to do so).
     *
     * @param PluginController $sender Instance of the calling class.
     *
     * @return bool|string False on failure "blocked"|"unblocked" on success.
     */
    public function pluginController_blockUser_create($sender) {
        $sender->permission('Garden.SignIn.Allow');
        if (count($sender->RequestArgs) <= 0) {
            // Only continue for logged in users.
            throw notFoundException();
        }

        switch ($sender->RequestArgs[0]) {
            case 'add':
                $this->controller_add($sender, $sender->ReqestArgs);
                break;
            case 'edit':
                $this->controller_edit($sender, $sender->ReqestArgs);
                break;
            case 'delete':
                $this->controller_delete($sender, $sender->ReqestArgs);
                break;
            default:
                throw notFoundException();
        }
    }

    public function controller_add($sender, $args) {
        $sender->setData('Title', t('Add User'));
    }
    public function controller_edit($sender, $args) {
        /*
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
        */
        if (!$sender->title()) {
            $sender->setData('Title', t('Edit Blocked User'));
        }

        // Form submission handling.
        if ($sender->Form->authenticatedPostBack()) {
            $sender->informMessage(t("Your changes have been saved."));
        }

        $sender->render('profile', '', 'plugins/blockUser');
    }
}
