<?php

namespace App\Support;

use App\Models\User;

/**
 * Central registry of ticket service types per institution (unit).
 *
 * Each entry maps a service type label (shown to the totem user and stored
 * verbatim on the ticket) to the single-letter prefix used to build the
 * daily ticket key (e.g. "N-0001"). Keeping this in one place avoids
 * duplicating/validating the list in multiple controllers and makes it easy
 * to add a new institution or service type later.
 */
class ServiceCatalog
{
    /**
     * @var array<string, string>
     */
    public const UNILAB_SERVICE_TYPES = [
        'Atendimento Normal' => 'N',
        'Atendimento Preferencial' => 'P',
        'Retirada de Exames ou Entrega de Amostras' => 'E',
    ];

    /**
     * @var array<string, string>
     */
    public const CRE_SERVICE_TYPES = [
        'Acadêmico/Matrículas' => 'A',
        'Solicitação de Documentos' => 'D',
        'Impressão de Boletos' => 'B',
        'Financiamentos e Bolsas' => 'F',
        'Renegociação de Mensalidades' => 'R',
    ];

    /**
     * @return array<string, array<string, string>>
     */
    public static function byInstitution(): array
    {
        return [
            User::INSTITUTION_UNILAB => self::UNILAB_SERVICE_TYPES,
            User::INSTITUTION_CRE => self::CRE_SERVICE_TYPES,
        ];
    }

    /**
     * Service types (label => prefix) allowed for the given location slug.
     * Falls back to the Unilab catalog for an unknown/legacy location so
     * existing behaviour never changes for locations created before the
     * institution concept existed.
     *
     * @return array<string, string>
     */
    public static function forLocation(?string $location): array
    {
        $institution = User::institutionForLocation($location) ?? User::INSTITUTION_UNILAB;

        return self::byInstitution()[$institution] ?? self::UNILAB_SERVICE_TYPES;
    }

    /**
     * @return array<int, string>
     */
    public static function allowedTypesForLocation(?string $location): array
    {
        return array_keys(self::forLocation($location));
    }

    public static function prefixFor(?string $location, string $serviceType): ?string
    {
        return self::forLocation($location)[$serviceType] ?? null;
    }
}
