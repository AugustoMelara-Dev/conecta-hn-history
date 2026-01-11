#!/bin/bash
set -e

# 1. DOMAIN LAYER: Strict Type Definitions & Factories
git add app/Domain/Product/Entities/ProductEntity.php
git add app/Domain/Product/Repositories/ProductRepositoryInterface.php
git add app/Domain/Product/Factories/ProductFactory.php
git add app/Domain/TenantGovernance/Repositories/TenantRepositoryInterface.php
git add app/Domain/TenantGovernance/Factories/TenantFactory.php
git add app/Domain/Identity/Factories/UserFactory.php

sleep 1
git commit -m "refactor(domain): enforce strict typing and immutability

- Convert ProductEntity to final readonly class
- Harden ProductRepositoryInterface and TenantRepositoryInterface contracts
- Introduce ProductFactory and UserFactory for encapsulated creation logic"

# 2. INFRASTRUCTURE LAYER: Strict Repository Adapters
git add app/Infrastructure/Repositories/Products/EloquentProductRepository.php
git add app/Infrastructure/Repositories/TenantGovernance/EloquentTenantRepository.php

sleep 1
git commit -m "refactor(infra): align repositories with strict domain contracts

- Implement Entity-Model mapping in EloquentProductRepository
- Implement Entity-Model mapping in EloquentTenantRepository
- Ensure save() methods return hydrated Entities to prevent ID loss"

# 3. APPLICATION LAYER: Secure Handlers
git add app/Application/Tenant/Handlers/RegisterTenantHandler.php
git add app/Application/Products/Handlers/CreateProductHandler.php

sleep 1
git commit -m "refactor(app): harden handlers against type blindness

- Eliminate array-driven persistence in RegisterTenantHandler and CreateProductHandler
- Enforce capture of hydrated entities after save()
- Wrap operations in atomic transactions"

echo "✅ All changes committed successfully in 3 atomic steps."
