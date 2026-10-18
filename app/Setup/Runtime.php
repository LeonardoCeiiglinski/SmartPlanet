<?php

declare(strict_types=1);

namespace App\Setup;

use App\Core\Env;
use App\Core\Paths;

final class Runtime
{
    public static function bootDatabase(): void
    {
        $auto = Env::bool('AUTO_MIGRATE', Paths::isServerless());
        if ($auto) {
            Migrator::up();
        }
        $email = Env::get('INSTALL_EMAIL');
        $password = Env::get('INSTALL_PASSWORD');
        $company = Env::get('INSTALL_COMPANY', 'SmartPlanet') ?? 'SmartPlanet';
        if ($email && $password && !Installer::isInstalled()) {
            Installer::run(
                $company,
                $email,
                $password,
                Env::get('INSTALL_NAME', 'Administrador') ?? 'Administrador',
                Env::get('INSTALL_DOCUMENT')
            );
        }
    }
}
