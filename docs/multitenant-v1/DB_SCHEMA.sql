-- Multi-tenant V1 schema (Postgres)

create extension if not exists "pgcrypto";

create table tenants (
  id uuid primary key default gen_random_uuid(),
  slug text unique not null,
  name text not null,
  status text not null default 'active',
  plan text not null default 'starter',
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table users (
  id uuid primary key default gen_random_uuid(),
  email text unique not null,
  display_name text not null,
  auth_subject text unique,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table memberships (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references tenants(id) on delete cascade,
  user_id uuid not null references users(id) on delete cascade,
  role text not null,
  status text not null default 'active',
  created_at timestamptz not null default now(),
  unique (tenant_id, user_id)
);

create table projects (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references tenants(id) on delete cascade,
  name text not null,
  key text not null,
  created_by uuid references users(id),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (tenant_id, key)
);

create table tasks (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references tenants(id) on delete cascade,
  project_id uuid references projects(id) on delete set null,
  external_ref text,
  title text not null,
  description text,
  status text not null default 'pending_review',
  priority text not null default 'normal',
  queue_position integer not null default 0,
  submitter_user_id uuid references users(id),
  needs_meeting boolean not null default false,
  due_at timestamptz,
  requested_deadline timestamptz,
  focalboard_card_id text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index idx_tasks_tenant_status on tasks(tenant_id, status);
create index idx_tasks_tenant_queue on tasks(tenant_id, queue_position);

create table task_assignments (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references tenants(id) on delete cascade,
  task_id uuid not null references tasks(id) on delete cascade,
  user_id uuid not null references users(id) on delete cascade,
  role text not null default 'owner',
  created_at timestamptz not null default now(),
  unique (task_id, user_id)
);

create table task_events (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references tenants(id) on delete cascade,
  task_id uuid not null references tasks(id) on delete cascade,
  actor_user_id uuid references users(id),
  event_type text not null,
  old_value jsonb,
  new_value jsonb,
  note text,
  created_at timestamptz not null default now()
);
create index idx_task_events_tenant_task on task_events(tenant_id, task_id, created_at desc);

create table task_comments (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references tenants(id) on delete cascade,
  task_id uuid not null references tasks(id) on delete cascade,
  author_user_id uuid not null references users(id),
  body text not null,
  created_at timestamptz not null default now()
);

create table task_files (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references tenants(id) on delete cascade,
  task_id uuid not null references tasks(id) on delete cascade,
  uploader_user_id uuid not null references users(id),
  file_role text not null default 'input',
  storage_key text not null,
  filename text not null,
  content_type text,
  size_bytes bigint,
  version_num integer not null default 1,
  expires_at timestamptz not null,
  created_at timestamptz not null default now()
);
create index idx_task_files_tenant_task_role on task_files(tenant_id, task_id, file_role, version_num desc);

create table meetings (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references tenants(id) on delete cascade,
  task_id uuid not null references tasks(id) on delete cascade,
  provider text not null default 'google',
  calendar_id text default 'primary',
  external_event_id text,
  meeting_url text,
  starts_at timestamptz,
  ends_at timestamptz,
  sync_direction text not null default 'two_way',
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table integrations_google (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null unique references tenants(id) on delete cascade,
  client_id text not null,
  encrypted_client_secret text not null,
  encrypted_access_token text,
  encrypted_refresh_token text,
  token_expires_at timestamptz,
  scope text,
  connected_by uuid references users(id),
  connected_at timestamptz,
  updated_at timestamptz not null default now()
);

create table notification_prefs (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references tenants(id) on delete cascade,
  user_id uuid not null references users(id) on delete cascade,
  event_key text not null,
  is_enabled boolean not null default true,
  updated_at timestamptz not null default now(),
  unique (tenant_id, user_id, event_key)
);

create table audit_log (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references tenants(id) on delete cascade,
  actor_user_id uuid references users(id),
  action text not null,
  object_type text not null,
  object_id text not null,
  details jsonb,
  created_at timestamptz not null default now()
);
create index idx_audit_tenant_created on audit_log(tenant_id, created_at desc);
