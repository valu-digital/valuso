<?php
namespace ValuSo\Proxy;

use ReflectionClass;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Zend\ServiceManager\Exception\RuntimeException;

/**
 * Class metadata for a generic service object
 */
class ServiceClassMetadata implements ClassMetadata
{
    /**
     * @var ReflectionClass
     */
    protected $reflectionClass;

    /**
     * @param object|string $service
     */
    public function __construct($service)
    {
        $this->reflectionClass = new ReflectionClass($service);
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return $this->reflectionClass->getName();
    }

    /**
     * {@inheritDoc}
     */
    public function getReflectionClass()
    {
        return $this->reflectionClass;
    }

    /**
     * {@inheritDoc}
     */
    public function hasField($fieldName)
    {
        return $this->reflectionClass->hasProperty($fieldName);
    }

    /**
     * {@inheritDoc}
     */
    public function getFieldNames()
    {
        $properties = $this->reflectionClass->getProperties();
        $fields     = array();

        foreach ($properties as $property) {
            $fields[] = $property->getName();
        }

        return $fields;
    }

    // @codeCoverageIgnoreStart

    /**
     * {@inheritDoc}
     */
    public function hasAssociation($fieldName)
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifier()
    {
        return array();
    }

    /**
     * {@inheritDoc}
     */
    public function isIdentifier($fieldName)
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isSingleValuedAssociation($fieldName)
    {
        throw new RuntimeException('Not implemented');
    }

    /**
     * {@inheritDoc}
     */
    public function isCollectionValuedAssociation($fieldName)
    {
        throw new RuntimeException('Not implemented');
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifierFieldNames()
    {
        return array();
    }

    /**
     * {@inheritDoc}
     */
    public function getAssociationNames()
    {
        return array();
    }

    /**
     * {@inheritDoc}
     */
    public function getTypeOfField($fieldName)
    {
        throw new RuntimeException('Not implemented');
    }

    /**
     * {@inheritDoc}
     */
    public function getAssociationTargetClass($assocName)
    {
        throw new RuntimeException('Not implemented');
    }

    /**
     * {@inheritDoc}
     */
    public function isAssociationInverseSide($assocName)
    {
        throw new RuntimeException('Not implemented');
    }

    /**
     * {@inheritDoc}
     */
    public function getAssociationMappedByTargetField($assocName)
    {
        throw new RuntimeException('Not implemented');
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifierValues($object)
    {
        throw new RuntimeException('Not implemented');
    }

    // @codeCoverageIgnoreEnd
}