# Kafka CLI

### kafka-configs
```bash
# Listando configurações genéricas:
# Pode ser `topics`, `brokers`, `broker-loggers`, `clients`, `users`.
kafka-configs --zookeeper zookeeper:2181 --describe --entity-type topics

# Alterando retention.ms para o tópico first_topic
kafka-configs --zookeeper zookeeper:2181 --alter --entity-type topics --entity-name first_topic --add-config retention.ms=259200000

# Desfazendo a config anterior
kafka-configs --zookeeper zookeeper:2181 --alter --entity-type topics --entity-name first_topic --delete-config retention.ms

# Listando todos os brokers
zookeeper-shell zookeeper:2181 ls /brokers/ids
```

### kafka-topics

```bash
# Listando os tópicos
kafka-topics --zookeeper zookeeper:2181 --list

# Criando tópicos
kafka-topics --zookeeper zookeeper:2181 --topic first_topic --create --partitions 3 --replication-factor 2

# Describe
kafka-topics --zookeeper zookeeper:2181 --topic first_topic --describe

# Delete (validar delete.topic.enable como true)
kafka-topics --zookeeper zookeeper:2181 --topic first_topic --delete

# Aumentando partições de um tópico
# If partitions are increased for a topic that has a key, the partition logic or ordering of the messages will be affected.
kafka-topics --zookeeper zookeeper:2181 --alter --topic first_topic --partitions 4
```

### kafka-console-producer

```bash
# Producer interativo
kafka-console-producer --broker-list kafka1:9092 --topic first_topic

# Producer interativo com acks
kafka-console-producer --broker-list kafka1:9092 --topic first_topic --producer-property acks=all

# Produzindo mensagens por um arquivo (cada linha é uma mensagem nova)
kafka-console-producer --broker-list kafka1:9092 --topic first_topic < messages.txt
```

Cada input é uma mensagem no Kafka.

### kafka-console-consumer

```bash
kafka-console-consumer --bootstrap-server kafka1:9092 --topic first_topic
kafka-console-consumer --bootstrap-server kafka1:9092 --topic first_topic --from-beginning
kafka-console-consumer --bootstrap-server kafka1:9092 --topic first_topic --group app
```

Quando você não passa um `--group`, ele cria um consumer group novo.

### kafka-consumer-groups

```bash
kafka-consumer-groups --bootstrap-server kafka1:9092 --list
kafka-consumer-groups --bootstrap-server kafka1:9092 --describe --group app
```

`CURRENT-OFFSET` é o offset da partição.

`LOG-END-OFFSET` é o último offset commitado.


## Resetando os offsets

```bash
kafka-consumer-groups --bootstrap-server kafka1:9092 \
                        --group app \
                        --reset-offsets \
                        --to-earliest \
                        --topic first_topic \
                        --execute
```

Você consegue resetar offsets de um consumer grupo em um tópico ou em todos os tópicos.

Você só consegue resetar o offset de um grupo que não esteja conectado.

Parâmetros pro `--reset-offsets`:

- `--to-earliest`: reseta o offset para 0, desde o começo.
- `--to-latest`: reseta o offset para máximo, como se já tivesse lido tudo.
- `--shift-by <number>`: avança ou volta offsets de cada partição. Positivo avança, negativo volta.
- `--to-offset 1`: reseta o offset pro número especificado em todas as partições.
- `--to-datetime`: reseta pra uma data específica no formato `YYYY-MM-DDTHH:mm:SS.sss`.

Alguns exemplos mais avançados:
```bash
# Resetando para uma data especifica
--group app --reset-offsets --to-datetime 2020-08-23T00:00:00.000

# Resetando o offset de uma partição específica
--group app --topic first_topic:0 --reset-offsets --to-latest
--group app --topic first_topic:1,2 --reset-offsets --to-latest
```

Alguns outros comandos com o consumer groups:

```bash
# Puxando o coordinator:
kafka-consumer-groups --bootstrap-server kafka1:9092 --describe --group app --state

# Puxando os membros de um consumer group:
kafka-consumer-groups --bootstrap-server kafka1:9092 --describe --group app --members

# Listando tópicos com configs diferentes do default
kafka-topics --zookeeper zookeeper:2181 --describe --topics-with-overrides
```

### Consumer/producer com keys:
```bash
# Produzindo com keys
kafka-console-producer --broker-list kafka1:9092 --topic first_topic \
                       --property parse.key=true --property key.separator=,

> key,value
> another key, another value

# Consumindo e mostrando as keys
kafka-console-consumer --bootstrap-server kafka1:9092 --topic first_topic \
                      --from-beginning --property print.key=true --property key.separator=,
```

### kafka-run-class
```bash
# Puxando os earliest offset de um tópico
kafka-run-class kafka.tools.GetOffsetShell --broker-list kafka1:9092 --topic first_topic
```

## Tips and tricks

### Limpando tópicos

Para limpar os tópicos, você tem 2 `log.cleanup.policy` possíveis:
- **delete (default):** é utilizado juntamente com `retention`. Basicamente apaga os logs depois de determinado tempo.
- **compact:** é utilizado juntamente com o `compaction`.

Você também pode usar os dois caso queira:
```bash
--add-config cleanup.policy=[compact,delete]
```

#### Log delete strategy

Para mexer na retenção das mensagens, você pode alterar o `retention.ms`.

```bash
kafka-configs --zookeeper zookeeper:2181 \
            --alter --entity-type topics --entity-name first_topic \
            --add-config retention.ms=100
```

Depois disso, você deve aguardar o `log.retention.check.interval.ms`, que tem default de 5 minutos.

Não adianta setar o offset para 0, caso já tenha passado o cleaner. Ele vai alterar o offset pro último segmento encontrado.

#### Log compact strategy

Com o compact você tem que entender que os logs das mensagens tem `head` e tem `tail`.

- `head`: é o espaço entre a última compactação e o offset atual.
- `tail`: é o espaço entre a limpeza e a última compactação.

Veja o link de *Log Compaction* para entender melhor.

Isso diz pro Kafka manter a última versão de um registro e deletar as versões mais antigas durante a compactação.

É uma forma de reter a última atualização de um registro de cada key em um tópico específico.

### Reassing partitions

O que o `kafka-reassign-partitions` é uma ferramenta utilizada para migrar alguns tópicos dos brokers atuais para brokers mais novos. É bem útil quando você expande o seu cluster, para você balancear e migrar tópicos para outros brokers. Essa ferramenta distribui uniformemente todas as partições para um novo conjunto de brokers.

Essa ferramenta nos permite mover replicas assinadas das partições, para:

- Mover tópicos de brokers antigos para novos brokers.
- Para expandir o cluster, já que os tópicos já existentes não vão para os novos brokers.
- Trocar brokers: mover partições para um novo broker e matá-lo, caso necessário.

O que o `kafka-reassign-partitions` faz é:

1. Cria novas réplicas nos brokers que forem necessários.
2. Deixa os dados réplicados até trocarem de lider.
3. Trigger leader elections onde for necessários.
4. Apaga réplicas onde for necessário.

Exemplo:

Crie um arquivo `topics-to-move.json` com os tópicos que você deseja.
```json
{
   "topics":[{"topic":"move_this_topic"}],
   "version":1
}
```

```bash
kafka-reassign-partitions --zookeeper zookeeper:2181 \
                --topics-to-move-json-file topics-to-move.json \
                --broker-list "2,3" \
                --generate
```

A ferramenta vai te retornar os dois dados:

```
Current partition replica assignment
{"version":1,"partitions":[{"topic":"move_this_topic","partition":1,"replicas":[1],"log_dirs":["any"]},{"topic":"move_this_topic","partition":0,"replicas":[3],"log_dirs":["any"]}]}

Proposed partition reassignment configuration
{"version":1,"partitions":[{"topic":"move_this_topic","partition":0,"replicas":[2],"log_dirs":["any"]},{"topic":"move_this_topic","partition":1,"replicas":[3],"log_dirs":["any"]}]}
```

É importante você salvar os dois: um para o rollback e o outro caso queira executar.

```bash
# Realmente rodando o reassing de partitions
kafka-reassign-partitions --zookeeper zookeeper:2181 \
                --reassignment-json-file expand-cluster.json \
                --execute
```

Você pode verificar o status do reassing usando o `--verify`:

```bash
kafka-reassign-partitions --zookeeper zookeeper:2181 \
                --reassignment-json-file expand-cluster.json \
                --verify
```

Você também pode utilizar a ferramenta para mover réplicas entre os brokers.

Por exemplo: dado um tópico `refact` com 1 partição e replication factor de 2.
O lider da partição é o broker 1 e a réplica está no broker 3.
Quero mover esse tópico para os brokers 2 e 3.

```json
{"version":1,"partitions":[{"topic":"refact","partition":0,"replicas":[2,3]}]}
```

Rodando:
```bash
kafka-reassign-partitions --zookeeper zookeeper:2181 \
                --reassignment-json-file move.json \
                --execute
```

Aplicando um `--throttle` na migração (não usar migrar partições em mais de 50MB/s).
Se quiser alterar durante o rebalanceamento, você pode aumentar o throttle on the fly.

```bash
kafka-reassign-partitions --zookeeper zookeeper:2181 \
                --reassignment-json-file bigger-cluster.json \
                --throttle 50000000 \
                --execute
```

Durante o rebalancemanto o cluster pode lidar com +20% de CPU e +30% de uso de disco.

### Election

O `auto.leader.rebalance.enable` é o que determina se de tempos em tempos roda um election das partições.

```bash
kafka-leader-election --bootstrap-server kafka1:9092,kafka2:9092 \
                --election-type PREFERRED \
                --topic first_topic \
                --partition 0
```

### Roleplay!

```bash
# Publicando 1000 mensagens randomicas:
kafka-verifiable-producer --broker-list kafka1:9092,kafka2:9092 \
                        --topic first_topic \
                        --max-messages 10000 \
                        --throughput 5

# Lendo esse tópico
kafka-verifiable-consumer --broker-list kafka1:9092,kafka2:9092 \
                --group-id app \
                --group-instance-id 1 \
                --topic first_topic
```

### You should read later:

- [Introduction to Topic Log Compaction in Apache Kafka](https://medium.com/swlh/introduction-to-topic-log-compaction-in-apache-kafka-3e4d4afd2262)
- [Kafka Architecture: Log Compaction](https://dzone.com/articles/kafka-architecture-log-compaction)
- [How to use reassign partition tool in Apache Kafka](https://www.youtube.com/watch?v=sWsWurfBI9c)
- [Automatically migrating data to new machines - Kafka](http://kafka.apache.org/documentation.html#basic_ops_automigrate)
- [Preferred leader election in Kafka](https://medium.com/@mandeep309/preferred-leader-election-in-kafka-4ec09682a7c4)