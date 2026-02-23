import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { supabase } from '../lib/supabase';

const AuthContext = createContext(null);

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [session, setSession] = useState(null);
  const [role, setRole] = useState('staff');
  const [loading, setLoading] = useState(true);
  const [initialized, setInitialized] = useState(false);

  // Fetch user role from backend
  async function fetchUserRole(token) {
    try {
      const response = await fetch('/api/me.php', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });
      if (response.ok) {
        const data = await response.json();
        return data.data?.role || data.role || 'staff';
      }
    } catch (error) {
      console.error('Failed to fetch user role:', error);
    }
    return 'staff';
  }

  useEffect(() => {
    let mounted = true;

    // Get initial session
    async function initAuth() {
      try {
        const { data: { session: currentSession } } = await supabase.auth.getSession();

        if (mounted) {
          if (currentSession) {
            setSession(currentSession);
            setUser(mapUser(currentSession.user));

            // Fetch user role
            const userRole = await fetchUserRole(currentSession.access_token);
            setRole(userRole);
          } else {
            setSession(null);
            setUser(null);
            setRole('staff');
          }
          setLoading(false);
          setInitialized(true);
        }
      } catch (error) {
        console.error('Auth initialization error:', error);
        if (mounted) {
          setSession(null);
          setUser(null);
          setRole('staff');
          setLoading(false);
          setInitialized(true);
        }
      }
    }

    initAuth();

    // Listen for auth changes
    const { data: { subscription } } = supabase.auth.onAuthStateChange(
      async (event, newSession) => {
        console.log('Auth state change:', event);

        if (mounted) {
          if (newSession) {
            setSession(newSession);
            setUser(mapUser(newSession.user));

            // Fetch user role
            const userRole = await fetchUserRole(newSession.access_token);
            setRole(userRole);
          } else {
            setSession(null);
            setUser(null);
            setRole('staff');
          }
          setLoading(false);
        }
      }
    );

    return () => {
      mounted = false;
      subscription.unsubscribe();
    };
  }, []);

  function mapUser(supabaseUser) {
    if (!supabaseUser) return null;
    return {
      id: supabaseUser.id,
      email: supabaseUser.email,
      name: supabaseUser.user_metadata?.full_name || supabaseUser.email?.split('@')[0] || 'User',
      avatar: supabaseUser.user_metadata?.avatar_url || null,
    };
  }

  const signInWithGoogle = useCallback(async () => {
    const { error } = await supabase.auth.signInWithOAuth({
      provider: 'google',
      options: {
        redirectTo: window.location.origin + '/',
      },
    });
    if (error) throw error;
  }, []);

  const signOut = useCallback(async () => {
    await supabase.auth.signOut();
    setSession(null);
    setUser(null);
    setRole('staff');
  }, []);

  // Get current access token for API calls
  const getAccessToken = useCallback(() => {
    return session?.access_token || null;
  }, [session]);

  const value = {
    user,
    session,
    role,
    loading,
    initialized,
    signInWithGoogle,
    signOut,
    getAccessToken,
    isAuthenticated: !!user,
    isAdmin: role === 'admin',
    isAdminOrManager: role === 'admin' || role === 'manager',
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
}
