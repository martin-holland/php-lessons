import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../lib/api';
import DataTable from '../components/DataTable';
import Badge from '../components/Badge';
import StockBar from '../components/StockBar';
import StatCard from '../components/StatCard';
import LoadingSkeleton from '../components/LoadingSkeleton';

export default function Inventory() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all');

  useEffect(() => {
    loadInventory();
  }, []);

  async function loadInventory() {
    try {
      const result = await api.get('/api/inventory.php');
      setData(result);
    } catch (err) {
      console.error('Failed to load inventory:', err);
    } finally {
      setLoading(false);
    }
  }

  if (loading) return <LoadingSkeleton rows={8} />;

  const products = data?.products || [];
  const summary = data?.summary || {};

  const filteredProducts = filter === 'all'
    ? products
    : products.filter((p) => p.stock_status === filter);

  const columns = [
    {
      key: 'name',
      label: 'Product',
      render: (row) => (
        <div>
          <Link to={`/products/${row.id}`} className="product-name">{row.name}</Link>
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
      key: 'stock_quantity',
      label: 'Stock Level',
      render: (row) => (
        <StockBar
          quantity={row.stock_quantity}
          threshold={row.reorder_threshold}
        />
      ),
    },
    {
      key: 'stock_status',
      label: 'Status',
      render: (row) => {
        const variants = {
          in_stock: 'success',
          low_stock: 'warning',
          out_of_stock: 'danger',
        };
        const labels = {
          in_stock: 'In Stock',
          low_stock: 'Low Stock',
          out_of_stock: 'Out of Stock',
        };
        return (
          <Badge variant={variants[row.stock_status]}>
            {labels[row.stock_status]}
          </Badge>
        );
      },
    },
    {
      key: 'reorder_threshold',
      label: 'Reorder At',
      align: 'right',
      render: (row) => row.reorder_threshold,
    },
  ];

  return (
    <div>
      <div className="page-header">
        <div>
          <h1 className="page-title">Inventory</h1>
          <p className="page-subtitle">Stock overview and management</p>
        </div>
      </div>

      <div className="stats-grid">
        <StatCard
          icon="📦"
          label="Total Products"
          value={summary.total_products || 0}
        />
        <StatCard
          icon="✅"
          label="In Stock"
          value={summary.in_stock || 0}
          changeType="up"
        />
        <StatCard
          icon="⚠️"
          label="Low Stock"
          value={summary.low_stock || 0}
          changeType="warn"
        />
        <StatCard
          icon="❌"
          label="Out of Stock"
          value={summary.out_of_stock || 0}
          changeType="down"
        />
      </div>

      <div className="filters-bar">
        <select
          className="form-input"
          value={filter}
          onChange={(e) => setFilter(e.target.value)}
        >
          <option value="all">All Products</option>
          <option value="in_stock">In Stock</option>
          <option value="low_stock">Low Stock</option>
          <option value="out_of_stock">Out of Stock</option>
        </select>
      </div>

      <DataTable
        columns={columns}
        data={filteredProducts}
      />
    </div>
  );
}
