declare namespace App.Application.Auth.Enums {
export type AuthStatus = {
name: string;
value: string;
};
}
declare namespace App.Application.Payments.Results {
export type PaymentWebhookResult = {
providerTransactionId: string;
internalStatus: string;
orderWasUpdated: boolean;
receiptUrl: string | null;
};
}
declare namespace App.Application.Products.Results {
export type UpdateProductResult = {
id: number;
sku: string;
changedAttributes: Array<any>;
};
}
declare namespace App.Application.Sales.DTOs {
export type OrderItemData = {
productId: number;
productName: string;
quantity: number;
unitPrice: number;
total: number;
};
}
declare namespace App.Application.Sales.Results {
export type CreateOrderResult = {
id: number;
orderNumber: string;
totalAmount: number;
status: string;
itemsCount: number;
currency: string;
items: { [key: number]: App.Application.Sales.DTOs.OrderItemData };
};
}
declare namespace App.Application.Tenant.Results {
export type RegisterTenantResult = {
tenantId: string;
domain: string;
ownerEmail: string;
setupUrl: string;
};
}
declare namespace App.DataTransferObjects.Marketing {
export type CouponRejection = {
name: string;
value: string;
};
export type CouponType = {
name: string;
value: string;
};
}
declare namespace App.Domain.Access.Enums {
export type Permission = {
name: string;
value: string;
};
export type Role = {
name: string;
value: string;
};
}
declare namespace App.Domain.Analytics.Enums {
export type InteractionType = {
name: string;
value: string;
};
}
declare namespace App.Domain.Billing.Enums {
export type PaymentStatus = {
name: string;
value: string;
};
}
declare namespace App.Domain.Catalog.Enums {
export type CatalogImportStatus = {
name: string;
value: string;
};
}
declare namespace App.Domain.Identity.Enums {
export type RegistrationSource = {
name: string;
value: string;
};
export type UserRole = {
name: string;
value: string;
};
}
declare namespace App.Domain.Sales.Enums {
export type OrderStatus = {
name: string;
value: string;
};
}
declare namespace App.Domain.Shared.Enums {
export type ProviderName = {
name: string;
value: string;
};
}
declare namespace App.Domain.TenantGovernance.Enums {
export type BusinessType = {
name: string;
value: string;
};
export type Capability = {
name: string;
value: string;
};
export type CommercialMode = {
name: string;
value: string;
};
export type TenantFeature = {
name: string;
value: string;
};
export type TenantPlan = {
name: string;
value: string;
};
}
declare namespace App.Enums {
export type AnnouncementType = {
name: string;
value: string;
};
export type LowStockSeverity = {
name: string;
value: string;
};
export type PaymentMethod = {
name: string;
value: string;
};
export type Severity = {
name: string;
value: string;
};
export type StockStatus = {
name: string;
value: string;
};
export type TenantRole = {
name: string;
value: string;
};
export type TransactionStatus = {
name: string;
value: string;
};
export type UserRole = {
name: string;
value: string;
};
}
