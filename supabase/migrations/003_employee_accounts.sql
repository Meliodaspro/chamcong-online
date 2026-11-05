begin;
alter table public.profiles add column if not exists email text not null default '';
update public.profiles p set email=coalesce(u.email,'') from auth.users u where u.id=p.id;

create sequence public.employee_number_seq;
revoke all on sequence public.employee_number_seq from public,anon,authenticated;
create function public.next_employee_code() returns text
language plpgsql security definer set search_path='' as $$
declare candidate text; number_text text;
begin
 loop
  number_text := nextval('public.employee_number_seq')::text;
  candidate := 'NV' || lpad(number_text,greatest(6,length(number_text)),'0');
  if not exists(select 1 from public.profiles where employee_code=candidate) then return candidate; end if;
 end loop;
end; $$;
revoke all on function public.next_employee_code() from public,anon,authenticated;
create function public.assign_employee_code() returns trigger
language plpgsql security definer set search_path='' as $$
begin
 if new.employee_code is null or trim(new.employee_code)='' then
  new.employee_code:=public.next_employee_code();
 end if;
 return new;
end; $$;
revoke all on function public.assign_employee_code() from public,anon,authenticated;
create trigger assign_employee_code before insert on public.profiles
for each row execute function public.assign_employee_code();
update public.profiles set employee_code=public.next_employee_code()
where employee_code is null or trim(employee_code)='';
alter table public.profiles alter column employee_code set not null;
-- Codes are assigned only by the server and are not editable from clients.
create or replace function public.update_employee(p_id uuid,p_name text,p_code text,p_phone text,p_position text,p_active boolean,p_role text) returns void
language plpgsql security definer set search_path='' as $$
declare old_row public.profiles; new_row public.profiles;
begin
 if not public.is_admin() then raise exception 'ADMIN_REQUIRED'; end if;
 if p_role is null or p_role not in ('admin','employee') or p_name is null or length(trim(p_name)) not between 1 and 100 or p_active is null then raise exception 'INVALID_INPUT'; end if;
 if p_id=auth.uid() and (not p_active or p_role<>'admin') then raise exception 'SELF_ADMIN_CHANGE'; end if;
 select * into strict old_row from public.profiles where id=p_id for update;
 update public.profiles set display_name=trim(p_name),phone=left(coalesce(p_phone,''),100),position=left(coalesce(p_position,''),100),is_active=p_active,role=p_role where id=p_id returning * into new_row;
 insert into public.admin_audit(actor_id,action,target_id,before_value,after_value) values(auth.uid(),'employee_update',p_id,to_jsonb(old_row),to_jsonb(new_row));
end; $$;
create or replace function public.create_employee_profile() returns trigger
language plpgsql security definer set search_path='' as $$
begin
 insert into public.profiles(id,display_name,email)
 values(new.id,left(coalesce(nullif(trim(new.raw_user_meta_data->>'display_name'),''),'Nhân viên'),100),coalesce(new.email,''));
 return new;
end; $$;
commit;
