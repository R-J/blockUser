<?php

class BlockUserModel extends VanillaModel {
    public function __construct() {
        parent::__construct('BlockUser');
    }

    /**
     * Get all the users a user has blocked.
     *
     * @param integer $userID The blocking users ID.
     *
     * @return array The blocked users ids.
     */
    public function getBlockedUsers($userID = 0) {
        if (!$userID) {
            $userID = Gdn::session()->UserID;
        }

        $result = Gdn::sql()->getWhere(['BlockingUserID' => $userID])->resultArray();
        return array_column($result, 'BlockedUserID');
        /*
        // Get blocked users from UserMeta.
        $blockedUserIDs = UserModel::getMeta(
            Gdn::session()->UserID,
            'BlockUser'
        );
        // Add IDs to clas variable.
        $this->blockedUserIDs = explode(',', $blockedUserIDs['BlockUser']);
        */
    }
}
