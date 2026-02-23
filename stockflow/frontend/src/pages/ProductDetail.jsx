import { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../components/Toast';
import { api } from '../lib/api';
import Badge from '../components/Badge';
import StockBar from '../components/StockBar';
import Modal from '../components/Modal';
import LoadingSkeleton from '../components/LoadingSkeleton';

export default function ProductDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { role } = useAuth();
  const toast = useToast();

  const [product, setProduct] = useState(null);
  const [loading, setLoading] = useState(true);
  const [showStockModal, setShowStockModal] = useState(false);
  const [stockForm, setStockForm] = useState({
    movement_type: 'in',
    quantity: '',
    reason: '',
    notes: '',
  });
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    loadProduct();
  }, [id]);

  async function loadProduct() {
    try {
      const data = await api.get('/api/product.php', { id });
      setProduct(data);
    } catch (err) {
      toast.error('Failed to load product');
      navigate('/products');
    } finally {
      setLoading(false);
    }
  }

  async function handleDelete() {
    if (!confirm('Are you sure you want to delete this product?')) return;

    try {
      await api.del('/api/product.php', { id });
      toast.success('Product deleted');
      navigate('/products');
    } catch (err) {
      toast.error(err.message);
    }
  }

  async function handleStockMovement(e) {
    e.preventDefault();
    if (!stockForm.quantity) return;

    setSubmitting(true);
    try {
      await api.post('/api/stock-movement.php', {
        product_id: id,
        ...stockForm,
        quantity: parseInt(stockForm.quantity),
      });
      toast.success('Stock updated');
      setShowStockModal(false);
      setStockForm({ movement_type: 'in', quantity: '', reason: '', notes: '' });
      loadProduct();
    } catch (err) {
      toast.error(err.message);
    } finally {
      setSubmitting(false);
    }
  }

  if (loading) return <LoadingSkeleton rows={6} />;
  if (!product) return null;

  return (
    <div>
      <div className="page-header">
        <div>
          <Link to="/products" className="breadcrumb-link">Products</Link>
          <h1 className="page-title">{product.name}</h1>
          <p className="page-subtitle">{product.sku}</p>
        </div>
        <div className="page-actions">
          <button className="btn btn-secondary" onClick={() => setShowStockModal(true)}>
            Adjust Stock
          </button>
          {role !== 'staff' && (
            <>
              <Link to={`/products/${id}/edit`} className="btn btn-primary">
                Edit
              </Link>
              {role === 'admin' && (
                <button className="btn btn-danger" onClick={handleDelete}>
                  Delete
                </button>
              )}
            </>
          )}
        </div>
      </div>

      <div className="detail-grid">
        <div className="card">
          <div className="card-header">
            <h3>Product Details</h3>
          </div>
          <div className="card-body">
            <dl className="detail-list">
              <div className="detail-row">
                <dt>Name</dt>
                <dd>{product.name}</dd>
              </div>
              <div className="detail-row">
                <dt>SKU</dt>
                <dd>{product.sku}</dd>
              </div>
              <div className="detail-row">
                <dt>Category</dt>
                <dd>{product.categories?.name || '-'}</dd>
              </div>
              <div className="detail-row">
                <dt>Price</dt>
                <dd>€{Number(product.price).toFixed(2)}</dd>
              </div>
              <div className="detail-row">
                <dt>Status</dt>
                <dd>
                  <Badge variant={product.status === 'active' ? 'success' : 'neutral'}>
                    {product.status}
                  </Badge>
                </dd>
              </div>
              <div className="detail-row">
                <dt>Supplier</dt>
                <dd>{product.supplier || '-'}</dd>
              </div>
              <div className="detail-row">
                <dt>Description</dt>
                <dd>{product.description || '-'}</dd>
              </div>
            </dl>
          </div>
        </div>

        <div className="card">
          <div className="card-header">
            <h3>Stock Information</h3>
          </div>
          <div className="card-body">
            <div className="stock-detail">
              <div className="stock-quantity">
                <span className="stock-number">{product.stock_quantity}</span>
                <span className="stock-label">units in stock</span>
              </div>
              <StockBar
                quantity={product.stock_quantity}
                threshold={product.reorder_threshold}
              />
              <p className="text-muted">
                Reorder threshold: {product.reorder_threshold} units
              </p>
            </div>
          </div>
        </div>
      </div>

      <Modal
        open={showStockModal}
        onClose={() => setShowStockModal(false)}
        title="Record Stock Movement"
      >
        <form onSubmit={handleStockMovement}>
          <div className="form-group">
            <label className="form-label">Movement Type</label>
            <select
              className="form-input"
              value={stockForm.movement_type}
              onChange={(e) => setStockForm({ ...stockForm, movement_type: e.target.value })}
            >
              <option value="in">Stock In</option>
              <option value="out">Stock Out</option>
              <option value="adjustment">Adjustment</option>
            </select>
          </div>

          <div className="form-group">
            <label className="form-label">Quantity</label>
            <input
              type="number"
              className="form-input"
              value={stockForm.quantity}
              onChange={(e) => setStockForm({ ...stockForm, quantity: e.target.value })}
              min="1"
              required
            />
          </div>

          <div className="form-group">
            <label className="form-label">Reason</label>
            <select
              className="form-input"
              value={stockForm.reason}
              onChange={(e) => setStockForm({ ...stockForm, reason: e.target.value })}
            >
              <option value="">Select reason...</option>
              <option value="delivery">Delivery</option>
              <option value="sale">Sale</option>
              <option value="return">Return</option>
              <option value="damage">Damage</option>
              <option value="correction">Correction</option>
            </select>
          </div>

          <div className="form-group">
            <label className="form-label">Notes</label>
            <textarea
              className="form-input"
              value={stockForm.notes}
              onChange={(e) => setStockForm({ ...stockForm, notes: e.target.value })}
              rows={3}
            />
          </div>

          <div className="modal-actions">
            <button type="button" className="btn btn-ghost" onClick={() => setShowStockModal(false)}>
              Cancel
            </button>
            <button type="submit" className="btn btn-primary" disabled={submitting}>
              {submitting ? 'Saving...' : 'Record Movement'}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
