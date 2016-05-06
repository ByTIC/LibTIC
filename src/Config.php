<?php

class Nip_Config
{

	protected $_data;

	public function parse($filename)
	{
		$config = parse_ini_file($filename, true);
		foreach ($config as $key => $value) {
			if (is_array($value)) {
				if (!isset($this->_data[$key])) {
					$this->_data[$key] = new stdClass;
				}
				foreach ($value as $subKey => $subValue) {
					$this->_data[$key]->$subKey = $subValue;
				}
			} else {
				$this->_data[$key] = $value;
			}
		}
		return $this;
	}

	public function __get($name)
	{
		return $this->_data[$name];
	}

	public function __isset($name)
	{
		return isset($this->_data[$name]);
	}

	/**
	 * Singleton
	 *
	 * @return Nip_Config
	 */
	static public function instance()
	{
		static $instance;
		if (!($instance instanceof self)) {
			$instance = new self();
		}
		return $instance;
	}
}