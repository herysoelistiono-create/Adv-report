<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function __construct(private readonly StockService $stockService) {}

    public function create(array $data, array $items): Sale
    {
        return DB::transaction(function () use ($data, $items) {
            $data['total_amount'] = $this->calculateTotal($items);
            $isDistributorSale    = ($data['sale_type'] ?? Sale::Type_Distributor) === Sale::Type_Distributor;

            $sale = Sale::create($data);

            foreach ($items as $item) {
                $item['subtotal'] = round($item['quantity'] * $item['price'], 2);
                $sale->items()->create($item);

                if (!empty($data['distributor_id'])) {
                    if ($isDistributorSale) {
                        // Agronomist: Advanta → Distributor. Distributor RECEIVES goods → add stock.
                        $this->stockService->addStock(
                            $data['distributor_id'],
                            $item['product_id'],
                            (float) $item['quantity'],
                            "SALE-{$sale->id}"
                        );
                    } else {
                        // BS: Distributor → R1/R2. Distributor SHIPS goods → deduct stock.
                        $this->stockService->deductStock(
                            $data['distributor_id'],
                            $item['product_id'],
                            (float) $item['quantity'],
                            "SALE-{$sale->id}"
                        );
                    }
                }
            }

            return $sale->load('items.product', 'distributor', 'retailer');
        });
    }

    public function update(Sale $sale, array $data, array $items): Sale
    {
        return DB::transaction(function () use ($sale, $data, $items) {
            $wasDistributorSale = ($sale->sale_type ?? Sale::Type_Distributor) === Sale::Type_Distributor;
            $isDistributorSale  = ($data['sale_type'] ?? Sale::Type_Distributor) === Sale::Type_Distributor;

            // Reverse old stock movements
            if ($sale->distributor_id) {
                foreach ($sale->items as $oldItem) {
                    if ($wasDistributorSale) {
                        // Was agronomist sale (addStock): undo by deducting
                        $this->stockService->reverseAddition(
                            $sale->distributor_id,
                            $oldItem->product_id,
                            (float) $oldItem->quantity,
                            "SALE-{$sale->id}-EDIT"
                        );
                    } else {
                        // Was BS sale (deductStock): undo by adding back
                        $this->stockService->reverseDeduction(
                            $sale->distributor_id,
                            $oldItem->product_id,
                            (float) $oldItem->quantity,
                            "SALE-{$sale->id}-EDIT"
                        );
                    }
                }
            }

            $sale->items()->delete();

            $data['total_amount'] = $this->calculateTotal($items);
            $sale->update($data);

            foreach ($items as $item) {
                $item['subtotal'] = round($item['quantity'] * $item['price'], 2);
                $sale->items()->create($item);

                if (!empty($data['distributor_id'])) {
                    if ($isDistributorSale) {
                        $this->stockService->addStock(
                            $data['distributor_id'],
                            $item['product_id'],
                            (float) $item['quantity'],
                            "SALE-{$sale->id}"
                        );
                    } else {
                        $this->stockService->deductStock(
                            $data['distributor_id'],
                            $item['product_id'],
                            (float) $item['quantity'],
                            "SALE-{$sale->id}"
                        );
                    }
                }
            }

            return $sale->fresh(['items.product', 'distributor', 'retailer']);
        });
    }

    public function delete(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $wasDistributorSale = ($sale->sale_type ?? Sale::Type_Distributor) === Sale::Type_Distributor;

            if ($sale->distributor_id) {
                foreach ($sale->items as $item) {
                    if ($wasDistributorSale) {
                        // Was agronomist sale (addStock): undo by deducting
                        $this->stockService->reverseAddition(
                            $sale->distributor_id,
                            $item->product_id,
                            (float) $item->quantity,
                            "SALE-{$sale->id}-DEL"
                        );
                    } else {
                        // Was BS sale (deductStock): undo by adding back
                        $this->stockService->reverseDeduction(
                            $sale->distributor_id,
                            $item->product_id,
                            (float) $item->quantity,
                            "SALE-{$sale->id}-DEL"
                        );
                    }
                }
            }

            $sale->items()->delete();
            $sale->delete();
        });
    }

    private function calculateTotal(array $items): float
    {
        return array_reduce($items, function ($carry, $item) {
            return $carry + round($item['quantity'] * $item['price'], 2);
        }, 0.0);
    }
}
