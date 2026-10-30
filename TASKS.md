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
8. A IA, por meio de um endpoint compatível com a API da OpenAI, gera o roteiro utilizando **somente o `source_text` como fonte factual (grounding)**.
9. O resultado é salvo em `generated_content`.
10. O usuário pode visualizar, atualizar metadados para gerar novamente, revisar o conteúdo existente por instrução contextual e excluir seus roteiros.

---

# 2. Banco de Dados

## USERS

Utilizar a tabela padrão `users` do Laravel.

| Campo        | Tipo      | Regras             |
| ------------ | --------- | ------------------ |
| `id`         | bigint    | PK                 |
| `name`       | varchar   | obrigatório        |
| `email`      | varchar   | obrigatório, único |
| `email_verified_at` | timestamp | nullable       |
| `password`   | varchar   | obrigatório        |
| `remember_token` | varchar | nullable           |
| `created_at` | timestamp | automático         |
| `updated_at` | timestamp | automático         |

---

## SCRIPTS

| Campo               | Tipo      | Regras             |
| ------------------- | --------- | ------------------ |
| `id`                | bigint    | PK                 |
| `user_id`           | bigint    | FK → `users.id`    |
| `topic`             | varchar   | obrigatório, até 255 caracteres |
| `source_text`       | text      | obrigatório        |
| `style_text`        | text      | nullable           |
| `generated_content` | text      | nullable           |
| `status`            | varchar   | default: `pending` |
| `error_message`     | text      | nullable           |
| `created_at`        | timestamp | automático         |
| `updated_at`        | timestamp | automático         |

### Relacionamentos

```text
User
 └── hasMany(Script)

Script
 └── belongsTo(User)
```

* [x] Criar migration `scripts`
* [x] Criar foreign key `user_id`
* [x] Configurar `onDelete('cascade')`
* [x] Criar model `Script`
* [x] Configurar relacionamento `User::scripts()`
* [x] Configurar relacionamento `Script::user()`

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

O usuário pode solicitar uma revisão contextual em um roteiro `done` ou `failed`
que ainda tenha `generated_content`. Se uma revisão falhar, o conteúdo anterior
permanece disponível.

---

# 4. Arquitetura da aplicação

Responsabilidades principais:

| Componente               | Responsabilidade                          |
| ------------------------ | ----------------------------------------- |
| `AuthController`         | Register, login e logout                  |
| `ScriptController`       | CRUD completo de scripts                  |
| `GenerateScriptJob`      | Processamento assíncrono da geração       |
| `ReviseScriptJob`        | Revisão assíncrona de um roteiro existente |
| `ScriptGenerationJob`    | Ciclo compartilhado de execução, sucesso e erro |
| `ScriptGeneratorService` | Comunicação com a IA para gerar e revisar roteiros |
| `StoreScriptRequest`     | Validação da criação                      |
| `UpdateScriptRequest`    | Validação da atualização                  |
| `ReviseScriptRequest`    | Validação da instrução de revisão         |
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
Provedor compatível com OpenAI
     ↓
Script
     ↓
generated_content + status
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

* [x] Validar nome
* [x] Validar email
* [x] Validar senha
* [x] Criar usuário
* [x] Gerar token Sanctum
* [x] Retornar usuário e token

## Login

Endpoint:

```text
POST /api/login
```

`device_name` é obrigatório para o login da API.

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
* [x] Revogar o token atual
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

Implementado em:

```text
app/Http/Controllers/Api/ScriptController.php
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
* [x] Ordenar pelos mais recentes

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
* [x] Disparar `GenerateScriptJob`
* [x] Retornar resposta da API

---

## `show()`

```text
GET /api/scripts/{script}
```

* [x] Aplicar `ScriptPolicy`
* [x] Permitir acesso somente ao proprietário
* [x] Retornar dados do script

---

## `update()`

```text
PUT/PATCH /api/scripts/{script}
```

O `update` atualiza metadados e solicita uma nova geração. A edição contextual
do texto existente é uma operação separada em `POST /api/scripts/{script}/revise`.

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
Limpar generated_content e error_message
   ↓
status = pending
   ↓
Dispatch GenerateScriptJob
```

* [x] Validar dados com `UpdateScriptRequest`
* [x] Verificar autorização através da Policy
* [x] Atualizar dados do script
* [x] Limpar `generated_content`
* [x] Limpar `error_message`
* [x] Alterar status para `pending`
* [x] Disparar `GenerateScriptJob`
* [x] Retornar HTTP `409` se já estiver em processamento

---

## Revisão contextual

Endpoint autenticado:

```text
POST /api/scripts/{script}/revise
```

Payload:

```json
{
  "instruction": "Amplie a conclusão sem alterar o restante desnecessariamente."
}
```

Fluxo:

```text
ReviseScriptRequest
   ↓
ScriptPolicy
   ↓
Checar e bloquear concorrência
   ↓
status = processing
   ↓
ReviseScriptJob
   ↓
ScriptGeneratorService::revise
   ↓
IA recebe roteiro anterior, instrução, tema, fonte e estilo
   ↓
Sucesso: substituir generated_content e status = done
Falha: manter generated_content e status = failed
```

Checklist:

* [x] Validar `instruction` obrigatória, string, até 20.000 caracteres e mensagens pt-BR
* [x] Aplicar autorização de proprietário existente
* [x] Recusar revisão sem conteúdo anterior
* [x] Recusar revisão simultânea com HTTP `409`
* [x] Preservar a versão anterior enquanto a IA processa
* [x] Salvar a nova versão somente após resposta não vazia bem-sucedida
* [x] Preservar a versão anterior quando IA ou fila falhar
* [x] Sanitizar mensagens de erro antes de salvar em `error_message`
* [x] Compartilhar ciclo de processamento entre os jobs sem duplicar tratamento
* [x] Cobrir validação, autorização, concorrência, sucesso e falhas em testes

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

* [x] Receber o `Script`
* [x] Alterar status para `processing`
* [x] Chamar `ScriptGeneratorService`
* [x] Receber roteiro gerado
* [x] Salvar resultado em `generated_content`.
* [x] Alterar status para `done`
* [x] Capturar exceções
* [x] Alterar status para `failed`
* [x] Salvar `error_message` com sanitização de credenciais.

---

# 9. Queue

A geração será processada através de Job.

* [x] Configurar driver de queue (padrão `database`; depende do ambiente).
* [ ] Configurar e manter o worker no ambiente de execução (`php artisan queue:work`); isso não é provisionado por este repositório.
* [x] Testar despacho dos Jobs e seus ciclos de sucesso e falha
* [ ] Configurar tentativas do Job
* [ ] Configurar timeout
* [ ] Testar falhas reais de integração com o provedor (a suíte atual testa falhas dos Jobs com o serviço simulado).

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

* [x] Configurar API Key através do `.env` (configuração lê `AI_API_KEY`).
* [x] Criar configuração da API (`config/services.php`, chave `ai_provider`).
* [x] Não armazenar API Key no código (nenhuma chave literal encontrada nos arquivos de configuração revisados).
* [x] Implementar comunicação com o provedor via NeuronAI/OpenAILike.
* [x] Processar resposta da IA
* [x] Tratar erros da API (job marca falha e relança a exceção).
* [x] Retornar somente o conteúdo do roteiro

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

* [x] Utilizar o `source_text` como fonte das informações
* [x] Não inventar fatos que não estejam presentes na fonte
* [x] Não adicionar informações externas como fatos
* [x] Utilizar `topic` para entender o objetivo do roteiro
* [x] Utilizar `style_text` como referência de estilo
* [x] Produzir um roteiro coerente com os dados fornecidos

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
AI_API_KEY=
```

E, caso necessário:

```env
AI_BASE_URI=
AI_MODEL=
```

* [x] Criar configuração em `config/services.php` (configuração `ai_provider`).
* [x] Ler API Key através do `.env` (variável `AI_API_KEY`).
* [x] Definir modelo através de configuração (`AI_MODEL`).
* [x] Evitar valores sensíveis diretamente no código (a configuração lê `AI_API_KEY` do ambiente).

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

* [x] `topic` obrigatório, string e limitado a 255 caracteres
* [x] `source_text` obrigatório
* [x] `source_text` deve ser string
* [x] `source_text` máximo de 20.000 caracteres
* [x] `style_text` opcional
* [x] `style_text` deve ser string quando informado
* [x] `style_text` máximo de 20.000 caracteres

### Mensagens

As mensagens de validação devem estar em **pt-BR**.

Exemplo:

```text
O tema é obrigatório.
O tema deve ser um texto.
O tema não pode ter mais de 255 caracteres.
```

---

# 14. UpdateScriptRequest

Criar:

```text
app/Http/Requests/UpdateScriptRequest.php
```

Responsável pela validação da atualização.

* [x] Validar `topic` (máximo de 255 caracteres)
* [x] Validar `source_text` (obrigatório, máximo de 20.000 caracteres)
* [x] Validar `style_text` (opcional, máximo de 20.000 caracteres)
* [x] Validar `source_text` e `style_text` com limite de 20.000 caracteres
* [x] Retornar mensagens de validação em pt-BR
* [x] Atualizar metadados, limpar conteúdo/erro, redefinir `pending` e despachar geração
* [x] Recusar atualização durante processamento

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

* [x] `viewAny()`
* [x] `view()`
* [x] `create()`
* [x] `update()`
* [x] `delete()`

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
    "source_text": "Fontes da historia dos buracos negros",
    "style_text": "...",
    "generated_content": "...",
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
GET  /api/user
```

## Scripts

Utilizar:

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/scripts/{script}/revise', [ScriptController::class, 'revise']);
    Route::apiResource('scripts', ScriptController::class);
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
POST   /api/scripts/{script}/revise
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
Provedor compatível com OpenAI
   ↓
Roteiro gerado
   ↓
generated_content = roteiro
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
generated_content = null
   ↓
status = pending
   ↓
GenerateScriptJob
   ↓
Novo roteiro
```

## Revisão contextual

```text
POST /api/scripts/{script}/revise
   ↓
Validar instrução e Policy
   ↓
Bloquear concorrência; manter generated_content atual
   ↓
status = processing
   ↓
ReviseScriptJob + ScriptGeneratorService
   ↓
IA revisa o roteiro atual usando a instrução e os metadados
   ↓
Sucesso: salvar roteiro completo revisado e status = done
Falha: manter versão anterior, status = failed e error_message sanitizado
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
Obrigatório — máximo 20.000 caracteres

Estilo de escrita
[____________________________]
Opcional — máximo 20.000 caracteres

[ Gerar roteiro ]
```

## Visualização

* [ ] Mostrar `topic`
* [ ] Mostrar `status`
* [ ] Mostrar `generated_content`
* [ ] Mostrar `error_message` quando houver
* [ ] Mostrar estado de processamento
* [ ] Permitir enviar instrução de revisão para `POST /api/scripts/{script}/revise`
* [ ] Manter o conteúdo anterior visível enquanto uma revisão está em processamento ou falha
* [ ] Atualizar a visualização quando a revisão terminar com status `done`

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

Durante uma revisão, `processing` não significa que o conteúdo anterior deixou
de existir. A interface deve mantê-lo visível. Se a revisão falhar, apresentar
`error_message` e continuar exibindo a versão preservada.

---

# 21. Testes

Os testes marcados abaixo referem-se à cobertura automatizada existente. A
implementação de uma rota, por si só, não significa que ela já tenha um teste
automatizado correspondente.

## Autenticação

* [ ] Cadastro funciona
* [ ] Login funciona
* [ ] Token Sanctum é gerado
* [ ] Logout invalida o token
* [ ] Endpoint protegido bloqueia usuário não autenticado

## Scripts

* [x] Usuário consegue criar script
* [x] Usuário consegue listar seus scripts
* [ ] Usuário consegue visualizar seu script
* [x] Usuário consegue atualizar seu script
* [x] Usuário proprietário consegue solicitar revisão contextual
* [ ] Usuário consegue excluir seu próprio script
* [x] Usuário não consegue visualizar, atualizar ou excluir script de outro usuário

## Validação

* [x] `topic` obrigatório
* [x] `source_text` obrigatório
* [x] `style_text` opcional
* [x] `source_text` e `style_text` limitados a 20.000 caracteres
* [x] `topic` limitado a 255 caracteres
* [x] Instrução de revisão obrigatória, limitada a 20.000 caracteres
* [x] Mensagens de validação em pt-BR

## Job

* [x] Script começa como `pending`
* [x] Job altera para `processing`
* [x] Sucesso altera para `done`
* [x] Resultado é salvo em `generated_content`
* [x] Erro altera para `failed`
* [x] Erro é salvo em `error_message`
* [x] Revisão mantém o conteúdo anterior até a conclusão bem-sucedida
* [x] Revisões simultâneas do mesmo roteiro são bloqueadas
* [x] Falha da revisão preserva o roteiro anterior

## IA

* [ ] Teste automatizado confirma a leitura da API Key do `.env`
* [ ] Testes automatizados verificam `topic`, `source_text` e `style_text` enviados ao provedor
* [ ] Teste automatizado verifica grounding e formato da resposta do provedor

---

# 22. Definition of Done

O backend do MVP implementa o seguinte fluxo. A interface continua pendente e
está fora deste repositório:

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
│ Provedor compatível     │
│ + grounding             │
└────────────┬────────────┘
             ↓
       ┌─────┴─────┐
       ↓           ↓
     SUCESSO      ERRO
       ↓           ↓
    generated_content      failed
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
