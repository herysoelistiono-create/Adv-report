<?php

namespace App\Services;

use App\Models\DistributorStock;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function addStock(int $distributorId, int $productId, float $qty, string $reference = '', string $notes = ''): void
    {
        DB::transaction(function () use ($distributorId, $productId, $qty, $reference, $notes) {
            $this->upsertStock($distributorId, $productId, $qty);
            $this->recordMovement($distributorId, $productId, 'in', $qty, $reference, $notes);
        });
    }

    public function deductStock(int $distributorId, int $productId, float $qty, string $reference = '', string $notes = ''): void
    {
        DB::transaction(function () use ($distributorId, $productId, $qty, $reference, $notes) {
            $this->upsertStock($distributorId, $productId, -$qty);
            $this->recordMovement($distributorId, $productId, 'out', $qty, $reference, $notes);
        });
    }

    public function reverseDeduction(int $distributorId, int $productId, float $qty, string $reference = ''): void
    {
        DB::transaction(function () use ($distributorId, $productId, $qty, $reference) {
            $this->upsertStock($distributorId, $productId, $qty);
            $this->recordMovement($distributorId, $productId, 'in', $qty, $reference, 'Reversal');
        });
    }

    public function reverseAddition(int $distributorId, int $productId, float $qty, string $reference = ''): void
    {
        DB::transaction(function () use ($distributorId, $productId, $qty, $reference) {
            $this->upsertStock($distributorId, $productId, -$qty);
            $this->recordMovement($distributorId, $productId, 'out', $qty, $reference, 'Reversal');
        });
    }

    public function getStock(int $distributorId, int $productId): float
    {
        $stock = DistributorStock::where('distributor_id', $distributorId)
            ->where('product_id', $productId)
            ->first();

        return $stock ? (float) $stock->stock_quantity : 0.0;
    }

    public function getStockSummary(int $distributorId): \Illuminate\Support\Collection
    {
        return DistributorStock::with('product:id,name,uom_1,uom_2')
            ->where('distributor_id', $distributorId)
            ->where('stock_quantity', '>', 0)
            ->orderBy('stock_quantity', 'desc')
            ->get();
    }

    private function upsertStock(int $distributorId, int $productId, float $delta): void
    {
        $stock = DistributorStock::firstOrNew([
            'distributor_id' => $distributorId,
            'product_id'     => $productId,
        ]);

        $stock->stock_quantity = max(0, (float) $stock->stock_quantity + $delta);
        $stock->updated_at = now();
        $stock->save();
    }

    private function recordMovement(int $distributorId, int $productId, string $type, float $qty, string $reference, string $notes): void
    {
        StockMovement::create([
            'distributor_id'  => $distributorId,
            'product_id'      => $productId,
            'type'            => $type,
            'quantity'        => $qty,
            'reference'       => $reference,
            'notes'           => $notes,
            'created_datetime' => current_datetime(),
            'created_by_uid'  => Auth::id(),
        ]);
    }
}
