<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

class ServiceCatalog
{
    public const PRIORITY_SERVICE_TYPE = 'Atendimento Preferencial';

    public const UNILAB_SERVICE_TYPES = [
        'Atendimento Normal' => 'N',
        self::PRIORITY_SERVICE_TYPE => 'P',
        'Retirada de Exames ou Entrega de Amostras' => 'E',
    ];

    public const CRE_SERVICE_TYPES = [
        'Acadêmico/Matrículas' => 'A',
        'Solicitação de Documentos' => 'D',
        'Impressão de Boletos' => 'B',
        'Financiamentos e Bolsas' => 'F',
        'Renegociação de Mensalidades' => 'R',
    ];

    public static function byInstitution(): array
    {
        return [
            User::INSTITUTION_UNILAB => self::UNILAB_SERVICE_TYPES,
            User::INSTITUTION_CRE => self::CRE_SERVICE_TYPES,
        ];
    }

    public static function forLocation(?string $location): array
    {
        $institution = User::institutionForLocation($location) ?? User::INSTITUTION_UNILAB;

        return self::byInstitution()[$institution] ?? self::UNILAB_SERVICE_TYPES;
    }

    public static function allowedTypesForLocation(?string $location): array
    {
        return array_keys(self::forLocation($location));
    }

    public static function prefixFor(?string $location, string $serviceType): ?string
    {
        return self::forLocation($location)[$serviceType] ?? null;
    }
}
