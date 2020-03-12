<?
	/** * Check if a given IP address is a messagemedia gateway 
	 * 
	 * Requires dig. Note that dig will use the local DNS cache as long as the * 
	 * TTL value for the DNS record is still current  
	 * @param ip The IP address to test * @param debug boolean whether to show debugging information 
	 * @return true if the IP address belongs to an m4u gateway, false otherwise 
	 */
	function isM4uServer ( $ip,$debug = false)
	{
		// do the dig to get the valid IPs 
		$m4uIps = m4uDig("m4u.com.au", $debug);
		
		// if we get no records from the m4u.com.au domain, use the backup domain 
		if (!is_array($m4uIps))
		{ 
			if ($debug) 
				echo "Error: No DNS records found for m4u.com.au.domain. Trying backup domain\n"; 
				$m4uIps = m4uDig("m4ubackup.com", $debug);
		}
		
		if (!is_array($m4uIps)){
			if ($debug) 
				echo "Error: No DNS records found for m4ubackup.com domain.\n";    
				// It is recommended that you change the following line to
				// "return true", otherwise you will reject messages if
				// BOTH the DNS lookups ever fail (highly unlikely,  except if
				// there are upstream DNS issues from the connection running
		}
		
		foreach ($m4uIps as $m4uIp)
		{ 
			if ($debug) 
				echo "Testing " . $m4uIp . " == " . $ip . "\n";
			if ($m4uIp == $ip) 
				return true;
		} 
		
		return false;
	}

	/** 
	 * Get the valid IP addresses of the messagemedia gateways
	 * 
	 * @param domain messagemedia domain name (can be m4u.com.au or m4ubackup.com)
	 * @return array 
	 */

	function m4uDig ( $domain,$debug = false)
	{
		$dig = "/usr/bin/dig"; // the path to dig
		// get all the valid messagemedia gateway IP addresses for the domain
		exec($dig . " +short smsmaster." . $domain ." smsmaster1. " .$domain. " " . "smsmaster2." . $domain, $m4uIps);
		if ($debug)
		{ 
			echo "Found IPs:\n";
			print_r($m4uIps);
		} 
		
		return $m4uIps;
	}

	/** * USAGE EXAMPLE */
	//$testip = $_SERVER["REMOTE_ADDR"]; 
	// the actual remote address $testip = "210.50.9.123"; 
	// an invalid test address
	if (isM4uServer($testip)) 
	{ 
		echo "OK";
	} else { 
		echo $testip . " is not a valid source address!";
	}

