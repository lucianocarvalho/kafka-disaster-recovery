# Roleplay

Balanceando as partições de um tópico on the fly.

### Cenário 1
Nesse cenário, temos 3 brokers e vamos criar um tópico com 1 partições e replication factor de 2.

Com isso, conseguimos deixar algum broker atoa (para migrarmos dados pra ele depois).

```bash
kafka-topics --zookeeper zookeeper:2181 --topic roleplay --create --partitions 1 --replication-factor 2
```

Vamos visualizar os dados desse tópico:

```bash
kafka-topics --zookeeper zookeeper:2181 --topic roleplay --describe
```

```
Topic: roleplay PartitionCount: 1   ReplicationFactor: 2    Configs:
    Topic: roleplay Partition: 0    Leader: 3   Replicas: 3,2   Isr: 3,2
```

Podemos reparar que o tópico `roleplay` tem apenas uma partições e replication factor de 2.

A partição `0` está nos brokers `3` e `2`, sendo o `3` o leader.

Vamos preparar o arquivo `roleplay.json` para migrar dados também para o broker 1.

```json
{
   "topics":[{"topic":"roleplay"}],
   "version":1
}
```

```bash
echo "{\"topics\":[{\"topic\":\"roleplay\"}],\"version\":1}" > roleplay.json
```

Com o json em mãos, geramos o arquivo de migração.

Apenas para testes, como o leader é o 3, eu quero mover esse tópico para os brokers 1 e 2.

```bash
kafka-reassign-partitions --zookeeper zookeeper:2181 \
                --topics-to-move-json-file roleplay.json \
                --broker-list "1,2" \
                --generate
```

O comando vai gerar as estratégias de rebalanceamento para nós:

```
Current partition replica assignment
{"version":1,"partitions":[{"topic":"roleplay","partition":0,"replicas":[3,2],"log_dirs":["any","any"]}]}

Proposed partition reassignment configuration
{"version":1,"partitions":[{"topic":"roleplay","partition":0,"replicas":[2,1],"log_dirs":["any","any"]}]}
```

Basicamente a estratégia gerada é mover o tópico `roleplay` do broker 3,2 para 2,1.

Criamos um novo arquivo json com essa estratégia para a execução:

```bash
echo "{\"version\":1,\"partitions\":[{\"topic\":\"roleplay\",\"partition\":0,\"replicas\":[2,1],\"log_dirs\":[\"any\",\"any\"]}]}" > strategy.json
```

Ok! Temos o arquivo para migração. Mas...

**O que acontece se eu migrar um tópico consumers e producers rolando?**

Vamos descobrir.

Startamos um consumer específico nesse tópico:

```bash
kafka-console-consumer --bootstrap-server kafka1:9092 --topic roleplay --group roleplay
```

Agora vamos subir um produtor randômico de mensagens pra esse tópico:

```bash
kafka-verifiable-producer --broker-list kafka1:9092,kafka2:9092 \
                        --topic roleplay \
                        --max-messages 10000 \
                        --throughput 5
```

Agora sim, com consumer de pé e produzindo mensagens nesse tópico, vamos executar o rebalanceamento:

```bash
kafka-reassign-partitions --zookeeper zookeeper:2181 \
                --reassignment-json-file strategy.json \
                --execute
```

Temos o retorno:

```
Current partition replica assignment

{"version":1,"partitions":[{"topic":"roleplay","partition":0,"replicas":[3,2],"log_dirs":["any","any"]}]}

Save this to use as the --reassignment-json-file option during rollback
Successfully started reassignment of partitions.
```

Se formos verificar:

```bash
kafka-reassign-partitions --zookeeper zookeeper:2181 \
                --reassignment-json-file strategy.json \
                --verify
```

Temos:

```
Status of partition reassignment:
Reassignment of partition roleplay-0 completed successfully
```

E agora, puxando novamente os dados do tópico:
```bash
kafka-topics --zookeeper zookeeper:2181 --topic roleplay --describe
```

```
Topic: roleplay PartitionCount: 1   ReplicationFactor: 2    Configs:
    Topic: roleplay Partition: 0    Leader: 2   Replicas: 2,1   Isr: 2,1
```

Rebalanceamos as partições como queriamos e o leader da partição se agora é o broker 2.

Os outputs para fins de debug:

```
[2020-08-23 23:54:53,587] INFO Created log for partition roleplay-0 in /var/lib/kafka/data/roleplay-0 with properties {compression.type -> producer, message.downconversion.enable -> true, min.insync.replicas -> 1, segment.jitter.ms -> 0, cleanup.policy -> [delete], flush.ms -> 9223372036854775807, segment.bytes -> 1073741824, retention.ms -> 604800000, flush.messages -> 9223372036854775807, message.format.version -> 2.4-IV1, file.delete.delay.ms -> 60000, max.compaction.lag.ms -> 9223372036854775807, max.message.bytes -> 1000012, min.compaction.lag.ms -> 0, message.timestamp.type -> CreateTime, preallocate -> false, min.cleanable.dirty.ratio -> 0.5, index.interval.bytes -> 4096, unclean.leader.election.enable -> false, retention.bytes -> -1, delete.retention.ms -> 86400000, segment.ms -> 604800000, message.timestamp.difference.max.ms -> 9223372036854775807, segment.index.bytes -> 10485760}. (kafka.log.LogManager)
[2020-08-23 23:54:53,591] INFO [Partition roleplay-0 broker=1] No checkpointed highwatermark is found for partition roleplay-0 (kafka.cluster.Partition)
[2020-08-23 23:54:53,591] INFO [Partition roleplay-0 broker=1] Log loaded for partition roleplay-0 with initial high watermark 0 (kafka.cluster.Partition)
[2020-08-23 23:54:53,592] INFO [ReplicaFetcherManager on broker 1] Removed fetcher for partitions Set(roleplay-0) (kafka.server.ReplicaFetcherManager)
[2020-08-23 23:54:53,592] TRACE [Broker id=1] Stopped fetchers as part of become-follower request from controller 2 epoch 1 with correlation id 15 for partition roleplay-0 with leader 3 (state.change.logger)
[2020-08-23 23:54:53,592] TRACE [Broker id=1] Truncated logs and checkpointed recovery boundaries for partition roleplay-0 as part of become-follower request with correlation id 15 from controller 2 epoch 1 with leader 3 (state.change.logger)
[2020-08-23 23:54:53,592] INFO [ReplicaFetcherManager on broker 1] Added fetcher to broker BrokerEndPoint(id=3, host=kafka3:9092) for partitions Map(roleplay-0 -> (offset=0, leaderEpoch=1)) (kafka.server.ReplicaFetcherManager)
[2020-08-23 23:54:53,593] TRACE [Broker id=1] Started fetcher to new leader as part of become-follower request from controller 2 epoch 1 with correlation id 15 for partition roleplay-0 with leader BrokerEndPoint(id=3, host=kafka3:9092) (state.change.logger)
[2020-08-23 23:54:53,593] TRACE [Broker id=1] Completed LeaderAndIsr request correlationId 15 from controller 2 epoch 1 for the become-follower transition for partition roleplay-0 with leader 3 (state.change.logger)
[2020-08-23 23:54:53,596] TRACE [Broker id=1] Cached leader info UpdateMetadataPartitionState(topicName='roleplay', partitionIndex=0, controllerEpoch=1, leader=3, leaderEpoch=1, isr=[3, 2], zkVersion=1, replicas=[2, 1, 3], offlineReplicas=[]) for partition roleplay-0 in response to UpdateMetadata request sent by controller 2 epoch 1 with correlation id 16 (state.change.logger)
[2020-08-23 23:54:53,601] TRACE [Broker id=1] Received LeaderAndIsr request LeaderAndIsrPartitionState(topicName='roleplay', partitionIndex=0, controllerEpoch=1, leader=3, leaderEpoch=1, isr=[3, 2], zkVersion=1, replicas=[2, 1, 3], addingReplicas=[1], removingReplicas=[3], isNew=true) correlation id 17 from controller 2 epoch 1 (state.change.logger)
[2020-08-23 23:54:53,607] DEBUG [Broker id=1] Ignoring LeaderAndIsr request from controller 2 with correlation id 17 epoch 1 for partition roleplay-0 since its associated leader epoch 1 matches the current leader epoch (state.change.logger)
[2020-08-23 23:54:53,614] TRACE [Broker id=1] Cached leader info UpdateMetadataPartitionState(topicName='roleplay', partitionIndex=0, controllerEpoch=1, leader=3, leaderEpoch=1, isr=[3, 2], zkVersion=1, replicas=[2, 1, 3], offlineReplicas=[]) for partition roleplay-0 in response to UpdateMetadata request sent by controller 2 epoch 1 with correlation id 18 (state.change.logger)
[2020-08-23 23:54:53,957] INFO [ReplicaFetcher replicaId=1, leaderId=3, fetcherId=0] Truncating partition roleplay-0 to local high watermark 0 (kafka.server.ReplicaFetcherThread)
[2020-08-23 23:54:53,957] INFO [Log partition=roleplay-0, dir=/var/lib/kafka/data] Truncating to 0 has no effect as the largest offset in the log is -1 (kafka.log.Log)
[2020-08-23 23:54:54,005] TRACE [Broker id=1] Received LeaderAndIsr request LeaderAndIsrPartitionState(topicName='roleplay', partitionIndex=0, controllerEpoch=1, leader=2, leaderEpoch=2, isr=[3, 2, 1], zkVersion=3, replicas=[2, 1], addingReplicas=[], removingReplicas=[], isNew=false) correlation id 19 from controller 2 epoch 1 (state.change.logger)
[2020-08-23 23:54:54,005] TRACE [Broker id=1] Handling LeaderAndIsr request correlationId 19 from controller 2 epoch 1 starting the become-follower transition for partition roleplay-0 with leader 2 (state.change.logger)
[2020-08-23 23:54:54,007] INFO [ReplicaFetcherManager on broker 1] Removed fetcher for partitions Set(roleplay-0) (kafka.server.ReplicaFetcherManager)
[2020-08-23 23:54:54,007] TRACE [Broker id=1] Stopped fetchers as part of become-follower request from controller 2 epoch 1 with correlation id 19 for partition roleplay-0 with leader 2 (state.change.logger)
[2020-08-23 23:54:54,007] TRACE [Broker id=1] Truncated logs and checkpointed recovery boundaries for partition roleplay-0 as part of become-follower request with correlation id 19 from controller 2 epoch 1 with leader 2 (state.change.logger)
[2020-08-23 23:54:54,007] INFO [ReplicaFetcherManager on broker 1] Added fetcher to broker BrokerEndPoint(id=2, host=kafka2:9092) for partitions Map(roleplay-0 -> (offset=102, leaderEpoch=2)) (kafka.server.ReplicaFetcherManager)
[2020-08-23 23:54:54,007] TRACE [Broker id=1] Started fetcher to new leader as part of become-follower request from controller 2 epoch 1 with correlation id 19 for partition roleplay-0 with leader BrokerEndPoint(id=2, host=kafka2:9092) (state.change.logger)
[2020-08-23 23:54:54,007] TRACE [Broker id=1] Completed LeaderAndIsr request correlationId 19 from controller 2 epoch 1 for the become-follower transition for partition roleplay-0 with leader 2 (state.change.logger)
[2020-08-23 23:54:54,010] TRACE [Broker id=1] Cached leader info UpdateMetadataPartitionState(topicName='roleplay', partitionIndex=0, controllerEpoch=1, leader=2, leaderEpoch=2, isr=[3, 2, 1], zkVersion=3, replicas=[2, 1], offlineReplicas=[]) for partition roleplay-0 in response to UpdateMetadata request sent by controller 2 epoch 1 with correlation id 20 (state.change.logger)
[2020-08-23 23:54:54,013] TRACE [Broker id=1] Received LeaderAndIsr request LeaderAndIsrPartitionState(topicName='roleplay', partitionIndex=0, controllerEpoch=1, leader=2, leaderEpoch=3, isr=[2, 1], zkVersion=4, replicas=[2, 1], addingReplicas=[], removingReplicas=[], isNew=false) correlation id 21 from controller 2 epoch 1 (state.change.logger)
[2020-08-23 23:54:54,014] TRACE [Broker id=1] Handling LeaderAndIsr request correlationId 21 from controller 2 epoch 1 starting the become-follower transition for partition roleplay-0 with leader 2 (state.change.logger)
[2020-08-23 23:54:54,015] INFO [ReplicaFetcherManager on broker 1] Removed fetcher for partitions Set(roleplay-0) (kafka.server.ReplicaFetcherManager)
[2020-08-23 23:54:54,015] TRACE [Broker id=1] Stopped fetchers as part of become-follower request from controller 2 epoch 1 with correlation id 21 for partition roleplay-0 with leader 2 (state.change.logger)
[2020-08-23 23:54:54,015] TRACE [Broker id=1] Truncated logs and checkpointed recovery boundaries for partition roleplay-0 as part of become-follower request with correlation id 21 from controller 2 epoch 1 with leader 2 (state.change.logger)
[2020-08-23 23:54:54,015] INFO [ReplicaFetcherManager on broker 1] Added fetcher to broker BrokerEndPoint(id=2, host=kafka2:9092) for partitions Map(roleplay-0 -> (offset=102, leaderEpoch=3)) (kafka.server.ReplicaFetcherManager)
[2020-08-23 23:54:54,016] TRACE [Broker id=1] Started fetcher to new leader as part of become-follower request from controller 2 epoch 1 with correlation id 21 for partition roleplay-0 with leader BrokerEndPoint(id=2, host=kafka2:9092) (state.change.logger)
[2020-08-23 23:54:54,016] TRACE [Broker id=1] Completed LeaderAndIsr request correlationId 21 from controller 2 epoch 1 for the become-follower transition for partition roleplay-0 with leader 2 (state.change.logger)
[2020-08-23 23:54:54,018] TRACE [Broker id=1] Cached leader info UpdateMetadataPartitionState(topicName='roleplay', partitionIndex=0, controllerEpoch=1, leader=2, leaderEpoch=3, isr=[2, 1], zkVersion=4, replicas=[2, 1], offlineReplicas=[]) for partition roleplay-0 in response to UpdateMetadata request sent by controller 2 epoch 1 with correlation id 22 (state.change.logger)
[2020-08-23 23:54:54,045] TRACE [Broker id=1] Cached leader info UpdateMetadataPartitionState(topicName='roleplay', partitionIndex=0, controllerEpoch=1, leader=2, leaderEpoch=3, isr=[2, 1], zkVersion=4, replicas=[2, 1], offlineReplicas=[]) for partition roleplay-0 in response to UpdateMetadata request sent by controller 2 epoch 1 with correlation id 23 (state.change.logger)
[2020-08-23 23:54:54,291] INFO [Log partition=roleplay-0, dir=/var/lib/kafka/data] Truncating to 102 has no effect as the largest offset in the log is 101 (kafka.log.Log)
[2020-08-23 23:54:54,630] TRACE [Broker id=1] Cached leader info UpdateMetadataPartitionState(topicName='roleplay', partitionIndex=0, controllerEpoch=1, leader=2, leaderEpoch=3, isr=[2, 1], zkVersion=4, replicas=[2, 1], offlineReplicas=[]) for partition roleplay-0 in response to UpdateMetadata request sent by controller 2 epoch 1 with correlation id 24 (state.change.logger)
 ```
