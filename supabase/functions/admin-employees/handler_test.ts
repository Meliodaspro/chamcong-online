import {makeHandler} from './handler.ts';
import type {SupabaseClient} from 'npm:@supabase/supabase-js@2';
function assert(value:unknown,message='Assertion failed'){if(!value)throw new Error(message);}
function fixture(role='admin',active=true){
 const state={created:[] as unknown[],updated:[] as unknown[],audit:[] as unknown[]};
 const client={auth:{getUser:async(token:string)=>({data:{user:token==='valid'?{id:'actor'}:null},error:null}),admin:{createUser:async(body:unknown)=>{state.created.push(body);return {data:{user:{id:'new'}},error:null};},updateUserById:async(id:string,body:unknown)=>{state.updated.push({id,body});return {error:null};}}},from:(table:string)=>({select:()=>({eq:(_col:string,id:string)=>({single:async()=>({data:id==='actor'?{id,role,is_active:active}:id==='new'?{id,display_name:'Sơn',employee_code:'NV000001'}:{id,role:'employee'},error:null})})}),insert:async(body:unknown)=>{assert(table==='admin_audit');state.audit.push(body);return {error:null};}})} as unknown as SupabaseClient;
 return {handler:makeHandler(client),state};
}
const payload={action:'create',name:'Sơn',email:'son@example.com',password:'test-password-only'};
function request(body:unknown,token='valid'){return new Request('https://test.local',{method:'POST',headers:{Authorization:token?'Bearer '+token:''},body:JSON.stringify(body)});}
Deno.test('no session, invalid token, employee and disabled admin rejected',async()=>{
 for(const [role,active,token,status] of [['admin',true,'',401],['admin',true,'invalid',401],['employee',true,'valid',403],['admin',false,'valid',403]] as const){
  const f=fixture(role,active);const r=await f.handler(request(payload,token));assert(r.status===status);assert(f.state.created.length===0);assert(f.state.updated.length===0);
 }
});
Deno.test('create ignores role injection, returns generated code and audits without password',async()=>{
 const f=fixture();const r=await f.handler(request({...payload,role:'admin',employee_code:'FAKE'}));const b=await r.json();
 assert(r.status===200);assert(b.employee.employee_code==='NV000001');
 const body=f.state.created[0] as Record<string,unknown>;assert(!('role' in body));assert(JSON.stringify(body.user_metadata)==='{"display_name":"Sơn"}');
 assert(!JSON.stringify(f.state.audit).includes(payload.password));
});
Deno.test('invalid password cannot create an account',async()=>{
 const f=fixture();const r=await f.handler(request({...payload,password:'short'}));assert(r.status===400);assert(f.state.created.length===0);
});
Deno.test('password reset only accepts employee targets and does not log password',async()=>{
 const f=fixture();assert((await f.handler(request({action:'reset_password',user_id:'actor',password:payload.password}))).status===403);
 const r=await f.handler(request({action:'reset_password',user_id:'employee',password:payload.password}));assert(r.status===200);assert(f.state.updated.length===1);assert(!JSON.stringify(f.state.audit).includes(payload.password));
});
