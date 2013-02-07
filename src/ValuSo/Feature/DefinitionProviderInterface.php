<?php
namespace ValuSo\Feature;

interface DefinitionProviderInterface
{
    
    /**
     * Retrieve service version
     * 
     * @return string
     */
    public static function version();
    
	/**
	 * Define service
	 * 
	 * @return \Valu\Service\Definition Service definition
	 */
	public function define();
}