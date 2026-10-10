<?php

namespace App\Domain\Cases;

use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CatalogSelection
{
    public const MAX_LINES = 100;

    /** @param array<mixed> $input
     * @return list<array{product_id: int, quantity: int}>
     */
    public function validate(array $input, bool $allowEmpty = true): array
    {
        $data = Validator::make(['lines' => $input], [
            'lines' => 'present|array|max:'.self::MAX_LINES.($allowEmpty ? '' : '|min:1'),
            'lines.*' => 'required|array:product_id,quantity',
            'lines.*.product_id' => 'required|integer|distinct',
            'lines.*.quantity' => 'required|integer|min:1|max:1000000',
        ])->validate();
        $ids = array_column($data['lines'], 'product_id');
        if (Product::whereIn('id', $ids)->where('published', true)->whereHasStylePublished()->count() !== count($ids)) {
            throw ValidationException::withMessages(['lines' => 'Una prenda ya no forma parte del catálogo publicado. Revisa tu selección.']);
        }

        return array_values(array_map(fn ($line) => ['product_id' => (int) $line['product_id'], 'quantity' => (int) $line['quantity']], $data['lines']));
    }
}
