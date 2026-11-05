import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'design.dart';
import 'management.dart' show db;
class AccountPage extends StatefulWidget{
 const AccountPage({this.employee,super.key});final Map<String,dynamic>? employee;
 @override State<AccountPage> createState()=>_AccountPageState();
}
class _AccountPageState extends State<AccountPage>{
 final form=GlobalKey<FormState>(); final name=TextEditingController(),email=TextEditingController(),password=TextEditingController();
 bool busy=false,hidden=true;String? error;Map<String,dynamic>? result;
 bool get reset=>widget.employee!=null;
 @override void dispose(){name.dispose();email.dispose();password.dispose();super.dispose();}
 Future<void> submit()async{
  if(!form.currentState!.validate())return;
  setState((){busy=true;error=null;});
  try{
   await db.auth.refreshSession();
   final token=db.auth.currentSession?.accessToken;
   if(token==null)throw const AuthException('AUTH_REQUIRED');
   final response=await db.functions.invoke('admin-employees',headers:{'Authorization':'Bearer $token'},body:{'action':reset?'reset_password':'create','name':name.text.trim(),'email':email.text.trim(),'password':password.text,if(reset)'user_id':widget.employee!['id']});
   final data=Map<String,dynamic>.from(response.data as Map);
   if(data['error']!=null)throw Exception(data['error']);
   password.clear();
   if(mounted)setState(()=>result=data);
  }catch(e){if(mounted)setState(()=>error=accountError(e));}
  finally{if(mounted)setState(()=>busy=false);}
 }
 @override Widget build(BuildContext context)=>Scaffold(appBar:AppBar(title:Text(reset?'Đặt lại mật khẩu':'Thêm nhân viên')),body:ListView(padding:const EdgeInsets.all(20),children:[
 if(result!=null)...[
  const Icon(Icons.check_circle_rounded,size:64,color:Color(0xFF12805C)),const SizedBox(height:16),Text(reset?'Đã đổi mật khẩu':'Tài khoản đã sẵn sàng',style:Theme.of(context).textTheme.headlineSmall,textAlign:TextAlign.center),
  Card(child:Padding(padding:const EdgeInsets.all(20),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
   if(!reset)...[Text(result!['employee']['display_name'],style:Theme.of(context).textTheme.titleLarge),const SizedBox(height:10),SelectableText('Mã nhân viên: ${result!['employee']['employee_code']}\nEmail: ${email.text.trim()}')],
   const SizedBox(height:12),const Text('Gửi thông tin đăng nhập cho nhân viên qua kênh riêng. Nhân viên có thể đổi mật khẩu trong app.'),
   if(result!['audit_saved']==false)const Text('Thao tác đã xong nhưng chưa lưu được nhật ký. Hãy báo quản lý.',style:TextStyle(color:Colors.orange)),
  ]))),FilledButton(onPressed:()=>Navigator.pop(context,true),child:const Text('Hoàn tất')),
 ] else Form(key:form,child:Column(crossAxisAlignment:CrossAxisAlignment.stretch,children:[
  DashboardBanner(title:reset?'Cấp lại mật khẩu':'Thêm một thành viên',subtitle:reset?'Nhân viên: ${widget.employee!['display_name']}':'Mã NV được cấp tự động. Tài khoản mới có quyền nhân viên.'),const SizedBox(height:24),
  if(!reset)...[TextFormField(controller:name,enabled:!busy,textCapitalization:TextCapitalization.words,decoration:const InputDecoration(labelText:'Họ và tên',prefixIcon:Icon(Icons.person_outline)),validator:(v)=>v==null||v.trim().isEmpty||v.trim().length>100?'Nhập họ tên (tối đa 100 ký tự)':null),const SizedBox(height:16),
  TextFormField(controller:email,enabled:!busy,keyboardType:TextInputType.emailAddress,autocorrect:false,decoration:const InputDecoration(labelText:'Email đăng nhập',prefixIcon:Icon(Icons.alternate_email)),validator:(v)=>!RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$').hasMatch(v?.trim()??'')?'Nhập email hợp lệ':null),const SizedBox(height:16)],
  TextFormField(controller:password,enabled:!busy,obscureText:hidden,autocorrect:false,enableSuggestions:false,decoration:InputDecoration(labelText:'Mật khẩu mới',helperText:'Từ 10 đến 128 ký tự',prefixIcon:const Icon(Icons.lock_outline),suffixIcon:IconButton(onPressed:()=>setState(()=>hidden=!hidden),icon:Icon(hidden?Icons.visibility_outlined:Icons.visibility_off_outlined))),validator:validatePassword),
  const SizedBox(height:20),if(error!=null)Padding(padding:const EdgeInsets.only(bottom:16),child:Text(error!,style:const TextStyle(color:Colors.red))),
  FilledButton.icon(onPressed:busy?null:submit,icon:Icon(reset?Icons.key:Icons.person_add_alt),label:Text(busy?'Đang xử lý…':reset?'Đổi mật khẩu nhân viên':'Tạo tài khoản')),
 ])),
 ]));
}
String? validatePassword(String? v)=>v==null||v.length<10||v.length>128?'Mật khẩu cần từ 10 đến 128 ký tự':null;
String accountError(Object e){
 final s=e.toString();
 if(s.contains('EMAIL_EXISTS')||s.contains('email_exists'))return 'Email đã có tài khoản. Nếu lần tạo trước bị mất mạng, hãy tải lại danh sách nhân viên để kiểm tra.';
 if(s.contains('ADMIN_REQUIRED')||s.contains('EMPLOYEE_ONLY'))return 'Bạn không có quyền thực hiện thao tác này.';
 if(s.contains('PROFILE_READ_FAILED'))return 'Tài khoản đã được tạo. Hãy quay lại tải danh sách nhân viên; không tạo lại.';
 if(s.contains('PASSWORD_TOO_SHORT'))return 'Mật khẩu cần từ 10 đến 128 ký tự.';
 if(s.contains('AUTH_REQUIRED'))return 'Phiên đăng nhập hết hạn. Hãy đăng nhập lại.';
 return 'Chưa xác nhận được kết quả. Kiểm tra mạng và tải lại danh sách nhân viên. Nếu lần đầu sử dụng, quản lý cần triển khai chức năng admin-employees trên Supabase.';
}
class PasswordPage extends StatefulWidget{
 const PasswordPage({super.key});
 @override State<PasswordPage> createState()=>_PasswordPageState();
}
class _PasswordPageState extends State<PasswordPage>{
 final form=GlobalKey<FormState>();final current=TextEditingController(),password=TextEditingController(),confirm=TextEditingController();bool busy=false;String? error;
 @override void dispose(){current.dispose();password.dispose();confirm.dispose();super.dispose();}
 Future<void> save()async{
 if(!form.currentState!.validate())return;setState((){busy=true;error=null;});
 try{
  final email=db.auth.currentUser?.email;
  if(email==null)throw const AuthException('AUTH_REQUIRED');
  await db.auth.signInWithPassword(email:email,password:current.text);
  await db.auth.updateUser(UserAttributes(password:password.text));
  if(mounted){ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content:Text('Đã đổi mật khẩu.')));Navigator.pop(context);}
 }catch(_){if(mounted)setState(()=>error='Chưa đổi được mật khẩu. Kiểm tra mật khẩu hiện tại và kết nối.');}
 finally{if(mounted)setState(()=>busy=false);}
 }
 @override Widget build(BuildContext context)=>Scaffold(appBar:AppBar(title:const Text('Đổi mật khẩu')),body:Form(key:form,child:ListView(padding:const EdgeInsets.all(20),children:[
 const DashboardBanner(title:'Bảo vệ tài khoản',subtitle:'Đổi mật khẩu được quản lý cấp thành mật khẩu riêng của bạn.'),const SizedBox(height:24),
 TextFormField(controller:current,enabled:!busy,obscureText:true,decoration:const InputDecoration(labelText:'Mật khẩu hiện tại'),validator:(v)=>v==null||v.isEmpty?'Nhập mật khẩu hiện tại':null),const SizedBox(height:16),
 TextFormField(controller:password,enabled:!busy,obscureText:true,decoration:const InputDecoration(labelText:'Mật khẩu mới'),validator:validatePassword),const SizedBox(height:16),
 TextFormField(controller:confirm,enabled:!busy,obscureText:true,decoration:const InputDecoration(labelText:'Nhập lại mật khẩu mới'),validator:(v)=>v!=password.text?'Mật khẩu không khớp':null),const SizedBox(height:20),
 if(error!=null)Text(error!,style:const TextStyle(color:Colors.red)),FilledButton(onPressed:busy?null:save,child:Text(busy?'Đang lưu…':'Lưu mật khẩu mới')),
 ])));
}
