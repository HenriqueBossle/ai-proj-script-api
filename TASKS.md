# Tasks — AI Script Generator MVP

## 1. Objetivo do MVP

Construir uma API Laravel para geração de roteiros com IA.

O usuário:

1. Cria uma conta.
2. Faz login utilizando Sanctum.
3. Preenche um formulário com:
   * `topic` — ideia/tema do vídeo.
   * `source_text` — fonte de informações para o roteiro.
   * `style_text` — referência de estilo de escrita.
4. Cria um roteiro.
5. O Laravel coloca a geração em uma fila.
6. O `GenerateScriptJob` chama o `ScriptGeneratorService`.
7. O serviço envia os dados para a IA.
8. A IA gera o roteiro utilizando **somente o `source_text` como fonte factual (grounding)**.
9. O resultado é salvo em `content`.
10. O usuário pode visualizar, editar/regenerar e excluir seus roteiros.

---

# 2. Banco de Dados

## USERS

Utilizar a tabela padrão `users` do Laravel.

| Campo        | Tipo      | Regras             |
| ------------ | --------- | ------------------ |
| `id`         | bigint    | PK                 |
| `name`       | varchar   | obrigatório        |
| `email`      | varchar   | obrigatório, único |
| `password`   | varchar   | obrigatório        |
| `created_at` | timestamp | automático         |
| `updated_at` | timestamp | automático         |

---

## SCRIPTS

| Campo              | Tipo      | Regras             |
| ------------------ | --------- | ------------------ |
| `id`               | bigint    | PK                 |
| `user_id`          | bigint    | FK → `users.id`    |
| `topic`            | varchar   | obrigatório        |
| `source_text`      | text      | nullable           |
| `style_text`       | text      | nullable           |
| `generate_content` | longtext  | nullable           |
| `status`           | varchar   | default: `pending` |
| `error_message`    | text      | nullable           |
| `created_at`       | timestamp | automático         |
| `updated_at`       | timestamp | automático         |

### Relacionamentos

```text
User
 └── hasMany(Script)

Script
 └── belongsTo(User)
```

* [ ] Criar migration `scripts`
* [ ] Criar foreign key `user_id`
* [ ] Configurar `onDelete('cascade')`
* [ ] Criar model `Script`
* [ ] Configurar relacionamento `User::scripts()`
* [ ] Configurar relacionamento `Script::user()`

---

# 3. Status do Script

Estados possíveis:

```text
pending
processing
done
failed
```

Fluxo principal:

```text
pending
   ↓
processing
   ↓
done
```

Fluxo de erro:

```text
pending
   ↓
processing
   ↓
failed
```

O usuário poderá tentar gerar novamente um roteiro que esteja em `failed`.

---

# 4. Arquitetura da aplicação

Responsabilidades principais:

| Componente               | Responsabilidade                          |
| ------------------------ | ----------------------------------------- |
| `AuthController`         | Register, login e logout                  |
| `ScriptController`       | CRUD completo de scripts                  |
| `GenerateScriptJob`      | Processamento assíncrono da geração       |
| `ScriptGeneratorService` | Comunicação com a IA e geração do roteiro |
| `StoreScriptRequest`     | Validação da criação                      |
| `UpdateScriptRequest`    | Validação da atualização                  |
| `ScriptPolicy`           | Autorização dos scripts                   |
| `ScriptResource`         | Formatação das respostas da API           |
| `routes/api.php`         | Definição das rotas da API                |

Fluxo:

```text
HTTP Request
     ↓
Controller
     ↓
Request Validation
     ↓
Policy / Authorization
     ↓
Script
     ↓
GenerateScriptJob
     ↓
ScriptGeneratorService
     ↓
OpenAI API
     ↓
Script
     ↓
content + status
```

---

# 5. AuthController

Criar:

```text
app/Http/Controllers/Api/AuthController.php
```

Responsabilidades:

* [x] `register()`
* [x] `login()`
* [x] `logout()`

## Register

Endpoint:

```text
POST /api/register
```

* [ ] Validar nome
* [ ] Validar email
* [ ] Validar senha
* [ ] Criar usuário
* [ ] Gerar token Sanctum
* [ ] Retornar usuário e token

## Login

Endpoint:

```text
POST /api/login
```

* [x] Validar email
* [x] Validar senha
* [x] Verificar credenciais
* [x] Criar token Sanctum
* [x] Retornar usuário e token
* [x] Retornar erro apropriado para credenciais inválidas

## Logout

Endpoint:

```text
POST /api/logout
```

* [x] Exigir autenticação
* [ ] Revogar o token atual
* [x] Retornar confirmação de logout

---

# 6. Sanctum

* [x] Configurar Laravel Sanctum
* [x] Utilizar `auth:sanctum`
* [x] Proteger as rotas de scripts
* [x] Utilizar token Bearer nas requisições autenticadas

Exemplo:

```text
Authorization: Bearer {token}
```

---

# 7. ScriptController

Criar:

```text
app/Http/Controllers/ScriptController.php
```

O controller terá CRUD completo:

* [x] `index()`
* [x] `store()`
* [x] `show()`
* [x] `update()`
* [x] `destroy()`

---

## `index()`

```text
GET /api/scripts
```

* [x] Retornar scripts do usuário autenticado
* [x] Não retornar scripts de outros usuários
* [ ] Ordenar pelos mais recentes

---

## `store()`

```text
POST /api/scripts
```

Fluxo:

```text
Request
  ↓
StoreScriptRequest
  ↓
Criar Script
  ↓
status = pending
  ↓
Dispatch GenerateScriptJob
  ↓
Retornar Script
```

* [x] Validar dados com `StoreScriptRequest`
* [x] Associar `user_id` ao usuário autenticado
* [x] Criar script
* [x] Definir `status = pending`
* [ ] Disparar `GenerateScriptJob`
* [ ] Retornar resposta da API

---

## `show()`

```text
GET /api/scripts/{script}
```

* [x] Aplicar `ScriptPolicy`
* [x] Permitir acesso somente ao proprietário
* [ ] Retornar dados do script

---

## `update()`

```text
PUT/PATCH /api/scripts/{script}
```

O `update` será responsável por **atualizar os dados e solicitar uma nova geração do roteiro**.

Fluxo:

```text
Request
   ↓
UpdateScriptRequest
   ↓
Policy
   ↓
Atualizar topic/source_text/style_text
   ↓
Limpar content
   ↓
status = pending
   ↓
Limpar error_message
   ↓
Dispatch GenerateScriptJob
```

* [x] Validar dados com `UpdateScriptRequest`
* [x] Verificar autorização através da Policy
* [x] Atualizar dados do script
* [ ] Limpar `content`
* [ ] Limpar `error_message`
* [x] Alterar status para `pending`
* [ ] Disparar `GenerateScriptJob`

---

## `destroy()`

```text
DELETE /api/scripts/{script}
```

* [x] Aplicar `ScriptPolicy`
* [x] Permitir exclusão somente pelo proprietário
* [x] Excluir script

---

# 8. GenerateScriptJob

Criar:

```text
app/Jobs/GenerateScriptJob.php
```

Responsabilidade:

**Processar a geração do roteiro de forma assíncrona.**

Fluxo:

```text
pending
   ↓
GenerateScriptJob
   ↓
processing
   ↓
ScriptGeneratorService
   ↓
IA
   ↓
done
```

Em caso de erro:

```text
processing
   ↓
Exception
   ↓
failed
   ↓
error_message
```

## Implementação

* [ ] Receber o `Script`
* [ ] Alterar status para `processing`
* [ ] Chamar `ScriptGeneratorService`
* [ ] Receber roteiro gerado
* [ ] Salvar resultado em `content`
* [ ] Alterar status para `done`
* [ ] Capturar exceções
* [ ] Alterar status para `failed`
* [ ] Salvar `error_message`

---

# 9. Queue

A geração será processada através de Job.

* [ ] Configurar driver de queue
* [ ] Configurar worker
* [ ] Testar processamento assíncrono
* [ ] Configurar tentativas do Job
* [ ] Configurar timeout
* [ ] Testar falhas da API

Fluxo:

```text
POST /scripts
      ↓
pending
      ↓
Queue
      ↓
GenerateScriptJob
      ↓
processing
      ↓
IA
      ↓
done / failed
```

---

# 10. ScriptGeneratorService

Criar:

```text
app/Services/ScriptGeneratorService.php
```

Responsabilidade:

**Centralizar toda a comunicação com a API de IA.**

O `GenerateScriptJob` não deve implementar diretamente a comunicação com a IA.

---

## Provedor de IA

O MVP utilizará uma API compatível com o modelo utilizado no projeto.

* [ ] Configurar API Key através do `.env`
* [ ] Criar configuração da API
* [ ] Não armazenar API Key no código
* [ ] Implementar chamada HTTP
* [ ] Processar resposta da IA
* [ ] Tratar erros da API
* [ ] Retornar somente o conteúdo do roteiro

---

# 11. Prompt de Grounding

O prompt deve garantir que a IA utilize o `source_text` como **fonte factual do roteiro**.

### Conceito

```text
topic
   ↓
Define o assunto do roteiro

source_text
   ↓
Fonte factual utilizada pela IA

style_text
   ↓
Define a forma de escrita
```

A IA deve ser instruída a:

* [ ] Utilizar o `source_text` como fonte das informações
* [ ] Não inventar fatos que não estejam presentes na fonte
* [ ] Não adicionar informações externas como fatos
* [ ] Utilizar `topic` para entender o objetivo do roteiro
* [ ] Utilizar `style_text` como referência de estilo
* [ ] Produzir um roteiro coerente com os dados fornecidos

### Regra principal

```text
SOURCE OF TRUTH
      ↓
source_text
```

O `topic` define **sobre o que escrever**.

O `style_text` define **como escrever**.

O `source_text` define **quais informações factuais podem ser utilizadas**.

---

# 12. Configuração da API

Adicionar ao `.env`:

```env
OPENAI_API_KEY=
```

E, caso necessário:

```env
OPENAI_BASE_URL=
OPENAI_MODEL=
```

* [ ] Criar configuração em `config/services.php`
* [ ] Ler API Key através do `.env`
* [ ] Definir modelo através de configuração
* [ ] Evitar valores sensíveis diretamente no código

---

# 13. StoreScriptRequest

Criar:

```text
app/Http/Requests/StoreScriptRequest.php
```

Responsável por validar a criação de scripts.

### Campos

```text
topic
source_text
style_text
```

### Regras

* [ ] `topic` obrigatório
* [ ] `topic` deve ser string
* [ ] `topic` máximo de 20.000 caracteres
* [ ] `source_text` opcional
* [ ] `source_text` deve ser string quando informado
* [ ] `source_text` máximo de 20.000 caracteres
* [ ] `style_text` opcional
* [ ] `style_text` deve ser string quando informado
* [ ] `style_text` máximo de 20.000 caracteres

### Mensagens

As mensagens de validação devem estar em **pt-BR**.

Exemplo:

```text
O tema é obrigatório.
O tema deve ser um texto.
O tema não pode ter mais de 20.000 caracteres.
```

---

# 14. UpdateScriptRequest

Criar:

```text
app/Http/Requests/UpdateScriptRequest.php
```

Responsável pela validação da atualização.

* [ ] Validar `topic`
* [ ] Validar `source_text`
* [ ] Validar `style_text`
* [ ] Limite de 20.000 caracteres
* [ ] Mensagens em pt-BR
* [ ] Permitir atualização dos dados utilizados para nova geração

---

# 15. ScriptPolicy

Criar:

```text
app/Policies/ScriptPolicy.php
```

Objetivo:

**O usuário só pode visualizar, editar e excluir seus próprios roteiros.**

Regra:

```text
User.id === Script.user_id
```

Implementar:

* [ ] `viewAny()`
* [ ] `view()`
* [ ] `create()`
* [ ] `update()`
* [ ] `delete()`

Regra principal:

```text
Usuário 1
 ├── Script 1 ✓
 ├── Script 2 ✓
 └── Script 3 ✗

Usuário 2
 └── Script 3 ✓
```

---

# 16. API Resource

Criar:

```text
app/Http/Resources/ScriptResource.php
```

Responsável por padronizar as respostas.

Exemplo:

```json
{
    "id": 1,
    "user_id": 1,
    "topic": "A história dos buracos negros",
    "source_text": "...",
    "style_text": "...",
    "content": "...",
    "status": "done",
    "error_message": null,
    "created_at": "...",
    "updated_at": "..."
}
```

---

# 17. Rotas

Arquivo:

```text
routes/api.php
```

## Autenticação

```text
POST /api/register
POST /api/login
POST /api/logout
```

## Scripts

Utilizar:

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('scripts', ScriptController::class);

    Route::post('/logout', [AuthController::class, 'logout']);
});
```

A estrutura final deve permitir:

```text
GET    /api/scripts
POST   /api/scripts
GET    /api/scripts/{script}
PUT    /api/scripts/{script}
PATCH  /api/scripts/{script}
DELETE /api/scripts/{script}
```

---

# 18. Fluxo completo do MVP

## Criação

```text
Usuário
   ↓
POST /api/scripts
   ↓
ScriptController@store
   ↓
StoreScriptRequest
   ↓
Script criado
   ↓
status = pending
   ↓
GenerateScriptJob
```

## Geração

```text
GenerateScriptJob
   ↓
status = processing
   ↓
ScriptGeneratorService
   ↓
OpenAI API
   ↓
Roteiro gerado
   ↓
content = roteiro
   ↓
status = done
```

## Erro

```text
GenerateScriptJob
   ↓
ScriptGeneratorService
   ↓
Exception
   ↓
status = failed
   ↓
error_message = erro
```

## Atualização / Regeneração

```text
PUT /api/scripts/{script}
   ↓
UpdateScriptRequest
   ↓
ScriptPolicy
   ↓
Atualiza topic/source_text/style_text
   ↓
content = null
   ↓
status = pending
   ↓
GenerateScriptJob
   ↓
Novo roteiro
```

---

# 19. Front-end do MVP

## Autenticação

* [ ] Tela de cadastro
* [ ] Tela de login
* [ ] Armazenar token
* [ ] Enviar token nas requisições
* [ ] Logout

## Dashboard

* [ ] Listar roteiros
* [ ] Mostrar status
* [ ] Criar novo roteiro
* [ ] Abrir roteiro existente
* [ ] Editar roteiro
* [ ] Excluir roteiro

## Formulário

```text
Tema do vídeo
[____________________________]

Fonte de informações
[____________________________]
Opcional — máximo 20.000 caracteres

Estilo de escrita
[____________________________]
Opcional — máximo 20.000 caracteres

[ Gerar roteiro ]
```

## Visualização

* [ ] Mostrar `topic`
* [ ] Mostrar `status`
* [ ] Mostrar `content`
* [ ] Mostrar `error_message` quando houver
* [ ] Mostrar estado de processamento

---

# 20. Estados da interface

## `pending`

```text
Aguardando geração...
```

## `processing`

```text
Gerando roteiro...
```

## `done`

```text
Roteiro pronto
```

## `failed`

```text
Não foi possível gerar o roteiro.

[Tentar novamente]
```

---

# 21. Testes

## Autenticação

* [ ] Cadastro funciona
* [ ] Login funciona
* [ ] Token Sanctum é gerado
* [ ] Logout invalida o token
* [ ] Endpoint protegido bloqueia usuário não autenticado

## Scripts

* [ ] Usuário consegue criar script
* [ ] Usuário consegue listar seus scripts
* [ ] Usuário consegue visualizar seu script
* [ ] Usuário consegue atualizar seu script
* [ ] Usuário consegue excluir seu script
* [ ] Usuário não consegue acessar script de outro usuário

## Validação

* [ ] `topic` obrigatório
* [ ] `source_text` opcional
* [ ] `style_text` opcional
* [ ] Limite de 20.000 caracteres
* [ ] Mensagens em pt-BR

## Job

* [ ] Script começa como `pending`
* [ ] Job altera para `processing`
* [ ] Sucesso altera para `done`
* [ ] Resultado é salvo em `content`
* [ ] Erro altera para `failed`
* [ ] Erro é salvo em `error_message`

## IA

* [ ] API Key é carregada pelo `.env`
* [ ] `topic` é enviado ao serviço
* [ ] `source_text` é enviado como fonte de grounding
* [ ] `style_text` é enviado como referência de estilo
* [ ] Prompt impede uso de fatos externos ao `source_text`
* [ ] Resposta da IA é salva corretamente

---

# 22. Definition of Done

O MVP estará concluído quando o seguinte fluxo funcionar:

```text
┌─────────────────────────┐
│ Usuário cria uma conta  │
└────────────┬────────────┘
             ↓
┌─────────────────────────┐
│ Login + Token Sanctum   │
└────────────┬────────────┘
             ↓
┌─────────────────────────┐
│ Preenche formulário     │
│                         │
│ topic                   │
│ source_text             │
│ style_text              │
└────────────┬────────────┘
             ↓
┌─────────────────────────┐
│ POST /api/scripts       │
└────────────┬────────────┘
             ↓
┌─────────────────────────┐
│ Script criado           │
│ status = pending        │
└────────────┬────────────┘
             ↓
┌─────────────────────────┐
│ GenerateScriptJob       │
└────────────┬────────────┘
             ↓
┌─────────────────────────┐
│ status = processing     │
└────────────┬────────────┘
             ↓
┌─────────────────────────┐
│ ScriptGeneratorService  │
│                         │
│ OpenAI API              │
│ + grounding             │
└────────────┬────────────┘
             ↓
       ┌─────┴─────┐
       ↓           ↓
     SUCESSO      ERRO
       ↓           ↓
    content      failed
       ↓        error_message
     done
       ↓
┌─────────────────────────┐
│ Usuário vê o roteiro    │
└─────────────────────────┘
```

---

# 23. Fora do escopo do MVP

Não implementar nesta versão:

* [ ] `writing_styles`
* [ ] `script_generations`
* [ ] Histórico de versões
* [ ] Múltiplas gerações armazenadas
* [ ] Controle de tokens utilizados
* [ ] Seleção de modelos pelo usuário
* [ ] Biblioteca de estilos
* [ ] Comparação de versões
* [ ] Regeneração de trechos específicos
* [ ] Analytics de geração
* [ ] Sistema de créditos
* [ ] Sistema de assinatura/pagamento

A estrutura de `WRITING_STYLES` e `SCRIPT_GENERATIONS` permanece como **referência para a v2**, mas não deve ser criada no MVP.
