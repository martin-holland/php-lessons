import { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useToast } from '../components/Toast';
import { api } from '../lib/api';
import Badge from '../components/Badge';
import LoadingSkeleton from '../components/LoadingSkeleton';

export default function OrderCreate() {
  const navigate = useNavigate();
  const toast = useToast();

  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [products, setProducts] = useState([]);
  const [customerName, setCustomerName] = useState('');
  const [notes, setNotes] = useState('');
  const [items, setItems] = useState([]);
  const [searchTerm, setSearchTerm] = useState('');

  useEffect(() => {
    loadProducts();
  }, []);

  async function loadProducts() {
    try {
      const data = await api.get('/api/products.php', { status: 'active' });
      setProducts(data);
    } catch (err) {
      toast.error('Failed to load products');
    } finally {
      setLoading(false);
    }
  }

  function addItem(product) {
    const existing = items.find((i) => i.product_id === product.id);
    if (existing) {
      setItems(items.map((i) =>
        i.product_id === product.id
          ? { ...i, quantity: i.quantity + 1 }
          : i
      ));
    } else {
      setItems([...items, {
        product_id: product.id,
        product_name: product.name,
        unit_price: parseFloat(product.price),
        quantity: 1,
      }]);
    }
    setSearchTerm('');
  }

  function updateQuantity(productId, quantity) {
    if (quantity < 1) {
      setItems(items.filter((i) => i.product_id !== productId));
    } else {
      setItems(items.map((i) =>
        i.product_id === productId
          ? { ...i, quantity }
          : i
      ));
    }
  }

  function removeItem(productId) {
    setItems(items.filter((i) => i.product_id !== productId));
  }

  const total = items.reduce((sum, item) => sum + item.unit_price * item.quantity, 0);

  async function handleSubmit(status = 'draft') {
    if (!customerName.trim()) {
      toast.error('Customer name is required');
      return;
    }
    if (items.length === 0) {
      toast.error('Add at least one product');
      return;
    }

    setSubmitting(true);
    try {
      await api.post('/api/orders.php', {
        customer_name: customerName,
        notes,
        status,
        items,
      });
      toast.success('Order created');
      navigate('/orders');
    } catch (err) {
      toast.error(err.message);
    } finally {
      setSubmitting(false);
    }
  }

  const filteredProducts = searchTerm
    ? products.filter((p) =>
        p.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        p.sku.toLowerCase().includes(searchTerm.toLowerCase())
      )
    : [];

  if (loading) return <LoadingSkeleton rows={8} />;

  return (
    <div>
      <div className="page-header">
        <div>
          <Link to="/orders" className="breadcrumb-link">Orders</Link>
          <h1 className="page-title">Create Order</h1>
        </div>
      </div>

      <div className="order-create-grid">
        <div className="card">
          <div className="card-header">
            <h3>Customer & Products</h3>
          </div>
          <div className="card-body">
            <div className="form-group">
              <label className="form-label">Customer Name *</label>
              <input
                type="text"
                className="form-input"
                value={customerName}
                onChange={(e) => setCustomerName(e.target.value)}
                placeholder="e.g., Verkkokauppa.com"
              />
            </div>

            <div className="form-group">
              <label className="form-label">Add Products</label>
              <div className="product-search-container">
                <input
                  type="text"
                  className="form-input"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  placeholder="Search products by name or SKU..."
                />
                {filteredProducts.length > 0 && (
                  <div className="product-search-dropdown">
                    {filteredProducts.slice(0, 5).map((product) => (
                      <button
                        key={product.id}
                        type="button"
                        className="product-search-item"
                        onClick={() => addItem(product)}
                      >
                        <div>
                          <div>{product.name}</div>
                          <div className="text-muted">{product.sku}</div>
                        </div>
                        <div>€{Number(product.price).toFixed(2)}</div>
                      </button>
                    ))}
                  </div>
                )}
              </div>
            </div>

            <div className="form-group">
              <label className="form-label">Notes</label>
              <textarea
                className="form-input"
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                rows={2}
                placeholder="Optional order notes..."
              />
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-header">
            <h3>Order Summary</h3>
          </div>
          <div className="card-body">
            {items.length === 0 ? (
              <p className="text-muted">No products added yet</p>
            ) : (
              <>
                <div className="order-items-list">
                  {items.map((item) => (
                    <div key={item.product_id} className="order-item">
                      <div className="order-item-info">
                        <span className="order-item-name">{item.product_name}</span>
                        <span className="order-item-price">€{item.unit_price.toFixed(2)} each</span>
                      </div>
                      <div className="order-item-controls">
                        <button
                          type="button"
                          className="qty-btn"
                          onClick={() => updateQuantity(item.product_id, item.quantity - 1)}
                        >
                          -
                        </button>
                        <span className="qty-value">{item.quantity}</span>
                        <button
                          type="button"
                          className="qty-btn"
                          onClick={() => updateQuantity(item.product_id, item.quantity + 1)}
                        >
                          +
                        </button>
                        <button
                          type="button"
                          className="btn btn-ghost btn-sm"
                          onClick={() => removeItem(item.product_id)}
                        >
                          Remove
                        </button>
                      </div>
                      <div className="order-item-total">
                        €{(item.unit_price * item.quantity).toFixed(2)}
                      </div>
                    </div>
                  ))}
                </div>

                <div className="order-total-section">
                  <span>Total</span>
                  <span className="order-total-value">€{total.toFixed(2)}</span>
                </div>
              </>
            )}

            <div className="form-actions" style={{ marginTop: 24 }}>
              <Link to="/orders" className="btn btn-ghost">
                Cancel
              </Link>
              <button
                type="button"
                className="btn btn-secondary"
                onClick={() => handleSubmit('draft')}
                disabled={submitting}
              >
                Save as Draft
              </button>
              <button
                type="button"
                className="btn btn-primary"
                onClick={() => handleSubmit('confirmed')}
                disabled={submitting}
              >
                Create & Confirm
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
