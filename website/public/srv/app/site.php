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

function site_origin(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return site_scheme() . '://' . $host;
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
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(SITE_STREET . ', ' . SITE_PLACE . ', ' . SITE_CEP);
}

/** @return array<string, array{file:string,title:string,description:string,nav?:string,crumb?:string}> */
function site_pages(): array
{
    return [
        '/' => [
            'file' => 'home.php',
            'title' => 'SmartPlanet · Boutique do celular em Campinas, São José/SC',
            'description' => 'Capinhas e acessórios em Campinas, São José. A SmartPlanet fica no ponto do Direto do Campo, com entrega na Grande Florianópolis.',
            'nav' => 'Início',
        ],
        '/a-loja' => [
            'file' => 'a-loja.php',
            'title' => 'A loja · SmartPlanet em Campinas, São José',
            'description' => 'A SmartPlanet é a boutique do celular em Campinas, São José/SC. Atendimento no balcão, no ponto do Direto do Campo.',
            'nav' => 'A loja',
            'crumb' => 'A loja',
        ],
        '/acessorios' => [
            'file' => 'acessorios.php',
            'title' => 'Capinhas e acessórios · SmartPlanet',
            'description' => 'Capinha, película, cabo, carregador, áudio e suporte. A escolha é no balcão da SmartPlanet ou pelo WhatsApp.',
            'nav' => 'Acessórios',
            'crumb' => 'Acessórios',
        ],
        '/entrega' => [
            'file' => 'entrega.php',
            'title' => 'Entrega na Grande Florianópolis · SmartPlanet',
            'description' => 'Entrega da SmartPlanet em São José, Florianópolis, Palhoça e Biguaçu. Combina o pedido pelo WhatsApp.',
            'nav' => 'Entrega',
            'crumb' => 'Entrega',
        ],
        '/onde-estamos' => [
            'file' => 'onde-estamos.php',
            'title' => 'Onde estamos · Campinas, São José/SC · SmartPlanet',
            'description' => 'SmartPlanet na Av. Josué Di Bernardi, 34, Campinas, São José/SC. Ponto de referência: Direto do Campo.',
            'nav' => 'Onde estamos',
            'crumb' => 'Onde estamos',
        ],
        '/contato' => [
            'file' => 'contato.php',
            'title' => 'WhatsApp e contato · SmartPlanet',
            'description' => 'Chama a SmartPlanet no WhatsApp (48) 99662-8811, no Instagram @smartplanet_oficial ou no Facebook.',
            'nav' => 'Contato',
            'crumb' => 'Contato',
        ],
        '/privacidade' => [
            'file' => 'privacidade.php',
            'title' => 'Privacidade · SmartPlanet',
            'description' => 'O que a SmartPlanet faz com as mensagens enviadas pelo WhatsApp e com a visita a este site.',
            'crumb' => 'Privacidade',
        ],
    ];
}
