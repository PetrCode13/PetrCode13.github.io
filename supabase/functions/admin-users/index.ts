// Deploy with Supabase CLI. Requires SUPABASE_URL, SUPABASE_ANON_KEY and SUPABASE_SERVICE_ROLE_KEY secrets.
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2.57.0';
const cors = { 'Access-Control-Allow-Origin': '*', 'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type', 'Access-Control-Allow-Methods': 'POST, OPTIONS', 'Content-Type': 'application/json' };
const reply = (body: unknown, status=200) => new Response(JSON.stringify(body), { status, headers: cors });
Deno.serve(async (req) => {
  if (req.method === 'OPTIONS') return new Response('ok', { headers: cors });
  if (req.method !== 'POST') return reply({ error: 'Method not allowed' }, 405);
  const url = Deno.env.get('SUPABASE_URL')!;
  const anonKey = Deno.env.get('SUPABASE_ANON_KEY')!;
  const serviceKey = Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!;
  const authHeader = req.headers.get('Authorization');
  if (!authHeader?.startsWith('Bearer ')) return reply({ error: 'Authentication required' }, 401);
  const authClient = createClient(url, anonKey, { global: { headers: { Authorization: authHeader } }, auth: { persistSession: false } });
  const { data: { user }, error: authError } = await authClient.auth.getUser();
  if (authError || !user) return reply({ error: 'Invalid session' }, 401);
  const adminClient = createClient(url, serviceKey, { auth: { persistSession: false, autoRefreshToken: false } });
  const { data: profile, error: profileError } = await adminClient.from('scms_profiles').select('role').eq('id', user.id).single();
  if (profileError || profile?.role !== 'admin') return reply({ error: 'Administrator role required' }, 403);
  let input: Record<string, unknown>;
  try { input = await req.json(); } catch { return reply({ error: 'Invalid JSON' }, 400); }
  const action = input.action;
  if (action === 'list') {
    const { data, error } = await adminClient.auth.admin.listUsers({ page: 1, perPage: 200 });
    if (error) return reply({ error: error.message }, 400);
    const ids = (data.users || []).map(u => u.id);
    const { data: profiles } = await adminClient.from('scms_profiles').select('id,role,display_name').in('id', ids);
    const byId = new Map((profiles || []).map(p => [p.id, p]));
    return reply({ users: (data.users || []).map(u => ({ id: u.id, email: u.email, email_confirmed_at: u.email_confirmed_at, role: byId.get(u.id)?.role || 'editor', display_name: byId.get(u.id)?.display_name || '' })) });
  }
  if (action === 'create') {
    const email = String(input.email || '').trim().toLowerCase(); const password = String(input.password || ''); const role = input.role;
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return reply({ error: 'Valid email required' }, 400);
    if (password.length < 12) return reply({ error: 'Password must contain at least 12 characters' }, 400);
    if (role !== 'admin' && role !== 'editor') return reply({ error: 'Invalid role' }, 400);
    const { data, error } = await adminClient.auth.admin.createUser({ email, password, email_confirm: false, user_metadata: { display_name: String(input.displayName || '') } });
    if (error) return reply({ error: error.message }, 400);
    if (data.user && role === 'admin') { const { error: roleError } = await adminClient.from('scms_profiles').update({ role }).eq('id', data.user.id); if (roleError) return reply({ error: 'User created, but role update failed: ' + roleError.message }, 500); }
    await adminClient.from('scms_audit_log').insert({ actor: user.id, action: 'user.create', target: data.user?.id, details: { email, role } });
    return reply({ user: { id: data.user?.id, email: data.user?.email, role } }, 201);
  }
  if (action === 'role') {
    const userId = String(input.userId || ''); const role = input.role;
    if (!userId || !['admin','editor'].includes(String(role))) return reply({ error: 'Invalid user or role' }, 400);
    if (userId === user.id && role !== 'admin') return reply({ error: 'You cannot remove your own administrator role' }, 400);
    const { error } = await adminClient.from('scms_profiles').update({ role }).eq('id', userId);
    if (error) return reply({ error: error.message }, 400);
    await adminClient.from('scms_audit_log').insert({ actor: user.id, action: 'user.role', target: userId, details: { role } });
    return reply({ ok: true });
  }
  if (action === 'delete') {
    const userId = String(input.userId || '');
    if (!userId) return reply({ error: 'userId required' }, 400);
    if (userId === user.id) return reply({ error: 'You cannot delete your own account while signed in' }, 400);
    const { error } = await adminClient.auth.admin.deleteUser(userId);
    if (error) return reply({ error: error.message }, 400);
    await adminClient.from('scms_audit_log').insert({ actor: user.id, action: 'user.delete', target: userId });
    return reply({ ok: true });
  }
  return reply({ error: 'Unknown action' }, 400);
});
