import { supabase } from './supabase';

const BASE_URL = '';

async function getHeaders() {
  const { data: { session } } = await supabase.auth.getSession();
  const token = session?.access_token || '';

  return {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`,
  };
}

function buildUrl(path, params = {}) {
  const url = new URL(path, window.location.origin);
  Object.entries(params).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') {
      url.searchParams.set(k, v);
    }
  });
  return url.toString();
}

async function request(method, path, body = null, params = {}) {
  const headers = await getHeaders();
  const url = buildUrl(path, params);

  const options = { method, headers };
  if (body && method !== 'GET') {
    options.body = JSON.stringify(body);
  }

  const response = await fetch(url, options);
  const json = await response.json();

  if (!response.ok) {
    const errMsg = json.error || json.message || `Request failed (${response.status})`;
    throw new Error(errMsg);
  }

  return json.data !== undefined ? json.data : json;
}

export const api = {
  get:  (path, params) => request('GET', path, null, params),
  post: (path, body)   => request('POST', path, body),
  put:  (path, body, params) => request('PUT', path, body, params),
  del:  (path, params) => request('DELETE', path, null, params),
};
