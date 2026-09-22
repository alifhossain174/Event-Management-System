<?php

namespace App\Services;

use App\Support\DecimalMath;
use Illuminate\Validation\ValidationException;

final class InvoiceCalculator
{
    /** @param list<array<string, mixed>> $items */
    public function calculate(array $items, string $defaultTaxRate = '0.000000'): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one Invoice item.']);
        }

        $calculated = [];
        foreach ($items as $index => $item) {
            $quantity = DecimalMath::normalize($item['quantity']);
            $unitPrice = DecimalMath::normalize($item['unit_price']);
            if (DecimalMath::compare($quantity, '0') <= 0 || DecimalMath::compare($unitPrice, '0') < 0) {
                throw ValidationException::withMessages(["items.{$index}" => 'Quantity must be positive and unit price cannot be negative.']);
            }
            $subtotal = DecimalMath::multiply($quantity, $unitPrice);
            $discountType = $item['discount_type'] ?? 'none';
            $discountValue = $item['discount_value'] ?? '0';
            $discount = match ($discountType) {
                'none' => '0.0000',
                'fixed' => DecimalMath::normalize($discountValue),
                'percentage' => DecimalMath::percentage($subtotal, $discountValue),
                default => throw ValidationException::withMessages(["items.{$index}.discount_type" => 'Select a valid discount type.']),
            };
            if (DecimalMath::compare($discount, '0') < 0 || DecimalMath::compare($discount, $subtotal) > 0) {
                throw ValidationException::withMessages(["items.{$index}.discount_value" => 'Discount cannot exceed the line subtotal.']);
            }
            $taxable = DecimalMath::subtract($subtotal, $discount);
            $taxRate = $this->rate($item['tax_rate'] ?? $defaultTaxRate, "items.{$index}.tax_rate");
            $tax = DecimalMath::percentage($taxable, $taxRate);
            $calculated[] = [
                'sort_order' => $index, 'description' => trim($item['description']),
                'quantity' => $quantity, 'unit_price' => $unitPrice, 'line_subtotal' => $subtotal,
                'discount_type' => $discountType, 'discount_value' => $this->rateOrMoney($discountType, $discountValue),
                'discount_amount' => $discount, 'taxable_amount' => $taxable,
                'tax_label' => trim($item['tax_label'] ?? 'Tax') ?: 'Tax', 'tax_rate' => $taxRate,
                'tax_amount' => $tax, 'line_total' => DecimalMath::add($taxable, $tax),
                'source_type' => $item['source_type'] ?? null, 'source_id' => $item['source_id'] ?? null,
            ];
        }

        return [
            'items' => $calculated,
            'subtotal' => DecimalMath::sum(array_column($calculated, 'line_subtotal')),
            'discount_total' => DecimalMath::sum(array_column($calculated, 'discount_amount')),
            'taxable_total' => DecimalMath::sum(array_column($calculated, 'taxable_amount')),
            'tax_total' => DecimalMath::sum(array_column($calculated, 'tax_amount')),
            'total' => DecimalMath::sum(array_column($calculated, 'line_total')),
        ];
    }

    private function rate(string|int $value, string $field): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^(\d+)(?:\.(\d{1,6}))?$/', $value, $matches)) {
            throw ValidationException::withMessages([$field => 'Rate must be between 0 and 100 with at most six decimal places.']);
        }

        $integer = ltrim($matches[1], '0') ?: '0';
        $fraction = str_pad($matches[2] ?? '', 6, '0');
        if (strlen($integer) > 3 || (int) $integer > 100 || ((int) $integer === 100 && trim($fraction, '0') !== '')) {
            throw ValidationException::withMessages([$field => 'Rate must be between 0 and 100 with at most six decimal places.']);
        }

        return $integer.'.'.$fraction;
    }

    private function rateOrMoney(string $type, string|int $value): string
    {
        return $type === 'percentage' ? $this->rate($value, 'discount_value') : DecimalMath::normalize($value).'00';
    }
}
