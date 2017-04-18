<?php
namespace ValuSo\Listener;

use ValuSo\Broker\ServiceEvent;
use ValuSo\Exception;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;

class UploadListenerAggregate implements ListenerAggregateInterface
{
    
    /**
     * Name of the "before" event
     * 
     * @var string
     */
    protected $eventBefore;
    
    /**
     * Name of the "after" event
     * 
     * @var string
     */
    protected $eventAfter;
    
    /**
     * Name of the parameters
     * 
     * @var array
     */
    protected $params = array();
    
    /**
     * Temporary directory
     * 
     * @var string
     */
    protected $tempDir;
    
    /**
     * Whether or not paths should be converted to local (file://)
     * URLs by default.
     * 
     * @var boolean
     */
    protected $pathToUrl = false;

    /**
     * Temporary files by event ID
     * 
     * @var array
     */
    protected $tmpFiles = array();
    
    /**
     * Attached listeners
     * 
     * @var array
     */
    protected $listeners;
    
    public function __construct($eventBefore, $eventAfter, array $params, $tempDir = null, $pathToUrl = false)
    {
        $this->eventBefore = strtolower($eventBefore);
        $this->eventAfter  = strtolower($eventAfter);
        $this->params     = $params;
        $this->tempDir    = $tempDir ? $tempDir : sys_get_temp_dir();
        $this->pathToUrl  = $pathToUrl;
    }
    
    /**
     * (non-PHPdoc)
     * @see \Zend\EventManager\ListenerAggregateInterface::attach()
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach($this->eventBefore, array($this, 'prepareUpload'));
        $this->listeners[] = $events->attach($this->eventAfter, array($this, 'finalizeUpload'));
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
     * @param \ValuSo\Broker\ServiceEvent $event
     */
    public function prepareUpload(ServiceEvent $event)
    {
        $command = $event->getCommand();
        
        if (strpos($command->getContext(), 'http') !== 0) {
            return;
        }
        
        $uploads = array();
        
        foreach ($this->params as $param) {
            $upload = $event->getParam($param);
            
            if ($this->isPhpFileUpload($upload)) {
                // Handle error
                $this->handleUploadError($upload['error']);
                
                $uploads[$param] = $upload;
            }
        }
        
        if (sizeof($uploads)) {
            foreach ($uploads as $param => $upload) {
                $files = $this->movePhpUploadTmpFiles($upload);
                $tmpFiles = [];
                
                foreach ($files as $path) {
                    $tmpFiles[] = $path;
                    $tmpFiles[] = dirname($path);
                }
                
                $this->tmpFiles[spl_object_hash($command)] = $tmpFiles;
                
                if ($this->pathToUrl) {
                    foreach ($files as &$value) {
                        $value = 'file://' . $value;
                    }
                }
                
                if ($this->getNumberOfUploads($upload) == 1) {
                    $event->setParam($param, array_pop($files));
                } else {
                    $event->setParam($param, $files);
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
                    if (is_dir($tmpFile)) {
                        rmdir($tmpFile);       
                    } else {
                        unlink($tmpFile);
                    }
                }
            }
            
            unset($this->tmpFiles[$id]);
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
            $targetDir  = $tmpDir . '/valu_so_upload_' . md5(basename($tmpFile) . microtime(true));
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