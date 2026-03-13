<?php

use App\Models\User;

return [
    User::Role_BS => [
        'admin.user.index',
        'admin.user.data',
        'admin.user.detail',
        'admin.user.export',

        'admin.product.index',
        'admin.product.data',
        'admin.product.detail',

        'admin.product-knowledge.index',
        'admin.product-knowledge.data',
        'admin.product-knowledge.gallery',

        'admin.demo-plot.index',
        'admin.demo-plot.data',
        'admin.demo-plot.detail',
        'admin.demo-plot.add',
        'admin.demo-plot.edit',
        'admin.demo-plot.save',
        'admin.demo-plot.duplicate',
        'admin.demo-plot.export',

        'admin.demo-plot-visit.index',
        'admin.demo-plot-visit.data',
        'admin.demo-plot-visit.detail',
        'admin.demo-plot-visit.add',
        'admin.demo-plot-visit.edit',
        'admin.demo-plot-visit.save',

        'admin.activity-plan.index',
        'admin.activity-plan.data',
        'admin.activity-plan.add',
        'admin.activity-plan.edit',
        'admin.activity-plan.save',
        'admin.activity-plan.delete',
        'admin.activity-plan.detail',

        'admin.activity-plan-detail.index',
        'admin.activity-plan-detail.data',
        'admin.activity-plan-detail.add',
        'admin.activity-plan-detail.edit',
        'admin.activity-plan-detail.save',
        'admin.activity-plan-detail.delete',

        'admin.activity.index',
        'admin.activity.data',
        'admin.activity.add',
        'admin.activity.edit',
        'admin.activity.save',
        'admin.activity.delete',
        'admin.activity.detail',

        'admin.customer.index',
        'admin.customer.data',
        'admin.customer.detail',
        'admin.customer.add',
        'admin.customer.edit',
        'admin.customer.save',
        'admin.customer.duplicate',

        'admin.inventory-log.index',
        'admin.inventory-log.data',
        'admin.inventory-log.detail',
        'admin.inventory-log.add',
        'admin.inventory-log.edit',
        'admin.inventory-log.save',

        // Territory API for cascading dropdowns in customer editor
        'admin.territory.api.provinces',
        'admin.territory.api.districts',
        'admin.territory.api.villages',

        // Sales: BS inputs retailer-type sales (Distributor → R1/R2)
        'admin.sale.index',
        'admin.sale.data',
        'admin.sale.add',
        'admin.sale.edit',
        'admin.sale.save',
        'admin.sale.detail',
        'admin.sale.import-template',
        'admin.sale.import',

        // Distributor list (needed for seller dropdown in BS sales)
        'admin.distributor.index',
        'admin.distributor.data',
    ],
    User::Role_Agronomist => [
        'admin.user.index',
        'admin.user.data',
        'admin.user.detail',
        'admin.user.export',

        'admin.product.index',
        'admin.product.data',
        'admin.product.detail',

        'admin.product-knowledge.index',
        'admin.product-knowledge.data',
        'admin.product-knowledge.gallery',

        'admin.customer.index',
        'admin.customer.data',
        'admin.customer.detail',
        'admin.customer.add',
        'admin.customer.edit',
        'admin.customer.save',
        'admin.customer.duplicate',

        'admin.demo-plot.index',
        'admin.demo-plot.data',
        'admin.demo-plot.detail',
        'admin.demo-plot.export',

        'admin.demo-plot-visit.index',
        'admin.demo-plot-visit.data',
        'admin.demo-plot-visit.detail',

        'admin.activity-type.index',
        'admin.activity-type.data',

        'admin.activity-plan.index',
        'admin.activity-plan.data',
        'admin.activity-plan.detail',
        'admin.activity-plan.respond',
        'admin.activity-plan.export',

        'admin.activity-plan-detail.index',
        'admin.activity-plan-detail.data',
        'admin.activity-plan-detail.add',
        'admin.activity-plan-detail.edit',
        'admin.activity-plan-detail.save',
        'admin.activity-plan-detail.delete',

        'admin.activity.index',
        'admin.activity.data',
        'admin.activity.detail',
        'admin.activity.respond',
        'admin.activity.export',

        'admin.activity-target.index',
        'admin.activity-target.data',
        'admin.activity-target.detail',
        'admin.activity-target.add',
        'admin.activity-target.edit',
        'admin.activity-target.delete',
        'admin.activity-target.save',
        'admin.activity-target.export',

        'admin.report.index',
        'admin.report.demo-plot-detail',
        'admin.report.demo-plot-detail-with-photo',
        'admin.report.new-demo-plot-detail',
        'admin.report.client-actual-inventory',
        'admin.report.activity-plan-detail',
        'admin.report.activity-realization-detail',
        'admin.report.activity-target-detail',

        'admin.inventory-log.index',

        'admin.inventory-log.index',
        'admin.inventory-log.data',
        'admin.inventory-log.detail',
        'admin.inventory-log.add',
        'admin.inventory-log.edit',
        'admin.inventory-log.save',
        'admin.inventory-log.export',

        // Territory API for cascading dropdowns
        'admin.territory.api.provinces',
        'admin.territory.api.districts',
        'admin.territory.api.villages',

        // Sales reports
        'admin.report.sales-by-region',
        'admin.report.sales-by-product',
        'admin.report.activity-vs-sales',
        'admin.report.distributor-performance',

        // Analytics (read-only)
        'admin.analytics.index',
        'admin.analytics.sales-by-region',
        'admin.analytics.sales-by-product',
        'admin.analytics.activity-vs-sales',
        'admin.analytics.top-performers',
        'admin.analytics.monthly-sales',

        // Sales: Agronomist inputs distributor-type sales (Distributor → R1/R2)
        'admin.sale.index',
        'admin.sale.data',
        'admin.sale.add',
        'admin.sale.edit',
        'admin.sale.save',
        'admin.sale.detail',
        'admin.sale.delete',
        'admin.sale.import-template',
        'admin.sale.import',

        // Distributor stocks (read-only for agronomist)
        'admin.distributor-stock.index',
        'admin.distributor-stock.data',

        // Distributor list (needed for filter dropdown in sales page)
        'admin.distributor.index',
        'admin.distributor.data',

        // Territory API (for cascading dropdowns)
        'admin.territory.api.provinces',
        'admin.territory.api.districts',
        'admin.territory.api.villages',

        // Distributor targets (CRUD)
        'admin.distributor-target.index',
        'admin.distributor-target.data',
        'admin.distributor-target.months',
        'admin.distributor-target.save',
        'admin.distributor-target.delete',
        'admin.distributor-target.import-pdf',
        'admin.distributor-target.import-excel',
    ],
    User::Role_ASM => [
        'admin.user.index',
        'admin.user.data',
        'admin.user.detail',
        'admin.user.export',

        'admin.product.index',
        'admin.product.data',
        'admin.product.detail',

        'admin.product-knowledge.index',
        'admin.product-knowledge.data',
        'admin.product-knowledge.gallery',

        'admin.customer.index',
        'admin.customer.data',
        'admin.customer.detail',

        'admin.activity-type.index',
        'admin.activity-type.data',

        // Distributor targets (read-only)
        'admin.distributor-target.index',
        'admin.distributor-target.data',
        'admin.distributor-target.months',
    ],

    // -------------------------------------------------------
    // Manager: view all reports, analytics, read-only sales
    // -------------------------------------------------------
    User::Role_Manager => [
        'admin.user.index',
        'admin.user.data',
        'admin.user.detail',
        'admin.user.export',

        'admin.product.index',
        'admin.product.data',
        'admin.product.detail',

        'admin.product-knowledge.index',
        'admin.product-knowledge.data',
        'admin.product-knowledge.gallery',

        'admin.customer.index',
        'admin.customer.data',
        'admin.customer.detail',

        // Sales read-only + export
        'admin.sale.index',
        'admin.sale.data',
        'admin.sale.detail',
        'admin.sale.export',

        // Distributors read-only
        'admin.distributor.index',
        'admin.distributor.data',
        'admin.distributor.detail',

        // Distributor stock read-only
        'admin.distributor-stock.index',
        'admin.distributor-stock.data',
        'admin.distributor-stock.movements',
        'admin.distributor-stock.movements.data',

        // Analytics
        'admin.analytics.index',
        'admin.analytics.sales-by-region',
        'admin.analytics.sales-by-product',
        'admin.analytics.activity-vs-sales',
        'admin.analytics.top-performers',
        'admin.analytics.monthly-sales',

        // Territory API (for cascading dropdowns)
        'admin.territory.api.provinces',
        'admin.territory.api.districts',
        'admin.territory.api.villages',

        // All reports
        'admin.report.index',
        'admin.report.demo-plot-detail',
        'admin.report.demo-plot-detail-with-photo',
        'admin.report.new-demo-plot-detail',
        'admin.report.client-actual-inventory',
        'admin.report.activity-plan-detail',
        'admin.report.activity-realization-detail',
        'admin.report.activity-target-detail',
        'admin.report.sales-by-region',
        'admin.report.sales-by-product',
        'admin.report.activity-vs-sales',
        'admin.report.distributor-performance',

        // Distributor targets (full CRUD + import)
        'admin.distributor-target.index',
        'admin.distributor-target.data',
        'admin.distributor-target.months',
        'admin.distributor-target.save',
        'admin.distributor-target.delete',
        'admin.distributor-target.import-pdf',
        'admin.distributor-target.import-excel',
    ],

    // -------------------------------------------------------
    // Field Officer: same as BS + can create/view sales
    // -------------------------------------------------------
    User::Role_FieldOfficer => [
        'admin.user.index',
        'admin.user.data',
        'admin.user.detail',
        'admin.user.export',

        'admin.product.index',
        'admin.product.data',
        'admin.product.detail',

        'admin.product-knowledge.index',
        'admin.product-knowledge.data',
        'admin.product-knowledge.gallery',

        'admin.demo-plot.index',
        'admin.demo-plot.data',
        'admin.demo-plot.detail',
        'admin.demo-plot.add',
        'admin.demo-plot.edit',
        'admin.demo-plot.save',
        'admin.demo-plot.duplicate',
        'admin.demo-plot.export',

        'admin.demo-plot-visit.index',
        'admin.demo-plot-visit.data',
        'admin.demo-plot-visit.detail',
        'admin.demo-plot-visit.add',
        'admin.demo-plot-visit.edit',
        'admin.demo-plot-visit.save',

        'admin.activity-plan.index',
        'admin.activity-plan.data',
        'admin.activity-plan.add',
        'admin.activity-plan.edit',
        'admin.activity-plan.save',
        'admin.activity-plan.delete',
        'admin.activity-plan.detail',

        'admin.activity-plan-detail.index',
        'admin.activity-plan-detail.data',
        'admin.activity-plan-detail.add',
        'admin.activity-plan-detail.edit',
        'admin.activity-plan-detail.save',
        'admin.activity-plan-detail.delete',

        'admin.activity.index',
        'admin.activity.data',
        'admin.activity.add',
        'admin.activity.edit',
        'admin.activity.save',
        'admin.activity.delete',
        'admin.activity.detail',

        'admin.customer.index',
        'admin.customer.data',
        'admin.customer.detail',
        'admin.customer.add',
        'admin.customer.edit',
        'admin.customer.save',
        'admin.customer.duplicate',

        'admin.inventory-log.index',
        'admin.inventory-log.data',
        'admin.inventory-log.detail',
        'admin.inventory-log.add',
        'admin.inventory-log.edit',
        'admin.inventory-log.save',

        // Sales CRUD
        'admin.sale.index',
        'admin.sale.data',
        'admin.sale.add',
        'admin.sale.edit',
        'admin.sale.save',
        'admin.sale.detail',
        'admin.sale.import-template',
        'admin.sale.import',

        // Distributor read-only
        'admin.distributor.index',
        'admin.distributor.data',
        'admin.distributor.detail',

        // Territory API
        'admin.territory.api.provinces',
        'admin.territory.api.districts',
        'admin.territory.api.villages',
    ],

    // -------------------------------------------------------
    // Distributor: input own sales + view own stock
    // -------------------------------------------------------
    User::Role_Distributor => [
        // Sales (own distributor only — scoped in controller)
        'admin.sale.index',
        'admin.sale.data',
        'admin.sale.add',
        'admin.sale.save',
        'admin.sale.detail',

        // Own stock
        'admin.distributor-stock.index',
        'admin.distributor-stock.data',
        'admin.distributor-stock.movements',
        'admin.distributor-stock.movements.data',

        // Products (read-only for reference)
        'admin.product.index',
        'admin.product.data',
        'admin.product.detail',

        // Territory API
        'admin.territory.api.provinces',
        'admin.territory.api.districts',
        'admin.territory.api.villages',

        // View own target
        'admin.distributor-target.index',
        'admin.distributor-target.data',
        'admin.distributor-target.months',
    ],
];
