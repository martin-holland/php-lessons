import { useAuth } from '../context/AuthContext';

export default function Settings() {
  const { role, user } = useAuth();

  return (
    <div>
      <div className="page-header">
        <div>
          <h1 className="page-title">Settings</h1>
          <p className="page-subtitle">Application settings and your account</p>
        </div>
      </div>

      <div className="settings-grid">
        <div className="card">
          <div className="card-header">
            <h3>Your Account</h3>
          </div>
          <div className="card-body">
            <dl className="detail-list">
              <div className="detail-row">
                <dt>Email</dt>
                <dd>{user?.email}</dd>
              </div>
              <div className="detail-row">
                <dt>Name</dt>
                <dd>{user?.name}</dd>
              </div>
              <div className="detail-row">
                <dt>Your Role</dt>
                <dd style={{ textTransform: 'capitalize' }}>{role}</dd>
              </div>
            </dl>
          </div>
        </div>

        <div className="card">
          <div className="card-header">
            <h3>Role Permissions</h3>
          </div>
          <div className="card-body">
            <dl className="detail-list">
              <div className="detail-row">
                <dt>Admin</dt>
                <dd>Full access: manage products, orders, stock, and team members</dd>
              </div>
              <div className="detail-row">
                <dt>Manager</dt>
                <dd>Can manage products, orders, and stock movements</dd>
              </div>
              <div className="detail-row">
                <dt>Staff</dt>
                <dd>Can view products, create orders, and record stock movements</dd>
              </div>
            </dl>
          </div>
        </div>
      </div>
    </div>
  );
}
