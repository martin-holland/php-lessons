import { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../components/Toast';
import { api } from '../lib/api';
import DataTable from '../components/DataTable';
import Badge from '../components/Badge';
import LoadingSkeleton from '../components/LoadingSkeleton';

export default function Team() {
  const { role } = useAuth();
  const toast = useToast();

  const [members, setMembers] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadMembers();
  }, []);

  async function loadMembers() {
    try {
      const data = await api.get('/api/team.php');
      setMembers(data);
    } catch (err) {
      toast.error('Failed to load team members');
    } finally {
      setLoading(false);
    }
  }

  async function updateRole(memberId, newRole) {
    try {
      await api.put('/api/team.php', { role: newRole }, { id: memberId });
      toast.success('Role updated');
      loadMembers();
    } catch (err) {
      toast.error(err.message);
    }
  }

  async function removeMember(memberId) {
    if (!confirm('Are you sure you want to remove this user role?')) return;

    try {
      await api.del('/api/team.php', { id: memberId });
      toast.success('User role removed');
      loadMembers();
    } catch (err) {
      toast.error(err.message);
    }
  }

  if (loading) return <LoadingSkeleton rows={5} />;

  const roleVariant = {
    admin: 'danger',
    manager: 'warning',
    staff: 'info',
  };

  const columns = [
    {
      key: 'user_id',
      label: 'User ID',
      render: (row) => row.user_id?.slice(0, 8) + '...',
    },
    {
      key: 'role',
      label: 'Role',
      render: (row) => (
        role === 'admin' ? (
          <select
            className="form-input form-input-sm"
            value={row.role}
            onChange={(e) => updateRole(row.id, e.target.value)}
          >
            <option value="admin">Admin</option>
            <option value="manager">Manager</option>
            <option value="staff">Staff</option>
          </select>
        ) : (
          <Badge variant={roleVariant[row.role]}>
            {row.role}
          </Badge>
        )
      ),
    },
    {
      key: 'created_at',
      label: 'Joined',
      render: (row) => new Date(row.created_at).toLocaleDateString(),
    },
    {
      key: 'actions',
      label: '',
      sortable: false,
      render: (row) => role === 'admin' && (
        <button
          className="btn btn-danger btn-sm"
          onClick={() => removeMember(row.id)}
        >
          Remove
        </button>
      ),
    },
  ];

  return (
    <div>
      <div className="page-header">
        <div>
          <h1 className="page-title">Team</h1>
          <p className="page-subtitle">{members.length} team members</p>
        </div>
      </div>

      <div className="card" style={{ marginBottom: 24 }}>
        <div className="card-body">
          <p className="text-muted">
            User roles are managed here. New users who sign in will default to the "staff" role.
            To add a user as admin, have them sign in first, then update their role here.
          </p>
        </div>
      </div>

      <DataTable
        columns={columns}
        data={members}
      />
    </div>
  );
}
