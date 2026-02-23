# Lesson 3: React Integration

## Learning Objectives

By the end of this lesson, you will:
1. Understand how React communicates with PHP APIs
2. Set up authentication context for React
3. Create an API client for making requests
4. Build components that fetch and display data
5. Handle loading states and errors

---

## Part 1: Understanding the React-PHP Connection

### How Traditional PHP Works (Lesson 11)

```
1. Browser requests PHP page
2. PHP processes and renders HTML
3. Browser displays the HTML
4. User clicks a link
5. Browser requests new PHP page (full reload)
```

### How React + PHP API Works

```
1. Browser loads React app (once)
2. React renders initial UI
3. React calls PHP API (fetch)
4. PHP returns JSON data
5. React updates UI (no page reload)
6. User interacts
7. React calls API again...
```

**Key Difference:** The browser loads React once, then React handles everything. PHP only provides data.

---

## Part 2: Project Setup

### Step 1: Create the React Project

```bash
cd stockflow
npm create vite@latest frontend -- --template react
cd frontend
npm install
npm install @supabase/supabase-js react-router-dom
```

### Step 2: Configure Vite Proxy

When React (localhost:5173) calls PHP (localhost:8080), we need to proxy the requests.

Create/update `frontend/vite.config.js`:

```javascript
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      // Proxy /api requests to PHP server
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
    },
  },
})
```

**What This Does:**
```
React request:  /api/products.php
Vite proxies:   http://localhost:8080/api/products.php
PHP responds:   JSON data
Vite forwards:  JSON data back to React
```

---

## Part 3: Supabase Client Setup

### Step 3: Create the Supabase Client

Create `frontend/src/lib/supabase.js`:

```javascript
import { createClient } from '@supabase/supabase-js'

// These come from your .env file
const supabaseUrl = import.meta.env.VITE_SUPABASE_URL
const supabaseKey = import.meta.env.VITE_SUPABASE_ANON_KEY

// Create the Supabase client
export const supabase = createClient(supabaseUrl, supabaseKey)
```

Create `frontend/.env`:

```
VITE_SUPABASE_URL=https://your-project.supabase.co
VITE_SUPABASE_ANON_KEY=eyJhbG...your-anon-key
```

**Note:** `VITE_` prefix is required for Vite to expose variables to the browser.

---

## Part 4: Authentication Context

### Step 4: Create AuthContext

Create `frontend/src/context/AuthContext.jsx`:

```jsx
import { createContext, useContext, useState, useEffect, useCallback } from 'react'
import { supabase } from '../lib/supabase'

// Create the context
const AuthContext = createContext(null)

// Custom hook for using auth
export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within AuthProvider')
  return ctx
}

// Provider component
export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [session, setSession] = useState(null)
  const [role, setRole] = useState('staff')
  const [loading, setLoading] = useState(true)
  const [initialized, setInitialized] = useState(false)

  // Fetch user role from our PHP API
  async function fetchUserRole(token) {
    try {
      const response = await fetch('/api/me.php', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      })
      if (response.ok) {
        const data = await response.json()
        return data.data?.role || 'staff'
      }
    } catch (error) {
      console.error('Failed to fetch role:', error)
    }
    return 'staff'
  }

  // Initialize auth on mount
  useEffect(() => {
    let mounted = true

    async function initAuth() {
      try {
        // Get current session from Supabase
        const { data: { session: currentSession } } = await supabase.auth.getSession()

        if (mounted && currentSession) {
          setSession(currentSession)
          setUser(mapUser(currentSession.user))

          // Fetch role from our API
          const userRole = await fetchUserRole(currentSession.access_token)
          setRole(userRole)
        }
      } catch (error) {
        console.error('Auth init error:', error)
      } finally {
        if (mounted) {
          setLoading(false)
          setInitialized(true)
        }
      }
    }

    initAuth()

    // Listen for auth changes (login/logout)
    const { data: { subscription } } = supabase.auth.onAuthStateChange(
      async (event, newSession) => {
        if (mounted) {
          setSession(newSession)
          setUser(newSession ? mapUser(newSession.user) : null)

          if (newSession) {
            const userRole = await fetchUserRole(newSession.access_token)
            setRole(userRole)
          } else {
            setRole('staff')
          }
          setLoading(false)
        }
      }
    )

    return () => {
      mounted = false
      subscription.unsubscribe()
    }
  }, [])

  // Convert Supabase user to our format
  function mapUser(supabaseUser) {
    if (!supabaseUser) return null
    return {
      id: supabaseUser.id,
      email: supabaseUser.email,
      name: supabaseUser.user_metadata?.full_name || supabaseUser.email?.split('@')[0],
      avatar: supabaseUser.user_metadata?.avatar_url,
    }
  }

  // Sign in with Google
  const signInWithGoogle = useCallback(async () => {
    const { error } = await supabase.auth.signInWithOAuth({
      provider: 'google',
      options: { redirectTo: window.location.origin + '/' },
    })
    if (error) throw error
  }, [])

  // Sign out
  const signOut = useCallback(async () => {
    await supabase.auth.signOut()
    setSession(null)
    setUser(null)
    setRole('staff')
  }, [])

  // Get access token for API calls
  const getAccessToken = useCallback(() => {
    return session?.access_token || null
  }, [session])

  // Context value
  const value = {
    user,
    session,
    role,
    loading,
    initialized,
    signInWithGoogle,
    signOut,
    getAccessToken,
    isAuthenticated: !!user,
    isAdmin: role === 'admin',
    isAdminOrManager: role === 'admin' || role === 'manager',
  }

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  )
}
```

**Key Concepts:**

1. **Context** - Shares auth state across all components
2. **useEffect** - Runs once on mount to check existing session
3. **onAuthStateChange** - Listens for login/logout events
4. **useCallback** - Prevents function recreation on re-render

---

## Part 5: API Client

### Step 5: Create the API Helper

Create `frontend/src/lib/api.js`:

```javascript
import { supabase } from './supabase'

/**
 * Get headers for API requests
 * Includes the Authorization token from Supabase
 */
async function getHeaders() {
  const { data: { session } } = await supabase.auth.getSession()
  const token = session?.access_token || ''

  return {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`,
  }
}

/**
 * Build URL with query parameters
 */
function buildUrl(path, params = {}) {
  const url = new URL(path, window.location.origin)
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      url.searchParams.set(key, value)
    }
  })
  return url.toString()
}

/**
 * Make an API request
 */
async function request(method, path, body = null, params = {}) {
  const headers = await getHeaders()
  const url = buildUrl(path, params)

  const options = { method, headers }

  if (body && method !== 'GET') {
    options.body = JSON.stringify(body)
  }

  const response = await fetch(url, options)
  const json = await response.json()

  if (!response.ok) {
    const errorMsg = json.error || json.message || `Request failed`
    throw new Error(errorMsg)
  }

  // Return data property if it exists
  return json.data !== undefined ? json.data : json
}

// Export convenient methods
export const api = {
  get:  (path, params) => request('GET', path, null, params),
  post: (path, body)   => request('POST', path, body),
  put:  (path, body, params) => request('PUT', path, body, params),
  del:  (path, params) => request('DELETE', path, null, params),
}
```

**Usage Examples:**

```javascript
import { api } from '../lib/api'

// GET all products
const products = await api.get('/api/products.php')

// GET with filters
const active = await api.get('/api/products.php', { status: 'active' })

// POST new product
const created = await api.post('/api/products.php', {
  name: 'New Product',
  sku: 'SKU-001',
  price: 99.99,
})

// PUT update
await api.put('/api/product.php', { price: 89.99 }, { id: productId })

// DELETE
await api.del('/api/product.php', { id: productId })
```

---

## Part 6: Building Components

### Step 6: Create the App Structure

Update `frontend/src/App.jsx`:

```jsx
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { AuthProvider, useAuth } from './context/AuthContext'

import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import Products from './pages/Products'

// Protected route wrapper
function ProtectedRoute({ children }) {
  const { isAuthenticated, loading, initialized } = useAuth()

  // Show loading while checking auth
  if (!initialized || loading) {
    return <div style={{ padding: 40 }}>Loading...</div>
  }

  // Redirect to login if not authenticated
  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  return children
}

function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />

      <Route path="/" element={
        <ProtectedRoute>
          <Dashboard />
        </ProtectedRoute>
      } />

      <Route path="/products" element={
        <ProtectedRoute>
          <Products />
        </ProtectedRoute>
      } />
    </Routes>
  )
}

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <AppRoutes />
      </AuthProvider>
    </BrowserRouter>
  )
}
```

### Step 7: Create the Login Page

Create `frontend/src/pages/Login.jsx`:

```jsx
import { useAuth } from '../context/AuthContext'
import { Navigate } from 'react-router-dom'

export default function Login() {
  const { isAuthenticated, signInWithGoogle } = useAuth()

  // Redirect if already logged in
  if (isAuthenticated) {
    return <Navigate to="/" replace />
  }

  return (
    <div style={{
      display: 'flex',
      flexDirection: 'column',
      alignItems: 'center',
      justifyContent: 'center',
      minHeight: '100vh',
      background: '#f8f9fa',
    }}>
      <h1>StockFlow</h1>
      <p>Inventory Management System</p>

      <button
        onClick={signInWithGoogle}
        style={{
          padding: '12px 24px',
          fontSize: '16px',
          background: '#4285f4',
          color: 'white',
          border: 'none',
          borderRadius: '4px',
          cursor: 'pointer',
        }}
      >
        Sign in with Google
      </button>
    </div>
  )
}
```

### Step 8: Create the Products Page

Create `frontend/src/pages/Products.jsx`:

```jsx
import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { api } from '../lib/api'

export default function Products() {
  const { role, signOut } = useAuth()

  // State
  const [products, setProducts] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  // Fetch products on mount
  useEffect(() => {
    loadProducts()
  }, [])

  async function loadProducts() {
    try {
      setLoading(true)
      setError(null)
      const data = await api.get('/api/products.php')
      setProducts(data)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  // Show loading state
  if (loading) {
    return <div style={{ padding: 20 }}>Loading products...</div>
  }

  // Show error state
  if (error) {
    return (
      <div style={{ padding: 20 }}>
        <p style={{ color: 'red' }}>Error: {error}</p>
        <button onClick={loadProducts}>Retry</button>
      </div>
    )
  }

  return (
    <div style={{ padding: 20 }}>
      <header style={{
        display: 'flex',
        justifyContent: 'space-between',
        marginBottom: 20,
      }}>
        <h1>Products</h1>
        <div>
          <span style={{ marginRight: 10 }}>Role: {role}</span>
          <button onClick={signOut}>Sign Out</button>
        </div>
      </header>

      <nav style={{ marginBottom: 20 }}>
        <Link to="/" style={{ marginRight: 10 }}>Dashboard</Link>
        <Link to="/products">Products</Link>
      </nav>

      {/* Only show Add button for admin/manager */}
      {role !== 'staff' && (
        <button style={{
          marginBottom: 20,
          padding: '10px 20px',
          background: '#28a745',
          color: 'white',
          border: 'none',
          borderRadius: '4px',
          cursor: 'pointer',
        }}>
          + Add Product
        </button>
      )}

      {/* Products table */}
      <table style={{ width: '100%', borderCollapse: 'collapse' }}>
        <thead>
          <tr style={{ borderBottom: '2px solid #ddd', textAlign: 'left' }}>
            <th style={{ padding: 10 }}>Name</th>
            <th style={{ padding: 10 }}>SKU</th>
            <th style={{ padding: 10 }}>Price</th>
            <th style={{ padding: 10 }}>Stock</th>
            <th style={{ padding: 10 }}>Status</th>
          </tr>
        </thead>
        <tbody>
          {products.map(product => (
            <tr key={product.id} style={{ borderBottom: '1px solid #eee' }}>
              <td style={{ padding: 10 }}>
                <strong>{product.name}</strong>
                <br />
                <small style={{ color: '#666' }}>
                  {product.categories?.name || 'No category'}
                </small>
              </td>
              <td style={{ padding: 10 }}>{product.sku}</td>
              <td style={{ padding: 10 }}>€{Number(product.price).toFixed(2)}</td>
              <td style={{ padding: 10 }}>
                <span style={{
                  color: product.stock_quantity <= product.reorder_threshold
                    ? 'red' : 'green'
                }}>
                  {product.stock_quantity}
                </span>
              </td>
              <td style={{ padding: 10 }}>
                <span style={{
                  padding: '2px 8px',
                  borderRadius: '4px',
                  background: product.status === 'active' ? '#d4edda' : '#f8d7da',
                  color: product.status === 'active' ? '#155724' : '#721c24',
                }}>
                  {product.status}
                </span>
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      {products.length === 0 && (
        <p style={{ textAlign: 'center', padding: 40, color: '#666' }}>
          No products found.
        </p>
      )}
    </div>
  )
}
```

### Step 9: Create the Dashboard Page

Create `frontend/src/pages/Dashboard.jsx`:

```jsx
import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { api } from '../lib/api'

export default function Dashboard() {
  const { user, role, signOut } = useAuth()

  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    loadDashboard()
  }, [])

  async function loadDashboard() {
    try {
      const result = await api.get('/api/dashboard.php')
      setData(result)
    } catch (err) {
      console.error('Dashboard error:', err)
    } finally {
      setLoading(false)
    }
  }

  if (loading) {
    return <div style={{ padding: 20 }}>Loading dashboard...</div>
  }

  const stats = data?.stats || {}

  return (
    <div style={{ padding: 20 }}>
      <header style={{
        display: 'flex',
        justifyContent: 'space-between',
        marginBottom: 20,
      }}>
        <h1>Dashboard</h1>
        <div>
          <span style={{ marginRight: 10 }}>
            {user?.name} ({role})
          </span>
          <button onClick={signOut}>Sign Out</button>
        </div>
      </header>

      <nav style={{ marginBottom: 20 }}>
        <Link to="/" style={{ marginRight: 10 }}>Dashboard</Link>
        <Link to="/products">Products</Link>
      </nav>

      <p>Welcome back, {user?.name}!</p>

      {/* Stats Grid */}
      <div style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(4, 1fr)',
        gap: 20,
        marginTop: 20,
      }}>
        <StatCard label="Total Products" value={stats.total_products} />
        <StatCard label="Total Orders" value={stats.total_orders} />
        <StatCard label="Low Stock" value={stats.low_stock} color="orange" />
        <StatCard
          label="Revenue"
          value={`€${(stats.total_revenue || 0).toFixed(2)}`}
          color="green"
        />
      </div>
    </div>
  )
}

function StatCard({ label, value, color = 'black' }) {
  return (
    <div style={{
      background: 'white',
      padding: 20,
      borderRadius: 8,
      boxShadow: '0 2px 4px rgba(0,0,0,0.1)',
    }}>
      <div style={{ color: '#666', fontSize: 14 }}>{label}</div>
      <div style={{ fontSize: 32, fontWeight: 'bold', color }}>
        {value ?? '-'}
      </div>
    </div>
  )
}
```

---

## Part 7: Running the Application

### Step 10: Start Both Servers

**Terminal 1 - PHP (Docker):**
```bash
cd stockflow
docker-compose up
```

**Terminal 2 - React:**
```bash
cd stockflow/frontend
npm run dev
```

**Open:** http://localhost:5173

---

## Exercises

### Exercise 1: Add Product Count

Update the Products page to show the total count: "45 products"

<details>
<summary>Solution</summary>

```jsx
<p style={{ marginBottom: 10 }}>
  {products.length} products
</p>
```
</details>

### Exercise 2: Add Search Filter

Add a search input that filters products by name.

<details>
<summary>Hint</summary>

1. Add state: `const [search, setSearch] = useState('')`
2. Add input: `<input value={search} onChange={e => setSearch(e.target.value)} />`
3. Modify loadProducts to pass search as parameter
4. Use useEffect with search as dependency to reload

</details>

### Exercise 3: Add Product Modal

Create a modal form for adding new products (admin/manager only).

Think about:
- When to show/hide the modal
- What fields to include
- How to submit and refresh the list

---

## Summary

In this lesson, you learned:

1. **Vite Proxy** - Routing API calls during development
2. **Auth Context** - Sharing authentication across components
3. **API Client** - Making authenticated requests
4. **Protected Routes** - Redirecting unauthenticated users
5. **Data Fetching** - useEffect + useState pattern
6. **Conditional Rendering** - Role-based UI elements

### Files Created

```
frontend/
└── src/
    ├── lib/
    │   ├── supabase.js     ✅
    │   └── api.js          ✅
    ├── context/
    │   └── AuthContext.jsx ✅
    ├── pages/
    │   ├── Login.jsx       ✅
    │   ├── Dashboard.jsx   ✅
    │   └── Products.jsx    ✅
    └── App.jsx             ✅
```

### The Complete Flow

```
1. User visits http://localhost:5173
2. React loads, checks Supabase for existing session
3. No session → Redirect to /login
4. User clicks "Sign in with Google"
5. Supabase OAuth flow → returns JWT token
6. React stores token, redirects to /
7. Dashboard calls /api/dashboard.php with token
8. PHP validates token, queries Supabase
9. PHP returns JSON data
10. React renders the dashboard
```

### Congratulations!

You've built a complete full-stack application with:
- PHP REST API backend
- React frontend
- Supabase authentication
- Role-based access control
- Real-time data updates

### Next Steps

- Add more pages (Orders, Inventory, Team)
- Implement create/edit forms
- Add proper styling (CSS or Tailwind)
- Deploy to production
