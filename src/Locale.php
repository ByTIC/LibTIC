<?php

class Nip_Locale
{

    protected $_supported;
    protected $_data = array();
    protected $_default = 'en_US';
    protected $_current;

    public function getFromINI()
    {
        return setLocale(LC_TIME, 0);
    }

    public function getCurrent()
    {
        if (!$this->_current) {
            $locale = $this->getFromINI();
            $data = $this->getData($locale);
            if ($data) {
                $this->_current = $locale;
            } else {
                $this->_current = $this->_default;
            }
        }
        return $this->_current;
    }

    public function getSupported()
    {
        if (!$this->_supported) {            
            $files = Nip_File_System::instance()->scanDirectory($this->getDataFolder());
            foreach ($files as $file) {
                if (substr($file, 0, 1) != '_') {
                    $name = str_replace('.php', '', $file);
                    $this->_supported[] = $name;
                }
            }
        }
        return $this->_supported;
    }

    public function getOption($path = array(), $locale = false)
    {
        $data = $this->getData($locale);
        $value = $data;
        $pathFlat = '';
        foreach ($path as $key) {
            $pathFlat .= $key;
            if (isset ($value[$key])) {
                $value = $value[$key];
            } else {
                trigger_error("invalid path [{$pathFlat}] for ". __CLASS__ ."->". __METHOD__, E_USER_WARNING);
                return false;
            }
        }

        return $value;
    }

    public function getData($locale = false)
    {
        $locale = $locale ? $locale : $this->getCurrent();
        if (!$this->_data[$locale]) {
            $data = $this->_getDataFromFile($locale);
            $this->_data[$locale] = $data;
        }

        return $this->_data[$locale];
    }

    protected function _getDataFromFile($name, $data = array())
    {
        $file = $this->_getDataFile($name);

        if (is_file($file)) {
            include $file;
            if (isset ($_import)) {
                $data = $this->_getDataFromFile($_import);
            }
            if (isset ($_data)) {
                $data = Nip_Helper_Array::instance()->merge_distinct($data, $_data);
            }
        } else {
            trigger_error("no locale data file at [{$file}]", E_USER_WARNING);
        }

        return $data;
    }

    protected function _getDataFile($name)
    {
        return $this->_getDataFolder() . $name . '.php';
    }

    protected function _getDataFolder()
    {
        return dirname(__FILE__) . '/locale/data/';
    }
    
	/**
	 * Singleton pattern
	 *
	 * @return Nip_Locale
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