<?php

class BlockUserPluginController extends Gdn_Plugin {
    public $blockUserModel;

    public function pluginController_blockUser_create($sender) {
        $this->blockUserModel = new BlockUserModel();

        $result = $this->blockUserModel->isBlocking(
            Gdn::session()->UserID,
            1008,
            [
                'BlockComments',
                'BlockPrivateMessages',
                'BlockActivities',
                'DisallowProfileVisits'
            ],
            true
        );

        decho($result);
    }
}