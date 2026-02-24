# Migração de Contas para Sistema Baseado em Usuários

## Introdução

Este diretório contém os scripts SQL necessários para criar e migrar o sistema de contas de investidores, que agora utiliza diretamente a tabela de usuários (`usuarios`) em vez da tabela de clientes (`clientes`).

## Arquivos

- `tabelas_contas.sql`: Cria a estrutura da tabela de contas e movimentações com referência direta a usuários
- `migracao_contas.sql`: Script para migrar dados existentes de um sistema baseado em clientes para usuários

## Instruções para Novas Instalações

Se você está instalando o sistema pela primeira vez, siga estas etapas:

1. Execute o script `tabelas_contas.sql` para criar as tabelas necessárias:

```sql
SOURCE configuracoes/sql/tabelas_contas.sql
```

2. Certifique-se de que existam usuários cadastrados no sistema, especialmente com o tipo "investidor"

## Instruções para Migração

Se você já tinha o sistema com contas vinculadas a clientes e deseja migrar para contas vinculadas a usuários, siga estas etapas:

1. **IMPORTANTE**: Faça um backup do banco de dados antes de começar!

```sql
mysqldump -u seu_usuario -p seu_banco > backup_antes_migracao.sql
```

2. Execute o script de migração:

```sql
SOURCE configuracoes/sql/migracao_contas.sql
```

3. Verifique se a migração foi bem-sucedida acessando as contas no sistema

4. Se tudo estiver funcionando corretamente, você pode remover a coluna `cliente_id` da tabela `contas` executando:

```sql
ALTER TABLE contas DROP COLUMN cliente_id;
```

## Observações Importantes

- Após a migração, todas as contas estarão vinculadas a usuários em vez de clientes
- O usuário administrador (ID 1) será selecionado caso haja contas sem uma correspondência de usuário
- Recomendamos que você verifique se todos os usuários necessários (investidores) estão cadastrados antes da migração 