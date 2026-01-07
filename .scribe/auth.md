# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_AUTH_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

This API uses Laravel Sanctum for token-based authentication. You can obtain a token by logging in via the <code>POST /api/v1/auth/login</code> endpoint or registering via <code>POST /api/v1/auth/register</code>. Include the token in the Authorization header as: <code>Bearer {YOUR_AUTH_TOKEN}</code>.
