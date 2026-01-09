declare namespace App.Application.DTOs.Identity {
export type RegisterUserResult = {
id: number;
tenantId: number;
email: string;
requiresVerification: boolean;
};
}
declare namespace App.Application.DTOs.Payments {
export type PaymentWebhookResult = {
providerTransactionId: string;
internalStatus: string;
orderWasUpdated: boolean;
receiptUrl: string | null;
};
}
declare namespace App.Application.DTOs.Products {
export type UpdateProductResult = {
id: number;
sku: string;
changedAttributes: Array<any>;
};
}
declare namespace App.Application.DTOs.Sales {
export type CreateOrderResult = {
id: number;
orderNumber: string;
totalAmount: number;
status: string;
itemsCount: number;
currency: string;
items: { [key: number]: App.Application.DTOs.Sales.OrderItemDto };
};
export type OrderItemDto = {
productId: number;
productName: string;
quantity: number;
unitPrice: number;
total: number;
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
declare namespace App.Domain.Analytics.Enums {
export type InteractionType = {
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
