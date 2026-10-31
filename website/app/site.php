<?php

declare(strict_types=1);

const SITE_NAME = 'SmartPlanet';
const SITE_TAG = 'a Boutique do Celular';
const SITE_PHONE_E164 = '5548996628811';
const SITE_PHONE_HUMAN = '(48) 99662-8811';
const SITE_STREET = 'Av. Josué Di Bernardi, 34';
const SITE_PLACE = 'Campinas, São José/SC';
const SITE_CEP = '88101-200';
const SITE_REF = 'Direto do Campo';
const SITE_HOURS = 'Confirme no WhatsApp antes de ir. O horário não está publicado neste site.';
const SITE_INSTAGRAM = 'https://www.instagram.com/smartplanet_oficial/';
const SITE_FACEBOOK = 'https://www.facebook.com/people/Smart-Planet/61569088366936/';
const SITE_LAT = -27.598923;
const SITE_LNG = -48.605613;

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function site_scheme(): string
{
    $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwarded === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) {
        return 'https';
    }
    return 'http';
}

function site_request_host(): string
{
    $raw = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    $raw = trim(explode(',', (string) $raw)[0]);

    return strtolower((string) preg_replace('/:\d+$/', '', $raw));
}

function site_origin(): string
{
    $host = site_request_host();
    if ($host === 'lojadeeletronicossaojose.com.br' || $host === 'www.lojadeeletronicossaojose.com.br') {
        return 'https://lojadeeletronicossaojose.com.br';
    }

    return site_scheme() . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function site_url(string $path = '/'): string
{
    return site_origin() . ($path === '/' ? '/' : $path);
}

function wa_link(string $text = ''): string
{
    $base = 'https://wa.me/' . SITE_PHONE_E164;
    $text = trim($text);
    if ($text === '') {
        return $base;
    }
    return $base . '?text=' . rawurlencode($text);
}

function maps_link(): string
{
    $query = SITE_STREET . ', Campinas, São José, SC, ' . SITE_CEP;

    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query);
}

/** @return list<array{q:string,a:string}> */
function site_delivery_faq(): array
{
    return [
        [
            'q' => 'Vocês entregam no meu bairro?',
            'a' => 'Informe o bairro e a cidade no WhatsApp. A loja confirma se a entrega chega nesse endereço.',
        ],
        [
            'q' => 'Quanto custa a entrega?',
            'a' => 'A taxa não está publicada neste site. A SmartPlanet informa o valor no WhatsApp ' . SITE_PHONE_HUMAN . ', junto com o acessório.',
        ],
        [
            'q' => 'Em quanto tempo a entrega chega?',
            'a' => 'O prazo não está publicado neste site. Ele é combinado no WhatsApp, de acordo com o endereço.',
        ],
    ];
}

/** @return list<array{q:string,a:string}> */
function site_faqs(string $path): array
{
    $pages = [
        '/peliculas' => [[
            'q' => 'A loja aplica a película?',
            'a' => 'Este site não descreve aplicação de película. A SmartPlanet confirma a película do seu modelo no balcão ou no WhatsApp ' . SITE_PHONE_HUMAN . '.',
        ]],
        '/carregadores' => [[
            'q' => 'Qual cabo serve no meu celular?',
            'a' => 'Informe a marca e o modelo. A loja trabalha com USB-C, Lightning e V8, e confirma o encaixe antes de você levar ou pedir a entrega.',
        ]],
        '/fones' => [[
            'q' => 'Tem fone bluetooth?',
            'a' => 'O que este site confirma é fone com fio. Fone sem fio muda conforme o dia, então a resposta fica no WhatsApp ' . SITE_PHONE_HUMAN . '.',
        ]],
        '/linha-gamer' => [[
            'q' => 'A linha gamer está na loja?',
            'a' => 'O interior da SmartPlanet tem um painel escrito GAMER, com headset e controle, ao lado do balcão. O modelo disponível é confirmado no local ou no WhatsApp.',
        ]],
        '/iphone' => [[
            'q' => 'A capinha serve em qualquer iPhone?',
            'a' => 'Não. Capinha, película e carregador de iPhone dependem do modelo. Envie o nome do aparelho no WhatsApp para a loja conferir o encaixe.',
        ]],
    ];
    if ($path === '/entrega') {
        return site_delivery_faq();
    }

    return $pages[$path] ?? [];
}

/** @return array<string, array{file:string,title:string,description:string,nav?:string,crumb?:string}> */
function site_pages(): array
{
    return [
        '/' => [
            'file' => 'home.php',
            'title' => 'Acessórios para Celular e Eletrônicos em São José/SC | SmartPlanet',
            'description' => 'Capinhas, películas, carregadores, cabos, fones e linha gamer na SmartPlanet, em Campinas, São José/SC. Retire na loja ou consulte a entrega no WhatsApp.',
            'nav' => 'Início',
        ],
        '/a-loja' => [
            'file' => 'a-loja.php',
            'title' => 'Loja de Celular em Campinas, São José | SmartPlanet',
            'description' => 'A SmartPlanet atende na Av. Josué Di Bernardi, 34, referência Direto do Campo, no bairro Campinas, em São José/SC. Chame no WhatsApp ou venha até o balcão.',
            'nav' => 'A loja',
            'crumb' => 'A loja',
        ],
        '/acessorios' => [
            'file' => 'acessorios.php',
            'title' => 'Capinhas para Celular em São José SC | SmartPlanet',
            'description' => 'Consulte capinha, película, cabo, fone e suporte na SmartPlanet, em São José/SC. Informe a marca e o modelo do celular pelo WhatsApp (48) 99662-8811.',
            'nav' => 'Acessórios',
            'crumb' => 'Acessórios',
        ],
        '/peliculas' => [
            'file' => 'peliculas.php',
            'title' => 'Películas para Celular em São José/SC | SmartPlanet',
            'description' => 'Película de tela e de lente na SmartPlanet, em Campinas, São José/SC. Informe o modelo do celular. A loja confirma a peça no balcão ou no WhatsApp.',
            'crumb' => 'Películas',
        ],
        '/carregadores' => [
            'file' => 'carregadores.php',
            'title' => 'Carregadores e Cabos em São José/SC | SmartPlanet',
            'description' => 'Cabos e carregadores USB-C, Lightning e V8 na SmartPlanet, em Campinas, São José/SC. O encaixe e o preço são confirmados no WhatsApp.',
            'crumb' => 'Carregadores e cabos',
        ],
        '/fones' => [
            'file' => 'fones.php',
            'title' => 'Fones de Ouvido em São José/SC | SmartPlanet',
            'description' => 'Fone com fio na SmartPlanet, loja de eletrônicos em Campinas, São José/SC. Fone sem fio é consultado no WhatsApp (48) 99662-8811.',
            'crumb' => 'Fones',
        ],
        '/linha-gamer' => [
            'file' => 'linha-gamer.php',
            'title' => 'Linha Gamer em São José/SC | SmartPlanet',
            'description' => 'Headset e controle no painel GAMER da SmartPlanet, em Campinas, São José/SC. Mouse USB e outros eletrônicos são confirmados no balcão ou no WhatsApp.',
            'crumb' => 'Linha gamer',
        ],
        '/iphone' => [
            'file' => 'iphone.php',
            'title' => 'Capinhas e Películas para iPhone | SmartPlanet',
            'description' => 'Capinha, película e carregador para iPhone na SmartPlanet, em Campinas, São José/SC. Envie o modelo no WhatsApp para conferir o encaixe.',
            'crumb' => 'iPhone',
        ],
        '/entrega' => [
            'file' => 'entrega.php',
            'title' => 'Retirada de Acessórios em Campinas, SC | SmartPlanet',
            'description' => 'Retire o acessório na SmartPlanet, bairro Campinas, São José/SC, ou combine a entrega pelo WhatsApp. O prazo e a taxa são confirmados na conversa.',
            'nav' => 'Entrega',
            'crumb' => 'Entrega',
        ],
        '/onde-estamos' => [
            'file' => 'onde-estamos.php',
            'title' => 'Como Chegar à SmartPlanet em Campinas, São José SC',
            'description' => 'A SmartPlanet fica na Av. Josué Di Bernardi, 34, bairro Campinas, São José/SC, CEP 88101-200. Veja a rota e use o Direto do Campo como referência.',
            'nav' => 'Onde estamos',
            'crumb' => 'Onde estamos',
        ],
        '/contato' => [
            'file' => 'contato.php',
            'title' => 'WhatsApp SmartPlanet: loja de acessórios em São José',
            'description' => 'Fale com a loja de acessórios SmartPlanet pelo WhatsApp (48) 99662-8811. Informe o modelo do celular, o acessório e o bairro para receber a resposta.',
            'nav' => 'Contato',
            'crumb' => 'Contato',
        ],
        '/privacidade' => [
            'file' => 'privacidade.php',
            'title' => 'Privacidade dos Dados da SmartPlanet | São José SC',
            'description' => 'O formulário da SmartPlanet pede nome, modelo do celular, acessório e bairro. Esses dados servem só para atender você pelo WhatsApp (48) 99662-8811.',
            'crumb' => 'Privacidade',
        ],
    ];
}
