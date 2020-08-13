<?php

namespace App;

use Enqueue\RdKafka\RdKafkaConnectionFactory;

class KafkaFactory
{
    private $context;
    private $factory;

    public function __construct()
    {
        $this->factory = new RdKafkaConnectionFactory([
            'global' => [
                'group.id' => 'php-worker',
                'metadata.broker.list' => 'kafka1:9092,kafka2:9092,kafka3:9092',
                'enable.auto.commit' => 'false'
            ]
        ]);

        $this->context = $this->factory->createContext();
    }

    public function getContext()
    {
        return $this->context;
    }

    public function dispatch(string $topic, array $payload)
    {
        $payload = json_encode($payload);

        try {
            $message = $this->context->createMessage($payload);
            $topic = $this->context->createTopic($topic);
            $this->context->createProducer()->send($topic, $message);

            echo $payload . PHP_EOL;
        } catch( \Exception $e ) {
            echo $e->getMessage();
        }
    }
}