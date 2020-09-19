# Como o Kafka salva as mensagens no disco?

As mensagens do Kafka são salvas dentro de uma partição: imutável e append only. Uma partição não pode ser separada entre multiplos brokers ou em múltiplos discos. Uma partição sempre irá por completo para um broker e essa partição será replicada (por completo) em outro broker como réplicas in-sync.

As retenções das mensagens lideram em como essas mensagens são gerenciadas dentro do Kafka, quer a mensagem ter sido consumida ou não. É essa retenção que coordena quando o Kafka tem que rodar o Log Cleaner e apagar mensagens antigas do disco. Sem ele, os brokers apenas aumentariam seu uso de disco para sempre.

O padrão das retenções de mensagens é de 7 dias, mas ajustável conforme sua necessidade pela configuração `retention.ms` em um tópico.

## Segments

Para que o Kafka não tenha que lidar com um arquivo gigantesco com milhões de mensagens, tanto pra dar append quanto para limpar as mensagens antigas, o Kafka escreve as mensagens dentro de um segmento: o segmento atual. Quando o segmento atinge seu limite máximo (o que vamos ver mais pra baixo), o Kafka cria um novo segmento e esse passa a ser o segmento atual.

Os segmentos são nomeados pelo seu offset base. O offset base tem que ser maior do que o último offset do último segmento criado e normalmente é o primeiro offset daquele segmento.

Em um disco, a partição é um diretório e cada segmento é um arquivo.

Os tamanhos dos segments de log são configurados pelo atributo `segment.bytes`.

Os tamanhos dos segmentos de index são configurados pelo o atributo `segment.index.bytes`.

#### Não entendi

Digamos que temos 9 mensagens e dizemos pro Kafka que queremos separar as mensagens em segmentos de 3 mensagens cada. Sua separação em segmentos ficaria mais um menos assim:

- Segment 0: mensagens do offset 0 até o 3
- Segment 3: mensagens do offset 3 até o 6
- Segment 6: mensagens do offset 6 até o 9
- Segment 9: próximas mensagens (10, 11, 12)

## Hands-on!

Vamos ver como isso fica dentro do Kafka.

Primeiro, para exemplificar, entramos em qualquer broker e criamos um tópico novo com apenas uma partição:
```bash
kafka-topics --zookeeper zookeeper:2181 --topic storage  \
             --partitions 1 \
             --replication-factor 1 \
             --create
```

Agora vamos verificar em qual broker esse tópico foi criado:

```bash
kafka-topics --zookeeper zookeeper:2181 --topic storage --describe
```
```text
Topic: storage  PartitionCount: 1   ReplicationFactor: 1    Configs:
    Topic: storage  Partition: 0    Leader: 3   Replicas: 3 Isr: 3
```

Podemos ver que a única partição desse tópico foi criado no broker 3.

### Inspencionando a partição

Os segmentos dentro dessa stack são escritos na pasta `/var/lib/kafka/data`, mas você pode ver onde os arquivos são escritos dentro de cada broker com o comando `kafka-log-dirs`:

```bash
kafka-log-dirs --describe --bootstrap-server kafka1:9092 --topic-list storage
```

Dentro do broker 3 e dentro dessa pasta dos segmentos, você vai encontrar alguma estrutura parecida com isso:

```text
/storage-0
|____00000000000000000000.log
|____leader-epoch-checkpoint
|____00000000000000000000.timeindex
|____00000000000000000000.index
```

As mensagens são escritas nesses segmentos de `log` 😁

Para inspencionar melhor ele, vamos publicar algumas mensagens com o Producer interativo.

```bash
kafka-console-producer --broker-list kafka1:9092 --topic storage
```

### Entendendo os arquivos

Pra cada segmento nós temos 3 arquivos:

- segment.index
- segment.log
- segment.timeindex

O arquivo de .log é onde realmente o arquivo é guardado, onde os dados reais estão.
A estrutura dele é ter um offset, alguns metadados e a sua mensagem.

Para que o Kafka não tenha que buscar um offset em todo o arquivo do segmento e causar um overhead na busca, é criado esse arquivo de `segment.index` que mapeiam os offset para onde eles se encontram dentro do `segment.log`. Esse arquivo do inde está sempre na memória e é utilizado busca binária para buscar suas respectivas mensagens.


Os arquivos de log seguem uma semância não muito friendly, mas conseguimos ver os dados utilizando o `DumpLogSegments`:

```bash
kafka-run-class kafka.tools.DumpLogSegments \
        --deep-iteration --print-data-log \
        --files /var/lib/kafka/data/storage-0/00000000000000000000.log
```

```text
| offset: 10041 CreateTime: 1600490339425 keysize: -1 valuesize: 4 sequence: -1 headerKeys: [] payload: 9995
| offset: 10042 CreateTime: 1600490339425 keysize: -1 valuesize: 4 sequence: -1 headerKeys: [] payload: 9996
| offset: 10043 CreateTime: 1600490339425 keysize: -1 valuesize: 4 sequence: -1 headerKeys: [] payload: 9997
| offset: 10044 CreateTime: 1600490339425 keysize: -1 valuesize: 4 sequence: -1 headerKeys: [] payload: 9998
| offset: 10045 CreateTime: 1600490339425 keysize: -1 valuesize: 4 sequence: -1 headerKeys: [] payload: 9999
```

### Referências:

- [How Kafka’s Storage Internals Work](https://thehoard.blog/how-kafkas-storage-internals-work-3a29b02e026)
- [What are the different logs under kafka data log dir](https://stackoverflow.com/questions/53744646/what-are-the-different-logs-under-kafka-data-log-dir)
- [A Practical Introduction to Kafka Storage Internals](https://medium.com/@durgaswaroop/a-practical-introduction-to-kafka-storage-internals-d5b544f6925f)