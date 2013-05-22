<?php
namespace ValuSo\Service;

use ValuSo\Command\CommandInterface;
use ValuSo\Exception;
use ValuSo\Feature;

/**
 * Service for invoking batch operations
 */
class BatchService
      implements Feature\ServiceBrokerAwareInterface{
    
    use Feature\ServiceBrokerTrait;
    
    /**
     * Command
     * 
     * @var CommandInterface
     */
    protected $command;

    public function __invoke(CommandInterface $command)
    {
        if ($this->command) {
            throw new Exception\ServiceException(
                'Batch service cannot be invoked recursively');
        }
        
        $this->command = $command;
        
        if ($command->getOperation() === 'execute') {
            $response = $this->execute(
                $command->getParam('commands', $command->getParam(0)),
                $command->getParam('options', $command->getParam(1)));
            
            $this->command = null;
            return $response;
        } else {
            $this->command = null;
            throw new Exception\UnsupportedOperationException(
                'Operation %OPERATION% is not supported', array('OPERATION' => $command->getOperation()));
        }
    }
    
    /**
     * Batch execute operations in other service(s)
     * 
     * Example of using batchExec():
     * <code>
     * $broker->service('Batch')->execute(
     *     [
     *         'cmd1' => ["service" => "filesystem.directory", "operation" => "create", "params" => ["path" => "/accounts/mc"]],
     *         'cmd2' => ["service" => "filesystem", "operation" => "exists", "params" => ["query" => "/accounts/mc"]],
     *     ]
     * );
     * </code>
     * 
     * The above might return something like this:
     * <code>
     * [
     *     'cmd1' => {object},
     *     'cmd2' => true
     * ]
     * </code>
     * 
     * Currently any error will simply result to false. If the first operation
     * of the previous example failed, the result might be something like:
     * <code>
     * [
     *     'cmd1' => false,
     *     'cmd2' => true
     * ]
     * </code>
     * 
     * @param array $commands
     * @param array $options
     * @throws Exception\InvalidCommandException
     * @return array
     */
    protected function execute(array $commands, $options = array())
    {
        $options = is_array($options) ? $options : array();
        
        $options = array_merge(
            array(),
            $options
        );
        
        $workers = array();
        foreach ($commands as $key => &$data) {

            $data['service'] = isset($data['service']) 
                ? $data['service'] : $options['service'];
            
            $data['operation'] = isset($data['operation']) 
                ? $data['operation'] : $options['operation'];
            
            $data['params'] = isset($data['params']) 
                ? $data['params'] : array();

            if (!isset($data['service'])) {
                throw new Exception\InvalidCommandException(
                    "Missing 'service' from command definition");
            } elseif ($data['service'] === $this->command->getService()) {
                throw new Exception\InvalidCommandException(
                    "Cannot call 'Batch' recursively");
            }
            
            if (!isset($data['operation'])) {
                throw new Exception\InvalidCommandException(
                    "Missing 'operation' from command definition");
            }
            
            if (!is_array($data['params'])) {
                throw new Exception\InvalidCommandException(
                    "Invalid 'params' in command definition");
            }
            
            // Initialize new worker
            $workers[$key] = $this->createWorker($data['service'])->args($data['params']);
        }
        
        $responses = array();
        foreach ($workers as $key => $worker) {
            try{
                $responses[$key] = $worker
                    ->exec($commands[$key]['operation'])
                    ->first();
                
            } catch(\Exception $e) {
                $responses[$key] = false;
            }
        }
        
        return $responses;
    }
    
    /**
     * Create service worker
     * 
     * @return \ValuSo\Broker\Worker
     */
    protected function createWorker($service)
    {
        return $this->getServiceBroker()
            ->service($service)
            ->context($this->command->getContext());
    }
}