-- Run in a disposable database AFTER bootstrap.sql and schema.sql. Rollback all data.
\set ON_ERROR_STOP on
begin;
insert into auth.users(id,raw_user_meta_data) values
('00000000-0000-4000-8000-000000000001','{"display_name":"Sơn"}'),
('00000000-0000-4000-8000-000000000002','{"display_name":"Người khác"}');
set local role anon;
do $$ begin
  perform public.month_overview((now() at time zone 'Asia/Ho_Chi_Minh')::date);
  raise exception 'FAIL anonymous can read';
exception when insufficient_privilege then null; end $$;
set local role authenticated;
select set_config('request.jwt.claim.sub','00000000-0000-4000-8000-000000000001',true);
do $$
declare r jsonb; r2 jsonb;
begin
  if (select count(*) from public.profiles) <> 1 then raise exception 'FAIL profile isolation'; end if;
  r := public.punch('in','10000000-0000-4000-8000-000000000001');
  r2 := public.punch('in','10000000-0000-4000-8000-000000000001');
  if r <> r2 then raise exception 'FAIL idempotent retry'; end if;
  if (select count(*) from public.attendance_sessions) <> 1 then raise exception 'FAIL duplicate'; end if;
  begin
    perform public.punch('out','10000000-0000-4000-8000-000000000001');
    raise exception 'FAIL request mismatch accepted';
  exception when raise_exception then
    if sqlerrm <> 'REQUEST_MISMATCH' then raise; end if;
  end;
  begin
    perform public.punch('in','10000000-0000-4000-8000-000000000002');
    raise exception 'FAIL double checkin accepted';
  exception when raise_exception then
    if sqlerrm <> 'ALREADY_CHECKED_IN' then raise; end if;
  end;
  begin
    update public.profiles set is_active = true;
    raise exception 'FAIL employee can edit status';
  exception when insufficient_privilege then null; end;
  begin
    insert into public.attendance_sessions(user_id,work_date,check_in)
      values(auth.uid(), current_date, now());
    raise exception 'FAIL employee can forge attendance';
  exception when insufficient_privilege then null; end;
  r := public.punch('out','20000000-0000-4000-8000-000000000001');
  r2 := public.punch('out','20000000-0000-4000-8000-000000000001');
  if r <> r2 or r->>'check_out' is null then raise exception 'FAIL checkout'; end if;
  begin
    perform public.punch('in','30000000-0000-4000-8000-000000000001');
    raise exception 'FAIL second daily shift accepted';
  exception when raise_exception then
    if sqlerrm <> 'DAY_COMPLETED' then raise; end if;
  end;
  if jsonb_array_length(public.month_overview((now() at time zone 'Asia/Ho_Chi_Minh')::date)->'records') <> 1 then
    raise exception 'FAIL monthly overview';
  end if;
end $$;
-- A second user cannot read the first user's records or close their shift.
select set_config('request.jwt.claim.sub','00000000-0000-4000-8000-000000000002',true);
do $$ begin
  if (select count(*) from public.attendance_sessions) <> 0 then raise exception 'FAIL cross-user read'; end if;
  begin
    perform public.punch('out','40000000-0000-4000-8000-000000000001');
    raise exception 'FAIL cross-user checkout';
  exception when raise_exception then
    if sqlerrm <> 'NO_OPEN_SESSION' then raise; end if;
  end;
end $$;
-- Simulate an overnight shift as administrator.
reset role;
insert into public.attendance_sessions(user_id,work_date,check_in) values
('00000000-0000-4000-8000-000000000002',
 ((now() at time zone 'Asia/Ho_Chi_Minh')::date - 1), now() - interval '10 hours');
set local role authenticated;
do $$ declare r jsonb; begin
  r := public.punch('out','50000000-0000-4000-8000-000000000001');
  if (r->>'work_date')::date <> ((now() at time zone 'Asia/Ho_Chi_Minh')::date - 1)
    or r->>'check_out' is null then raise exception 'FAIL overnight'; end if;
end $$;
reset role;
update public.profiles set is_active = false where id = '00000000-0000-4000-8000-000000000002';
set local role authenticated;
do $$ begin
  if (select count(*) from public.attendance_sessions) <> 0 then raise exception 'FAIL disabled read'; end if;
  begin
    perform public.month_overview((now() at time zone 'Asia/Ho_Chi_Minh')::date);
    raise exception 'FAIL disabled overview';
  exception when raise_exception then
    if sqlerrm <> 'ACCOUNT_DISABLED' then raise; end if;
  end;
  begin
    perform public.punch('in','60000000-0000-4000-8000-000000000001');
    raise exception 'FAIL disabled punch';
  exception when raise_exception then
    if sqlerrm <> 'ACCOUNT_DISABLED' then raise; end if;
  end;
end $$;
rollback;
