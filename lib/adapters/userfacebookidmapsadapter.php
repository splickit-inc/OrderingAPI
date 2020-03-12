<?php

class UserFacebookIdMapsAdapter extends MySQLAdapter
{

    function UserFacebookIdMapsAdapter($mimetypes)
    {
        parent::MysqlAdapter(
            $mimetypes,
            'User_Facebook_Id_Maps',
            '%([0-9]{4,15})%',
            '%d',
            array('id'),
            null,
            array('created', 'modified')
        );

    }

    function &select($url, $options = NULL)
    {
        $options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
        return parent::select($url,$options);
    }

    function findUserIdFromFacebookId($facebook_id)
    {
        if ($record = $this->getRecord(['facebook_user_id'=>$facebook_id])) {
            return $record['user_id'];
        } else {
            return null;
        }

    }

    function getUserResourceFromFacebookUserId($facebook_user_id)
    {
        if ($user_id = $this->findUserIdFromFacebookId($facebook_user_id)) {
            if ($user_resource = UserAdapter::doesUserExist($user_id)) {
                return $user_resource;
            }
        }
    }

}
?>