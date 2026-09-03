# Gestão de Eventos — Starter Laravel para GI

Demonstra login por código único e exibe usuário, sistema, perfil, permissões, caminho solicitado e o contexto JSON da sessão.

## Instalação

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan serve --port=8001
```

Cadastre a aplicação no GI e copie Client ID e segredo. Configure as permissões na tela de cada perfil; a aplicação deve validar `data.permissoes` no backend. Nunca versione o segredo. Em produção use HTTPS e a origem exata do GI em frame-ancestors.
