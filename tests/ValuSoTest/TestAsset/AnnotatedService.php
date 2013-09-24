<?php
namespace ValuSoTest\TestAsset;

use ValuSo\Annotation as ValuService;

class AnnotatedService
{
    /**
     * Create operation
     * 
     * @param string $name
     * @param string|null $property
     * @param array $specs
     * @return boolean
     * 
     * @ValuService\Context({"cli", "http", "http-put"})
     * @ValuService\Alias({"httpPut"})
     */
    public function create($name, $property = null, array $specs = array())
    {
        return true;
    }
    
    /**
     * Update operation
     *
     * @param string|array $query
     * @param array $specs
     * @return boolean
     *
     * @ValuService\Context({"cli", "http", "http-post"})
     * @ValuService\Alias({"httpPost"})
     */
    public function update($query, array $specs = array())
    {
        return true;   
    }
    
    /**
     * @ValuService\Exclude
     */
    public function internal()
    {
        return false;
    }
}