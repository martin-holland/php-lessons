# StockFlow - Inventory Management

A proof of concept inventory and order management system built with React (Vite) and PHP API backend, using Supabase for authentication and database.

## Architecture

```
Browser (React)
    |
    +-- Supabase JS (Auth)
    |
    +-- PHP API (via fetch)
            |
            +-- Supabase (Database)
```

## Quick Start

### 1. Set up Supabase

1. Go to your Supabase project SQL editor
2. Run `database/schema.sql` to create tables, RLS policies, and seed data

### 2. Set yourself as admin

After logging in with Google OAuth for the first time, run this in Supabase SQL:

```sql
INSERT INTO user_roles (user_id, role)
SELECT id, 'admin' FROM auth.users WHERE email = 'your-email@example.com'
ON CONFLICT (user_id) DO UPDATE SET role = 'admin';
```

### 3. Start the application

**Option A: Docker**
```bash
docker-compose up
```

**Option B: Manual**

Terminal 1 - PHP (requires Apache with PHP 8):
```bash
cd phpDir
# Start your local Apache server pointing to this directory
```

Terminal 2 - React:
```bash
cd frontend
npm install
npm run dev
```

### 4. Open the app

- React app: http://localhost:5173
- PHP API: http://localhost:8080/api/

## Project Structure

```
stockflow/
├── frontend/                 # React SPA
│   ├── src/
│   │   ├── components/       # UI components
│   │   ├── context/          # Auth context
│   │   ├── lib/              # Supabase client & API wrapper
│   │   ├── pages/            # Page components
│   │   └── styles/           # CSS
│   └── package.json
│
├── phpDir/                   # PHP API Backend
│   ├── api/                  # API endpoints
│   │   ├── products.php
│   │   ├── orders.php
│   │   ├── inventory.php
│   │   ├── team.php
│   │   └── ...
│   └── includes/             # Helpers
│       ├── config.php
│       ├── supabase.php
│       ├── auth.php
│       └── role.php
│
├── database/
│   └── schema.sql            # Tables, RLS policies, seed data
│
└── docker-compose.yml
```

## Features

- **Google OAuth** authentication via Supabase
- **Role-based access** (Admin, Manager, Staff)
- **Products** - CRUD with categories
- **Inventory** - Stock levels and movements
- **Orders** - Create, status workflow
- **Team** - User role management (admin only)

## Role Permissions

| Role    | Products | Orders | Stock | Team |
|---------|----------|--------|-------|------|
| Admin   | CRUD     | CRUD   | CRUD  | CRUD |
| Manager | CRUD     | CRUD   | CRUD  | View |
| Staff   | View     | Create | CRUD  | -    |

## Environment Variables

Create a `.env` file or set in docker-compose.yml:

```
VITE_SUPABASE_URL=https://your-project.supabase.co
VITE_SUPABASE_ANON_KEY=your-anon-key
SUPABASE_URL=https://your-project.supabase.co
SUPABASE_ANON_KEY=your-anon-key
```

For local development, create `phpDir/includes/config.local.php`:

```php
<?php
define('SUPABASE_URL', 'https://your-project.supabase.co');
define('SUPABASE_ANON_KEY', 'your-anon-key');
define('SITE_URL', 'http://localhost:5173');
```
