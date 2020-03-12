<?php

class SplickitCrypter
{
	
	private $key = array("mikesmarketer"=>"Ac797X5Ub6758kF86B2ga6F0rdrA3aqP0ViR6659Cw13fqnpe7");
	
	static function doEncryption($clear_string,$admin_user)
	{
		$sc = new SplickitCrypter();
		if (isset($sc->key[$admin_user]))
		{
			$aes256Key = hash("SHA256", $sc->key[$admin_user], true);
			return $sc->fnEncrypt($clear_string, $aes256Key, $iv);
		} else {
			return false;
		}
	}

	static function doDecryption($enc_string,$admin_user)
	{
		$sc = new SplickitCrypter();
		if (isset($sc->key[$admin_user]))
		{
			$key = $sc->key[$admin_user];
			$aes256Key = hash("SHA256",$key, true);
			return $sc->fnDecrypt($enc_string, $aes256Key, $iv);
		} else {
			return false;
		}
	}
	
	function fnEncrypt($sValue, $sSecretKey, $iv) {
	    return rtrim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $sSecretKey, $sValue, MCRYPT_MODE_CBC, $iv)), "\0\3");
	}
	
	function fnDecrypt($sValue, $sSecretKey, $iv) {
	    return rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $sSecretKey, base64_decode($sValue), MCRYPT_MODE_CBC, $iv), "\0\3");
	}	
	
}

?>