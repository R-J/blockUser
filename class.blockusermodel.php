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

    public function getByBlockedUserName($blockedUserName, $blockingUserID = 0) {
        if ($blockingUserID == 0) {
            $blockingUserID = Gdn::session()->UserID;
        }
        $sql = Gdn::sql()
            ->select('u.Name, bu.*')
            ->from('User u')
            ->join(
                'BlockUser bu',
                'u.UserID = bu.BlockedUserID',
                'left outer'
            )
            ->where(['bu.BlockingUserID' => Gdn::session()->UserID])
            ->where(['u.Name' => $blockedUserName]);

        return $sql->get()->firstRow();
    }

    /**
     * Get either one or all blocked user information.
     *
     * @param  [type] $blockingUserID [description]
     * @param  [type] $blockedUserID  [description]
     * @return dataset
     */
    public function getByBlockingUserID($blockingUserID, $blockedUserID = null) {
        $sql = Gdn::sql()
            ->select('u.Name, u.UserID, u.Banned, u.Title, u.Photo, bu.*')
            ->from('User u')
            ->join(
                'BlockUser bu',
                'u.UserID = bu.BlockedUserID',
                'left outer'
            )
            ->where(['bu.BlockingUserID' => $blockingUserID]);
        if ($blockedUserID) {
            $sql->where(['bu.BlockedUserID' => $blockedUserID]);
        }
        return $sql->get();
    }

    public function getUserIDByName($userName) {
        $user = Gdn::userModel()->getUserFromCache($userName, 'name');
        if ($user) {
            return $blockedUser['UserID'];
        }

        return Gdn::sql()
            ->select('UserID')
            ->from('User')
            ->where(['Name' => $userName])
            ->get()
            ->firstRow()
            ->UserID;
    }
}
