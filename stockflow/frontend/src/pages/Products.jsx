import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { api } from '../lib/api';
import DataTable from '../components/DataTable';
import Badge from '../components/Badge';
import StockBar from '../components/StockBar';
import EmptyState from '../components/EmptyState';
import LoadingSkeleton from '../components/LoadingSkeleton';

export default function Products() {
  const { role } = useAuth();
  const navigate = useNavigate();
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');

  useEffect(() => {
    loadProducts();
  }, []);

  async function loadProducts() {
    try {
      const params = {};
      if (search) params.search = search;
      if (statusFilter) params.status = statusFilter;
      const data = await api.get('/api/products.php', params);
      setProducts(data);
    } catch (err) {
      console.error('Failed to load products:', err);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    const timer = setTimeout(() => {
      loadProducts();
    }, 300);
    return () => clearTimeout(timer);
  }, [search, statusFilter]);

  if (loading) return <LoadingSkeleton rows={6} />;

  if (products.length === 0 && !search && !statusFilter) {
    return (
      <EmptyState
        icon="📦"
        title="No products yet"
        description="Add your first product to get started."
        action={role !== 'staff' ? { label: '+ Add Product', to: '/products/new' } : null}
      />
    );
  }

  const columns = [
    {
      key: 'name',
      label: 'Product',
      render: (row) => (
        <div>
          <div className="product-name">{row.name}</div>
          <div className="product-sku text-muted">{row.sku}</div>
        </div>
      ),
    },
    {
      key: 'category',
      label: 'Category',
      render: (row) => row.categories?.name || '-',
    },
    {
      key: 'price',
      label: 'Price',
      align: 'right',
      render: (row) => `€${Number(row.price).toFixed(2)}`,
    },
    {
      key: 'stock_quantity',
      label: 'Stock',
      render: (row) => (
        <StockBar
          quantity={row.stock_quantity}
          threshold={row.reorder_threshold}
        />
      ),
    },
    {
      key: 'status',
      label: 'Status',
      render: (row) => (
        <Badge variant={row.status === 'active' ? 'success' : 'neutral'}>
          {row.status}
        </Badge>
      ),
    },
  ];

  return (
    <div>
      <div className="page-header">
        <div>
          <h1 className="page-title">Products</h1>
          <p className="page-subtitle">{products.length} products</p>
        </div>
        {role !== 'staff' && (
          <Link to="/products/new" className="btn btn-primary">
            + Add Product
          </Link>
        )}
      </div>

      <div className="filters-bar">
        <input
          type="search"
          className="form-input search-input"
          placeholder="Search products..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        <select
          className="form-input"
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
        >
          <option value="">All Status</option>
          <option value="active">Active</option>
          <option value="archived">Archived</option>
        </select>
      </div>

      <DataTable
        columns={columns}
        data={products}
        onRowClick={(row) => navigate(`/products/${row.id}`)}
      />
    </div>
  );
}
