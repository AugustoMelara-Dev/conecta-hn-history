<?php

declare(strict_types=1);

namespace App\Domain\Access\Enums;

enum Permission: string
{
    case TENANT_VIEW = 'tenant.view';
    case TENANT_UPDATE = 'tenant.update';
    case SETTINGS_VIEW = 'settings.view';
    case SETTINGS_UPDATE = 'settings.update';
    case BILLING_VIEW = 'billing.view';
    case BILLING_MANAGE = 'billing.manage';
    case CATALOG_VIEW = 'catalog.view';
    case CATALOG_CREATE = 'catalog.create';
    case CATALOG_UPDATE = 'catalog.update';
    case CATALOG_DELETE = 'catalog.delete';
    case CATALOG_PUBLISH = 'catalog.publish';
    case MENU_VIEW = 'menu.view';
    case MENU_MANAGE = 'menu.manage';
    case INVENTORY_VIEW = 'inventory.view';
    case INVENTORY_ADJUST = 'inventory.adjust';
    case INVENTORY_TRANSFER = 'inventory.transfer';
    case ORDERS_VIEW = 'orders.view';
    case ORDERS_CREATE = 'orders.create';
    case ORDERS_MANAGE = 'orders.manage';
    case ORDERS_FULFILL = 'orders.fulfill';
    case ORDERS_CANCEL = 'orders.cancel';
    case ORDERS_EXPORT = 'orders.export';
    case PAYMENTS_VIEW = 'payments.view';
    case PAYMENTS_RECORD = 'payments.record';
    case CUSTOMERS_VIEW = 'customers.view';
    case CUSTOMERS_EXPORT = 'customers.export';
    case CRM_DEALS_VIEW = 'crm.deals.view';
    case CRM_DEALS_CREATE = 'crm.deals.create';
    case CRM_DEALS_UPDATE = 'crm.deals.update';
    case CRM_DEALS_MOVE_STAGE = 'crm.deals.move_stage';
    case CRM_DEALS_DELETE = 'crm.deals.delete';
    case CRM_REPORTS_VIEW = 'crm.reports.view';
    case APPOINTMENTS_VIEW = 'appointments.view';
    case APPOINTMENTS_MANAGE = 'appointments.manage';
    case STAFF_VIEW = 'staff.view';
    case STAFF_MANAGE = 'staff.manage';
    case STAFF_ASSIGN_ROLES = 'staff.assign_roles';
    case PLAYBOOKS_VIEW = 'playbooks.view';
    case PLAYBOOKS_MANAGE = 'playbooks.manage';
    case PLAYBOOKS_ASSIGN = 'playbooks.assign';
    case MARKETING_VIEW = 'marketing.view';
    case MARKETING_MANAGE = 'marketing.manage';
    case ANALYTICS_VIEW = 'analytics.view';
    case ANALYTICS_EXPORT = 'analytics.export';
    case INTEGRATIONS_VIEW = 'integrations.view';
    case INTEGRATIONS_MANAGE = 'integrations.manage';
    case WEBHOOKS_VIEW = 'webhooks.view';
    case WEBHOOKS_MANAGE = 'webhooks.manage';
    case AI_USE = 'ai.use';
}
