<?php
namespace ValuSo\Feature;

interface ConfigurableInterface
{
    /**
     * Set options as config file, array or traversable
     * object
     *
     * @param string|array|\Traversable $config
     */
    public function setConfig($config);
}