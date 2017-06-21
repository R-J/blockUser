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
// unused
die;
        if (!$userID) {
            $userID = Gdn::session()->UserID;
        }

        $result = $this->getWhere(['BlockingUserID' => $userID])->resultArray();
        return array_column($result, 'BlockedUserID');
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
    public function getBlocked($blockingUserID, $blockedUserID = 0) {
        // Get info from cache if available.
        $blockedUserInfo = Gdn::cache()->get('BlockUserInfo.'.$blockingUserID);
        if ($blockedUserInfo === Gdn_Cache::CACHEOP_FAILURE) {
            // Get info from db.
            $blockedUserInfo = Gdn::sql()
                ->select('u.Name, u.UserID, u.Banned, u.Title, u.Photo, bu.*')
                ->from('User u')
                ->join(
                    'BlockUser bu',
                    'u.UserID = bu.BlockedUserID',
                    'left outer'
                )
                ->where(['bu.BlockingUserID' => $blockingUserID])
                ->get()
                ->resultArray();
            // Save to cache.
            Gdn::cache()->store(
                'BlockUserInfo.'.$blockingUserID,
                $blockedUserInfo,
                [
                    Gdn_Cache::FEATURE_EXPIRY => 300 // 5 minutes.
                ]
            );
        }

        // Return all if no individual datasat has been requested.
        if (!$blockedUserID) {
            return $blockedUserInfo;
        }
        // Search for single dataset.
        $index = array_search(
            $blockedUserID,
            array_column($blockedUserInfo, 'BlockedUserID')
        );
        if (!$index) {
            return false;
        }
        return $blockedUserInfo[$index];
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

    public function isBlocking($blockingUserID, $blockedUserID, $blockedActions = [], $fullMatch = true) {
        $blockedUserInfo = $this->getBlocked($blockingUserID, $blockedUserID);
        if (!$blockedUserInfo) {
            return false;
        }

        $validateActions = array_intersect_key(
            $blockedUserInfo,
            array_flip($blockedActions)
        );

        $result = true;
        foreach($validateActions as $action) {
            if (!$fullMatch && $action) {
                return true;
            }
            $result = $result && $action;
        }
        return $result;
    }

    /**
     * [isBlocked description]
     * @param  [type]  $blockedUserID [description]
     *
     * @return array UserIDs of users blocking the input user id.
     */
    public function getBlocking($blockedUserID) {
        return Gdn::sql()
            ->select('u.Name, bu.*')
            ->from('User u')
            ->join(
                'BlockUser bu',
                'u.UserID = bu.BlockingUserID',
                'left outer'
            )
            ->where(['bu.BlockedUserID' => $blockedUserID])
            ->get()
            ->resultArray();
    }
}
