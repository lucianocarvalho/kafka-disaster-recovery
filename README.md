# Apache Kafka Disaster Recovery

> Ring for some experiments with Apache Kafka.

Checking the behavior of Kafka in some scenarios:

- Failure of some brokers
- Add more brokers on the fly
- Increase partitions
- Decrease partitions
- Reassign partitions
- Kill leader brokers
- Long tasks executions
- Invalids acks and rejects
- Elevated number of partitions
- Modify offsets on the fly

But you can use to make some beanchmarks:
- Commit offset sync and async
- Max.poll.interval.ms values
- Cleanup polices
- Kafka Message Key
- **Use your creativity :)**

## About

#### Requirements

- **[Docker](https://www.docker.com/)** working in your machine.
- **[docker-compose](https://docs.docker.com/compose/)** already installed.

#### Containers

For testing purposes, we setup a Kafka cluster with 3 brokers.

To produce and consume messages, we write a simple code using PHP and **[enqueue](https://github.com/php-enqueue)**.

To view cluster settings, topics and messages, we use **[Kafdrop](https://github.com/obsidiandynamics/kafdrop)** and **[Kowl](https://github.com/cloudhut/kowl)**.

| About                | Image                       | Internal Port | External Port |
|----------------------|-----------------------------|---------------|---------------|
| **Zookeeper**        | `confluentinc/cp-zookeeper` | 2181          | 2181          |
| **Kafka Broker 1**   | `confluentinc/cp-kafka`     | 9092          | 9093          |
| **Kafka Broker 2**   | `confluentinc/cp-kafka`     | 9092          | 9094          |
| **Kafka Broker 3**   | `confluentinc/cp-kafka`     | 9092          | 9096          |
| **Kafdrop (Web UI)** | `obsidiandynamics/kafdrop`  | 9000          | 9000          |
| **Kowl (Web UI)**    | `quay.io/cloudhut/kowl`     | 8080          | 8080          |
| **PHP (worker)**     | `php:7.4-cli`               | -             | -             |

## Quick start

```
# Clone the repository
git clone https://github.com/lucianocarvalho/kafka-disaster-recovery.git

# Setup the containers
cd kafka-disaster-recovery && docker-compose up -d

# Install the dependencies locally
docker exec -ti php-worker composer install
```

To open Kafdrop, go to **[http://localhost:9000/](http://localhost:9000/)**.

To open Kowl, go to **[http://localhost:8080/](http://localhost:8080/)**.

## How to use

Producers and consumers are implemented at `/src` folder. Feel free to edit the files to suit your tests.

To execute producers and consumers in a more intuitive way, it's easier get bash of the container:

```bash
docker exec -ti php-worker bash
```

### Consumer

Start a single consumer to a specific topic name:
```bash
php consumer.php test-topic
```

### Producer
Produce a random message to a specific topic name:
```bash
php producer.php test-topic
```

### Apache Kafka Command-Line Tools

Kafka's internal scripts can also be useful in your tests.

```bash
# Get the bash from some broker
docker exec -ti <random-kafka-broker-container> bash

# List all topics using kafka-topics
/usr/bin/kafka-topics --list --zookeeper zookeeper:2181
```

**Now, use your creativity :)**

## Author

- Luciano Carvalho ([@lucianocarvalho](https://github.com/lucianocarvalho))