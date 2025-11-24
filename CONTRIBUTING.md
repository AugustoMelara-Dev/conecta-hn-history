# Contributing to Conecta HN

First off, thanks for taking the time to contribute! 🎉

The following is a set of guidelines for contributing to Conecta HN. These are mostly guidelines, not rules. Use your best judgment, and feel free to propose changes to this document in a pull request.

## Code of Conduct

This project and everyone participating in it is governed by our Code of Conduct. By participating, you are expected to uphold this code.

## How Can I Contribute?

### Reporting Bugs

This section guides you through submitting a bug report. Following these guidelines helps maintainers and the community understand your report, reproduce the behavior, and find related reports.

- **Use a clear and descriptive title** for the issue to identify the problem.
- **Describe the exact steps which reproduce the problem** in as many details as possible.
- **Provide specific examples** to demonstrate the steps.

### Pull Requests

1. Fork the repo and create your branch from `main`.
2. If you've added code that should be tested, add tests.
3. Ensure the test suite passes (`php artisan test`).
4. Make sure your code follows the existing style standards. We use **Laravel Pint** for PHP styling.
   ```bash
   ./vendor/bin/pint
   ```
5. Issue that pull request!

## Styleguides

### PHP

We follow the [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standard and use [Laravel Pint](https://laravel.com/docs/pint) to enforce it.

### JavaScript / React

- Use functional components with Hooks.
- Use strict typing where possible (Prop Types or TypeScript if migrated).
- Follow the existing folder structure in `resources/js`.

## Commit Messages

- Use the present tense ("Add feature" not "Added feature").
- Use the imperative mood ("Move cursor to..." not "Moves cursor to...").
- Limit the first line to 72 characters or less.
- Reference issues and pull requests liberally after the first line.
