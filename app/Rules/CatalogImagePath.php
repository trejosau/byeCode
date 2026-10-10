<?php

namespace App\Rules;

use App\Domain\Cases\CatalogManifest;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class CatalogImagePath implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('La imagen debe ser una ruta del catálogo o una URL HTTPS.');

            return;
        }
        if (filter_var($value, FILTER_VALIDATE_URL) && parse_url($value, PHP_URL_SCHEME) === 'https') {
            return;
        }
        if (preg_match('~^/images/catalog/v[0-9]+/[A-Z0-9-]+/[a-z0-9-]+/(front|back|three-quarter|detail)\.png$~D', $value) && (is_file(public_path($value)) || app(CatalogManifest::class)->expectsPath($value))) {
            return;
        }
        $fail('Recurso de catálogo inválido o ausente.');
    }
}
