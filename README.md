<div align="center">

```
 █████╗ ██╗   ██╗████████╗ ██████╗  ██████╗ ██████╗  █████╗ ██████╗ ██╗  ██╗ ██████╗ ██╗
██╔══██╗██║   ██║╚══██╔══╝██╔═══██╗██╔════╝ ██╔══██╗██╔══██╗██╔══██╗██║  ██║██╔═══██╗██║
███████║██║   ██║   ██║   ██║   ██║██║  ███╗██████╔╝███████║██████╔╝███████║██║   ██║██║
██╔══██║██║   ██║   ██║   ██║   ██║██║   ██║██╔══██╗██╔══██║██╔═══╝ ██╔══██║██║▄▄ ██║██║
██║  ██║╚██████╔╝   ██║   ╚██████╔╝╚██████╔╝██║  ██║██║  ██║██║     ██║  ██║╚██████╔╝███████╗
╚═╝  ╚═╝ ╚═════╝    ╚═╝    ╚═════╝  ╚═════╝ ╚═╝  ╚═╝╚═╝  ╚═╝╚═╝     ╚═╝  ╚═╝ ╚══▀▀═╝ ╚══════╝
```

**Zero-Config REST → GraphQL Bridge for Laravel**

[![Latest Version](https://img.shields.io/packagist/v/mrnamra/laravel-autographql.svg?style=flat-square)](https://packagist.org/packages/mrnamra/laravel-autographql)
[![PHP Version](https://img.shields.io/badge/PHP-%5E8.1-blue?style=flat-square)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-10%2B%20%7C%2011%2B-red?style=flat-square)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green?style=flat-square)](LICENSE.md)

> **Install the package. Your REST API is now also a GraphQL API. Zero code changes.**

</div>

---

## 🚀 Why AutoGraphQL?

Every existing Laravel GraphQL package is **schema-first** or **code-first**. You usually have to rewrite everything. 

**AutoGraphQL is different:** It scans your `routes/api.php`, detects your Eloquent models and relationships, and automatically generates a full GraphQL schema. It then proxies GraphQL requests directly to your existing Controllers.

- **Zero Schema Files**: No `.graphql` to maintain.
- **Zero Type Classes**: No manual PHP type definitions.
- **Automatic Eager Loading**: Solves the N+1 problem automatically by analyzing query depth.
- **Auth Mirroring**: Respects all your existing Route and Controller middleware.

---

## 📋 Installation

Install the package via composer:

```bash
composer require mrnamra/laravel-autographql
```

The package will automatically register itself using Laravel's auto-discovery.

---

## ⚙️ Configuration

### Publish the Config File
To customize the default behavior, publish the configuration file:

```bash
php artisan vendor:publish --tag=autographql-config
```

Items you can configure in `config/autographql.php`:
- `endpoint`: The URI for your GraphQL API (defaults to `/graphql`).
- `middleware`: Apply global middleware (e.g., `api`, `auth:sanctum`).
- `route_prefix`: Which REST routes to scan (defaults to `api`).
- `eager_loading`: Toggle automatic N+1 prevention.
- `playground`: Enable/Disable the GraphiQL playground in debug mode.

### GraphiQL Playground
If `APP_DEBUG` is true, you can access the interative playground at:
`http://your-app.test/graphiql`

---

## 🛠️ Usage

### Auto-Discovery
By default, the package scans all routes starting with `/api/`.
- `GET /api/posts` → `query { posts { ... } }`
- `POST /api/posts` → `mutation { createPost(input: { ... }) { ... } }`

### Explicit Customization
Use the `#[GraphQL]` attribute on your controller methods for fine-grained control:

```php
use MrNamra\AutoGraphQL\Attributes\GraphQL;

class PostController extends Controller
{
    #[GraphQL(query: 'all_posts', description: 'Lists all blog posts')]
    public function index() { ... }

    #[GraphQL(mutation: 'publishPost', model: \App\Models\Post::class)]
    public function store(Request $request) { ... }
}
```

### High-Performance Search
Mark model properties as `#[Searchable]` to enable high-performance filtering across tables:

```php
use MrNamra\AutoGraphQL\Attributes\Searchable;

class Post extends Model {
    #[Searchable]
    protected $title;

    #[Searchable(relation: 'comments', column: 'body')]
    public function comments() { ... }
}
```

---

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

---

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.