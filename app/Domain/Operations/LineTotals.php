<?php

declare(strict_types=1);

namespace App\Domain\Operations;

/**
 * Turns priced lines into a subtotal, tax, and total.
 *
 * @phpstan-type RawLine array{description: string, quantity: float|int|string, unit_price: float|int|string}
 * @phpstan-type StoredLine array{description: string, quantity: string, unit_price: string, amount: string}
 */
final class LineTotals
{
    /**
     * @param  list<RawLine>  $lines
     * @return array{lines: list<StoredLine>, subtotal: string, tax: string, total: string, tax_rate: string}
     */
    public static function calculate(array $lines, float|int|string $taxRate): array
    {
        $stored = [];
        $subtotal = 0.0;

        foreach ($lines as $line) {
            $quantity = round((float) $line['quantity'], 2);
            $price = round((float) $line['unit_price'], 2);
            $amount = round($quantity * $price, 2);
            $subtotal += $amount;

            $stored[] = [
                'description' => trim((string) $line['description']),
                'quantity' => Money::of($quantity),
                'unit_price' => Money::of($price),
                'amount' => Money::of($amount),
            ];
        }

        $subtotal = round($subtotal, 2);
        $rate = round((float) $taxRate, 2);
        $tax = round($subtotal * $rate / 100, 2);

        return [
            'lines' => $stored,
            'subtotal' => Money::of($subtotal),
            'tax' => Money::of($tax),
            'total' => Money::of(round($subtotal + $tax, 2)),
            'tax_rate' => Money::of($rate),
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:30'],
            'lines.*.description' => ['required', 'string', 'max:200'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ];
    }
}
