import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { api } from '../lib/api';
import StatCard from '../components/StatCard';
import Badge from '../components/Badge';
import StockBar from '../components/StockBar';
import LoadingSkeleton from '../components/LoadingSkeleton';

export default function Dashboard() {
  const { user } = useAuth();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadDashboard();
  }, []);

  async function loadDashboard() {
    try {
      const result = await api.get('/api/dashboard.php');
      setData(result);
    } catch (err) {
      console.error('Failed to load dashboard:', err);
    } finally {
      setLoading(false);
    }
  }

  if (loading) return <LoadingSkeleton rows={8} />;

  const stats = data?.stats || {};
  const recentOrders = data?.recent_orders || [];
  const lowStockProducts = data?.low_stock_products || [];

  const statusVariant = {
    draft: 'neutral',
    confirmed: 'info',
    fulfilled: 'success',
    cancelled: 'danger',
  };

  return (
    <div>
      <div className="page-header">
        <div>
          <h1 className="page-title">Dashboard</h1>
          <p className="page-subtitle">Welcome back, {user?.name}</p>
        </div>
      </div>

      <div className="stats-grid">
        <StatCard
          icon="📦"
          label="Total Products"
          value={stats.total_products || 0}
        />
        <StatCard
          icon="🛒"
          label="Total Orders"
          value={stats.total_orders || 0}
        />
        <StatCard
          icon="⚠️"
          label="Low Stock"
          value={stats.low_stock || 0}
          changeType="warn"
        />
        <StatCard
          icon="💰"
          label="Total Revenue"
          value={`€${(stats.total_revenue || 0).toFixed(2)}`}
        />
      </div>

      <div className="dashboard-grid">
        <div className="card">
          <div className="card-header">
            <h3>Recent Orders</h3>
            <Link to="/orders" className="btn btn-ghost btn-sm">View All</Link>
          </div>
          <div className="card-body">
            {recentOrders.length === 0 ? (
              <p className="text-muted">No orders yet</p>
            ) : (
              <table className="data-table">
                <thead>
                  <tr>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  {recentOrders.map((order) => (
                    <tr key={order.id}>
                      <td>
                        <Link to={`/orders/${order.id}`}>{order.customer_name}</Link>
                      </td>
                      <td>€{Number(order.total_amount).toFixed(2)}</td>
                      <td>
                        <Badge variant={statusVariant[order.status]}>
                          {order.status}
                        </Badge>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>

        <div className="card">
          <div className="card-header">
            <h3>Low Stock Alerts</h3>
            <Link to="/inventory" className="btn btn-ghost btn-sm">View All</Link>
          </div>
          <div className="card-body">
            {lowStockProducts.length === 0 ? (
              <p className="text-muted">All products are well stocked</p>
            ) : (
              <div className="low-stock-list">
                {lowStockProducts.map((product) => (
                  <div key={product.id} className="low-stock-item">
                    <div className="low-stock-info">
                      <Link to={`/products/${product.id}`}>{product.name}</Link>
                      <span className="text-muted">{product.sku}</span>
                    </div>
                    <StockBar
                      quantity={product.stock_quantity}
                      threshold={product.reorder_threshold}
                    />
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
