<?php
namespace ValuSo\Listener;

use ValuSo\Broker\ServiceEvent;
use ValuSo\Broker\ServiceBroker;
use ValuSo\Command\CommandInterface;
use ValuSo\Exception;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;

class UploadListenerAggregate implements ListenerAggregateInterface
{
    
    /**
     * Name of the service
     * 
     * @var string
     */
    private $service;
    
    /**
     * Name of the operation
     * 
     * @var string
     */
    private $operation;
    
    /**
     * Name of the parameters
     * 
     * @var array
     */
    private $params = array();
    
    /**
     * Temporary directory
     * 
     * @var string
     */
    private $tempDir;

    /**
     * Temporary files by event ID
     * 
     * @var array
     */
    private $tmpFiles = array();
    
    /**
     * Attached listeners
     * 
     * @var array
     */
    protected $listeners;
    
    public function __construct($service, $operation, array $params, $tempDir = null)
    {
        $this->service    = $service;
        $this->operation  = $operation;
        $this->params     = $params;
        $this->tempDir    = $tempDir ? $tempDir : sys_get_temp_dir();
    }
    
    /**
     * (non-PHPdoc)
     * @see \Zend\EventManager\ListenerAggregateInterface::attach()
     */
    public function attach(EventManagerInterface $events)
    {
        $suffix = strtolower($this->getService() . '.' . $this->getOperation());
            
        // Attach listeners for init and final
        $this->listeners[] = $events->attach('init.' . $suffix, array($this, 'prepareUpload'));
        $this->listeners[] = $events->attach('final.' . $suffix, array($this, 'finalizeUpload'));
    }
    
    /**
     * (non-PHPdoc)
     * @see \Zend\EventManager\ListenerAggregateInterface::detach()
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    /**
     * Prepares upload parameters by moving PHP's upload
     * tmp files to a new location using the correct filename.
     * After this operation, the event parameters are updated so
     * that the PHP upload data is replaced by filename(s)
     * in local filesystem.
     * 
     * @param CommandInterface $command
     */
    public function prepareUpload(CommandInterface $command)
    {
        if (strpos($command->getContext(), 'http') !== 0) {
            return;
        }
        
        $uploads = array();
        
        foreach ($this->params as $param) {
            $upload = $command->getParam($param);
            
            if ($this->isPhpFileUpload($upload)) {
                // Handle error
                $this->handleUploadError($upload['error']);
                
                $uploads[$param] = $upload;
            }
        }
        
        if (sizeof($uploads)) {
            foreach ($uploads as $param => $upload) {
                $files = $this->movePhpUploadTmpFiles($upload);
                $this->tmpFiles[spl_object_hash($command)] = $files;
                
                if ($this->getNumberOfUploads($upload) == 1) {
                    $command->setParam($param, array_pop($files));
                } else {
                    $command->setParam($param, $files);
                }
            }
        }
    }
    
    /**
     * Finalizes upload by removing any tmp files left from
     * prepareUpload
     * 
     * @param \ValuSo\Broker\ServiceEvent $event
     */
    public function finalizeUpload(ServiceEvent $event)
    {
        $id = spl_object_hash($event->getCommand());
        $this->removeTmpFiles($id);
    }
    
    /**
     * Retrieve the name of the service to listen to
     * 
     * @return string
     */
    public function getService()
    {
        return $this->service;
    }
    
	/**
	 * Retrieve the name of the operation to listen to
	 * 
     * @return string
     */
    public function getOperation()
    {
        return $this->operation;
    }
    
    /**
     * Retrieve the name of the params that are expected
     * to contain upload information
     * 
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }
    
    protected function getTempDir()
    {
        return $this->tempDir;
    }
    
    /**
     * Make sure that all temporary files are removed when object
     * is released
     */
    public function __destruct()
    {
        if (sizeof($this->tmpFiles)) {
            foreach (array_keys($this->tmpFiles) as $id) {
                $this->removeTmpFiles($id);
            }
        }
    }
    
    /**
     * Remove any temp files by event ID
     * 
     * @param string $id
     * @return UploadListenerAggregate
     */
    protected function removeTmpFiles($id)
    {
        if (isset($this->tmpFiles[$id])) {
            foreach ($this->tmpFiles[$id] as $tmpFile) {
                
                if (file_exists($tmpFile)) {
                    unlink($tmpFile);
                }
                
                unset($this->tmpFiles[$id]);
            }
        }
        
        return $this;
    }
    
    /**
     * Test whether given value contains typical PHP's upload
     * parameters
     * 
     * @param mixed $value
     * @return boolean
     */
    protected function isPhpFileUpload($value)
    {
        if (is_array($value) 
            && isset($value['tmp_name']) 
            && isset($value['name'])) {
            
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Retrieve number of files defined in given upload param
     * 
     * @param array $upload
     * @return number
     */
    protected function getNumberOfUploads(array $upload)
    {
        return is_array($upload['tmp_name']) ? sizeof($upload['tmp_name']) : 1;
    }
    
    /**
     * Move uploaded files to a new location, where the
     * real filename is used instead of the temporary one
     * 
     * @param array $upload
     * @return array
     */
    protected function movePhpUploadTmpFiles(array $upload)
    {
        $asArray     = is_array($upload['tmp_name']);
        $tmpFiles    = (array) $upload['tmp_name'];
        $filenames   = (array) $upload['name'];
        $files       = array();
        
        foreach ($tmpFiles as $key => $tmpFile) {
            
            if (!is_uploaded_file($tmpFile)) {
                throw new Exception\UploadException(
                    'File is not an uploaded file', array(), 10110);
            }
            
            $filename   = $filenames[$key];
            $tmpDir     = $this->getTempDir();
            $targetDir  = $tmpDir . '/' . md5(basename($tmpFile) . microtime(true));
            $file       = $targetDir . DIRECTORY_SEPARATOR . $filename;
            
            if (!file_exists($targetDir) && mkdir($targetDir)) {
                move_uploaded_file($tmpFile, $file);
                
                $files[] = realpath($file);
            }
        }

        return $files;
    }
    
    /**
     * Handle file upload errors
     *
     * @param int $error
     * @throws MaxFileSizeExceededException
     * @throws Exception\UploadException
     */
    protected function handleUploadError($error)
    {
    
        switch($error){
            case UPLOAD_ERR_OK:
                return;
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception\UploadException(
                    'Maximum upload file size exceeded', array(), 10100+$error);
                break;
            case UPLOAD_ERR_PARTIAL:
                throw new Exception\UploadException(
                    'File was only partially uploaded', array(), 10100+$error);
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new Exception\UploadException(
                    'No file was uploaded', array(), 10100+$error);
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new Exception\UploadException(
                    'Invalid upload_tmp_dir; directory is not writable', array(), 10100+$error);
                break;
            case UPLOAD_ERR_CANT_WRITE:
                throw new Exception\UploadException(
                    'Unable to write uploaded file', array(), 10100+$error);
                break;
            default:
                throw new Exception\UploadException(
                    'Unknown error when uploading the file', array(), 10100+$error);
                break;
        }
    }
}