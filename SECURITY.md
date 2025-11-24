# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

We take security very seriously. If you discover a security vulnerability within Conecta HN, please follow these steps:

1.  **Do NOT create a public GitHub issue.**
2.  Email the security team at `security@conectahn.com` (or the repository owner).
3.  Include a detailed description of the vulnerability and steps to reproduce it.

We will acknowledge receipt of your vulnerability report within 48 hours and strive to send you regular updates about our progress.

## Security Best Practices

- **Authentication**: We use Laravel's built-in authentication and Filament's auth guards. Do not bypass these.
- **Authorization**: Ensure all new resources and endpoints have proper `can()` checks or Policy enforcement.
- **Input Validation**: Always validate user input using Laravel's Form Requests or Validation rules.
- **XSS Protection**: Blade and React automatically escape output, but be careful when using `v-html` or `dangerouslySetInnerHTML`.
