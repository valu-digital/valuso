<?php
namespace ValuSo\Feature;

use ArrayObject;
use ValuSo\Annotation as ValuService;

trait OptionsTrait
{
    /**
     * Options
     */
    protected $options;
    
    /**
     * Set service options from array or traversable object
     *
     * @param  array|Traversable $options
     * @return \stdClass
     * 
     * @ValuSo\Exclude
     */
    public function setOptions($options)
    {
        if (!is_array($options) && !$options instanceof \Traversable) {
            throw new \InvalidArgumentException(sprintf(
                    'Parameter provided to %s must be an array or Traversable',
                    __METHOD__
            ));
        }
    
        foreach ($options as $key => $value){
            $this->setOption($key, $value);
        }
         
        return $this;
    }
    
    /**
     * Retrieve service options
     *
     * @return array
     * 
     * @ValuSo\Exclude
     */
    public function getOptions()
    {
        if(!$this->options){
            if (isset($this->optionsClass)) {
                $this->options = new $this->optionsClass(array());
            } else {
                $this->options = new ArrayObject(array());
            }
            
            if ($this->options instanceof \ArrayObject) {
                $this->options->setFlags(\ArrayObject::ARRAY_AS_PROPS);
            }
        }
    
        return $this->options;
    }
    
    /**
     * Is an option present?
     *
     * @param  string $key
     * @return bool
     * 
     * @ValuSo\Exclude
     */
    public function hasOption($key)
    {
        return $this->getOptions()->__isset($key);
    }
    
    /**
     * Set option
     *
     * @param string $key
     * @param mixed $value
     * @return AbstractService
     * 
     * @ValuSo\Exclude
     */
    public function setOption($key, $value)
    {
        $this->getOptions()->__set($key, $value);
        return $this;
    }
    
    /**
     * Retrieve a single option
     *
     * @param  string $key
     * @return mixed
     * 
     * @ValuSo\Exclude
     */
    public function getOption($key, $default = null)
    {
        if ($this->hasOption($key)) {
            return $this->getOptions()->__get($key);
        }
        
        return $default;
    }
    
    /**
     * Clears all service options
     * 
     * @return \stdClass
     * 
     * @ValuSo\Exclude
     */
    public function clearOptions()
    {
        $this->options = null;
        return $this;
    }
    
    /**
     * Set options as config file, array or traversable
     * object
     *
     * @param string|array|\Traversable $config
     * 
     * @ValuSo\Exclude
     */
    public function setConfig($config)
    {
        if(is_string($config) && file_exists($config)){
            $config = \Zend\Config\Factory::fromFile($config);
        }
    
        if(!is_array($config) && !($config instanceof \Traversable)){
            throw new \InvalidArgumentException(
                    sprintf('Config must be an array, Traversable object or filename; %s received',
                            is_object($config) ? get_class($config) : gettype($config)));
        }
    
        $this->setOptions($config);
    }
}