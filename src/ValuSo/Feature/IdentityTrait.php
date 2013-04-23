<?php
namespace ValuSo\Feature;

use ArrayAccess;

trait IdentityTrait
{
    /**
     * Identity
     * 
     * @var \ArrayAccess
     */
    protected $identity;
    
    /**
     * Retrieve identity or identity spec
     *
     * @param string|null $spec
     * @param mixed       $default
     * @return \ArrayAccess|null
     */
    public function getIdentity($spec = null, $default = null)
    {
        if ($spec !== null) {
            return isset($this->identity[$spec]) ? $this->identity[$spec] : $default;
        } else {
            return $this->identity;
        }
    }
    
    /**
     * @see \ValuAuth\ServiceBroker\IdentityAwareInterface::setIdentity()
     */
    public function setIdentity(ArrayAccess $identity)
    {
        $this->identity = $identity;
    }
}