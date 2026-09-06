=== EventosGI — Formulários de Atividades ===
Contributors: nossafco
Tags: formulário, inscrição, eventos, shortcode
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Exibe o formulário de inscrição de uma atividade do sistema Gestão de Eventos em qualquer post ou página.

== Description ==

O plugin consome a API pública de formulários do sistema Gestão de Eventos (Laravel) e renderiza o formulário
com o HTML do próprio WordPress — herdando as fontes e cores do tema do site. As inscrições e os arquivos
enviados são gravados diretamente no sistema de eventos; nada é armazenado no WordPress.

Uso: `[eventosgi_formulario id="1"]`

Atributos:

* `id` — ID da atividade (obrigatório).
* `titulo` — `nao` oculta o título e o subtítulo do formulário.
* `conteudo` — `nao` oculta o texto livre configurado na atividade.

== Installation ==

1. Envie a pasta `eventosgi-formularios` para `wp-content/plugins/` ou instale o .zip por Plugins → Adicionar novo.
2. Ative o plugin.
3. Em Ajustes → EventosGI, informe a URL do sistema de eventos e o token (`FORMULARIOS_API_TOKEN` do `.env`).
4. Insira o shortcode na página desejada.

== Changelog ==

= 1.0.0 =
* Primeira versão: shortcode, tela de ajustes, cache da estrutura, envio com arquivos e proteção antispam.
