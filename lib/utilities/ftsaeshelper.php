<?php
class FTSAESHelper
{
	protected $pTokenContext; 
	protected $pTokenUsername;
	protected $pTokenApiKey;
	protected $pTokenClient; 
	protected $pEncryptionKey; 
	protected $pEncryptionInitVector;
	
	public function __construct($pSecurityContext)
	{
		$this->pTokenContext=$pSecurityContext;                        
    $this->pTokenUsername= "splickitapi";  //<--- IMPORTANT: Enter a valid Username
    $this->pTokenApiKey= getProperty('sfax_api_key');  //<--- IMPORTANT: Enter a valid Encryption key
    $this->pTokenClient="";   //<--- IMPORTANT: Leave Blank
    $this->pEncryptionKey='D8FBMrS57mffM%*xA3ZxP^MAdJ@*$hRB';  //<--- IMPORTANT: Enter a valid Encryption key
    $this->pEncryptionInitVector= 'x49e*wJVXr8BrALE';  //<--- IMPORTANT: Enter a valid Init vector
	}
	
	public function GenerateSecurityTokenUrl()
	{
        $tokenDataInput;
        $tokenDataEncoded;
        $tokenGenDT;
		
		$tokenGenDT = gmdate("Y-m-d") . "T" . gmdate("H:i:s") . "Z";
		
		$tokenDataInput = "Context=" . $this->pTokenContext . "&Username=" . $this->pTokenUsername. "&ApiKey=" . $this->pTokenApiKey . "&GenDT=" . $tokenGenDT . "";
		
		if($this->pTokenClient != null && $this->pTokenClient != "")
		{
			$tokenDataInput .= "&Client=" . $this->pTokenClient;
		}
		
		$AES = new AES_Encryption($this->pEncryptionKey, $this->pEncryptionInitVector, "PKCS7", "cbc");
		$tokenDataEncoded = base64_encode($AES->encrypt($tokenDataInput));
		
		return $tokenDataEncoded;
	}
}
?>