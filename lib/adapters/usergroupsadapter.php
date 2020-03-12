<?php

class UserGroupsAdapter extends MySQLAdapter
{

	function UserGroupsAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'User_Groups',
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
	
	static function getAllGroupsInformationThatThisUserIsAMemeberOf($user_id)
	{
		$uga = new UserGroupsAdapter($mimetypes);
		if ($user_group_member_records = UserGroupMembersAdapter::getUserGroupRecordsThatThisUserIsAMemberOf($user_id))
		{
			$group_records = array();
			foreach ($user_group_member_records as $ugmr)
			{
				$record =  $uga->getRecord(array("id"=>$ugmr['user_group_id']));
				$record['active'] = $ugmr['active'];
				$group_records[] =$record;
			}
			return $group_records;
		}
		return null;
	}
}
?>