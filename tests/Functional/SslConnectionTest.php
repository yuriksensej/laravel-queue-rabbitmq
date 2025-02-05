<?php

namespace Yuriksensej\LaravelQueueRabbitMQ\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Enqueue\AmqpLib\AmqpContext;
use Illuminate\Events\Dispatcher;
use Enqueue\AmqpLib\AmqpConnectionFactory;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use Yuriksensej\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;
use Yuriksensej\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;

/**
 * @group functional
 */
class SslConnectionTest extends TestCase
{
    public function testConnectorEstablishSecureConnectionWithRabbitMQBroker()
    {
        $config = [
            'factory_class' => AmqpConnectionFactory::class,
            'dsn'      => null,
            'host'     => getenv('HOST'),
            'port'     => getenv('PORT_SSL'),
            'login'    => 'guest',
            'password' => 'guest',
            'vhost'    => '/',
            'options' => [
                'exchange' => [
                    'name' => null,
                    'declare' => true,
                    'type' => \Interop\Amqp\AmqpTopic::TYPE_DIRECT,
                    'passive' => false,
                    'durable' => true,
                    'auto_delete' => false,
                ],

                'queue' => [
                    'name' => 'default',
                    'declare' => true,
                    'bind' => true,
                    'passive' => false,
                    'durable' => true,
                    'exclusive' => false,
                    'auto_delete' => false,
                    'arguments' => '[]',
                ],
            ],
            'ssl_params' => [
                'ssl_on'        => true,
                'cafile'        => getenv('RABBITMQ_SSL_CAFILE'),
                'local_cert'    => null,
                'local_key'     => null,
                'verify_peer'   => false,
                'passphrase'    => null,
            ],
        ];

        $connector = new RabbitMQConnector(new Dispatcher());
        /** @var RabbitMQQueue $queue */
        $queue = $connector->connect($config);

        $this->assertInstanceOf(RabbitMQQueue::class, $queue);

        /** @var AmqpContext $context */
        $context = $queue->getContext();
        $this->assertInstanceOf(AmqpContext::class, $context);

        $this->assertInstanceOf(AMQPSSLConnection::class, $context->getLibChannel()->getConnection());
        $this->assertTrue($context->getLibChannel()->getConnection()->isConnected());
    }
}
