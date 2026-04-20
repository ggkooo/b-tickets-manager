# B-Unilab API

API REST do sistema de senhas da Unilab (totem + guiche), com operacao multiunidade e isolamento total por local.

## Visao geral

- Stack: Laravel 12 + Sanctum
- Autenticacao: Bearer Token (Sanctum)
- Seguranca adicional: API Key obrigatoria em todas as rotas `/api/*`
- Multiunidade: `campus` e `centro`
- Perfis: usuario comum, administrador e superadministrador
- Impressao automatica de senha na criacao do ticket
- Sem endpoint de reimpressao

## Requisitos

- PHP 8.2+
- Composer
- Banco compativel com Laravel (SQLite, MySQL, PostgreSQL etc.)

## Setup rapido

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure no `.env`:

```dotenv
APP_API_KEY=seu_api_key_forte
PRINTER_SMB_USERNAME=
PRINTER_SMB_PASSWORD=
PRINTER_SMB_WORKGROUP=

DB_CONNECTION=sqlite
# ou mysql/pgsql...
```

Depois rode:

```bash
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve
```

Base URL local:

```text
http://localhost:8000/api
```

## Producao em Debian com Apache

### Topologia recomendada

- Apache como reverse proxy e balanceador na frente da aplicacao
- Aplicacao Laravel servida por mais de um backend, se voce realmente quiser balanceamento
- Banco, cache, sessoes e filas compartilhados entre todos os nos

Se existir apenas um backend, o Apache funciona como proxy reverso, mas nao ha ganho real de balanceamento.

### Modulos Apache

No Debian, habilite:

```bash
sudo a2enmod proxy
sudo a2enmod proxy_http
sudo a2enmod proxy_balancer
sudo a2enmod lbmethod_byrequests
sudo a2enmod headers
sudo systemctl reload apache2
```

### Variaveis importantes no `.env`

Exemplo minimo para producao:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.exemplo.com.br
APP_KEY=base64:...
APP_API_KEY=uma_chave_forte
TRUSTED_PROXIES=*

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
APP_MAINTENANCE_DRIVER=cache
APP_MAINTENANCE_STORE=database

SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SANCTUM_STATEFUL_DOMAINS=api.exemplo.com.br,painel.exemplo.com.br
```

Observacoes:

- Use a mesma `APP_KEY` em todos os nos
- Todos os nos precisam apontar para o mesmo banco
- Se a autenticacao for somente por Bearer token, `SANCTUM_STATEFUL_DOMAINS` pode ficar vazio
- Se houver frontend web com cookie, configure `SESSION_DOMAIN` e `SANCTUM_STATEFUL_DOMAINS`

### Exemplo de VirtualHost com balanceamento

```apache
<VirtualHost *:80>
  ServerName api.exemplo.com.br

  ProxyPreserveHost On
  ProxyRequests Off

  RequestHeader set X-Forwarded-Proto "http"
  RequestHeader set X-Forwarded-Port "80"

  <Proxy "balancer://b-unilab-cluster">
    BalancerMember "http://127.0.0.1:8081"
    BalancerMember "http://127.0.0.1:8082"
    ProxySet lbmethod=byrequests
  </Proxy>

  ProxyPass "/" "balancer://b-unilab-cluster/"
  ProxyPassReverse "/" "balancer://b-unilab-cluster/"
</VirtualHost>
```

Se o SSL terminar no Apache, use HTTPS no VirtualHost publico e encaminhe o protocolo correto:

```apache
<VirtualHost *:443>
  ServerName api.exemplo.com.br

  SSLEngine on
  SSLCertificateFile /etc/letsencrypt/live/api.exemplo.com.br/fullchain.pem
  SSLCertificateKeyFile /etc/letsencrypt/live/api.exemplo.com.br/privkey.pem

  ProxyPreserveHost On
  ProxyRequests Off

  RequestHeader set X-Forwarded-Proto "https"
  RequestHeader set X-Forwarded-Port "443"

  <Proxy "balancer://b-unilab-cluster">
    BalancerMember "http://127.0.0.1:8081"
    BalancerMember "http://127.0.0.1:8082"
    ProxySet lbmethod=byrequests
  </Proxy>

  ProxyPass "/" "balancer://b-unilab-cluster/"
  ProxyPassReverse "/" "balancer://b-unilab-cluster/"
</VirtualHost>
```

### O que precisa estar correto na aplicacao

- O Laravel precisa confiar nos headers `X-Forwarded-*` do Apache
- `APP_URL` deve apontar para a URL publica final
- Cookies de sessao devem usar `SESSION_SECURE_COOKIE=true` quando houver HTTPS
- Sessoes, cache e filas nao podem depender de arquivo local em ambiente com multiplos nos

### Atencao com arquivos e videos

Os videos enviados pela API usam o disco `public` local. Em ambiente com mais de um backend, isso significa que um upload feito no no A pode nao existir no no B.

Para evitar isso, use uma destas abordagens:

- storage compartilhado entre os servidores
- disco externo compativel com S3
- apenas um backend para upload e streaming, o que reduz a vantagem do balanceamento

### Atencao com o worker de impressao

O job de impressao roda na fila `printing`. Em ambiente com varios nos, so devem consumir essa fila os servidores que realmente consigam acessar a impressora daquele local.

Exemplo:

```bash
php artisan queue:work --queue=printing,default
```

Se um no nao tiver acesso a impressora, nao deixe esse no consumir a fila de impressao.

### Deploy basico no Debian

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan queue:restart
```

### Workers de fila no Debian

O Apache nao executa workers da fila. Ele atende apenas as requisicoes HTTP.

Para processar filas em producao, rode os workers como servicos do sistema com `systemd`. Eles podem ficar na mesma maquina do Apache, mas devem rodar como processos separados.

Nesta aplicacao, isso e importante porque:

- a fila padrao processa os jobs gerais
- a fila `printing` processa os jobs de impressao

Exemplo de servico para a fila padrao:

```ini
[Unit]
Description=B-Unilab Queue Worker Default
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/b-unilab
ExecStart=/usr/bin/php artisan queue:work database --queue=default --sleep=3 --tries=3 --timeout=120
KillSignal=SIGTERM
TimeoutStopSec=60

[Install]
WantedBy=multi-user.target
```

Salve como `/etc/systemd/system/b-unilab-queue.service`.

Exemplo de servico para a fila de impressao:

```ini
[Unit]
Description=B-Unilab Queue Worker Printing
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/b-unilab
ExecStart=/usr/bin/php artisan queue:work database --queue=printing --sleep=3 --tries=3 --timeout=120
KillSignal=SIGTERM
TimeoutStopSec=60

[Install]
WantedBy=multi-user.target
```

Salve como `/etc/systemd/system/b-unilab-printing.service`.

Depois habilite e inicie os servicos:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now b-unilab-queue.service
sudo systemctl enable --now b-unilab-printing.service
sudo systemctl status b-unilab-queue.service
sudo systemctl status b-unilab-printing.service
```

Para acompanhar logs:

```bash
journalctl -u b-unilab-queue.service -f
journalctl -u b-unilab-printing.service -f
```

### Recomendacoes para ambiente com mais de um no

- a fila `default` pode rodar em qualquer no da aplicacao
- a fila `printing` so deve rodar em nos que realmente tenham acesso a impressora

Se um no nao tiver acesso a impressora e consumir a fila `printing`, os jobs vao falhar.

### Reinicio de workers apos deploy

Depois de publicar nova versao da aplicacao, rode:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan queue:restart
```

O comando `queue:restart` faz os workers recarregarem o codigo novo com seguranca.

## Seguranca e headers

### Header obrigatorio em TODAS as rotas

```http
X-API-KEY: <APP_API_KEY>
Accept: application/json
```

Se faltar ou estiver invalida:

```json
{
  "message": "Unauthorized: Invalid or missing API Key"
}
```

### Header adicional em rotas autenticadas

```http
Authorization: Bearer <sanctum_token>
```

## Multiunidade e isolamento

Locais validos:

- `campus`
- `centro`

Regras:

- Login exige `login + password + location`
- Mesmo `login` pode existir em locais diferentes
- Usuarios so enxergam e operam dados do proprio local
- Administradores so podem consultar relatorios do proprio local
- Superadministradores gerenciam usuarios, videos e impressoras do proprio local
- Regra de ultimo admin e por local

## Como informar `location`

### Rotas publicas de ticket

Para as rotas abaixo, informe o local por body/query/header:

- `GET /api/tickets`
- `POST /api/tickets`
- `GET /api/tickets/recently-called`

Pode enviar:

1. Campo `location` (query/body)
2. Header `X-UNILAB-LOCATION`

Se enviar os dois, o backend prioriza o campo `location`.

### Rotas autenticadas

Nao precisa enviar `location`; o sistema usa o local do usuario autenticado.

## Usuarios padrao

O seeder cria (ou atualiza) um admin para cada local:

- `login`: `admin`
- `password`: `admin`
- `name`: `Administrador`
- `is_admin`: `true`
- `is_super_admin`: `true`
- `location`: `campus` e `centro`

Altere essas senhas em producao.

## Impressao de tickets

A impressao ocorre automaticamente no `POST /api/tickets`.

### Importante

- Nao existe endpoint de reimpressao
- A impressao e resolvida pela configuracao do local do ticket
- Sem configuracao cadastrada em banco para o local, a impressao falha (log em `storage/logs/laravel.log`)

### Formatos suportados

- `network` (TCP/IP): host + port
- `shared_windows` (impressora compartilhada via rede)

Para `shared_windows`, o sistema aceita UNC e converte para SMB internamente:

- Entrada aceita: `\\SERVIDOR\\IMPRESSORA`
- Uso interno: `smb://SERVIDOR/IMPRESSORA`

Se o servidor da aplicacao estiver em Linux com `smbclient`, voce pode informar as credenciais SMB pelo `.env`:

- `PRINTER_SMB_USERNAME`
- `PRINTER_SMB_PASSWORD`
- `PRINTER_SMB_WORKGROUP` (opcional)

Essas credenciais sao aplicadas automaticamente nas impressoes `shared_windows` em tempo de execucao. O `share_path` salvo no banco continua sendo apenas o caminho da impressora.

### Fonte de configuracao

A impressao usa exclusivamente os dados salvos em `printer_settings` por localidade (`campus`/`centro`).

## Regras de negocio de ticket

`service_type` aceitos:

- `Atendimento Normal`
- `Atendimento Preferencial`
- `Retirada de Exames ou Entrega de Amostras`

Prefixos:

- `N` -> Atendimento Normal
- `P` -> Atendimento Preferencial
- `E` -> Retirada de Exames ou Entrega de Amostras

Sequencia:

- Reinicia diariamente por prefixo e por local
- Exemplo: `N-0001`, `P-0001`, `E-0001`

## Endpoints

### Publicos

| Metodo | Endpoint | Descricao |
|---|---|---|
| POST | `/api/login` | Login com `location` |
| GET | `/api/tickets` | Fila aberta do local |
| POST | `/api/tickets` | Cria ticket e tenta imprimir |
| GET | `/api/tickets/recently-called` | Ultimas 5 chamadas do local |
| GET | `/api/videos` | Lista videos mp4 |
| GET | `/api/videos/{filename}` | Stream de video mp4 |

### Autenticados (`auth:sanctum`)

| Metodo | Endpoint | Descricao |
|---|---|---|
| POST | `/api/tickets/{id}/call` | Chama ticket |
| POST | `/api/tickets/{id}/recall` | Rechama ticket |
| PATCH | `/api/tickets/{id}/complete` | Finaliza como atendido |
| PATCH | `/api/tickets/{id}/cancel` | Finaliza como cancelado |
| GET | `/api/tickets/completed` | Tickets finalizados hoje no local |

Observacao: em `/api/tickets/{id}/...`, o `{id}` pode ser ID numerico ou chave (ex.: `P-0001`).

### Admin (`auth:sanctum` + `admin`)

| Metodo | Endpoint | Descricao |
|---|---|---|
| GET | `/api/reports/attendances` | Relatorio de atendimentos |

### Superadmin (`auth:sanctum` + `superadmin`)

| Metodo | Endpoint | Descricao |
|---|---|---|
| POST | `/api/register` | Cadastra usuario no mesmo local do superadmin |
| GET | `/api/printer-settings` | Lista impressoras do local |
| POST | `/api/printer-settings` | Cadastra uma nova impressora para o local |
| PATCH | `/api/printer-settings/{printerSetting}` | Atualiza uma impressora e permite habilitar/desabilitar |
| POST | `/api/videos/upload` | Upload de video mp4 |
| DELETE | `/api/videos/{filename}` | Remove video |
| GET | `/api/users` | Lista usuarios do local |
| PATCH | `/api/users/{user}` | Atualiza usuario |
| DELETE | `/api/users/{user}` | Remove usuario |
| PATCH | `/api/users/{user}/make-admin` | Promove para admin |
| PATCH | `/api/users/{user}/remove-admin` | Remove admin |

## Exemplos de payloads

### 1) Login

`POST /api/login`

```json
{
  "login": "admin",
  "password": "admin",
  "location": "campus"
}
```

Sucesso `200`:

```json
{
  "status": "success",
  "message": "Login successful",
  "data": {
    "access_token": "1|token-value",
    "token_type": "Bearer",
    "user": {
      "id": 1,
      "uuid": "...",
      "name": "Administrador",
      "login": "admin",
      "location": "campus",
      "active": true,
      "is_admin": true,
      "is_super_admin": true,
      "created_at": "2026-04-14T12:00:00.000000Z",
      "updated_at": "2026-04-14T12:00:00.000000Z"
    }
  }
}
```

Erro `401`:

```json
{
  "status": "error",
  "message": "Invalid credentials"
}
```

### 2) Registrar usuario (superadmin)

`POST /api/register`

```json
{
  "name": "Maria Silva",
  "login": "maria.silva",
  "password": "secret123",
  "password_confirmation": "secret123"
}
```

Regras:

- `login` e normalizado para minusculo
- unico por local (nao global)
- usuario criado como `is_admin = false`, `is_super_admin = false` e no local do superadmin logado

### 3) Criar ticket

`POST /api/tickets`

```json
{
  "service_type": "Atendimento Preferencial",
  "location": "campus"
}
```

Sucesso `201`:

```json
{
  "ticket": {
    "id": 12,
    "key": "P-0003",
    "location": "campus",
    "service_type": "Atendimento Preferencial",
    "completed": false,
    "created_at": "2026-04-14T12:41:09.000000Z",
    "updated_at": "2026-04-14T12:41:09.000000Z"
  },
  "print": {
    "status": "enviando"
  }
}
```

Observacao: a impressao e disparada de forma assincrona apos a resposta ser enviada ao cliente. O campo `print.status` sempre retorna `"enviando"`. Sucesso ou falha sao registrados em `storage/logs/laravel.log`.

### 4) Configurar impressora (superadmin)

`GET /api/printer-settings`

- Retorna as impressoras cadastradas no local do superadmin
- Ordena com as habilitadas primeiro e depois por nome

Quando nao existir nenhuma impressora cadastrada:

```json
{
  "location": "campus",
  "data": []
}
```

Resposta de exemplo:

```json
{
  "location": "campus",
  "data": [
    {
      "id": 1,
      "location": "campus",
      "name": "Balcao 1",
      "enabled": true,
      "connection_type": "network",
      "host": "10.0.0.25",
      "port": 9100,
      "share_path": null,
      "profile": "simple",
      "header": "SENHA CAMPUS",
      "created_at": "2026-04-14T12:31:34.000000Z",
      "updated_at": "2026-04-14T12:31:34.000000Z"
    }
  ]
}
```

Status de sucesso: `200 OK`

`POST /api/printer-settings`

Campos:

- `name` (string, obrigatorio, unico por local)
- `enabled` (boolean, obrigatorio)
- `connection_type` (`network` ou `shared_windows`, obrigatorio)
- `host` (obrigatorio quando `network`)
- `port` (opcional quando `network`, default `9100`)
- `share_path` (obrigatorio quando `shared_windows`)
- `profile` (opcional, default `simple`)
- `header` (opcional, default `SENHA DE ATENDIMENTO`)

Observacoes:

- `name` precisa ser unico dentro do local do superadmin autenticado
- `host` e `port` sao limpos quando `connection_type = shared_windows`
- `share_path` e limpo quando `connection_type = network`

Exemplo `network`:

```json
{
  "name": "Balcao 1",
  "enabled": true,
  "connection_type": "network",
  "host": "10.0.0.25",
  "port": 9100,
  "profile": "simple",
  "header": "SENHA CAMPUS"
}
```

Resposta de sucesso `201 Created`:

```json
{
  "message": "Impressora cadastrada com sucesso.",
  "data": {
    "id": 2,
    "location": "campus",
    "name": "Balcao 1",
    "enabled": true,
    "connection_type": "network",
    "host": "10.0.0.25",
    "port": 9100,
    "share_path": null,
    "profile": "simple",
    "header": "SENHA CAMPUS",
    "created_at": "2026-04-20T12:31:34.000000Z",
    "updated_at": "2026-04-20T12:31:34.000000Z"
  }
}
```

Exemplo `shared_windows`:

```json
{
  "name": "Recepcao",
  "enabled": true,
  "connection_type": "shared_windows",
  "share_path": "\\\\200.132.194.29\\EPSON-TM-T20X",
  "profile": "simple",
  "header": "SENHA CENTRO"
}
```

Resposta de sucesso `201 Created`:

```json
{
  "message": "Impressora cadastrada com sucesso.",
  "data": {
    "id": 1,
    "location": "centro",
    "name": "Recepcao",
    "enabled": true,
    "connection_type": "shared_windows",
    "host": null,
    "port": null,
    "share_path": "\\\\200.132.194.29\\EPSON-TM-T20X",
    "profile": "simple",
    "header": "SENHA DE ATENDIMENTO",
    "created_at": "2026-04-14T12:31:34.000000Z",
    "updated_at": "2026-04-14T12:31:34.000000Z"
  }
}
```

`PATCH /api/printer-settings/{printerSetting}`

- Aceita atualizacao parcial
- Use este endpoint para habilitar ou desabilitar uma impressora especifica
- A impressora precisa pertencer ao mesmo local do superadmin autenticado

Exemplo para atualizar nome e cabecalho:

```json
{
  "name": "Balcao Principal",
  "header": "SENHA CAMPUS PRINCIPAL"
}
```

Exemplo para desabilitar:

```json
{
  "enabled": false
}
```

Exemplo para trocar o tipo para `shared_windows`:

```json
{
  "connection_type": "shared_windows",
  "share_path": "\\\\PC-CAMPUS\\EPSON-TM-T20",
  "enabled": true
}
```

Resposta de sucesso `200 OK`:

```json
{
  "message": "Configuracao da impressora atualizada com sucesso.",
  "data": {
    "id": 1,
    "location": "campus",
    "name": "Balcao Principal",
    "enabled": false,
    "connection_type": "network",
    "host": "10.0.0.25",
    "port": 9100,
    "share_path": null,
    "profile": "simple",
    "header": "SENHA CAMPUS",
    "created_at": "2026-04-20T12:31:34.000000Z",
    "updated_at": "2026-04-20T12:45:00.000000Z"
  }
}
```

Resposta de erro `404 Not Found`:

```json
{
  "message": "Impressora nao encontrada."
}
```

Observacao: a fila de impressao tenta imprimir em todas as impressoras habilitadas do local. Se uma falhar e outra funcionar, o ticket segue como impresso e a falha parcial fica registrada no log.

Erros `422` comuns:

```json
{
  "message": "Host e obrigatorio para impressora de rede."
}
```

```json
{
  "message": "share_path e obrigatorio para impressora compartilhada no Windows."
}
```

```json
{
  "message": "The name has already been taken.",
  "errors": {
    "name": [
      "The name has already been taken."
    ]
  }
}
```

### 5) Relatorio de atendimentos (admin/superadmin)

`GET /api/reports/attendances?start_date=2026-04-01&end_date=2026-04-14`

Parametros obrigatorios:

- `start_date` (`Y-m-d`)
- `end_date` (`Y-m-d`, maior ou igual a `start_date`)

Escopo:

- Sempre filtrado pela localidade do usuario autenticado (admin ou superadmin)
- Consolida dados de `tickets` + `ticket_archives`

Retorna metricas como:

- periodo
- tempo medio de espera
- media de atendimentos por dia
- atendimentos por dia
- atendimentos por tipo
- atendimentos por resultado (`completed`, `canceled`, `unknown`)
- atendimentos por guiche
- atendimentos por usuario
- total de atendimentos

### 6) Videos

`POST /api/videos/upload` (superadmin, multipart/form-data):

- campo `video`: obrigatorio, MIME `video/mp4`

Resposta `201`:

```json
{
  "message": "Video uploaded successfully",
  "filename": "video_abc123.mp4",
  "url": "/storage/videos/video_abc123.mp4"
}
```

`GET /api/videos`:

- Lista arquivos mp4 com `filename` e `url`

`GET /api/videos/{filename}`:

- Stream inline de mp4

`DELETE /api/videos/{filename}` (superadmin):

- Remove video
- Se nao existir: `404` com `Video not found`

### 7) Usuarios (superadmin)

#### GET `/api/users`

Retorna usuarios do local do superadmin logado.

#### PATCH `/api/users/{user}`

Body opcional:

```json
{
  "name": "Guiche 02",
  "login": "guiche_02",
  "password": "newsecret123",
  "password_confirmation": "newsecret123",
  "active": true
}
```

Regras:

- login normalizado para minusculo
- login unico dentro do local
- tentativa de mexer em usuario de outro local retorna `404`

#### DELETE `/api/users/{user}`

- Remove usuario do mesmo local
- Bloqueia remocao do ultimo admin do local (`422`)

#### PATCH `/api/users/{user}/make-admin`

- Promove usuario do mesmo local para admin

#### PATCH `/api/users/{user}/remove-admin`

- Remove privilegio admin
- Bloqueia se for o ultimo admin do local (`422`)

## Codigos de erro comuns

### 401 Unauthorized

- API key ausente/invalida
- token ausente/invalido em rota protegida

Exemplo:

```json
{
  "message": "Unauthenticated."
}
```

### 403 Forbidden

Usuario autenticado sem perfil de acesso exigido:

```json
{
  "message": "Forbidden: administrator access required"
}
```

Ou, em rotas exclusivas de superadmin:

```json
{
  "message": "Forbidden: super administrator access required"
}
```

### 404 Not Found

- recurso nao encontrado
- acesso a recurso de outra localidade (em rotas com model binding ou lookup local)

### 422 Unprocessable Entity

Erros de validacao ou regra de negocio.

Exemplos:

```json
{
  "message": "Local invalido. Use campus ou centro."
}
```

```json
{
  "message": "Cannot delete the last administrator"
}
```

## Operacao e manutencao

### Arquivamento de tickets finalizados

Existe comando:

```bash
php artisan tickets:archive-completed
```

Comportamento:

- Move tickets finalizados de dias anteriores para `ticket_archives`
- Preserva localidade e dados de atendimento

Agendamento:

- executa diariamente as `00:05`
- com `withoutOverlapping`

### Logs uteis

Falhas de impressao sao registradas em `storage/logs/laravel.log`.

## Testes

Rodar todos:

```bash
php artisan test
```

## Licenca

Proprietario - Unilab.
