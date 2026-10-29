# API de geração de roteiros com IA

API Laravel para cadastro de usuários e criação de roteiros. A geração é solicitada por um Job de fila e a integração com o provedor de IA fica no `ScriptGeneratorService`, usando NeuronAI com um endpoint compatível com a API da OpenAI.

A IA recebe três entradas com funções diferentes:

- `topic`: assunto e objetivo do roteiro.
- `source_text`: fonte factual autorizada.
- `style_text`: referência de estilo, sem servir como fonte de fatos.

O resultado é persistido e exposto pela API no campo `generated_content`.
Scripts concluídos também podem ser revisados por instruções em linguagem natural,
usando o roteiro atual como contexto, sem perder a versão existente se a revisão falhar.

## Requisitos

- PHP 8.3 ou superior
- Composer
- Um banco configurado pelo Laravel (SQLite é o padrão no `.env.example`)
- Um provedor de IA compatível com a API da OpenAI

## Instalação local

Na raiz do projeto:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure o banco no `.env`. Para SQLite, crie o arquivo se ainda não existir:

```bash
touch database/database.sqlite
```

Configure as credenciais do provedor de IA no mesmo arquivo:

```dotenv
AI_API_KEY=sua-chave
AI_BASE_URI=https://seu-provedor/v1
AI_MODEL=nome-do-modelo
```

Esses valores são lidos por `config/services.php`. Não coloque chaves reais no código ou em arquivos versionados.

Depois, execute as migrations:

```bash
php artisan migrate
```

Inicie a API:

```bash
php artisan serve
```

Por padrão, ela estará disponível em `http://127.0.0.1:8000`.

## Processamento da fila

A configuração padrão usa a fila `database`. Em outro terminal, inicie o worker:

```bash
php artisan queue:work
```

A criação do roteiro despacha `GenerateScriptJob`. A revisão contextual despacha
`ReviseScriptJob`; ambos usam o ciclo compartilhado em `ScriptGenerationJob` e
chamam o `ScriptGeneratorService`. O worker deve permanecer em execução com
`QUEUE_CONNECTION=database` para processar os jobs de forma assíncrona.

## Autenticação

As rotas de cadastro e login retornam um token Sanctum. Envie esse token nas rotas protegidas:

```http
Authorization: Bearer SEU_TOKEN
Accept: application/json
```

### Cadastro

`POST /api/register`

```json
{
  "name": "Ana",
  "email": "ana@example.com",
  "password": "senha-segura",
  "password_confirmation": "senha-segura",
  "device_name": "meu-app"
}
```

O cadastro retorna HTTP `201`, com `token` e `user`.

### Login

`POST /api/login`

```json
{
  "email": "ana@example.com",
  "password": "senha-segura",
  "device_name": "meu-app"
}
```

`device_name` é obrigatório no login atual. O endpoint retorna o token e os dados do usuário. Credenciais inválidas retornam HTTP `422` com erro de validação.

### Logout

`POST /api/logout` — requer autenticação. Revoga o token atual.

`GET /api/user` — requer autenticação. Retorna o usuário associado ao token.

## Rotas de scripts

Todas as rotas abaixo exigem token Sanctum.

| Método | Rota | Ação |
| --- | --- | --- |
| `GET` | `/api/scripts` | Lista scripts do usuário autenticado |
| `POST` | `/api/scripts` | Cria um script e despacha a geração |
| `GET` | `/api/scripts/{script}` | Exibe um script próprio |
| `PUT` / `PATCH` | `/api/scripts/{script}` | Atualiza metadados e solicita uma nova geração |
| `POST` | `/api/scripts/{script}/revise` | Revisa o roteiro existente com uma instrução |
| `DELETE` | `/api/scripts/{script}` | Exclui um script próprio |

### Criar script

`POST /api/scripts`

```json
{
  "topic": "Uma apresentação sobre o tema",
  "source_text": "Texto com os fatos que podem ser usados no roteiro.",
  "style_text": "Exemplo de estilo de escrita."
}
```

`source_text` é obrigatório e `style_text` é opcional segundo as regras do request. A resposta usa `ScriptResource` e contém os campos do script, incluindo `generated_content`, `status` e `error_message`.
`topic` aceita até 255 caracteres; `source_text` e `style_text` aceitam até
20.000 caracteres.

### Atualizar metadados e gerar novamente

`PUT` ou `PATCH /api/scripts/{script}` atualiza `topic`, `source_text` e/ou
`style_text`, limpa o conteúdo atual e solicita uma geração nova com os dados
atualizados. A operação retorna HTTP `409` se o roteiro já estiver sendo
processado. Use a rota de revisão abaixo para alterar o texto existente sem
descartá-lo.

### Revisar o roteiro existente

`POST /api/scripts/{script}/revise`

```json
{
  "instruction": "Melhore a introdução para prender a atenção, preservando os fatos e o restante do roteiro."
}
```

`instruction` é obrigatório e aceita até 20.000 caracteres. A requisição
precisa ser autenticada e o usuário deve ser o proprietário do roteiro. O script
precisa ter `generated_content`; uma geração ainda sem conteúdo não pode ser
revisada. Se já estiver em `processing`, a API retorna HTTP `409`.

Ao aceitar a revisão, a API retorna o `ScriptResource` com `status: processing`
e o `generated_content` anterior ainda disponível. O job envia à IA o roteiro
atual, a instrução, o tema, a fonte factual e o estilo. A IA deve retornar o
roteiro completo revisado; o conteúdo anterior só é substituído quando essa
resposta não estiver vazia e a revisão terminar com sucesso. Em caso de erro da
IA ou falha definitiva da fila, `status` passa a `failed`, `error_message` é
preenchido sem credenciais, e o roteiro anterior é preservado. Consulte
`GET /api/scripts/{script}` para acompanhar o processamento.

### Status de geração

Os estados usados pelo Job são:

- `pending`: aguardando processamento
- `processing`: geração em andamento
- `done`: geração concluída
- `failed`: ocorreu uma falha; consulte `error_message`. Em uma revisão, a versão anterior continua disponível.

Consulte `GET /api/scripts/{script}` para acompanhar o estado.

## Modelo de dados

A aplicação usa a tabela padrão `users` e a tabela de domínio `scripts`. O script inclui `user_id`, `topic`, `source_text`, `style_text`, `generated_content`, `status` e `error_message`. As Policies restringem visualização, atualização e exclusão ao proprietário.

## Escopo do repositório

Este repositório contém a API Laravel. A interface React não está incluída; o
frontend deve chamar a rota de revisão com o token Sanctum e consultar o script
novamente para exibir a versão revisada quando o status chegar a `done`.

## Testes

Para executar a suíte Laravel:

```bash
php artisan test
```

A configuração PHPUnit usa SQLite em memória e define a fila como `sync` durante os testes.

## Deploy no Render com Docker

O [Dockerfile](./Dockerfile) prepara a API para produção com PHP 8.3, Apache e
PostgreSQL. O filesystem dos serviços Render é efêmero por padrão; use um
PostgreSQL gerenciado para persistir scripts, sessões, cache e fila. Não use o
SQLite local em produção.

### Serviço Web

1. Crie um PostgreSQL no Render e copie a **Internal Database URL**.
2. Crie um **Web Service** conectado a este repositório, selecionando o runtime
   **Docker** e o `Dockerfile` da raiz.
3. Configure estas variáveis no serviço:

   | Variável | Valor |
   | --- | --- |
   | `APP_ENV` | `production` |
   | `APP_DEBUG` | `false` |
   | `APP_KEY` | Gere com `php artisan key:generate --show` localmente |
   | `APP_URL` | URL HTTPS pública do serviço Web |
   | `DB_CONNECTION` | `pgsql` |
   | `DB_URL` | Internal Database URL do PostgreSQL |
   | `QUEUE_CONNECTION` | `database` |
   | `LOG_CHANNEL` | `stderr` |
   | `AI_API_KEY` | Chave do provedor de IA |
   | `AI_BASE_URI` | URL base do endpoint compatível com OpenAI |
   | `AI_MODEL` | Identificador do modelo |

   A configuração Laravel deste projeto lê `DB_URL`; por isso, copie para essa
   variável a Internal Database URL que o Render também pode apresentar como
   `DATABASE_URL`. Não coloque URLs com credenciais, tokens ou chaves no
   repositório.

4. Configure o **Pre-Deploy Command** do serviço como
   `php artisan migrate --force`. Se o plano escolhido não disponibilizar esse
   recurso, execute o mesmo comando pelo Shell do serviço antes de receber
   tráfego.
5. Configure o health check path como `/up`. O container escuta a porta
   informada pelo Render através de `PORT`.

### Worker da fila

Crie um serviço **Background Worker** no Render usando o mesmo repositório e
Dockerfile. Configure nele as mesmas variáveis de ambiente do Web Service,
incluindo `APP_KEY`, `DB_CONNECTION`, `DB_URL`, `QUEUE_CONNECTION` e as
credenciais de IA. Defina o comando do worker para:

```bash
php artisan queue:work database --sleep=3 --tries=1 --timeout=60
```

O worker e a API precisam usar o mesmo PostgreSQL; sem o worker, os pedidos
permanecem na fila e a geração/revisão não é processada. O serviço Web e o
Background Worker são recursos separados no Render e podem ter custos próprios.
