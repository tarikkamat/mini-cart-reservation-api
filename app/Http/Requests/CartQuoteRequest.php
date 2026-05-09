<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Cart\Data\CartItemData;
use App\Domain\Cart\Data\CartQuoteData;
use Illuminate\Foundation\Http\FormRequest;

class CartQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'customer_email' => ['required', 'email'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function toData(): CartQuoteData
    {
        return new CartQuoteData(
            customerEmail: (string) $this->validated('customer_email'),
            items: collect((array) $this->validated('items'))->map(
                fn (array $row): CartItemData => new CartItemData(
                    productId: (int) $row['product_id'],
                    quantity: (int) $row['quantity'],
                ),
            ),
        );
    }
}
