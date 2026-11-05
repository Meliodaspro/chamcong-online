-- Chấm Công • Supabase/Postgres. Run ONCE in a NEW project's SQL Editor.
-- Existing PHP/MySQL data is not migrated by this file.
begin;
create table public.profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  display_name text not null default 'Nhân viên',
  is_active boolean not null default true,
  created_at timestamptz not null default now()
);
create table public.attendance_sessions (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references public.profiles(id) on delete cascade,
  work_date date not null,
  check_in timestamptz not null,
  check_out timestamptz,
  constraint valid_times check (check_out is null or check_out >= check_in),
  unique (user_id, work_date)
);
create unique index one_open_session_per_user on public.attendance_sessions(user_id)
where check_out is null;
create table public.punch_receipts (
  user_id uuid not null references public.profiles(id) on delete cascade,
  request_id uuid not null,
  action text not null check (action in ('in','out')),
  result jsonb not null,
  primary key(user_id, request_id)
);
alter table public.profiles enable row level security;
alter table public.attendance_sessions enable row level security;
alter table public.punch_receipts enable row level security;
revoke all on public.profiles, public.attendance_sessions, public.punch_receipts from anon, authenticated;
grant select on public.profiles, public.attendance_sessions to authenticated;
create policy own_profile on public.profiles for select to authenticated
using (id = (select auth.uid()));
create policy own_attendance on public.attendance_sessions for select to authenticated
using (user_id = (select auth.uid()) and exists (
  select 1 from public.profiles p where p.id = (select auth.uid()) and p.is_active
));

create function public.create_employee_profile() returns trigger
language plpgsql security definer set search_path = '' as $$
begin
  insert into public.profiles(id, display_name)
  values(new.id, left(coalesce(nullif(trim(new.raw_user_meta_data->>'display_name'), ''), 'Nhân viên'), 100));
  return new;
end;
$$;
revoke all on function public.create_employee_profile() from public, anon, authenticated;
create trigger create_employee_profile after insert on auth.users
for each row execute function public.create_employee_profile();
-- Include accounts created before the migration.
insert into public.profiles(id, display_name)
select id, left(coalesce(nullif(trim(raw_user_meta_data->>'display_name'), ''), 'Nhân viên'), 100)
from auth.users on conflict do nothing;

create function public.punch(p_action text, p_request_id uuid) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
  v_uid uuid := auth.uid();
  v_now timestamptz;
  v_session public.attendance_sessions;
  v_receipt public.punch_receipts;
  v_result jsonb;
begin
  if v_uid is null then raise exception 'AUTH_REQUIRED'; end if;
  if p_action is null or p_action not in ('in','out') or p_request_id is null then
    raise exception 'INVALID_REQUEST';
  end if;
  -- Serializes taps, retries and simultaneous requests across multiple devices.
  perform pg_advisory_xact_lock(hashtextextended(v_uid::text, 0));
  if not exists(select 1 from public.profiles where id = v_uid and is_active) then
    raise exception 'ACCOUNT_DISABLED';
  end if;
  select * into v_receipt from public.punch_receipts
  where user_id = v_uid and request_id = p_request_id;
  if found then
    if v_receipt.action <> p_action then raise exception 'REQUEST_MISMATCH'; end if;
    return v_receipt.result;
  end if;
  v_now := clock_timestamp(); -- Never trusts the phone clock.
  select * into v_session from public.attendance_sessions
  where user_id = v_uid and check_out is null for update;
  if p_action = 'in' then
    if found then raise exception 'ALREADY_CHECKED_IN'; end if;
    if exists(select 1 from public.attendance_sessions where user_id = v_uid
      and work_date = (v_now at time zone 'Asia/Ho_Chi_Minh')::date) then
      raise exception 'DAY_COMPLETED';
    end if;
    insert into public.attendance_sessions(user_id, work_date, check_in)
    values(v_uid, (v_now at time zone 'Asia/Ho_Chi_Minh')::date, v_now)
    returning * into v_session;
  else
    if not found then raise exception 'NO_OPEN_SESSION'; end if;
    -- Finds the open shift even after midnight or across month boundaries.
    update public.attendance_sessions set check_out = v_now
    where id = v_session.id returning * into v_session;
  end if;
  v_result := to_jsonb(v_session);
  insert into public.punch_receipts values(v_uid, p_request_id, p_action, v_result);
  return v_result;
end;
$$;
revoke all on function public.punch(text, uuid) from public, anon;
grant execute on function public.punch(text, uuid) to authenticated;

create function public.month_overview(p_month date) returns jsonb
language plpgsql security definer set search_path = '' as $$
declare
  v_uid uuid := auth.uid();
  v_start date;
  v_name text;
begin
  if v_uid is null then raise exception 'AUTH_REQUIRED'; end if;
  select display_name into v_name from public.profiles where id = v_uid and is_active;
  if not found then raise exception 'ACCOUNT_DISABLED'; end if;
  if p_month is null then raise exception 'INVALID_MONTH'; end if;
  v_start := date_trunc('month', p_month)::date;
  return jsonb_build_object(
    'server_time', clock_timestamp(),
    'today', (clock_timestamp() at time zone 'Asia/Ho_Chi_Minh')::date,
    'display_name', v_name,
    'today_record', (select to_jsonb(a) from public.attendance_sessions a where a.user_id = v_uid
      and a.work_date = (clock_timestamp() at time zone 'Asia/Ho_Chi_Minh')::date),
    'active', (select to_jsonb(a) from public.attendance_sessions a
      where a.user_id = v_uid and a.check_out is null),
    'records', coalesce((select jsonb_agg(to_jsonb(a) order by a.work_date desc)
      from public.attendance_sessions a where a.user_id = v_uid
      and a.work_date >= v_start and a.work_date < (v_start + interval '1 month')::date), '[]'::jsonb)
  );
end;
$$;
revoke all on function public.month_overview(date) from public, anon;
grant execute on function public.month_overview(date) to authenticated;
commit;
