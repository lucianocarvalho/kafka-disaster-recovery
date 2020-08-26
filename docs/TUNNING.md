# Tunning

Alguns artigos auxiliares que usei como fonte:
- [Configuring Apache Kafka for Performance and Resource Management](https://docs.cloudera.com/documentation/kafka/latest/topics/kafka_performance.html)
- [librdkafka - Explain the consumer's memory usage to me](https://github.com/edenhill/librdkafka/wiki/FAQ#explain-the-consumers-memory-usage-to-me)
- [Kafka Tutorial Offset Management](https://www.youtube.com/watch?v=kZT8v2_b2XE)
- [Consumer Configurations - Confluent](https://docs.confluent.io/current/installation/configuration/consumer-configs.html)
- [Tuning Your Apache Kafka Cluster](https://www.youtube.com/watch?v=6hFhf6LgEps&feature=youtu.be)

Algumas configurações são bem importantes de você ter em mente antes de se adentrar ao tunning.

- `max.poll.interval.ms` **(default 5 minutos)**: o máximo de delay (em milisegundos) entre as invocações do poll(), dentro de um consumer group. Essa configuração é um limite na quantidade de tempo que o consumidor pode ficar inativo antes de buscar mais registros. Se ele não chamar o poll() e expirar esse tempo limite, ele é considerado um consumer falho e o Kafka rebalanceia a partição dele para outro consumidor.
- `max.poll.records` **(default 500)**: indica o número máximo de registros retornados em uma chamada do poll().

O intervalo de cada poll não é controlado pelas duas propriedades, mas sim, pelo tempo gasto pelo seu consumer em dar ACK nos registros já buscados.

Exemplo prático:

Por exemplo, digamos que temos um tópico com 100 mensagens e estamos com as configurações de `max.poll.interval.ms=100` e o `max.poll.records=20` e o tempo de processamento de cada poll dele em tempos normais seja de 20ms. Então, o consumer vai dar um poll no tópico a cada 20ms e em cada poll, o máximo de 20 registros serão retornados. Ele não ficará aguardando 80ms atoa. O ponto é, se por algum problema ele demorar mais do que os 100 ms pra dar acknowledge, esse polling vai ser considerado como falha.

### Um passo pra trás

Antes do tunning, vamos entender como o Kafka gerencia os offsets e como é feito o poll.

Quando você chama `poll()` method, o Kafka envia algumas mensagens para a gente.

Digamos que em um tópico nós temos 100 mensagens (100 offsets):

> M1 | M2 | M3 | M4 | M5 | ... | M100

**Committed offset** é o último offset que o consumer processou com sucesso.

**Current offset** é até qual offset o último poll() parou (e fica em memória).

Em um cenário de rebalanceamento de partições, o consumer que assumir aquela partição tem que responder uma pergunta: *a partir de qual offset eu devo continuar lendo?*

E a resposta é: **a partir do último committed offset**.

### Mas, nem tudo são flores...

Como commitar?
- Auto commit
- Manual commit

#### Auto commit

Commita offsets em background. Em um rebalanceamento ou crash, há chances dele não ter commitado o último offset processado e há chances de pegar mensagens que já foram processadas anteriormente.

- `enable.auto.commit` **(default true)**: ele vai commitar os offsets regularmente no background.
- `auto.commit.interval.ms` **(default 5 segundos)**: intervalo para ele commitar os offsets. É importante frisar que o commit do offset é feito durante o poll de mensagens. Se ele processar todo o poll em um intervalo menor que o intervalo de commit, ele vai realizar um novo poll e commitar só no próximo.

#### Manual commit

Dentro do Manual commit você tem 2 abordagens:

- `Commit sync`: blocking method. Se ele der problema, ele vai retentar enviar novamente.
- `Commit async`: ele envia o request de commit e continua lendo os próximos.

No cenário de commit async não vai retentar e o motivo é porquê como ele continua lendo os próximos ele vai enviar novos pedidos de commit.

Exemplo:

O consumer leu do offset 1 ao 10 e enviou a requisição de commit.
Por algum motivo, deu falha, mas como é commit async, ele continuou lendo os offsets 11 em diante.
Mesmo com a falha, ele vai chegar no offset 20 e realizar um novo request de commit, descartando a solicitação anterior.

Em alguns cenários como uma Exception, faz sentido ele realizar o commit sync dentro de um bloco try catch que faz commit assync.

No caso do `enable.auto.commit`, a librdkafka vai commitar o último offset guardado em cada partição em intervalos regulares, durante um rebalanceamento e quando o consumer é desligado.

Os offsets usados aqui são retirados da store em memória. Essa store é atualizada automaticamente quando `enable.auto.offset.store=true`. Se você desligar o offset store, você pode usar o `rd_kafka_offsets_store()` para guardar o offset por você mesmo.

### Fetch Request

Na lib do rdkafka, damos pre-fetch em algumas mensagens para uma fila interna. Quando não há mais nenhuma mensagem disponíveis para o fetch para aquele tópico/partição, o fetch request vai ficar bloqueado pelo `fetch.max.wait.ms`. Pelo seu valor default de 500ms, o consumer faz 2 heartbeats por segundo no broker caso não tenha mais mensagens.

- `fetch.max.wait.ms` **(default 500ms)**: tempo que o servidor ficará bloqueado em cenários sem mensagens.
- `fetch.min.bytes` **(default 1)**: deixa o consumer especificar a quantidade mínima de dados que ele quer receber do broker quando estiver dando fetch nos registros.
- `max.partition.fetch.bytes` **(default 1048576)**: quantidade máxima de bytes de dados por partição.

### rdkafka

São configurações mais específicas pro PHP utilizando rdkafka em si, mas válidas mencionar.

- `queued.min.messages` **(default 10000)**: quantidade mínima de mensagens por tópico/partição que a librdkafka vai tentar manter na fila local.
- `queued.max.messages.kbytes` **(default 1048576)**: tem prioridade maior que a configuração acima. É o máximo de kilobytes por tópico/partição na fila local e pode ser sobrescrito pelo `fetch.message.max.bytes`.

