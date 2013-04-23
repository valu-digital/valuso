<?php
namespace ValuSo\Feature;

use ArrayAccess;

interface IdentityAwareInterface
{
    /**
     * Inject identity
     * 
     * @param ArrayAccess $identity
     */
    public function setIdentity(ArrayAccess $identity);
}