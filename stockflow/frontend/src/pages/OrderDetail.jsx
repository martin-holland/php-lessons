import { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../components/Toast';
import { api } from '../lib/api';
import Badge from '../components/Badge';
import StatusFlow from '../components/StatusFlow';
import LoadingSkeleton from '../components/LoadingSkeleton';

export default function OrderDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { role } = useAuth();
  const toast = useToast();

  const [order, setOrder] = useState(null);
  const [loading, setLoading] = useState(true);
  const [updating, setUpdating] = useState(false);

  useEffect(() => {
    loadOrder();
  }, [id]);

  async function loadOrder() {
    try {
      const data = await api.get('/api/order.php', { id });
      setOrder(data);
    } catch (err) {
      toast.error('Failed to load order');
      navigate('/orders');
    } finally {
      setLoading(false);
    }
  }

  async function updateStatus(newStatus) {
    setUpdating(true);
    try {
      await api.put('/api/order.php', { status: newStatus }, { id });
      toast.success('Order status updated');
      loadOrder();
    } catch (err) {
      toast.error(err.message);
    } finally {
      setUpdating(false);
    }
  }

  if (loading) return <LoadingSkeleton rows={8} />;
  if (!order) return null;

  const statusVariant = {
    draft: 'neutral',
    confirmed: 'info',
    fulfilled: 'success',
    cancelled: 'danger',
  };

  const statusSteps = [
    { label: 'Draft' },
    { label: 'Confirmed' },
    { label: 'Fulfilled' },
  ];

  const currentStep = {
    draft: 0,
    confirmed: 1,
    fulfilled: 2,
    cancelled: -1,
  }[order.status];

  return (
    <div>
      <div className="page-header">
        <div>
          <Link to="/orders" className="breadcrumb-link">Orders</Link>
          <h1 className="page-title">{order.customer_name}</h1>
          <p className="page-subtitle">Order from {new Date(order.created_at).toLocaleDateString()}</p>
        </div>
        <div className="page-actions">
          {order.status !== 'cancelled' && order.status !== 'fulfilled' && (
            <>
              {order.status === 'draft' && (
                <button
                  className="btn btn-primary"
                  onClick={() => updateStatus('confirmed')}
                  disabled={updating}
                >
                  Confirm Order
                </button>
              )}
              {order.status === 'confirmed' && role !== 'staff' && (
                <button
                  className="btn btn-success"
                  onClick={() => updateStatus('fulfilled')}
                  disabled={updating}
                >
                  Mark Fulfilled
                </button>
              )}
              <button
                className="btn btn-danger"
                onClick={() => updateStatus('cancelled')}
                disabled={updating}
              >
                Cancel Order
              </button>
            </>
          )}
        </div>
      </div>

      {order.status !== 'cancelled' && (
        <div className="card" style={{ marginBottom: 24 }}>
          <div className="card-body">
            <StatusFlow steps={statusSteps} currentStep={currentStep} />
          </div>
        </div>
      )}

      <div className="detail-grid">
        <div className="card">
          <div className="card-header">
            <h3>Order Details</h3>
          </div>
          <div className="card-body">
            <dl className="detail-list">
              <div className="detail-row">
                <dt>Customer</dt>
                <dd>{order.customer_name}</dd>
              </div>
              <div className="detail-row">
                <dt>Status</dt>
                <dd>
                  <Badge variant={statusVariant[order.status]}>
                    {order.status}
                  </Badge>
                </dd>
              </div>
              <div className="detail-row">
                <dt>Total</dt>
                <dd className="order-total">€{Number(order.total_amount).toFixed(2)}</dd>
              </div>
              <div className="detail-row">
                <dt>Created</dt>
                <dd>{new Date(order.created_at).toLocaleString()}</dd>
              </div>
              {order.notes && (
                <div className="detail-row">
                  <dt>Notes</dt>
                  <dd>{order.notes}</dd>
                </div>
              )}
            </dl>
          </div>
        </div>

        <div className="card">
          <div className="card-header">
            <h3>Line Items</h3>
          </div>
          <div className="card-body">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Product</th>
                  <th style={{ textAlign: 'right' }}>Qty</th>
                  <th style={{ textAlign: 'right' }}>Unit Price</th>
                  <th style={{ textAlign: 'right' }}>Total</th>
                </tr>
              </thead>
              <tbody>
                {(order.items || []).map((item, i) => (
                  <tr key={item.id || i}>
                    <td>{item.product_name}</td>
                    <td style={{ textAlign: 'right' }}>{item.quantity}</td>
                    <td style={{ textAlign: 'right' }}>€{Number(item.unit_price).toFixed(2)}</td>
                    <td style={{ textAlign: 'right' }}>€{Number(item.line_total).toFixed(2)}</td>
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr>
                  <td colSpan={3} style={{ textAlign: 'right', fontWeight: 600 }}>Order Total</td>
                  <td style={{ textAlign: 'right', fontWeight: 600 }}>€{Number(order.total_amount).toFixed(2)}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}
