begin;
alter table public.profiles add column if not exists role text not null default 'employee' check(role in ('employee','admin'));
alter table public.profiles add column if not exists employee_code text;
alter table public.profiles add column if not exists phone text not null default '';
alter table public.profiles add column if not exists position text not null default '';
create unique index if not exists employee_code_unique on public.profiles(employee_code) where employee_code is not null;
create or replace function public.is_admin() returns boolean language sql stable security definer set search_path='' as $$
 select exists(select 1 from public.profiles where id=auth.uid() and is_active and role='admin');
$$;
revoke all on function public.is_admin() from public,anon;
grant execute on function public.is_admin() to authenticated;
create policy admin_profiles on public.profiles for select to authenticated using(public.is_admin());
create policy admin_attendance on public.attendance_sessions for select to authenticated using(public.is_admin());
create table public.correction_requests(
 id uuid primary key default gen_random_uuid(), user_id uuid not null references public.profiles(id),
 work_date date not null, check_in timestamptz not null, check_out timestamptz,
 reason text not null check(length(trim(reason)) between 3 and 1000),
 status text not null default 'pending' check(status in ('pending','approved','rejected')),
 created_at timestamptz not null default now(), reviewed_by uuid references public.profiles(id), reviewed_at timestamptz,
 check(check_out is null or check_out>=check_in),
 check((check_in at time zone 'Asia/Ho_Chi_Minh')::date=work_date)
);
create unique index one_pending_correction on public.correction_requests(user_id,work_date) where status='pending';
create table public.admin_audit(
 id bigint generated always as identity primary key, actor_id uuid not null references public.profiles(id),
 action text not null, target_id uuid, before_value jsonb, after_value jsonb, reason text,
 created_at timestamptz not null default now()
);
alter table public.correction_requests enable row level security;
alter table public.admin_audit enable row level security;
revoke all on public.correction_requests,public.admin_audit from anon,authenticated;
grant select on public.correction_requests,public.admin_audit to authenticated;
create policy read_requests on public.correction_requests for select to authenticated using(public.is_admin() or
 (user_id=auth.uid() and exists(select 1 from public.profiles where id=auth.uid() and is_active)));
create policy read_audit on public.admin_audit for select to authenticated using(public.is_admin());

create function public.update_employee(p_id uuid,p_name text,p_code text,p_phone text,p_position text,p_active boolean,p_role text) returns void
language plpgsql security definer set search_path='' as $$
declare old_row public.profiles; new_row public.profiles;
begin
 if not public.is_admin() then raise exception 'ADMIN_REQUIRED'; end if;
 if p_role is null or p_role not in ('admin','employee') or p_name is null or length(trim(p_name)) not between 1 and 100 or p_active is null then raise exception 'INVALID_INPUT'; end if;
 if p_id=auth.uid() and (not p_active or p_role<>'admin') then raise exception 'SELF_ADMIN_CHANGE'; end if;
 select * into strict old_row from public.profiles where id=p_id for update;
 update public.profiles set display_name=trim(p_name), employee_code=nullif(trim(p_code),''),phone=left(coalesce(p_phone,''),100),position=left(coalesce(p_position,''),100),is_active=p_active,role=p_role where id=p_id returning * into new_row;
 insert into public.admin_audit(actor_id,action,target_id,before_value,after_value) values(auth.uid(),'employee_update',p_id,to_jsonb(old_row),to_jsonb(new_row));
end; $$;

create function public.request_correction(p_date date,p_in timestamptz,p_out timestamptz,p_reason text) returns void
language plpgsql security definer set search_path='' as $$
begin
 if not exists(select 1 from public.profiles where id=auth.uid() and is_active) then raise exception 'ACCOUNT_DISABLED'; end if;
 if p_date>(clock_timestamp() at time zone 'Asia/Ho_Chi_Minh')::date or p_in>clock_timestamp() or p_out>clock_timestamp() then raise exception 'FUTURE_TIME'; end if;
 insert into public.correction_requests(user_id,work_date,check_in,check_out,reason) values(auth.uid(),p_date,p_in,p_out,trim(p_reason));
end; $$;

create function public.save_attendance(p_user uuid,p_date date,p_in timestamptz,p_out timestamptz,p_reason text) returns void
language plpgsql security definer set search_path='' as $$
declare old_row public.attendance_sessions; new_row public.attendance_sessions;
begin
 if not public.is_admin() then raise exception 'ADMIN_REQUIRED'; end if;
 if p_reason is null or length(trim(p_reason)) not between 3 and 1000 or p_date is null or p_in is null or
 (p_in at time zone 'Asia/Ho_Chi_Minh')::date<>p_date or p_date>(clock_timestamp() at time zone 'Asia/Ho_Chi_Minh')::date or p_in>clock_timestamp() or p_out>clock_timestamp() then raise exception 'INVALID_INPUT'; end if;
 perform pg_advisory_xact_lock(hashtextextended(p_user::text,0));
 select * into old_row from public.attendance_sessions where user_id=p_user and work_date=p_date for update;
 insert into public.attendance_sessions(user_id,work_date,check_in,check_out) values(p_user,p_date,p_in,p_out)
 on conflict(user_id,work_date) do update set check_in=excluded.check_in,check_out=excluded.check_out returning * into new_row;
 insert into public.admin_audit(actor_id,action,target_id,before_value,after_value,reason) values(auth.uid(),'attendance_update',p_user,to_jsonb(old_row),to_jsonb(new_row),trim(p_reason));
end; $$;

create function public.review_correction(p_id uuid,p_approve boolean) returns void
language plpgsql security definer set search_path='' as $$
declare r public.correction_requests;
begin
 if not public.is_admin() then raise exception 'ADMIN_REQUIRED'; end if;
 if p_approve is null then raise exception 'INVALID_INPUT'; end if;
 select * into strict r from public.correction_requests where id=p_id for update;
 if r.status<>'pending' then raise exception 'ALREADY_REVIEWED'; end if;
 if p_approve then perform public.save_attendance(r.user_id,r.work_date,r.check_in,r.check_out,r.reason); end if;
 update public.correction_requests set status=case when p_approve then 'approved' else 'rejected' end, reviewed_by=auth.uid(),reviewed_at=clock_timestamp() where id=p_id;
 insert into public.admin_audit(actor_id,action,target_id,before_value,after_value,reason) values(auth.uid(),'request_review',r.user_id,to_jsonb(r),jsonb_build_object('approved',p_approve),r.reason);
end; $$;
revoke all on function public.update_employee(uuid,text,text,text,text,boolean,text),public.request_correction(date,timestamptz,timestamptz,text),public.save_attendance(uuid,date,timestamptz,timestamptz,text),public.review_correction(uuid,boolean) from public,anon;
grant execute on function public.update_employee(uuid,text,text,text,text,boolean,text),public.request_correction(date,timestamptz,timestamptz,text),public.save_attendance(uuid,date,timestamptz,timestamptz,text),public.review_correction(uuid,boolean) to authenticated;
commit;
