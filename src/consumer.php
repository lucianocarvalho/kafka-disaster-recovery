<?php

namespace App;

require_once __DIR__ . '/vendor/autoload.php';

use Enqueue\Consumption\QueueConsumer;
use Enqueue\Consumption\ChainExtension;
use Interop\Queue\Processor;
use Interop\Queue\Message;
use Interop\Queue\Context;
use Enqueue\Consumption\Result;

class Consumer
{
    private $factory;

    public function __construct(KafkaFactory $factory)
    {
        $this->factory = $factory;
    }

    public function consume(string $topic) : void
    {
        $consumer = new QueueConsumer(
            $this->factory->getContext()
        );

        $consumer->bind($topic, new class implements Processor {
                public function process(Message $message, Context $context) {
                    var_dump($message->getBody());
                    return Result::ACK;
                }
            }
        );

        try {
            $consumer->consume();
        } catch (Exception $e) {
            var_dump($e->getMessage());
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }
    }
}

$topic = $argv[1] ?? 'default';

$consumer = (new Consumer(new KafkaFactory))->consume($topic);