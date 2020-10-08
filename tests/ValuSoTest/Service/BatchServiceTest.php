<?php

namespace ValuSoTest\Service;

use ValuSoTest\Mock\MockIdentity;
use PHPUnit_Framework_TestCase as TestCase;
use ValuSo\Broker\ServiceBroker;
use ValuSo\Service\BatchService;
use Zend\Json\Server\Smd\Service;
use Zend\Stdlib\ArrayObject;

class BatchServiceTest extends TestCase
{

    private $batchService;
    private $serviceBroker;

    public $identity;
    public $changedIdentity;

    public function setUp()
    {
        $this->identity = new MockIdentity(new \ArrayObject([
            'username' => 'batch-tester',
            'superuser' => false
        ]));

        $self = $this;

        $this->serviceBroker = $broker = new ServiceBroker();
        $this->serviceBroker->setDefaultIdentity($this->identity);
        $this->batchService = new BatchService();
        $this->batchService->setServiceBroker($this->serviceBroker);

        $broker->getLoader()->registerService('batch', 'Batch', $this->batchService);

        $broker->getLoader()->registerService('identity', 'Identity', function ($command) use ($self) {
            if ($command->getOperation() === 'getIdentity') {
                return $self->changedIdentity ? $self->changedIdentity : $self->identity;
            } else {
                if ($command->getOperation() === 'setIdentity') {
                    $self->changedIdentity = new MockIdentity($command->getParam(0));
                }
            }
        });

        $broker->getLoader()->registerService('runner', 'Runner', function ($command) {
            if ($command->getOperation() === 'run') {
                return 'running';
            }
        });

        $broker->getLoader()->registerService('flyer', 'Flyer', function ($command) {
            if ($command->getOperation() === 'fly') {
                return 'flying';
            } elseif ($command->getOperation() === 'crash') {
                throw new \Exception('Crashed');
            }
        });

    }

    public function testExecuteBatch()
    {
        $result = $this->serviceBroker->service('Batch')->execute([
            'run' => ['service' => 'Runner', 'operation' => 'run'],
            'fly' => ['service' => 'Flyer', 'operation' => 'fly'],
        ]);

        $this->assertEquals(['run' => 'running', 'fly' => 'flying'], $result);
    }

    public function testExecuteBatchInVerboseMode()
    {
        $result = $this->serviceBroker->service('Batch')->execute([
            'run' => ['service' => 'Runner', 'operation' => 'run'],
            'fly' => ['service' => 'Flyer', 'operation' => 'crash'],
        ], ['verbose' => true]);

        $this->assertEquals([
            'results' => ['run' => 'running', 'fly' => null],
            'errors' => ['fly' => ['m' => 'Unknown error', 'c' => 0]]
        ], $result);
    }

    public function testExecuteBatchRestoresIdentity()
    {
        $self = $this;
        $broker = $this->serviceBroker;

        $broker->getLoader()->registerService('test', 'Test', function ($command) use ($broker) {
            if ($command->getOperation() === 'first') {
                $broker->service('Identity')->setIdentity(['superuser' => true]);
                return $broker->service('Identity')->getIdentity()->toArray()['superuser'];
            } elseif ($command->getOperation() === 'second') {
                return $broker->service('Identity')->getIdentity()->toArray()['superuser'];
            }
        });

        $result = $this->serviceBroker->service('Batch')->execute([
            'first' => ['service' => 'Test', 'operation' => 'first'],
            'second' => ['service' => 'Flyer', 'operation' => 'second'],
        ]);

        $this->assertEquals(['first' => true, 'second' => false], $result);
    }

    public function testExecuteBatchRestoresIdentityWhenCommandFails()
    {
        $self = $this;
        $broker = $this->serviceBroker;

        $broker->getLoader()->registerService('test', 'Test', function ($command) use ($broker) {
            if ($command->getOperation() === 'first') {
                $broker->service('Identity')->setIdentity(['superuser' => true]);
                throw new \Exception('Unknown');
            } elseif ($command->getOperation() === 'second') {
                return $broker->service('Identity')->getIdentity()->toArray()['superuser'];
            }
        });

        $result = $this->serviceBroker->service('Batch')->execute([
            'first' => ['service' => 'Test', 'operation' => 'first'],
            'second' => ['service' => 'Flyer', 'operation' => 'second'],
        ]);

        $this->assertEquals(['first' => null, 'second' => false], $result);
    }
}