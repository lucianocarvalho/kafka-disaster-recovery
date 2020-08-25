# Configs

### Producers

#### Acknowledgement
- `acks=0`: o Producer não tem a resposta se a mensagem chegou no Kafka (+ performance)
- `acks=1`: o Producer tem a resposta do partition leader
- `acks=all`: o Producer tem a resposta do partition leader e suas réplicas.

É pra ser usado em conjunto com a config `min.insync.replicas` (default 2, incluíndo o leader).

Tomar bastante cuidado com o replication factor para bater os insync.replicas, se não você recebe uma Exception.

#### Retries

É importante lidar com as Exceptions, se não, você pode perder os dados.

Um exemplo é a Exception `NotEnoughReplicasException`.

A config `retries`:
- É `0` para Kafka <= 2.0
- É `2147483647` para Kafka >= 2.1

Existe uma config chamada `retry.backoff.ms` que por default é 100 ms.

O seu Producer vai retentar em um intervalo a cada 100 ms, caso não consiga produzir a mensagem, até que atinja a config `delivery.timeout.ms` (que por default é 120000 ms, 2 minutos).

**Adendo:** no caso de retries, tem uma chance das mensagens serem enviadas fora de ordem. Se você se basear na ordenação com base em keys, isso pode ser um problema. Para isso, existe uma config chamada `max.in.flight.requests.per.connection`, que configura quantas solicitações de producer podem ser feitas em paralelo.

Permitir retries sem setar a config `max.in.flight.requests.per.connection` para 1 vai potencialmente alterar a ordem dos registros porquê dois baches podem enviar mensagens para uma mesma partição. Mas, alterar para 1 também afeta o throughput.

Importante ler: [Kafka - Delivery Semantics](http://kafka.apache.org/documentation/#semantics)

A melhor solução pra isso é utilizar idempotencia.

#### Producers Idempotentes

Um produtor pode postar mensagens duplicadas no Kafka como resultado de problema de rede.

Caso ele não consiga retornar o `ACK` para o Producer e o mesmo tentar realizar uma nova tentativa no retries, pode ser que o Producer tenha recebido apenas um acknowledgement, mas o Kafka tenha commitado 2 offsets.

Usando uma chave de idempotencia de produção, o Kafka, caso já tenha commitado aquela chave, apenas retorna o ACK sem commitar duas vezes.

Para ativá-lo, apenas use `enable.idempotence` como `true`.

Um safe producer vai impactar no throughput e na latencia, use com moderação:

- `enable.idempotence`: `true`
- `acks`: `all`
- `retries`: `MAX_INT`
- `max.in.flight.requests.per.connection`: `5` (ou 1 em caso de Kafka <= 1.1)