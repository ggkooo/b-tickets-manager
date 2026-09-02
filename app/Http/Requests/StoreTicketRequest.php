<?php

namespace App\Http\Requests;

use App\Support\LocationResolver;
use App\Support\ServiceCatalog;
use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    private string $location;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Resolved once, up front, so both validation (service type must be
        // allowed for this location) and the controller can use it without
        // resolving/validating the location twice.
        $this->location = LocationResolver::resolveFromRequest($this);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
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
