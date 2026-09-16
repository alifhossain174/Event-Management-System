<?php

namespace App\Support;

use App\Models\DocumentCategory;
use App\Models\EventCategory;
use App\Models\FinanceCategory;
use App\Models\InventoryCategory;
use App\Models\VendorCategory;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class MasterDataRegistry
{
    public function all(): array
    {
        return [
            'event-categories' => [
                'label' => 'Event categories',
                'singular' => 'Event category',
                'model' => EventCategory::class,
                'table' => 'event_categories',
            ],
            'vendor-categories' => [
                'label' => 'Vendor categories',
                'singular' => 'Vendor category',
                'model' => VendorCategory::class,
                'table' => 'vendor_categories',
            ],
            'inventory-categories' => [
                'label' => 'Inventory categories',
                'singular' => 'Inventory category',
                'model' => InventoryCategory::class,
                'table' => 'inventory_categories',
            ],
            'finance-categories' => [
                'label' => 'Finance categories',
                'singular' => 'Finance category',
                'model' => FinanceCategory::class,
                'table' => 'finance_categories',
                'direction' => true,
            ],
            'document-categories' => [
                'label' => 'Document categories',
                'singular' => 'Document category',
                'model' => DocumentCategory::class,
                'table' => 'document_categories',
            ],
        ];
    }

    public function get(string $type): array
    {
        return $this->all()[$type] ?? throw new NotFoundHttpException;
    }
}
