<?php

class Nip_Helper_XML extends Nip_Helper_String
{

	public function format($string)
	{
		$doc = new DOMDocument('1.0');
		$doc->preserveWhiteSpace = false;
		$doc->loadXML($string);
		$doc->formatOutput = true;
		return $doc->saveXML();
	}

	/**
	 * Singleton
	 *
	 * @return Nip_Helper_XML
	 */
	public static function instance()
	{
		static $instance;
		if (!($instance instanceof self)) {
			$instance = new self();
		}
		return $instance;
	}

}