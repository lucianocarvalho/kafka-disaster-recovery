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

| About                | Image                       | Internal Port | External Port |
|----------------------|-----------------------------|---------------|---------------|
| **Zookeeper**        | `confluentinc/cp-zookeeper` | 2181          | 2181          |
| **Kafka Broker 1**   | `confluentinc/cp-kafka`     | 9092          | 9093          |
| **Kafka Broker 2**   | `confluentinc/cp-kafka`     | 9092          | 9094          |
| **Kafdrop (Web UI)** | `obsidiandynamics/kafdrop`  | 9000          | 9000          |

## Quick start

```
# Clone the repository
git clone https://github.com/lucianocarvalho/kafka-disaster-recovery.git

# Setup the containers
cd kafka-disaster-recovery && docker-compose up -d
```

To open Kafdrop, open **[http://localhost:9000/](http://localhost:9000/)**.

## Scenarios

WIP

## Author

- Luciano Carvalho ([@lucianocarvalho](https://github.com/lucianocarvalho))