\set ON_ERROR_STOP on
begin;
insert into auth.users(id) values('00000000-0000-4000-8000-000000000011'),('00000000-0000-4000-8000-000000000012');
update public.profiles set role='admin' where id='00000000-0000-4000-8000-000000000011';
set local role authenticated;
select set_config('request.jwt.claim.sub','00000000-0000-4000-8000-000000000012',true);
do $$ begin
 if public.is_admin() then raise exception 'FAIL employee admin'; end if;
 begin
  perform public.update_employee(auth.uid(),'X','','','',true,'admin');
  raise exception 'FAIL escalation';
 exception when raise_exception then if sqlerrm<>'ADMIN_REQUIRED' then raise; end if; end;
 begin
  update public.profiles set role='admin' where id=auth.uid();
  raise exception 'FAIL direct escalation';
 exception when insufficient_privilege then null; end;
 begin
  perform public.save_attendance(auth.uid(),current_date-2,(current_date-2)::timestamp at time zone 'Asia/Ho_Chi_Minh',null,'fake');
  raise exception 'FAIL forge';
 exception when raise_exception then if sqlerrm<>'ADMIN_REQUIRED' then raise; end if; end;
 perform public.request_correction(current_date-2,((current_date-2)::text||' 08:00+07')::timestamptz,((current_date-2)::text||' 17:00+07')::timestamptz,'Quên chấm');
 if(select count(*) from public.admin_audit)<>0 then raise exception 'FAIL audit isolation'; end if;
end $$;
select set_config('request.jwt.claim.sub','00000000-0000-4000-8000-000000000011',true);
do $$ declare req uuid; begin
 if not public.is_admin() or (select count(*) from public.profiles)<>2 then raise exception 'FAIL admin reads'; end if;
 select id into req from public.correction_requests;
 perform public.review_correction(req,true);
 if(select count(*) from public.attendance_sessions)<>1 then raise exception 'FAIL approved attendance'; end if;
 if(select count(*) from public.admin_audit)<>2 then raise exception 'FAIL audit'; end if;
 begin
  perform public.review_correction(req,true);raise exception 'FAIL double approval';
 exception when raise_exception then if sqlerrm<>'ALREADY_REVIEWED' then raise; end if; end;
 begin
  perform public.update_employee(auth.uid(),'Admin','','','',false,'employee');raise exception 'FAIL self lock';
 exception when raise_exception then if sqlerrm<>'SELF_ADMIN_CHANGE' then raise; end if; end;
end $$;
reset role;
update public.profiles set is_active=false where role='admin';
set local role authenticated;
do $$ begin
 if public.is_admin() then raise exception 'FAIL inactive admin'; end if;
 begin
  perform public.review_correction(gen_random_uuid(),false); raise exception 'FAIL disabled admin';
 exception when raise_exception then if sqlerrm<>'ADMIN_REQUIRED' then raise; end if; end;
end $$;
rollback;
