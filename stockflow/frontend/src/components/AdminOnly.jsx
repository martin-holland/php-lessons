import { useAuth } from '../context/AuthContext';

export default function AdminOnly({ children }) {
  const { role } = useAuth();

  if (role === 'staff') {
    return null;
  }

  return children;
}
