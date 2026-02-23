# StockFlow: Building a Full-Stack Inventory System

## Course Overview

In these three lessons, you'll build **StockFlow**, a complete inventory management system. This bridges your existing PHP knowledge with modern full-stack development practices.

### What You'll Build

A professional inventory system with:
- Product management (CRUD operations)
- Stock tracking and movements
- Order processing
- Role-based access control (Admin, Manager, Staff)
- React frontend with real-time updates

### Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     BROWSER (React)                         │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐         │
│  │  Dashboard  │  │  Products   │  │   Orders    │         │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘         │
│         │                │                │                 │
│         └────────────────┼────────────────┘                 │
│                          │                                  │
│                   ┌──────▼──────┐                          │
│                   │   api.js    │  (API client)            │
│                   └──────┬──────┘                          │
└──────────────────────────┼──────────────────────────────────┘
                           │ HTTP (JSON)
┌──────────────────────────┼──────────────────────────────────┐
│                     PHP API                                 │
│                   ┌──────▼──────┐                          │
│                   │ products.php │  (REST endpoints)       │
│                   │ orders.php   │                         │
│                   │ auth.php     │                         │
│                   └──────┬──────┘                          │
│                          │                                  │
│                   ┌──────▼──────┐                          │
│                   │ supabase.php│  (Database client)       │
│                   └──────┬──────┘                          │
└──────────────────────────┼──────────────────────────────────┘
                           │ HTTPS (REST API)
┌──────────────────────────┼──────────────────────────────────┐
│                     SUPABASE                                │
│  ┌─────────────┐  ┌──────▼──────┐  ┌─────────────┐         │
│  │    Auth     │  │  PostgreSQL │  │     RLS     │         │
│  │  (Google)   │  │  (Database) │  │  (Security) │         │
│  └─────────────┘  └─────────────┘  └─────────────┘         │
└─────────────────────────────────────────────────────────────┘
```

### Prerequisites

Before starting, you should have completed:
- ✅ Lessons 0-8: PHP fundamentals (variables, arrays, functions, forms)
- ✅ Lesson 11: Supabase authentication basics
- ✅ Basic understanding of HTML/CSS

### Lesson Structure

| Lesson | Topic | Duration | Focus |
|--------|-------|----------|-------|
| 1 | PHP API Fundamentals | 2 hours | JSON APIs, REST principles, error handling |
| 2 | Building the Product API | 2 hours | CRUD operations, authentication, role-based access |
| 3 | React Integration | 2 hours | Frontend setup, API consumption, state management |

### What's Different from Lesson 11?

In Lesson 11, you built a traditional PHP application where:
- PHP rendered HTML directly
- Forms submitted via POST
- Pages refreshed on each action

In StockFlow, you'll learn the **modern API approach**:
- PHP returns JSON data (no HTML)
- React handles all UI rendering
- JavaScript makes API calls
- Single-page application (no page refreshes)

### Files You'll Create

```
stockflow/
├── phpDir/
│   ├── api/                    # Lesson 2
│   │   ├── products.php        # Product list & create
│   │   ├── product.php         # Single product CRUD
│   │   ├── categories.php      # Category management
│   │   └── dashboard.php       # Statistics
│   │
│   └── includes/               # Lesson 1
│       ├── config.php          # Configuration
│       ├── supabase.php        # API client
│       ├── auth.php            # Token validation
│       ├── role.php            # Permission checks
│       ├── json_response.php   # JSON helpers
│       └── cors.php            # Cross-origin setup
│
├── frontend/                   # Lesson 3
│   └── src/
│       ├── App.jsx
│       ├── context/AuthContext.jsx
│       ├── lib/api.js
│       └── pages/Products.jsx
│
└── database/
    └── schema.sql              # Database setup
```

### Getting Started

1. **Set up Supabase** (if not already done)
   - Create a project at supabase.com
   - Enable Google OAuth
   - Run `database/schema.sql` in SQL Editor

2. **Start with Lesson 1**
   - Open `lessons/01-php-api-fundamentals.md`
   - Follow the step-by-step instructions

Let's begin!
