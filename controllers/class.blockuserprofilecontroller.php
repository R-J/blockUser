<?php

class BlockUserProfileController extends Gdn_Plugin {
    /** @var array Blocked Users IDs */
    protected $blockedUserIDs = [];

    protected $blockingUserID;

    protected $blockedUser;

    public $blockUserModel;

    public function profileController_render_before($sender) {
        $sender->addJsFile('jquery.tokeninput.js');
    }

    /**
     * End point for all profile actions.
     *
     * Profile actions are typical CRUD actions for users which should
     * be blocked. This is a simple dispatcher.
     *
     * @param PluginController $sender Instance of the calling class.
     *
     * @return void.
     */
    public function profileController_blockUser_create($sender) {
        $sender->permission('Garden.SignIn.Allow');

        $this->blockingUserID = Gdn::session()->UserID;

        $sender->getUserInfo('', '', $this->blockingUserID, false);
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

        $sender->setData('BaseUrl', '/profile/blockuser/');
        $sender->setData('TransientKey', Gdn::session()->transientKey());

        if (count($sender->RequestArgs) != 3) {
            $this->controller_index($sender);
            return;
        }

        list($action, $blockedUserName, $transientKey) = $sender->RequestArgs;
        // Check if request is valid.
        if (!Gdn::session()->validateTransientKey($transientKey)) {
            throw permissionException();
        }
        // Get info about the user.
        $this->blockedUser = Gdn::userModel()->getByUsername($blockedUserName);


        switch ($action) {
            case 'block':
            case 'add':
                $this->controller_add($sender);
                break;
            case 'edit':
                $this->controller_edit($sender);
                break;
            case 'unblock':
            case 'delete':
                $this->controller_delete($sender);
                break;
            default:
                $this->controller_index($sender);
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
            $this->blockUserModel->getByBlockingUserID($this->blockingUserID)
        );
        $sender->render('index', '', 'plugins/blockUser');
    }

    /**
     * [controller_add description]
     * @param  [type] $sender [description]
     * @param  [type] $args   [description]
     * @return [type]         [description]
     */
    public function controller_add($sender) {
        $sender->setData(
            'Title',
            sprintf(
                t('Block "%s"'),
                $this->blockedUser->Name
            )
        );
        $this->controller_edit($sender);
    }

    /**
     * [controller_edit description]
     * @param  [type] $sender [description]
     * @param  [type] $args   [description]
     * @return [type]         [description]
     */
    public function controller_edit($sender, $args) {
        if (!$sender->title()) {
            $sender->setData(
                'Title',
                sprintf(
                    t('Edit Blocked User "%s"'),
                    $this->blockedUser->Name
                )
            );
        }
        $sender->setData('BlockedUser', $this->blockedUser);
        // Set data from database, if available.
        $data = $this->blockUserModel->getByBlockingUserID(
            $this->blockingUserID,
            $this->blockedUser->UserID
        );
        if($data) {
            $sender->Form->setData($data);
            // Set PrimaryKey if available, in order to make editing possible.
            $sender->Form->addHidden('BlockUserID', $data['BlockUserID'], true);
        }
        $sender->Form->addHidden('BlockingUserID', $this->blockingUserID, true);
        $sender->Form->addHidden('BlockedUserID', $this->blockedUser->UserID, true);

        // Form submission handling.
        if ($sender->Form->authenticatedPostBack()) {
            if($sender->Form->save() !== false) {
                Gdn::cache()->remove('BlockUserInfo.'.$this->blockingUserID);

// TODOs:
// check if all fields have been cleared => delete record;
// redirect to page
                $sender->informMessage(t("Your changes have been saved."));
                // redirect('/profile/blockuser');
            }
        }

        $sender->render('edit', '', 'plugins/blockUser');
    }

    public function controller_delete($sender, $args) {
        $this->blockUserModel->delete(
            [
                'BlockingUserID' => $this->blockingUserID,
                'BlockedUserID' => $this->blockedUser->UserID
            ]
        );
        Gdn::cache()->remove('BlockUserInfo.'.$this->blockingUserID);
        redirect('/profile/blockuser');
    }


   /**
     * Add "block user" button to profile.
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
        if ((new BlockUserModel())->isBlocking($sessionUserID, $profileUserID)) {
            $action = 'unblock';
        } else {
            $action = 'block';
        }

        $url = "/profile/blockuser/{$action}/{$sender->User->Name}/".Gdn::session()->transientKey();
        $action = ucfirst($action);
        $text = trim(sprite('Sp'.$action).' '.t($action));

        if (c('blockUser.UseDropDownButton', true)) {
            // Enhance messge button on profile with a second option
            $sender->EventArguments['MemberOptions'][] = [
                'Text' => $text,
                'Url' => $url,
                'CssClass' => "{$action}UserButton Popup"
            ];
        } else {
            // Add some styling.
            echo '<style>.BlockUserButton,.UnblockUserButton{margin-right:4px}</style>';
            // Show button on profile.
            echo anchor(
                $text,
                $url,
                ['class' => "NavButton {$action}Button Popup"]
            );
        }
    }

    /**
     * Add Block-User-Management option to profile edit.
     *
     * @param ProfileController $sender Instance of the calling class.
     *
     * @return void.
     */
    public function profileController_afterAddSideMenu_handler($sender) {
        // Get a reference to the menu that we like to extend.
        $menu = &$sender->EventArguments['SideMenu'];
        $menu->addLink(
            'Options',
            sprite('SpBlockUser').' '.t('Block Users'),
            'profile/blockuser',
            ['Garden.SignIn.Allow']
        );
    }
}