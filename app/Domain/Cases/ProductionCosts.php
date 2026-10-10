<?php

namespace App\Domain\Cases;

use App\Models\CommercialCase;
use App\Models\Product;
use App\Models\QuoteVersion;
use Illuminate\Support\Facades\DB;

final class ProductionCosts
{
    /** @return array<string, mixed> */
    public function context(CommercialCase $case, ?QuoteVersion $quote): array
    {
        $lines = $quote?->lines;
        if ($lines === null) {
            $request = DB::table('purchase_requests')->where('commercial_case_id', $case->id)->value('lines');
            $lines = $request ? json_decode($request, true, 512, JSON_THROW_ON_ERROR) : [];
        }
        $products = Product::whereIn('id', array_column($lines, 'product_id'))->get()->keyBy('id');
        $unit = (int) config('byecode.commercial_costs.playera_unit_cents');
        $knownCost = 0;
        $unknown = 0;
        $items = [];
        foreach ($lines as $line) {
            $category = mb_strtolower(trim((string) ($line['category'] ?? $products->get($line['product_id'] ?? null)->category ?? '')));
            $known = in_array($category, ['playera', 'playeras'], true);
            $cost = $known ? $unit * (int) $line['quantity'] : null;
            $knownCost += $cost ?? 0;
            $unknown += $known ? 0 : 1;
            $items[] = ['line_id' => $line['line_id'] ?? null, 'product_id' => $line['product_id'] ?? null,
                'quantity' => (int) $line['quantity'], 'unit_cost_cents' => $known ? $unit : null,
                'production_cost_cents' => $cost, 'quoted_line_total_cents' => $line['total_cents'] ?? null];
        }
        $complete = count($items) > 0 && $unknown === 0;
        $net = $quote ? $quote->subtotal_cents - $quote->discount_cents : null;

        return ['currency' => 'MXN', 'source' => 'internal_confirmed_manufacturing_cost', 'playera_unit_cost_cents' => $unit,
            'scope' => 'Playeras solamente. Otros costos y margen objetivo no confirmados; no equivale a utilidad neta.',
            'lines' => $items, 'all_lines_costed' => $complete, 'known_production_cost_cents' => $knownCost,
            'production_cost_cents' => $complete ? $knownCost : null, 'net_merchandise_cents' => $net,
            'contribution_before_other_costs_cents' => $complete && $net !== null ? $net - $knownCost : null,
            'minimum_proposed_quote_total_cents' => $quote && $complete ? $knownCost + $quote->tax_cents + $quote->shipping_cents : null,
            'target_margin' => null, 'automatic_discount_authorized' => false];
    }
}
