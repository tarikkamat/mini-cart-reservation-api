<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Cart\Actions\CalculateCartQuoteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\CartQuoteRequest;
use Illuminate\Http\JsonResponse;

class CartController extends Controller
{
    /**
     * Calculate cart quote.
     *
     * Server-priced cart calculation. The client only sends `product_id` + `quantity`;
     * any client-supplied price field is ignored. Validates each line against active
     * status, available stock, and single-currency cart rules. Both this endpoint and
     * the reservation endpoint share the same `CartCalculator` so totals never diverge.
     */
    public function quote(CartQuoteRequest $request, CalculateCartQuoteAction $action): JsonResponse
    {
        $result = $action->execute($request->toData());

        return response()->json([
            'customer_email' => $request->validated('customer_email'),
            'items' => $result->lines->map(fn ($line): array => [
                'product_id' => $line->productId,
                'name' => $line->name,
                'sku' => $line->sku,
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice,
                'line_total' => $line->lineTotal,
            ])->all(),
            'subtotal' => $result->subtotal,
            'currency' => $result->currency,
        ]);
    }
}
