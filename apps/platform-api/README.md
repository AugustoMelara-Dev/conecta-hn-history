# platform-api (bootstrap)

Purpose: shared contracts for identity, tenancy, catalog, orders, and directory guide.

Bootstrap contract:
- Auth responses must include `redirect_to`, `home_surface`, `audience`.
- `GET /api/v1/me/context` is the source of actor and membership context.
- `GET /api/v1/surfaces/bootstrap` publishes split migration adapters.
