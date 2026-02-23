import { useAuth } from '../context/AuthContext';

export default function Header() {
  const { user, role, signOut } = useAuth();

  return (
    <header className="header">
      <div className="header-left">
        <span className="app-title">StockFlow</span>
        <span className="role-badge" style={{
          marginLeft: '12px',
          padding: '4px 8px',
          borderRadius: '4px',
          fontSize: '12px',
          textTransform: 'capitalize',
          backgroundColor: role === 'admin' ? '#dcfce7' : role === 'manager' ? '#dbeafe' : '#f3f4f6',
          color: role === 'admin' ? '#166534' : role === 'manager' ? '#1e40af' : '#374151',
        }}>
          {role}
        </span>
      </div>

      <div className="header-right">
        <div className="user-menu">
          {user?.avatar && (
            <img src={user.avatar} alt="" className="user-avatar" />
          )}
          <span className="user-name">{user?.name}</span>
          <button onClick={signOut} className="btn btn-ghost btn-sm">
            Sign Out
          </button>
        </div>
      </div>
    </header>
  );
}
