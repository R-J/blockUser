<?php

class BlockUserModel extends VanillaModel {
    /**
     * Attach the "BlockUser" table to this model.
     *
     * @return  void.
     */
    public function __construct() {
        parent::__construct('BlockUser');
    }

    /**
     * Get either one or all blocked user information.
     *
     * @param integer $blockingUserID The blocking users ID.
     * @param integer $blockedUserID The blocked users ID.
     *
     * @return array Info about a blocked user.
     */
    public function getBlocked($blockingUserID, $blockedUserID = 0) {
        // Get info from cache if available.
        $blockedUserInfo = Gdn::cache()->get('BlockUserInfo.'.$blockingUserID);
        if ($blockedUserInfo === Gdn_Cache::CACHEOP_FAILURE) {
            // Get info from db.
            $blockedUserInfo = Gdn::sql()
                ->select('u.Name, u.UserID, u.Banned, u.Title, u.Photo, u.Email, bu.*')
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
        if ($index === false) {
            return false;
        }
        return $blockedUserInfo[$index];
    }

    /**
     * Helper function to find out if one user is blocking another.
     *
     * @param integer $blockingUserID The blockig users ID.
     * @param integer $blockedUserID  The blocked users ID.
     * @param array $blockedActions All actions from Table BlockUser headings.
     * @param boolean $fullMatch Whether all blocked actions must apply.
     *
     * @return boolean Whether BlockingUser is blocking BlockedUser.
     */
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
     * Get information about the blocking user.
     *
     * @param integer $blockedUserID The blocked users ID.
     *
     * @return array Info about the blocing user.
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
