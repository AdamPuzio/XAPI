<?php
/**
 * Api Plugin App Controller
 *
 * xAPI
 *
 * @copyright     Copyright 2012
 * @link          http://xapi.co
 * @package       xapi
 * @subpackage    xapi.cake.libs.controller
 */

/**
 * Api Plugin App Controller
 *
 * @package       xapi
 * @subpackage    xapi.cake.libs.controller
 */
class ApiAppController extends AppController {
	public $uses = array();
	public $helpers = array('Html', 'Form', 'Session');
	public $components = array('Session', 'Auth');
	public $_apiConfig;
	
	protected $_apiName = null;
    protected $_apiAttribute = null;
    protected $_nameAttribute = null;
    protected $_formAttribute = null;
    protected $_jsName = 'API';
	
	
	public function authenticate(){
		$auth = $this->_apiConfig['authenticate'];
		
		// If authenticate is set to 'false', we don't need to authenticate
		if($auth === false) return true;
		
		// If authenticate is set and is not 'true', use it as a config object
		if($auth !== true) $this->Auth->authenticate = $auth;
		if($this->Auth->login()){
			return true;
		}else{
			return false;
		}
	}
	
	public function authorize(){
		$auth = $this->_apiConfig['authorize'];
		if($auth === false) return true;
		
	}
	
	public function getApiConfig($api){
		Configure::load('Api.xapi', 'default');
		$default = Configure::read('XAPI._default');
		$api = Configure::read('XAPI.' . $api);
		if(!$api){
			$this->error404();
		}
		$this->_apiConfig = array_merge($default, $api);
		return $this->_apiConfig;
	}
	
/**
 * Handles Incoming Request
 */
	public function handleRequest($args=null){
		if(!$args) $args = $this->request->params['pass'];
		$apiType = array_shift($args);
		$mapping = $this->buildApiObject($apiType);
		$request = array(
			'request' => null
			, 'action' => null
			, 'success' => true
			, 'errors' => array()
		);
		
		$numargs = count($args);
		if($numargs < 2){
			$request['success'] = false;
			$request['errors'][] = 'Invalid request';
			$this->_outputJson($request);
		}
		
		// Get and load the model
		$data = $this->request->data;
		$modelName = Inflector::camelize($request['request'] = array_shift($args));
		$actionName = $request['action'] = array_shift($args);
		if(!isset($mapping[$modelName]) || !isset($mapping[$modelName][$actionName])){
			$request['success'] = false;
			$request['errors'][] = 'Invalid request';
			$this->_outputJson($request);
		}
		
		$methodInfo = $mapping[$modelName][$actionName];
		$params = array();
		$missing = array();
		
		if($methodInfo['isForm']){
			$params[] = $data;
		}else if(!empty($args)){
			foreach($methodInfo['params'] as $i => $param){
				$paramName = $param['name'];
				if(isset($args[$i])){
					$params[$i] = $args[$i];
				}else if($param['isOptional']){
					$params[$i] = $param['defaultValue'];
				}else{
					$missing[] = $paramName;
				}
			}
		}else{
			foreach($methodInfo['params'] as $param){
				$paramName = $param['name'];
				if(isset($data[$paramName])){
					$params[] = $data[$paramName];
				}else if($param['isOptional']){
					$params[] = $param['defaultValue'];
				}else{
					$missing[] = $paramName;
				}
			}
		}
		
		
		if(!empty($missing)){
			$request['success'] = false;
			$request['errors'][] = 'Missing Parameters: ' . implode(', ', $missing);
			$this->_outputJson($request);
		}
		
		$className = $methodInfo['class'];
		$type = $methodInfo['type'];
		$method = $methodInfo['method'];
		
		switch($type){
			case 'Controller':
				$cfg = array(
					'pass' => $params
					, 'return'
				);
				echo $this->requestAction(array(
					'controller' => Inflector::tableize($modelName)
					, 'action' => $method
				), $cfg);
				die();
				break;
			case 'Model':
				$load = $this->loadModel($modelName);
				$response = call_user_func_array(array($this->$modelName, $method), $params);
				if(is_array($response) && isset($response['render'])){
					$render = $response['render'];
					unset($response['render']);
					$renderParams = isset($render['params']) ? $render['params'] : array();
					$pass = array('data' => $renderParams, 'return');
					$content = $this->requestAction($render['action'], $pass);
					$response['content'] = $content;
					$this->set($renderParams);
				}
				$this->_outputJson($response);
				break;
		}
	}
	
/**
 * Build or retrieve the API object
 */
	public function buildApiObject($type, $force=true){
		$this->_apiName = $type;
		Cache::config('api', array(
			'engine' => 'File'
			, 'duration' => '+1 day'
			, 'probability' => 100
			, 'path' => CACHE . 'models' . DS
		));
		$classes = Cache::read('api_' . $type . '_list', 'api');
		if($force || !$classes) {
			$name = $this->_apiName;
			if(!$name) die('API type not specified');
			$this->_apiAttribute = '@' . $name . 'Api';
			$this->_nameAttribute = '@' . $name . 'ApiName';
			$this->_formAttribute = '@' . $name . 'ApiForm';
			$classes = $this->parseFiles('Model');
			$controllers = $this->parseFiles('Controller');
			foreach($controllers as $class => $methods){
				if(isset($classes[$class])){
					$classes[$class] = array_merge($classes[$class], $methods);
				}else{
					$classes[$class] = $methods;
				}
			}
			$write = Cache::write('api_' . $type . '_list', $classes, 'api');
		}
		return $classes;
	}
	
/**
 * Parse files for API
 * 
 * @param string $type The type of API to create (Model, Controller)
 */
	public function parseFiles($type){
		App::uses($type, $type);
		App::uses('App' . $type, $type);
		App::uses('Folder', 'Utility');
		$folder = new Folder(APP . DS . $type);
		$files = $folder->read(true, true);
		$classes = array();

		foreach($files[1] as $file){
			$actions = array();
			if(!strstr($file, '.php')) continue;
			$class = str_replace('.php', '', $file);
			if($class == 'App' . $type) continue;
			App::uses($class, $type);
			$m = new ReflectionClass($class);
			$methods = $m->getMethods(ReflectionMethod::IS_PUBLIC);
			foreach($methods as $methodObj){
				$methodName = $methodObj->name;
				$method = $m->getMethod($methodName);
				$docComment = $method->getDocComment();
				if($method->isPublic() && strlen($docComment) > 0) {
					if(!!preg_match('/' . $this->_apiAttribute . '/', $docComment)) {
						preg_match_all("/@param (.*?)\n/", $docComment, $paramMatches);
						$docParams = array();
						foreach($paramMatches[1] as $paramMatch){
							$var = array(
								'type' => null
								, 'name' => null
								, 'desc' => null
							);
							$paramMatch = trim($paramMatch);
							$strpos = strpos($paramMatch, '$');
							if($strpos > 0){
								$var['type'] = substr($paramMatch, 0, $strpos);
								$paramMatch = substr($paramMatch, $strpos);
							}
							$strpos = strpos($paramMatch, ' ');
							$var['name'] = trim(substr($paramMatch, 0, $strpos));
							$var['desc'] = trim(substr($paramMatch, $strpos));
							$docParams[str_replace('$', '', $var['name'])] = $var;
						}
						preg_match('/' . $this->_nameAttribute . " (.*?)\n/", $docComment, $matches);
						$methodAlias = trim(empty($matches) ? $methodName : $matches[1]);
						$params = $method->getParameters();
						$parameters = array();
						foreach($params as $param){
							$p = array(
								'name' => $param->name
								, 'isOptional' => $param->isOptional()
								, 'comment' => isset($docParams[$param->name]) ? $docParams[$param->name] : null
							);
							if($param->isOptional()){
								$p['defaultValue'] = $param->getDefaultValue();
							}
							$parameters[] = $p;
						}
						preg_match('/' . $this->_formAttribute . "/", $docComment, $formMatches);
						$isForm = !empty($formMatches);
						$methodInfo = array(
							'type' => $type
							, 'class' => $class
							, 'method' => $methodName
							, 'alias' => $methodAlias
							, 'totalParams' => $method->getNumberOfParameters()
							, 'isForm' => $isForm
							, 'requiredParams' => $method->getNumberOfRequiredParameters()
							, 'startLine' => $method->getStartLine()
							//, 'docParams' => $docParams
							, 'params' => $parameters
						);
						if($method->isDeprecated()) $methodInfo['deprecated'] = true;
						
						$actions[$methodAlias] = $methodInfo;
					}
				}
			}
			if(!empty($actions)){
				// Convert any Controller names into Model names
				$classConvert = Inflector::singularize(str_replace('Controller', '', $class));
				$classes[$classConvert] = $actions;
			}
		}
		return $classes;
	}
	
/**
 * JSON encoded output
 */
	public function _outputJson($request){
		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Content-type: application/json');
		echo json_encode($request);
		die();
	}
	
	public function error404(){
		die('404');
	}
	
	
	
	
/*
 * OLD
 */
	
	/*protected $_apiName = null;
    protected $_apiAttribute = null;
    protected $_nameAttribute = null;
    protected $_formAttribute = null;
    protected $_jsName = 'API';*/
	
	public function beforeFilter(){
		parent::beforeFilter();
		
	}
	
	public function docs(){
		$obj = $this->buildApiObject();
		pr($obj);
		die();
	}
	
/**
 * Ouput a JavaScript version of the API
 */
	public function js(){
		$ApiObj = $this->buildApiObject();
		$obj = array();
		foreach($ApiObj as $class => $actions){
			$obj[$class] = array();
			foreach($actions as $actionName => $info){
				$action = array(
					'request' => array(
						'dataType' => $info['type'] == 'Controller' ? 'html' : 'json'
						, 'type' => 'POST'
						, 'cache' => false
					)
					, 'isForm' => $info['isForm']
					, 'totalParams' => $info['totalParams']
					, 'requiredParams' => $info['requiredParams']
					, 'params' => array()
				);
				foreach($info['params'] as $pinfo){
					$param = array(
						'name' => $pinfo['name']
						, 'isOptional' => $pinfo['isOptional']
					);
					$action['params'][] = $param;
				}
				
				$obj[$class][$actionName] = $action;
			}
		}
		//echo 'var ' . $this->_jsName . ' = ' . json_encode($obj) . ';';
		echo json_encode($obj);
		die();
	}
}