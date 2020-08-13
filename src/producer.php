<?php

namespace App;

require_once __DIR__ . '/vendor/autoload.php';

use App\KafkaFactory;

class Producer
{
    private $factory;

    public function __construct(KafkaFactory $factory)
    {
        $this->factory = $factory;
    }

    public function dispatch(string $destination, $payload) : void
    {
        try {
            $this->factory->dispatch($destination, $payload);
        } catch(\Exception $e) {
            echo $e->getMessage();
        }
    }
}

$destination = $argv[1] ?? 'default';
$payload = [
    'id' => uniqid()
];

$producer = (new Producer(new KafkaFactory))->dispatch(
    $destination,
    $payload
);