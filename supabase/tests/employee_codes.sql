\set ON_ERROR_STOP on
begin;
insert into auth.users(id) values('00000000-0000-4000-8000-000000000021'),('00000000-0000-4000-8000-000000000022');
do $$ begin
 if (select count(distinct employee_code) from public.profiles)<>2 then raise exception 'FAIL generated codes'; end if;
 if exists(select 1 from public.profiles where employee_code !~ '^NV[0-9]{6,}$') then raise exception 'FAIL code format'; end if;
end $$;
update public.profiles set role='admin' where id='00000000-0000-4000-8000-000000000021';
set local role authenticated;
select set_config('request.jwt.claim.sub','00000000-0000-4000-8000-000000000021',true);
do $$ declare old_code text; begin
 select employee_code into old_code from public.profiles where id=auth.uid();
 perform public.update_employee(auth.uid(),'Admin','FAKE','','',true,'admin');
 if (select employee_code from public.profiles where id=auth.uid())<>old_code then raise exception 'FAIL client code override'; end if;
end $$;
rollback;
