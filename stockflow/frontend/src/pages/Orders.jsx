import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api } from '../lib/api';
import DataTable from '../components/DataTable';
import Badge from '../components/Badge';
import EmptyState from '../components/EmptyState';
import LoadingSkeleton from '../components/LoadingSkeleton';

export default function Orders() {
  const navigate = useNavigate();
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState('');

  useEffect(() => {
    loadOrders();
  }, [statusFilter]);

  async function loadOrders() {
    try {
      const params = {};
      if (statusFilter) params.status = statusFilter;
      const data = await api.get('/api/orders.php', params);
      setOrders(data);
    } catch (err) {
      console.error('Failed to load orders:', err);
    } finally {
      setLoading(false);
    }
  }

  if (loading) return <LoadingSkeleton rows={6} />;

  if (orders.length === 0 && !statusFilter) {
    return (
      <EmptyState
        icon="🛒"
        title="No orders yet"
        description="Create your first order to get started."
        action={{ label: '+ Create Order', to: '/orders/new' }}
      />
    );
  }

  const statusVariant = {
    draft: 'neutral',
    confirmed: 'info',
    fulfilled: 'success',
    cancelled: 'danger',
  };

  const columns = [
    {
      key: 'customer_name',
      label: 'Customer',
      render: (row) => (
        <Link to={`/orders/${row.id}`} className="order-customer">
          {row.customer_name}
        </Link>
      ),
    },
    {
      key: 'total_amount',
      label: 'Total',
      align: 'right',
      render: (row) => `€${Number(row.total_amount).toFixed(2)}`,
    },
    {
      key: 'status',
      label: 'Status',
      render: (row) => (
        <Badge variant={statusVariant[row.status]}>
          {row.status}
        </Badge>
      ),
    },
    {
      key: 'created_at',
      label: 'Date',
      render: (row) => new Date(row.created_at).toLocaleDateString(),
    },
  ];

  return (
    <div>
      <div className="page-header">
        <div>
          <h1 className="page-title">Orders</h1>
          <p className="page-subtitle">{orders.length} orders</p>
        </div>
        <Link to="/orders/new" className="btn btn-primary">
          + Create Order
        </Link>
      </div>

      <div className="filters-bar">
        <select
          className="form-input"
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
        >
          <option value="">All Status</option>
          <option value="draft">Draft</option>
          <option value="confirmed">Confirmed</option>
          <option value="fulfilled">Fulfilled</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      <DataTable
        columns={columns}
        data={orders}
        onRowClick={(row) => navigate(`/orders/${row.id}`)}
      />
    </div>
  );
}
