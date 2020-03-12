<?php

class UserSkinDonationAdapter extends MySQLAdapter
{

	function UserSkinDonationAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'User_Skin_Donation',
			'%([0-9]{1,15})%',
			'%d',
			array('id'),
			null,
			array('created','modified')
			);
	}
	
	static function getDonationResourceForUserAndSkin($user_id, $skin_id)
	{
		$options[TONIC_FIND_BY_METADATA] = UserSkinDonationAdapter::dataArrayForUserIdAndSkinId($user_id, $skin_id);
		
		$user_skin_donation_adapter = new UserSkinDonationAdapter(getM());
		if ($resource =& Resource::findExact($user_skin_donation_adapter, null, $options)) {
			return $resource;
		} else {
			return false;
		}
	}
	
	static function setDonationResourceForUserAndSkin($user_id, $skin_id, $active='Y', $type='R', $amt='0.00')
	{
		$resource = UserSkinDonationAdapter::getDonationResourceForUserAndSkin($user_id, $skin_id);
		if ($resource == false) {
			$user_skin_donation_adapter = new UserSkinDonationAdapter($mimetypes);
			$data = UserSkinDonationAdapter::dataArrayForUserIdAndSkinId($user_id, $skin_id);
			$resource = Resource::factory($user_skin_donation_adapter,$data);
		}
		
		$resource->donation_active = $active;
		$resource->donation_type = $type;
		$resource->donation_amt = $amt;
		$resource->modified = time();
		return $resource->save();
	}
	
	private static function dataArrayForUserIdAndSkinId($user_id, $skin_id) {
		$data['user_id'] = $user_id;
		$data['skin_id'] = $skin_id;
		return $data;
	}


	
}
?>
