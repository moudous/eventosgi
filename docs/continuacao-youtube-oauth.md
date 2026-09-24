# Continuação — integração YouTube OAuth

Data: 24/09/2026

## O que já foi implementado

- Módulo de transmissões com model `Transmissao`, migration `transmissoes`, controller e telas de listagem/criação.
- Correção do plural do Eloquent: `Transmissao` usa explicitamente a tabela `transmissoes`.
- OAuth do YouTube implementado sem expor credenciais:
  - configuração em `config/youtube.php`;
  - model `YouTubeConexao`, com `access_token` e `refresh_token` criptografados;
  - migration `2026_09_24_130000_create_youtube_conexoes_table.php` aplicada;
  - serviço `YouTubeOAuthService`;
  - botão “Conectar conta do YouTube” na tela `/transmissao`;
  - retorno OAuth validado por `state` armazenado na sessão;
  - rotas de retorno aceitas:
    - `http://localhost:8006/transmissao/youtube/callback`
    - `http://localhost:8006/transmissao/youtube/retorno`

## Configuração local já confirmada

As variáveis abaixo existem e foram reconhecidas pelo EventosGI. Não registrar valores neste arquivo:

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost:8006/transmissao/youtube/callback
```

Após mudar o `.env`, executar:

```bash
php artisan optimize:clear
```

## Bloqueio atual

Ao conectar a conta proprietária do canal, que é `@gmail.com`, o Google mostra:

> Acesso bloqueado: o app FCO só pode ser usado dentro da organização.

Isso significa que o projeto Google Cloud cujas credenciais estão no `.env` está configurado com público OAuth **Interno**. A conta Gmail não pode autorizá-lo.

## Próximo passo

Usar um projeto Google Cloud dedicado ao YouTube configurado como **Externo**:

1. Em **APIs e serviços → Tela de consentimento OAuth → Público**, escolher **Externo**.
2. Manter inicialmente como **Em teste**.
3. Adicionar a conta Gmail proprietária do canal em **Usuários de teste**.
4. Habilitar a **YouTube Data API v3**.
5. Criar um cliente OAuth do tipo **Aplicativo da Web**.
6. Registrar o URI `http://localhost:8006/transmissao/youtube/callback`.
7. Trocar no `.env` o Client ID e Client Secret pelos desse projeto externo e limpar o cache.
8. Abrir `/transmissao` e usar o botão **Conectar conta do YouTube**.

Se a organização FCO não permitir criar ou converter um projeto para Externo, criar o projeto dedicado usando uma conta Gmail pessoal no Google Cloud. Isso não altera os projetos internos nem os logins das outras aplicações FCO.

## Observação para produção

No modo OAuth **Em teste**, a autorização/refresh token expira em sete dias. Depois de validar a integração, publicar/verificar o aplicativo externo para manter a conexão permanentemente.
