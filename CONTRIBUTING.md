# Contributing to AutoGraphQL

Thank you for your interest in contributing to AutoGraphQL! This package aims to be the most transparent and effortless way to add GraphQL to Laravel REST APIs.

## 🛠️ Development Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/mrnamra/laravel-autographql.git
   cd laravel-autographql
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Run tests**
   ```bash
   vendor/bin/phpunit
   ```

4. **Run static analysis**
   ```bash
   vendor/bin/phpstan analyse src
   ```

## 📜 Contribution Rules

- **PSR-12 coding standard**: Ensure your code follows the standard. We use `laravel/pint` for code styling.
- **Provide tests**: Any new feature or bug fix must come with a corresponding test.
- **Maintain zero-config**: The core philosophy is **zero-config**. Any feature that requires manual schema writing or class creation should be an optional override, not the default.
- **Performance matters**: GraphQL selection set analysis and eager loading are critical. Avoid any performance regressions in these areas.

## 🐛 Bug Reports

If you find a bug, please open an issue and include:
- Laravel version
- PHP version
- Your route/model structure
- Steps to reproduce

## 💡 Feature Requests

We love new ideas! If you want to request a feature, please outline:
- Use case
- Expected behavior
- Why it fits the **AutoGraphQL** philosophy

---

Let's make Laravel GraphQL easier for everyone!
