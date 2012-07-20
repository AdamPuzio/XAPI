<?php
/**
 * Api Controller
 *
 * xAPI
 *
 * @copyright     Copyright 2012
 * @link          http://xapi.co
 * @package       xapi
 * @subpackage    xapi.cake.libs.controller
 */

/**
 * Api Controller
 *
 * @package       xapi
 * @subpackage    xapi.cake.libs.controller
 */
class ApiController extends ApiAppController {
	public $uses = array();

	public function beforeFilter() {
		parent::beforeFilter();
		$this->Auth->allow('*');
	}
	
	public function index(){
		$pass = $this->request->pass;
		$apiType = $pass[0];
		$this->getApiConfig($apiType);
		//pr($this->request);die();
		$request = array(
			'url' => $this->request->url
			, 'domain' => $this->request->domain()
			, 'subdomains' => $this->request->subdomains()
			, 'host' => $this->request->host()
			, 'method' => $this->request->method()
			, 'referer' => $this->request->referer()
			, 'clientIp' => $this->request->clientIp()
			, 'accepts' => $this->request->accepts()
			, 'params' => $pass
			, 'request' => array(
				'pass' => $pass
				, 'named' => $this->request->named
				, 'query' => $this->request->query
				, 'post' => $this->request->data
			)
			, 'user' => array(
				'authenticate' => $this->authenticate()
				, 'id' => AuthComponent::user('id')
			)
		);
		$this->_request = $request;
		pr($request);
		
		//pr($this->buildApiObject($apiType));
		$this->handleRequest();
	}
}