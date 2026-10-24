// Official Supabase JS client is loaded from the official ESM CDN. Configure the project in /assets/config.js.
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2.57.0';
import { CONFIG } from './config.js';
export const configured = Boolean(CONFIG.url && CONFIG.anonKey && !CONFIG.url.includes('YOUR_PROJECT'));
export const supabase = configured ? createClient(CONFIG.url, CONFIG.anonKey, { auth: { persistSession: true, autoRefreshToken: true, detectSessionInUrl: true } }) : null;
