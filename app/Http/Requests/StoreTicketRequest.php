<?php

namespace App\Http\Requests;

use App\Support\LocationResolver;
use App\Support\ServiceCatalog;
use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    private string $location;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->location = LocationResolver::resolveFromRequest($this);
    }

    public function rules(): array
    {
        $allowedTypes = ServiceCatalog::allowedTypesForLocation($this->location);

        return [
            'service_type' => ['required', 'string', 'in:' . implode(',', $allowedTypes)],
        ];
    }

    public function resolvedLocation(): string
    {
        return $this->location;
    }
}
