<?php

namespace Yuriksensej\LaravelQueueRabbitMQ\Queue\Connectors;

use Illuminate\Support\Arr;
use Interop\Amqp\AmqpContext;
use InvalidArgumentException;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Events\JobFailed;
use Interop\Amqp\AmqpConnectionFactory;
use Enqueue\AmqpTools\DelayStrategyAware;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\WorkerStopping;
use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Yuriksensej\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;
use Interop\Amqp\AmqpConnectionFactory as InteropAmqpConnectionFactory;
use Enqueue\AmqpLib\AmqpConnectionFactory as EnqueueAmqpConnectionFactory;
use Yuriksensej\LaravelQueueRabbitMQ\Horizon\Listeners\RabbitMQFailedEvent;
use Yuriksensej\LaravelQueueRabbitMQ\Horizon\RabbitMQQueue as HorizonRabbitMQQueue;

class RabbitMQConnector implements ConnectorInterface
{
    /**
     * @var Dispatcher
     */
    private $dispatcher;

    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Establish a queue connection.
     *
     * @param array $config
     *
     * @return Queue
     * @throws \ReflectionException
     */
    public function connect(array $config): Queue
    {
        $factoryClass = Arr::get($config, 'factory_class', EnqueueAmqpConnectionFactory::class);

        if (! class_exists($factoryClass) || ! (new \ReflectionClass($factoryClass))->implementsInterface(InteropAmqpConnectionFactory::class)) {
            throw new \LogicException(sprintf('The factory_class option has to be valid class that implements "%s"', InteropAmqpConnectionFactory::class));
        }

        /** @var AmqpConnectionFactory $factory */
        $factory = new $factoryClass([
            'dsn' => Arr::get($config, 'dsn'),
            'host' => Arr::get($config, 'host', '127.0.0.1'),
            'port' => Arr::get($config, 'port', 5672),
            'user' => Arr::get($config, 'login', 'guest'),
            'pass' => Arr::get($config, 'password', 'guest'),
            'vhost' => Arr::get($config, 'vhost', '/'),
            'ssl_on' => Arr::get($config, 'ssl_params.ssl_on', false),
            'ssl_verify' => Arr::get($config, 'ssl_params.verify_peer', true),
            'ssl_cacert' => Arr::get($config, 'ssl_params.cafile'),
            'ssl_cert' => Arr::get($config, 'ssl_params.local_cert'),
            'ssl_key' => Arr::get($config, 'ssl_params.local_key'),
            'ssl_passphrase' => Arr::get($config, 'ssl_params.passphrase'),
        ]);

        if ($factory instanceof DelayStrategyAware) {
            $factory->setDelayStrategy(new RabbitMqDlxDelayStrategy());
        }

        /** @var AmqpContext $context */
        $context = $factory->createContext();

        $this->dispatcher->listen(WorkerStopping::class, function () use ($context) {
            $context->close();
        });

        $worker = Arr::get($config, 'worker', 'default');

        if ($worker === 'default') {
            return new RabbitMQQueue($context, $config);
        }

        if ($worker === 'horizon') {
            $this->dispatcher->listen(JobFailed::class, RabbitMQFailedEvent::class);

            return new HorizonRabbitMQQueue($context, $config);
        }

        if ($worker instanceof RabbitMQQueue) {
            return new $worker($context, $config);
        }

        throw new InvalidArgumentException('Invalid worker.');
    }
}
