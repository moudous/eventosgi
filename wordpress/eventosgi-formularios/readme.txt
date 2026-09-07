=== EventosGI — Eventos e Formulários ===
Contributors: nossafco
Tags: evento, formulário, inscrição, shortcode
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later

Exibe páginas de eventos e formulários de atividades do sistema Gestão de Eventos em qualquer post ou página.

== Description ==

O plugin abre a URL pública da página completa do evento em um quadro isolado, preservando o visual do
template. O formulário da atividade consome a API e usa o HTML do próprio WordPress. As inscrições e os
arquivos enviados são gravados diretamente no sistema de eventos.

Página do evento: `[eventosgi_evento id="1"]`

Formulário da atividade: `[eventosgi_formulario id="1"]`

Atributos:

Na página do evento:

* `id` — ID do evento (obrigatório).
* `altura` — altura do quadro em pixels (opcional; padrão: 900).

No formulário da atividade:

* `id` — ID da atividade (obrigatório).
* `titulo` — `nao` oculta o título e o subtítulo do formulário.
* `conteudo` — `nao` oculta o texto livre configurado na atividade.

== Installation ==

1. Envie a pasta `eventosgi-formularios` para `wp-content/plugins/` ou instale o .zip por Plugins → Adicionar novo.
2. Ative o plugin.
3. Em Ajustes → EventosGI, informe a URL do sistema de eventos e o token (`FORMULARIOS_API_TOKEN` do `.env`).
4. Insira o shortcode do evento ou da atividade na página desejada.

== Changelog ==

= 1.2.0 =
* Adiciona o shortcode `[eventosgi_evento]` para páginas completas de eventos.

= 1.1.0 =
* Adiciona a identificação do participante por e-mail antes da inscrição.

= 1.0.0 =
* Primeira versão: shortcode, tela de ajustes, cache da estrutura, envio com arquivos e proteção antispam.
