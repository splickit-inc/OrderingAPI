<?php

class Encrypter {
	
	protected static $ENCRYPT_KEY="z1Mc6KRxA7Nw90dGjY5qLXhtrPgJOfeCaUmHvQT3yW8nDsI2VkEpiS4blFoBuZ";
	
	public static function Encrypt($plain_text)
	{
		//$salt = Encrypter::unique_salt();
		//$salt = substr(sha1(mt_rand()),0,22);
		//$hash = crypt($plain_text, '$2a$10$' . $salt);
        $hash = password_hash($plain_text,PASSWORD_DEFAULT);
		return $hash;
	}
}
?>