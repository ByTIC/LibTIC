<?php

class Nip_DB_Exception extends Exception
{

	public function __construct($message, $code = E_USER_ERROR)
	{
		parent::__construct($message, $code);
	}

}