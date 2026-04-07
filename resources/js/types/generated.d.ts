declare namespace App {
namespace Application {
namespace Auth {
namespace Enums {
export type AuthStatus = "success" | "new_user" | "requires_2fa" | "requires_onboarding";
}
}
}
namespace DataTransferObjects {
namespace Marketing {
export type CouponRejection = "not_found" | "expired" | "not_started" | "usage_limit" | "user_limit" | "min_spend" | "already_used" | "inactive" | "product_excluded" | "category_excluded";
export type CouponType = "percentage" | "fixed" | "free_shipping";
}
}
namespace Domain {
namespace Access {
namespace Enums {
export type Permission = "tenant.view" | "tenant.update" | "settings.view" | "settings.update" | "billing.view" | "billing.manage" | "catalog.view" | "catalog.create" | "catalog.update" | "catalog.delete" | "catalog.publish" | "menu.view" | "menu.manage" | "inventory.view" | "inventory.adjust" | "inventory.transfer" | "orders.view" | "orders.create" | "orders.manage" | "orders.fulfill" | "orders.cancel" | "orders.export" | "payments.view" | "payments.record" | "customers.view" | "customers.export" | "appointments.view" | "appointments.manage" | "staff.view" | "staff.manage" | "staff.assign_roles" | "playbooks.view" | "playbooks.manage" | "playbooks.assign" | "marketing.view" | "marketing.manage" | "analytics.view" | "analytics.export" | "integrations.view" | "integrations.manage" | "webhooks.view" | "webhooks.manage" | "ai.use";
export type Role = "owner" | "admin" | "manager" | "operator" | "viewer";
}
}
namespace Analytics {
namespace Enums {
export type InteractionType = "whatsapp_click" | "call" | "location_view" | "cart_add" | "social_share" | "conversion" | "purchase";
}
}
namespace Billing {
namespace Enums {
export type PaymentStatus = "pending" | "paid" | "rejected" | "review";
}
}
namespace Identity {
namespace Enums {
export type UserRole = "super_admin" | "seller" | "staff" | "user";
}
}
namespace Inventory {
namespace Enums {
export type MovementType = "initial" | "purchase" | "sale" | "restock_return" | "manual_adjustment" | "loss" | "transfer";
}
}
namespace Marketing {
namespace Enums {
export type AudienceType = "all_customers" | "crm_customers" | "followers" | "waitlist" | "leads";
}
}
namespace Sales {
namespace Enums {
export type OrderStatus = "pending" | "confirmed" | "processing" | "ready" | "delivered" | "cancelled";
}
}
namespace Shared {
namespace Enums {
export type ProviderName = "google" | "facebook" | "apple" | "github" | "linkedin";
}
}
namespace TenantGovernance {
namespace Enums {
export type BusinessType = "retail" | "food" | "service" | "hybrid";
export type Capability = "catalog_basic" | "checkout_basic" | "customer_directory" | "payments_basic" | "notifications_basic" | "analytics_basic" | "coupons_basic" | "staff_basic" | "ai_content" | "web_push" | "product_variants" | "inventory_tracking" | "stock_alerts" | "pickup_orders" | "shipping_orders" | "delivery_orders" | "menu_modifiers" | "kitchen_display" | "table_management" | "appointment_booking" | "staff_calendar" | "service_duration_buffers" | "service_packages" | "appointment_reminders";
export type TenantFeature = "advanced_catalog" | "marketing_suite" | "marketing_campaigns" | "promotional_coupons" | "loyalty_program" | "loyalty_rewards" | "advanced_branding" | "upgrade_prompt";
export type TenantPlan = "semilla" | "pro";
}
}
}
namespace Enums {
export type AnnouncementType = "info" | "warning" | "promo" | "urgent";
export type LowStockSeverity = "info" | "warning" | "critical";
export type PaymentMethod = "cash" | "bank_transfer" | "card" | "pending";
export type Severity = "debug" | "info" | "notice" | "warning" | "error" | "critical" | "alert" | "emergency";
export type StockStatus = "in_stock" | "out_of_stock" | "on_backorder" | "low_stock" | "discontinued" | "pre_order";
export type TenantRole = "owner" | "manager" | "employee" | "viewer";
export type TransactionStatus = "pending" | "processing" | "requires_action" | "completed" | "failed" | "refunded" | "partially_refunded" | "disputed" | "cancelled";
export type UserRole = "super_admin" | "seller" | "staff" | "user";
}
}
