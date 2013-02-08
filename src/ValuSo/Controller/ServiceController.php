<?php
namespace ValuSo\Controller;

use ValuSo\Exception\OperationNotFoundException;
use ValuSo\Exception\ServiceNotFoundException;
use ValuSo\Exception\NotFoundException;
use ValuSo\Exception\PermissionDeniedException;
use ValuSo\Exception\ServiceException;
use ValuSo\Broker\ServiceBroker;
use Zend\EventManager\ResponseCollection;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\Json\Json;
use Zend\Json\Decoder as JsonDecoder;
use Zend\Json\Encoder as JsonEncoder;
use Zend\Http\Header\HeaderInterface;
use Zend\Http\PhpEnvironment\Response;
use Zend\Console\Request as ConsoleRequest;

/**
 * Service controller
 *
 * Service controller provides an RPC style HTTP API.
 * 
 * Common HTTP route to service broker is in form:
 * <code>
 * path/to/api/&lt;service&gt;/&lt;operation&gt;?&lt;parameters&gt;
 * </code>
 * 
 * When using this route syntax, each parameter in query string is
 * passed to the operation.
 *
 * RPC API supports also an alternative syntax, where
 * parameters for an operation are passed in special <b>q</b> param
 * using query string. The value for <b>q</b> param must be <b>JSON</b> encoded.
 *
 * RPC API supports both GET and POST methods. GET should be used only
 * for operations that don't change the application state, usually these
 * are find/get operations.
 * 
 * RPC API responses are mostly in JSON format. Key <b>d</b> contains the
 * actual response data and key <b>e</b> contains error information.
 * 
 * HTTP response's status code is 200 for succesful operation.
 * When service or operation is missing or cannot be found a 404 status
 * code is returned. Further, some exceptions thrown by the actual service
 * are translated into special status codes, e.g. PermissionDeniedException
 * translates into status code 403.
 * 
 * You may control how verbose the error messages should be using
 * <strong>X-VALU-ERRORS</strong> header. It accepts values <strong>default</strong>
 * and <strong>verbose</strong> When using value <strong>verbose</strong>, error message 
 * is returned in raw format, that is it may contain variables, such as %MESSAGE%.
 * Also variables are returned in an array.
 * 
 * <strong>Example of using RPC API with HTTP POST:</strong>
 * <code>
 * POST /filesystem.file/upload HTTP/1.1
 * Host: api.valu.fi
 *
 * q={"url":“http://files.com/pic1.pdf”,“target”:“/page1/gallery”,“specs”:{"description":“New York 2012”}}
 * </code>
 * 
 * <strong>Example of a valid response:</strong>
 * <code>
 * {d:true}
 * </code>
 * 
 * <strong>Example of a response with error information:</strong>
 * <code>
 * {d:null, e:{m:"File not found", c:1001}}
 * </code>
 * 
 * <strong>Example of request and response with verbose error:</strong>
 * <code>
 * POST /filesystem.file/update HTTP/1.1
 * Host: api.valu.fi
 *
 * q={"query":“/pic1.pdf”,“specs”:{"description":“New York 2012”}}
 * </code>
 * 
 * Response data:
 * <code>
 * {"d":null,"e":{"m":"Permission denied for user %USER% to update %FILE%", "a":{"USER":"guest", "FILE":"/pic1.pdf"}, "c":1200}}
 * </code>
 * 
 * @author Juha Suni
 * @package Service
 */
class ServiceController extends AbstractActionController
{
    /**
     * HTTP status code for successful operation
     * @var int
     */
    const STATUS_SUCCESS = 200;
    
    /**
     * HTTP status code for "not found" exceptions
     * @var int
     */
    const STATUS_NOT_FOUND = 404;
    
    /**
     * HTTP status code for unknown exceptions
     * @var int
     */
    const STATUS_UNKNOWN_EXCEPTION = 500;
    
    /**
     * HTTP status code for "permission denied" exception
     * @var int
     */
    const STATUS_PERMISSION_DENIED = 403;
    
    /**
     * HTTP header to describe how verbose the
     * errors should be
     * @var string
     */
    const HEADER_ERRORS = 'X-VALU-ERRORS';
    
    /**
     * Header value for verbose error messages
     * @var string
     */
    const HEADER_ERRORS_VERBOSE = 'verbose';
    
    /**
     * Header value for verbose normal error messages
     * @var string
     */
    const HEADER_ERRORS_DEFAULT = 'default';
	
	/**
	 * PREG pattern to validate service name against
	 * 
	 * @var string
	 */
	protected $servicePattern = '/^[a-z][a-z0-9]+([\.\-][a-z0-9]+)*$/i';
	
	/**
	 * PREG pattern to validate operation name against
	 * 
	 * @var string
	 */
	protected $operationPattern = '/^[a-z][a-z0-9]+([\-][a-z0-9]+)*$/i';
	
	/**
	 * Performs service operation matched by HTTP router
	 * 
	 * @throws \Exception
	 * @return Response
	 */
	public function httpAction(){
	    
	    $status		= self::STATUS_SUCCESS;
	    $responses	= array();
	    $exception	= null;
	    $data       = null;

        try {
            $service	= $this->fetchService();
            $operation	= $service ? $this->fetchOperation() : false;
            
            if(!$service){
                throw new ServiceNotFoundException("Route doesn't contain service information");
            } elseif (!$operation) {
                throw new OperationNotFoundException("Route doesn't contain operation information");
            }
            
            // Parse parameters
            if ($this->getRequest()->isPost()) {
                $params = $this->getRequest()
                    ->getPost()
                    ->toArray();
            
                $params = array_merge(
                        $params,
                        $this->getRequest()
                        ->getFiles()
                        ->toArray()
                );
            } else {
                $params = $this->getRequest()
                    ->getQuery()
                    ->toArray();
            }
            
            // Merge params with special q parameter
            if (isset($params['q'])) {
                $params = array_merge(
                    JsonDecoder::decode($params['q'],
                    Json::TYPE_ARRAY), $params);
            
                unset($params['q']);
            }
            
            $data = $this->exec(
                    $service, 
                    $operation, 
                    $params, 
                    ServiceBroker::CONTEXT_HTTP);
            
        } catch (PermissionDeniedException $exception) {
            $status = self::STATUS_PERMISSION_DENIED;
        } catch (NotFoundException $exception) {
            $status = self::STATUS_NOT_FOUND;
        } catch (ServiceException $exception) {
            $status = self::STATUS_UNKNOWN_EXCEPTION;
        } catch (\Exception $exception) {}
	    
        // Log error
	    if ($exception) {
	        error_log($exception->getMessage() . ' (' . $exception->getFile().':'.$exception->getLine().')');
	    }
    
		return $this->prepareHttpResponse(
			$data, 
	        $status,
			$exception);
	}
	
	/**
	 * Performs service operation routed by console router
	 */
	public function consoleAction()
	{
	    $request   = $this->getRequest();
	    $service   = $this->fetchService();
	    $operation = $this->fetchOperation();
	    $query     = $this->fetchConsoleQuery();
	    
	    if (!$request instanceof ConsoleRequest) {
	        throw new \RuntimeException('You can only use this action from a console!');
	    }
	    
	    // Check verbose flag
	    $verbose = $request->getParam('verbose') || $request->getParam('v');
	    
	    $params = JsonDecoder::decode(
            $query,
            Json::TYPE_ARRAY
        );
	    
	    try {
	        $data = $this->exec($service, $operation, $params, ServiceBroker::CONTEXT_CLI);
	        return $this->prepareConsoleResponse($data, null, $verbose);
	    } catch (\Exception $exception) {
	        return $this->prepareConsoleResponse(null, $exception, $verbose);
	    }
	}
	
	/**
	 * Execute operation
	 * 
	 * @param string $service
	 * @param string $operation
	 * @param array|null $params
	 * @param string $context
	 */
	protected function exec($service, $operation, $params, $context)
	{
	    // Perform operation and fetch first result
	    return $this->serviceBroker()
    	    ->service($service)
    	    ->context($context)
    	    ->until(array($this, '_break'))
    	    ->exec($operation, $params)
    	    ->first();
	}

	/**
	 * Prepares HTTP response
	 * 
	 * @param mixed $data
	 * @param int $status
	 * @param \Exception|null $exception
	 * @return Response
	 */
	protected function prepareHttpResponse($data, $status, $exception = null){
	    
	    $error = $this->getRequest()->getHeader(
	            self::HEADER_ERRORS, 
	            self::HEADER_ERRORS_DEFAULT);
	    
	    if ($error instanceof HeaderInterface) {
	        $error = $error->getFieldValue();
	    }
	    
	    $response = $this->getResponse();
	    
	    $response->getHeaders()->addHeaders(array(
	    	'Cache-Control' => 'no-cache, must-revalidate',
	        'Pragma' 		=> 'no-cache',
	    	'Expires' 		=> '2000-01-01 00:00:00',
            'Content-Type'  => 'application/json',
	    ));
	    
	    // update response status code
	    $response->setStatusCode(
        	$status
        );
	    
	    // update reason phrase
	    if($exception){
	        
	        if ($exception instanceof ServiceException) {
	            $message = $exception->getMessage();
	        } else {
	            $message = 'Unknown exception';
	        }
	        
	    	$response->setReasonPhrase(
    	        $message
	    	);
	    }
	    
	    if ($data instanceof \Zend\Http\Response) {
	        return $data;
	    } else {
	        
	        $responseModel = array(
                'd' => $data
	        );
	        
	        // Append exception data
	        if ($exception) {
	            
	            if ($error == self::HEADER_ERRORS_VERBOSE && $exception instanceof ServiceException) {
	                $responseModel['e'] = array(
                        'm' => $exception->getRawMessage(),
                        'c' => $exception->getCode(),
                        'a' => $exception->getVars()
	                );
	                
	            } else {
	                $responseModel['e'] = array(
                        'm' => $response->getReasonPhrase(),
                        'c' => $exception->getCode(),
	                );
	            }
	        }
	        
	        $response->setContent(JsonEncoder::encode($responseModel));
	        return $response;
	    }
	}
	
	/**
	 * Prepares console response
	 * 
	 * @param mixed $data
	 * @param \Exception|null $exception
	 * @param boolean $verbose
	 */
	protected function prepareConsoleResponse($data, \Exception $exception = null, $verbose = false)
	{
	    if ($exception) {
	        
	        if ($verbose) {
	            $msg = $exception->getMessage() . ' (' . $exception->getFile().':'.$exception->getLine() . ')';
	        } else {
	            $msg = $exception->getMessage();
	        }
	        
	        return "Error: " . $msg . "\n";
	    }
	    
	    if (is_array($data) || is_object($data)) {
	        $json = JsonEncoder::encode($data);
	        return Json::prettyPrint($json) . "\n";
	    } else {
	        return $data;
	    }
	}
	
	/**
	 * Parse service name from request
	 * 
	 * @return string|boolean
	 */
	protected function fetchService()
	{
        $service = $this->getRouteParam('service');
        $service = $this->parseCanonicalName($service, true);
        
        if (preg_match($this->servicePattern, $service)) {
            return $service;
        } else {
            return false;
        }
    }
	
	/**
	 * Parse operation name from request
	 * 
	 * @return string|boolean
	 */
	protected function fetchOperation()
	{
        $operation = $this->getRouteParam('operation');
        $operation = $this->parseCanonicalName($operation);
        
        if (preg_match($this->operationPattern, $operation)) {
            return $operation;
        } else {
            return false;
        }
	}
	
	/**
	 * Retrieve console query
	 * 
	 * @return string
	 */
	protected function fetchConsoleQuery()
	{
	    return $this->getRouteParam('query');
	}
	
	/**
	 * @todo Does ZF provide a more convenient approach?
	 */
	protected function getRouteParam($param)
	{
	    return $this->getEvent()->getRouteMatch()->getParam($param);
	}
	
	/**
	 * Parse canonical service/operation name
	 * 
	 * @param string $name
	 * @param boolean $toUcc
	 * @return string
	 */
	protected function parseCanonicalName($name, $toUcc = false)
	{
        $array = explode('-', $name);
        
        if (sizeof($array) > 1) {
            $array = array_map('strtolower', $array);
        }
        
        $array = array_map('ucfirst', $array);
        
        $canonical = implode('', $array);
        
        return ! $toUcc ? lcfirst($canonical) : $canonical;
	}
	
	public function _break()
	{
	    return true;
	}
}