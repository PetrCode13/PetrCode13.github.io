-- ScriptCMS 1.4.00 JavaS schema for Supabase (PostgreSQL).
-- Run in Supabase SQL Editor. Review policies before production use.
create extension if not exists pgcrypto;

create table if not exists public.scms_profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  email text,
  display_name text not null default '',
  role text not null default 'editor' check (role in ('admin','editor')),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create table if not exists public.scms_pages (
  id uuid primary key default gen_random_uuid(),
  slug text not null unique check (slug ~ '^[a-z0-9-]+$'),
  title text not null,
  content text not null default '',
  published boolean not null default false,
  sort_order integer not null default 100,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  updated_by uuid references public.scms_profiles(id) on delete set null
);
create table if not exists public.scms_settings (
  key text primary key,
  value jsonb not null default 'null'::jsonb,
  updated_at timestamptz not null default now(),
  updated_by uuid references public.scms_profiles(id) on delete set null
);
create table if not exists public.scms_audit_log (
  id bigint generated always as identity primary key,
  actor uuid references public.scms_profiles(id) on delete set null,
  action text not null,
  target text,
  details jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now()
);

create or replace function public.scms_is_admin()
returns boolean language sql stable security definer set search_path = public
as $$ select exists(select 1 from public.scms_profiles p where p.id = auth.uid() and p.role = 'admin'); $$;

create or replace function public.scms_new_user_profile()
returns trigger language plpgsql security definer set search_path = public
as $$ begin
  insert into public.scms_profiles(id,email,display_name,role)
  values(new.id,new.email,coalesce(new.raw_user_meta_data->>'display_name',''),'editor')
  on conflict(id) do update set email=excluded.email;
  return new;
end; $$;
drop trigger if exists scms_auth_user_created on auth.users;
create trigger scms_auth_user_created after insert on auth.users
for each row execute procedure public.scms_new_user_profile();

alter table public.scms_profiles enable row level security;
alter table public.scms_pages enable row level security;
alter table public.scms_settings enable row level security;
alter table public.scms_audit_log enable row level security;

drop policy if exists "profiles read own or admin" on public.scms_profiles;
create policy "profiles read own or admin" on public.scms_profiles for select to authenticated using (id=auth.uid() or public.scms_is_admin());
drop policy if exists "admin updates profiles" on public.scms_profiles;
create policy "admin updates profiles" on public.scms_profiles for update to authenticated using (public.scms_is_admin()) with check (public.scms_is_admin());
-- Do not permit client-side self-promotion: there is intentionally no INSERT/DELETE policy for profiles.

drop policy if exists "public reads published pages" on public.scms_pages;
create policy "public reads published pages" on public.scms_pages for select to anon, authenticated using (published=true or auth.uid() is not null);
drop policy if exists "authenticated editors insert pages" on public.scms_pages;
create policy "authenticated editors insert pages" on public.scms_pages for insert to authenticated with check (exists(select 1 from public.scms_profiles p where p.id=auth.uid() and p.role in ('admin','editor')));
drop policy if exists "authenticated editors update pages" on public.scms_pages;
create policy "authenticated editors update pages" on public.scms_pages for update to authenticated using (exists(select 1 from public.scms_profiles p where p.id=auth.uid() and p.role in ('admin','editor'))) with check (exists(select 1 from public.scms_profiles p where p.id=auth.uid() and p.role in ('admin','editor')));
drop policy if exists "admins delete pages" on public.scms_pages;
create policy "admins delete pages" on public.scms_pages for delete to authenticated using (public.scms_is_admin());

drop policy if exists "public reads selected settings" on public.scms_settings;
create policy "public reads selected settings" on public.scms_settings for select to anon, authenticated using (key in ('site_title','site_description','theme') or auth.uid() is not null);
drop policy if exists "admins manage settings" on public.scms_settings;
create policy "admins manage settings" on public.scms_settings for all to authenticated using (public.scms_is_admin()) with check (public.scms_is_admin());
drop policy if exists "admins read audit" on public.scms_audit_log;
create policy "admins read audit" on public.scms_audit_log for select to authenticated using (public.scms_is_admin());

insert into public.scms_pages(slug,title,content,published,sort_order)
values ('index','Vítej na svém webu',E'# Vítej na svém webu\n\nToto je úvodní stránka ScriptCMS 1.4.00 JavaS. Otevři administraci a uprav její obsah.',true,0),
       ('404','Stránka nenalezena',E'# Stránka nenalezena\n\nPožadovaná stránka neexistuje.',true,999)
on conflict(slug) do nothing;
insert into public.scms_settings(key,value) values ('site_title','"ScriptCMS"'::jsonb),('site_description','"Web vytvořený pomocí ScriptCMS"'::jsonb),('theme','"system"'::jsonb) on conflict(key) do nothing;

-- FIRST ADMIN: after registering the first account, run this once in SQL Editor,
-- replacing the email with the exact account email. Do not expose SQL Editor credentials.
-- update public.scms_profiles set role='admin' where lower(email)=lower('admin@example.com');
