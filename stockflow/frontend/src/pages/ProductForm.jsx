import { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useToast } from '../components/Toast';
import { api } from '../lib/api';
import LoadingSkeleton from '../components/LoadingSkeleton';

export default function ProductForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const toast = useToast();
  const isEdit = !!id;

  const [loading, setLoading] = useState(isEdit);
  const [submitting, setSubmitting] = useState(false);
  const [categories, setCategories] = useState([]);
  const [form, setForm] = useState({
    name: '',
    sku: '',
    description: '',
    price: '',
    category_id: '',
    stock_quantity: '0',
    reorder_threshold: '10',
    supplier: '',
  });

  useEffect(() => {
    loadCategories();
    if (isEdit) {
      loadProduct();
    }
  }, [id]);

  async function loadCategories() {
    try {
      const data = await api.get('/api/categories.php');
      setCategories(data);
    } catch (err) {
      console.error('Failed to load categories:', err);
    }
  }

  async function loadProduct() {
    try {
      const data = await api.get('/api/product.php', { id });
      setForm({
        name: data.name || '',
        sku: data.sku || '',
        description: data.description || '',
        price: data.price?.toString() || '',
        category_id: data.category_id || '',
        stock_quantity: data.stock_quantity?.toString() || '0',
        reorder_threshold: data.reorder_threshold?.toString() || '10',
        supplier: data.supplier || '',
      });
    } catch (err) {
      toast.error('Failed to load product');
      navigate('/products');
    } finally {
      setLoading(false);
    }
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setSubmitting(true);

    try {
      const payload = {
        ...form,
        price: parseFloat(form.price),
        stock_quantity: parseInt(form.stock_quantity),
        reorder_threshold: parseInt(form.reorder_threshold),
        category_id: form.category_id || null,
      };

      if (isEdit) {
        await api.put('/api/product.php', payload, { id });
        toast.success('Product updated');
      } else {
        await api.post('/api/products.php', payload);
        toast.success('Product created');
      }
      navigate('/products');
    } catch (err) {
      toast.error(err.message);
    } finally {
      setSubmitting(false);
    }
  }

  if (loading) return <LoadingSkeleton rows={8} />;

  return (
    <div>
      <div className="page-header">
        <div>
          <Link to="/products" className="breadcrumb-link">Products</Link>
          <h1 className="page-title">{isEdit ? 'Edit Product' : 'New Product'}</h1>
        </div>
      </div>

      <div className="card" style={{ maxWidth: 600 }}>
        <div className="card-body">
          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label className="form-label">Product Name *</label>
              <input
                type="text"
                className="form-input"
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                required
              />
            </div>

            <div className="form-row">
              <div className="form-group">
                <label className="form-label">SKU *</label>
                <input
                  type="text"
                  className="form-input"
                  value={form.sku}
                  onChange={(e) => setForm({ ...form, sku: e.target.value })}
                  required
                />
              </div>

              <div className="form-group">
                <label className="form-label">Price *</label>
                <input
                  type="number"
                  className="form-input"
                  value={form.price}
                  onChange={(e) => setForm({ ...form, price: e.target.value })}
                  step="0.01"
                  min="0"
                  required
                />
              </div>
            </div>

            <div className="form-group">
              <label className="form-label">Category</label>
              <select
                className="form-input"
                value={form.category_id}
                onChange={(e) => setForm({ ...form, category_id: e.target.value })}
              >
                <option value="">No category</option>
                {categories.map((cat) => (
                  <option key={cat.id} value={cat.id}>{cat.name}</option>
                ))}
              </select>
            </div>

            <div className="form-group">
              <label className="form-label">Description</label>
              <textarea
                className="form-input"
                value={form.description}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
                rows={3}
              />
            </div>

            <div className="form-row">
              <div className="form-group">
                <label className="form-label">Initial Stock</label>
                <input
                  type="number"
                  className="form-input"
                  value={form.stock_quantity}
                  onChange={(e) => setForm({ ...form, stock_quantity: e.target.value })}
                  min="0"
                />
              </div>

              <div className="form-group">
                <label className="form-label">Reorder Threshold</label>
                <input
                  type="number"
                  className="form-input"
                  value={form.reorder_threshold}
                  onChange={(e) => setForm({ ...form, reorder_threshold: e.target.value })}
                  min="0"
                />
              </div>
            </div>

            <div className="form-group">
              <label className="form-label">Supplier</label>
              <input
                type="text"
                className="form-input"
                value={form.supplier}
                onChange={(e) => setForm({ ...form, supplier: e.target.value })}
              />
            </div>

            <div className="form-actions">
              <Link to="/products" className="btn btn-ghost">
                Cancel
              </Link>
              <button type="submit" className="btn btn-primary" disabled={submitting}>
                {submitting ? 'Saving...' : (isEdit ? 'Update Product' : 'Create Product')}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}
