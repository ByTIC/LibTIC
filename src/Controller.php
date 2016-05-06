<?php

class Nip_Controller
{

	protected $_dispatcher;
	protected $_action;
	protected $_name;
	protected $_request;
	protected $_config;
	protected $_helpers = array();

	public function __construct()
	{
		$name = str_replace("Controller", "", get_class($this));
		$this->_name = inflector()->unclassify($name);
	}

	public function __call($name, $arguments)
	{
		if ($name === ucfirst($name)) {
			$class = 'Nip_Helper_' . $name;

			if (!isset($this->helpers[$class])) {
				$this->_helpers[$class] = new $class;
			}

			return $this->_helpers[$class];
		} else {
			trigger_error("Call to undefined method $name", E_USER_ERROR);
		}
	}

	public function dispatch($action = false)
	{
		if (!$action) {
			$action = $this->_action;
		}

		if ($action) {
			if ($this->validAction($action)) {
				$this->setAction($action);

				$this->beforeAction();
				$this->{$this->_action}();
				$this->afterAction();
			} else {
				$this->getDispatcher()->throwError('Action [' . $action . '] is not valid for ' . get_class($this));
			}
		} else {
			trigger_error('No action specified', E_USER_ERROR);
		}
	}

	public function call($action = false, $controller = false, $module = false, $params = array())
	{
		list($controller, $action) = $this->getDispatcher()->prepareControllerAction($action, $controller, $module);
		$controller->setView($this->getView());
		return call_user_func_array(array($controller, $action), $params);
	}

	/**
	 * Returns the config Object
	 * @return Nip_Config
	 */
	public function getConfig()
	{
		if (!$this->_config instanceof Nip_Config) {
			$this->_config = Nip_Config::instance();
		}
		return $this->_config;
	}

	/**
	 * Returns the request Object
	 * @return Nip_Request
	 */
	public function getRequest()
	{
		if (!$this->_request instanceof Nip_Request) {
			$this->_request = Nip_Request::instance();
		}
		return $this->_request;
	}

	/**
	 * @param Nip_Request $request
	 * @return Nip_Controller
	 */
	public function setRequest(Nip_Request $request)
	{
		$this->_request = $request;
		return $this;
	}

	/**
	 * Returns the dispatcher Object
	 * @return Nip_Dispatcher
	 */
	public function getDispatcher()
	{
		if (!$this->_dispatcher instanceof Nip_Dispatcher) {
			$this->_dispatcher = Nip_Dispatcher::instance();
		}
		return $this->_dispatcher;
	}

	/**
	 * @param Nip_Request $request
	 * @return Nip_Controller
	 */
	public function setDispatcher(Nip_Dispatcher $dispatcher)
	{
		$this->_dispatcher = $dispatcher;
		return $this;
	}

	/**
	 * @param string $action
	 * @return Nip_Controller
	 */
	public function setAction($action)
	{
		$this->_action = $action;
		return $this;
	}

	/**
	 * Called before $this->action
	 */
	protected function beforeAction()
	{
		return true;
	}

	/**
	 * Called after $this->action
	 */
	protected function afterAction()
	{
		return true;
	}

	protected function validAction($action)
	{
		return in_array($action, get_class_methods(get_class($this)));
	}

    public function getName()
    {
        return $this->_name;
    }
}