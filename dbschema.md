## Diagrama de banco de dados
```mermaid
erDiagram
    USERS ||--o{ SCRIPTS : "cria"

    USERS {
        bigint id PK
        varchar name
        varchar email
        timestamp email_verified_at "nullable"
        varchar password
        varchar remember_token "nullable"
        timestamp created_at
        timestamp updated_at
    }

    SCRIPTS {
        bigint id PK
        bigint user_id FK "→ users.id, cascade on delete"
        varchar topic "obrigatório — máximo 255 caracteres"
        text source_text "obrigatório — fonte de fatos"
        text style_text "nullable — referência de estilo"
        text generated_content "nullable — roteiro gerado"
        varchar status "default: pending"
        text error_message "nullable"
        timestamp created_at
        timestamp updated_at
    }
```

## Fluxos de status implementados

```mermaid
stateDiagram-v2
    [*] --> pending : script criado ou metadados atualizados
    pending --> processing : GenerateScriptJob inicia
    processing --> done : IA respondeu
    processing --> failed : exceção / timeout da API
    done --> processing : revisão solicitada
    failed --> processing : revisão solicitada com conteúdo anterior
    processing --> done : revisão concluída
    failed --> pending : atualização de metadados solicita nova geração
```

## Evolução v2 (referência futura, não criar ainda)

```mermaid
erDiagram
    USERS ||--o{ SCRIPTS : "cria"
    USERS ||--o{ WRITING_STYLES : "possui"
    SCRIPTS ||--o{ SCRIPT_GENERATIONS : "tem versões"
    SCRIPTS }o--o| WRITING_STYLES : "usa (opcional)"

    WRITING_STYLES {
        bigint id PK
        bigint user_id FK
        varchar name
        text content
        timestamp created_at
        timestamp updated_at
    }

    SCRIPT_GENERATIONS {
        bigint id PK
        bigint script_id FK
        longtext content
        varchar status
        varchar model
        int tokens_used
        text error_message
        timestamp created_at
    }
```