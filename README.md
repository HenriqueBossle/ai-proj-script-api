# API de geração de roteiros com IA

API Laravel para cadastro de usuários e criação de roteiros. A geração é solicitada por um Job de fila e a integração com o provedor de IA fica no `ScriptGeneratorService`, usando NeuronAI e um endpoint compatível com a API da OpenAI.

A IA recebe três entradas com funções diferentes:

- `topic`: assunto e objetivo do roteiro.
- `source_text`: fonte factual autorizada.
- `style_text`: referência de estilo, sem servir como fonte de fatos.

O resultado é persistido e exposto pela API no campo `generated_content`.

## Requisitos

- PHP 8.3 ou superior
- Composer
- Um banco configurado pelo Laravel (SQLite é o padrão no `.env.example`)
- Um provedor de IA compatível com OpenAI configurado para o agente

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

A criação do roteiro despacha `GenerateScriptJob`. O Job atualiza o status, chama o serviço de geração e salva o resultado ou o erro. Para processar a geração de forma assíncrona, mantenha o worker em execução e use `QUEUE_CONNECTION=database`.

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

`device_name` é obrigatório no login atual. O endpoint retorna o token e os dados do usuário. Credenciais inválidas retornam erro de validação.

### Logout

`POST /api/logout` — requer autenticação. Revoga o token atual.

## Rotas de scripts

Todas as rotas abaixo exigem token Sanctum.

| Método | Rota | Ação |
| --- | --- | --- |
| `GET` | `/api/scripts` | Lista scripts do usuário autenticado |
| `POST` | `/api/scripts` | Cria um script e despacha a geração |
| `GET` | `/api/scripts/{script}` | Exibe um script próprio |
| `PUT` / `PATCH` | `/api/scripts/{script}` | Atualiza os campos enviados |
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

`source_text` é obrigatorio e `style_text` é opcionail segundo as regras do request. A resposta usa `ScriptResource` e contém os campos do script, incluindo `generated_content`, `status` e `error_message`.

### Status de geração

Os estados usados pelo Job são:

- `pending`: aguardando processamento
- `processing`: geração em andamento
- `done`: geração concluída
- `failed`: ocorreu uma falha; consulte `error_message`

Consulte `GET /api/scripts/{script}` para acompanhar o estado.

## Modelo de dados

A aplicação usa a tabela padrão `users` e a tabela de domínio `scripts`. O script inclui `user_id`, `topic`, `source_text`, `style_text`, `generated_content`, `status` e `error_message`. As Policies restringem visualização, atualização e exclusão ao proprietário.

## Limitações conhecidas do estado atual

- O método `update()` atualiza os campos enviados, mas ainda não limpa `generated_content`, redefine o status nem despacha uma nova geração.
- `index()` filtra pelo usuário autenticado, mas ainda não ordena os resultados por data.
- As validações de texto não aplicam o limite de 20.000 caracteres descrito nos requisitos do MVP; `topic` está limitado a 255 caracteres.
- Há divergência entre a nulabilidade esperada e as migrations atuais: `source_text` é criado sem `nullable()`, e uma migration posterior torna `generated_content` obrigatório. Revise as migrations conforme os dados que o cliente pode omitir antes de preparar um banco novo.
- A autenticação da API e o CRUD de scripts estão definidos em `routes/api.php`; os testes existentes no repositório são principalmente os testes padrão de autenticação web.

