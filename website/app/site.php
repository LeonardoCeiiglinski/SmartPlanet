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

/** @return array<string, array{file:string,title:string,description:string,nav?:string,crumb?:string}> */
function site_pages(): array
{
    return [
        '/' => [
            'file' => 'home.php',
            'title' => 'Acessórios para Celular em São José SC | SmartPlanet',
            'description' => 'Capinha, película, cabo, fone e suporte na loja SmartPlanet, no bairro Campinas, em São José/SC. Retire no local ou chame no WhatsApp (48) 99662-8811.',
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
