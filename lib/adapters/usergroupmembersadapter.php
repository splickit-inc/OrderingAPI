<?php
class UserGroupMembersAdapter extends MySQLAdapter
{

	function UserGroupMembersAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'User_Group_Members',
			'%([0-9]{4,10})%',
			'%d',
			array('id'),
			null,
			array('created','modified')
		);
		
		$this->allow_full_table_scan = false;
						
	}
	
	function &select($url, $options = NULL)
    {
		$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
	}
	
	static function isUserAMemberOfTheGroupById($user_id,$group_id)
	{
		$ugma = new UserGroupMembersAdapter($mimetypes);
		if ($ugma->getRecord(array("user_id"=>$user_id,"user_group_id"=>$user_group_id))) {
			return true;
		} 
		return false;
		
	}
	
	static function isUserAMemberOfTheGroupByGroupName($user_id,$group_name)
	{
		if ($record = UserGroupMembersAdapter::getGroupIfUserIsAMemberOfItByName($user_id, $group_name))
			return true;
		return false;
	}
	
	static function getGroupIfUserIsAMemberOfItByName($user_id,$group_name)
	{
		$uga = new UserGroupsAdapter($mimetypes);
		if ($record = $uga->getRecord(array("name"=>$group_name))) {
			if (UserGroupMembersAdapter::isUserAMemberOfTheGroupById($user_id, $record['id'])) {
				return $record;
			} else {
				return null;
			}
		}
		throw new NoMatchingGroupException();
	}
	
	static function joinGroup($group_id,$user_id)
	{
		$data['user_group_id'] = $group_id;
		$data['user_id'] = $user_id;
		$options[TONIC_FIND_BY_METADATA] = $data;
		$resource = Resource::findOrCreateIfNotExists(new UserGroupMembersAdapter($mimetypes), $url, $options);
		return $resource;
	}
	
	static function unJoinGroup($group_id,$user_id)
	{
		$ugma = new UserGroupMembersAdapter($mimetypes);
		if ($ugma_record = $ugma->getRecord(array("user_group_id"=>$group_id,"user_id"=>$user_id))) {
			$ugma->delete(''.$ugma_record['id']);
		}		
	}
	
	/**
	 * 
	 * @desc will return an array of the user group member records that match this user_id
	 * @param $user_id
	 */
	static function getUserGroupRecordsThatThisUserIsAMemberOf($user_id)
	{
		$ugma = new UserGroupMembersAdapter($mimetypes);
		return $ugma->getRecords(array("user_id"=>$user_id));	
	}

}

class NoMatchingGroupException extends Exception
{
    public function __construct($code = 0) {
        parent::__construct("There is no group by that name", $code);
    }
}
?>