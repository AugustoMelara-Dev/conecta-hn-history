# business-app (bootstrap)

Purpose: B2B operations surface for tenant owners and staff.

Bootstrap contract:
- Consume `GET /api/v1/me/context`.
- Consume `GET /api/v1/surfaces/bootstrap`.
- Redirect legacy `/workspace/*` and `/dashboard` to canonical business routes.
