import 'dart:convert';
import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:share_plus/share_plus.dart';
import 'package:cross_file/cross_file.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'models.dart';
import 'main.dart' show HomePage, ErrorNotice;

final db = Supabase.instance.client;
String managementError(Object e) {
 final s=e.toString();
 if(s.contains('ADMIN_REQUIRED')) return 'Bạn không còn quyền quản trị. Hãy đăng nhập lại.';
 if(s.contains('SELF_ADMIN_CHANGE')) return 'Không thể tự khóa hoặc hạ quyền tài khoản đang dùng.';
 if(s.contains('23505')) return 'Mã nhân viên hoặc yêu cầu ngày này đã tồn tại.';
 if(s.contains('23514') || s.contains('INVALID_INPUT') || s.contains('FUTURE_TIME')) return 'Kiểm tra ngày, giờ vào/ra và lý do. Không nhập giờ trong tương lai.';
 if(s.contains('ALREADY_REVIEWED')) return 'Yêu cầu đã được xử lý. Hãy tải lại.';
 return 'Không thực hiện được. Kiểm tra kết nối, quyền tài khoản và bản cập nhật database.';
}
class RoleGate extends StatefulWidget {
 const RoleGate({super.key});
 @override State<RoleGate> createState()=>_RoleGateState();
}
class _RoleGateState extends State<RoleGate> {
 late Future<Map<String,dynamic>> role;
 @override void initState(){super.initState(); role=load();}
 Future<Map<String,dynamic>> load()=>db.from('profiles').select('role,is_active').eq('id',db.auth.currentUser!.id).single();
 @override Widget build(BuildContext context)=>FutureBuilder<Map<String,dynamic>>(future:role,builder:(context,s){
 if(s.connectionState!=ConnectionState.done) return const Scaffold(body:Center(child:CircularProgressIndicator()));
 if(s.hasError || s.data?['is_active']!=true) return Scaffold(appBar:AppBar(title:const Text('Tài khoản')),body:Padding(padding:const EdgeInsets.all(24),child:Column(children:[
 const Text('Không tải được quyền tài khoản hoặc tài khoản đã bị khóa. Kiểm tra bản cập nhật Supabase với quản lý.'),
 TextButton(onPressed:()=>setState(()=>role=load()),child:const Text('Thử lại')),
 TextButton(onPressed:()=>db.auth.signOut(scope:SignOutScope.local),child:const Text('Đăng xuất'))])));
 return s.data!['role']=='admin'?const AdminPage():const HomePage();
 });
}

class AdminPage extends StatefulWidget {
 const AdminPage({super.key});
 @override State<AdminPage> createState()=>_AdminPageState();
}
class _AdminPageState extends State<AdminPage> {
 int tab=0; bool busy=false; String? error; String search=''; String? employee;
 DateTime month=DateTime(vietnamTime(DateTime.now()).year,vietnamTime(DateTime.now()).month);
 List<Map<String,dynamic>> people=[], rows=[], requests=[], audit=[];
 @override void initState(){super.initState();load();}
 Future<List<Map<String,dynamic>>> allRows(String table, String order, {String? start,String? end}) async {
  final result=<Map<String,dynamic>>[];
  for(var offset=0;;offset+=500){
   var q=db.from(table).select();
   if(start!=null) q=q.gte('work_date',start);
   if(end!=null) q=q.lt('work_date',end);
   final page=await q.order(order).range(offset,offset+499);
   result.addAll(page);
   if(page.length<500) break;
  }
  return result;
 }
 Future<void> load() async {
  if(busy)return;
  setState((){busy=true;error=null;});
  try{
   if(await db.rpc('is_admin')!=true) throw Exception('ADMIN_REQUIRED');
   final p=await allRows('profiles','id');
   final r=await allRows('attendance_sessions','id',start:monthKey(month),end:monthKey(DateTime(month.year,month.month+1)));
   final req=await db.from('correction_requests').select().eq('status','pending').order('created_at');
   final a=await db.from('admin_audit').select().order('id',ascending:false).limit(100);
   if(mounted)setState((){people=p;rows=r;requests=req;audit=a;});
  }catch(e){if(mounted)setState((){error=managementError(e);people=[];rows=[];requests=[];audit=[];});}
  finally{if(mounted)setState(()=>busy=false);}
 }
 String name(dynamic id)=>people.where((p)=>p['id']==id).map((p)=>p['display_name'] as String).firstOrNull??'Nhân viên';
 List<Map<String,dynamic>> get filtered=>rows.where((r)=>employee==null||r['user_id']==employee).toList();
 Future<void> act(Future<void> Function() fn) async {
  if(busy)return; setState((){busy=true;error=null;});
  try{await fn();}catch(e){if(mounted)setState(()=>error=managementError(e));}
  finally{if(mounted)setState(()=>busy=false);}
  final message=error; await load(); if(mounted&&message!=null)setState(()=>error=message);
 }
 Future<void> editPerson(Map<String,dynamic> p) async {
  final c=[TextEditingController(text:p['display_name']),TextEditingController(text:p['employee_code']),TextEditingController(text:p['phone']),TextEditingController(text:p['position'])];
  var active=p['is_active'] as bool; var role=p['role'] as String;
  final ok=await showDialog<bool>(context:context,builder:(ctx)=>StatefulBuilder(builder:(ctx,update)=>AlertDialog(title:const Text('Thông tin nhân viên'),content:SingleChildScrollView(child:Column(mainAxisSize:MainAxisSize.min,children:[
   for(var i=0;i<c.length;i++) Padding(padding:const EdgeInsets.only(bottom:12),child:TextField(controller:c[i],decoration:InputDecoration(labelText:['Họ tên','Mã nhân viên','Điện thoại','Chức vụ'][i]))),
   DropdownButtonFormField<String>(initialValue:role,items:const [DropdownMenuItem(value:'employee',child:Text('Nhân viên')),DropdownMenuItem(value:'admin',child:Text('Admin'))],onChanged:(v)=>update(()=>role=v!)),
   SwitchListTile(title:const Text('Đang hoạt động'),value:active,onChanged:(v)=>update(()=>active=v)),
  ])),actions:[TextButton(onPressed:()=>Navigator.pop(ctx,false),child:const Text('Hủy')),FilledButton(onPressed:()=>Navigator.pop(ctx,true),child:const Text('Lưu'))])));
  if(ok==true)await act(()async{await db.rpc('update_employee',params:{'p_id':p['id'],'p_name':c[0].text,'p_code':c[1].text,'p_phone':c[2].text,'p_position':c[3].text,'p_active':active,'p_role':role});});
  // Controllers live until the dialog route has finished its exit transition.
 }
 Future<void> export() async {
  final csv=reportCsv(filtered, {for(final p in people) p['id'] as String:p['display_name'] as String});
  final box=context.findRenderObject() as RenderBox?;
  await Share.shareXFiles([XFile.fromData(Uint8List.fromList(utf8.encode('\uFEFF$csv')),mimeType:'text/csv',name:'bang-cong-${monthKey(month)}.csv')],sharePositionOrigin:box==null?null:box.localToGlobal(Offset.zero)&box.size);
 }
 @override Widget build(BuildContext context){
  final completed=filtered.where((r)=>r['check_out']!=null).map(Attendance.fromJson).fold(Duration.zero,(Duration s,r)=>s+r.completedDuration);
  final today=dateKey(DateTime.now());
  return Scaffold(appBar:AppBar(title:const Text('Quản trị Chấm Công'),actions:[IconButton(onPressed:busy?null:load,icon:const Icon(Icons.refresh),tooltip:'Tải lại'),IconButton(onPressed:()=>Navigator.push(context,MaterialPageRoute<void>(builder:(_)=>const HomePage())),icon:const Icon(Icons.fingerprint),tooltip:'Công cá nhân'),IconButton(onPressed:busy?null:()=>db.auth.signOut(scope:SignOutScope.local),icon:const Icon(Icons.logout),tooltip:'Đăng xuất')]),
   bottomNavigationBar:NavigationBar(selectedIndex:tab,onDestinationSelected:(v)=>setState(()=>tab=v),destinations:const [NavigationDestination(icon:Icon(Icons.dashboard_outlined),label:'Tổng quan'),NavigationDestination(icon:Icon(Icons.people_outline),label:'Nhân viên'),NavigationDestination(icon:Icon(Icons.table_chart_outlined),label:'Bảng công'),NavigationDestination(icon:Icon(Icons.fact_check_outlined),label:'Duyệt công')]),
   body:RefreshIndicator(onRefresh:load,child:ListView(padding:const EdgeInsets.all(20),children:[
    if(busy)const LinearProgressIndicator(),if(error!=null)ErrorNotice(error!),
    if(tab==0)...[
     Text('Không gian quản lý',style:Theme.of(context).textTheme.headlineMedium),const Text('Nhân viên • Chấm công • Báo cáo',style:TextStyle(color:Colors.white60)),const SizedBox(height:20),
     Wrap(spacing:12,runSpacing:12,children:[stat('Nhân viên hoạt động','${people.where((p)=>p['is_active']==true).length}',Icons.people),stat('Ca trong tháng','${rows.length}',Icons.calendar_month),stat('Chờ duyệt','${requests.length}',Icons.pending_actions),stat('Giờ hoàn tất',durationLabel(completed),Icons.schedule)]),
     const SizedBox(height:20),const Text('TRẠNG THÁI HÔM NAY'),
     if(!rows.any((r)=>r['work_date']==today))const Padding(padding:EdgeInsets.all(16),child:Text('Không có công hôm nay trong tháng đang chọn.')),
     for(final r in rows.where((r)=>r['work_date']==today))attendanceTile(r),
     const SizedBox(height:20),const Text('LỊCH SỬ QUẢN TRỊ · 100 thay đổi gần nhất'),
     for(final a in audit)Card(child:ListTile(leading:const Icon(Icons.history),title:Text('${name(a['actor_id'])} · ${auditLabel(a['action'])}'),subtitle:Text('${name(a['target_id'])}\n${a['reason']??''}\n${vietnamTime(DateTime.parse(a['created_at'])).toString().substring(0,16)}'))),
    ],
    if(tab==1)...[
     TextField(decoration:const InputDecoration(labelText:'Tìm tên / mã nhân viên',prefixIcon:Icon(Icons.search)),onChanged:(v)=>setState(()=>search=v.toLowerCase())),const SizedBox(height:12),
     Card(child:ListTile(leading:const Icon(Icons.person_add_alt),title:const Text('Cấp tài khoản mới'),subtitle:const Text('Tạo email/mật khẩu trong Supabase → Authentication → Users. Tải lại để sửa thông tin và cấp quyền tại đây. Không xóa tài khoản có dữ liệu công; hãy khóa tài khoản.'))),
     for(final p in people.where((p)=>'${p['display_name']} ${p['employee_code']??''}'.toLowerCase().contains(search)))Card(child:ListTile(leading:CircleAvatar(child:Icon(p['role']=='admin'?Icons.admin_panel_settings:Icons.person_outline)),title:Text(p['display_name']),subtitle:Text('${p['employee_code']??'Chưa có mã'} · ${p['role']=='admin'?'Admin':'Nhân viên'}\n${p['is_active']==true?'Hoạt động':'Đã khóa'} · ${p['position']}'),trailing:const Icon(Icons.edit_outlined),onTap:busy?null:()=>editPerson(p))),
    ],
    if(tab==2)...[
     Row(mainAxisAlignment:MainAxisAlignment.spaceBetween,children:[IconButton(onPressed:busy?null:()=>move(-1),icon:const Icon(Icons.chevron_left)),Text('Tháng ${month.month}/${month.year}'),IconButton(onPressed:busy?null:()=>move(1),icon:const Icon(Icons.chevron_right))]),
     DropdownButtonFormField<String>(initialValue:employee,decoration:const InputDecoration(labelText:'Nhân viên'),items:[const DropdownMenuItem<String>(value:null,child:Text('Tất cả nhân viên')),for(final p in people)DropdownMenuItem(value:p['id'] as String,child:Text(p['display_name']))],onChanged:busy?null:(v)=>setState(()=>employee=v)),
     Padding(padding:const EdgeInsets.symmetric(vertical:16),child:Text('${filtered.length} ca · ${durationLabel(completed)} đã hoàn tất')),
     Wrap(spacing:12,children:[FilledButton.icon(onPressed:busy?null:()=>export().catchError((Object e){if(mounted)setState(()=>error=managementError(e));}),icon:const Icon(Icons.ios_share),label:const Text('Xuất CSV / Excel')),OutlinedButton.icon(onPressed:busy?null:()=>editAttendance(context,people:people,onSave:load),icon:const Icon(Icons.edit_calendar),label:const Text('Nhập / sửa công'))]),
     if(filtered.isEmpty)const Padding(padding:EdgeInsets.all(24),child:Text('Chưa có dữ liệu.')),
     for(final r in filtered)attendanceTile(r),
    ],
    if(tab==3)...[
     Text('Yêu cầu sửa công',style:Theme.of(context).textTheme.headlineSmall),
     if(requests.isEmpty)const Padding(padding:EdgeInsets.all(24),child:Text('Không có yêu cầu chờ duyệt.')),
     for(final r in requests)Card(child:Padding(padding:const EdgeInsets.all(16),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text('${name(r['user_id'])} · ${r['work_date']}'),Text('${clockLabel(DateTime.parse(r['check_in']))} → ${clockLabel(r['check_out']==null?null:DateTime.parse(r['check_out']))}'),Text(r['reason']),Row(children:[TextButton(onPressed:busy?null:()=>review(r,false),child:const Text('Từ chối')),FilledButton(onPressed:busy?null:()=>review(r,true),child:const Text('Duyệt'))])]))),
    ],
   ])));
 }
 void move(int d){setState(()=>month=DateTime(month.year,month.month+d));load();}
 Future<void> review(Map<String,dynamic> r,bool yes) async {
  final ok=await showDialog<bool>(context:context,builder:(ctx)=>AlertDialog(title:Text(yes?'Duyệt sửa công?':'Từ chối yêu cầu?'),content:Text('${name(r['user_id'])} · ${r['work_date']}\n${r['reason']}'),actions:[TextButton(onPressed:()=>Navigator.pop(ctx,false),child:const Text('Hủy')),FilledButton(onPressed:()=>Navigator.pop(ctx,true),child:const Text('Xác nhận'))]));
  if(ok==true)await act(()async{await db.rpc('review_correction',params:{'p_id':r['id'],'p_approve':yes});});
 }
 Widget attendanceTile(Map<String,dynamic> r){final a=Attendance.fromJson(r);return Card(child:ListTile(title:Text('${name(r['user_id'])} · ${a.workDate}'),subtitle:Text('${clockLabel(a.checkIn)} → ${clockLabel(a.checkOut)}'),trailing:Text(a.checkOut==null?'Đang làm':durationLabel(a.completedDuration)),onTap:busy?null:()=>editAttendance(context,people:people,record:r,onSave:load)));}
 Widget stat(String label,String value,IconData icon)=>SizedBox(width:155,child:Card(child:Padding(padding:const EdgeInsets.all(16),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Icon(icon,color:Colors.tealAccent),const SizedBox(height:12),Text(value,style:const TextStyle(fontSize:24,fontWeight:FontWeight.bold)),Text(label)]))));
}
String auditLabel(dynamic s)=>switch(s){'employee_update'=>'Sửa nhân viên','attendance_update'=>'Sửa công','request_review'=>'Duyệt yêu cầu',_=>'Thay đổi'};
String csvCell(Object? value){var s='${value??''}';if(RegExp(r'^[\s]*[=+@-]').hasMatch(s))s="'$s";return '"${s.replaceAll('"','""')}"';}
String reportCsv(List<Map<String,dynamic>> rows,Map<String,String> names)=>[
 ['Nhân viên','Ngày','Giờ vào (VN)','Giờ ra (VN)','Phút làm','Trạng thái'].map(csvCell).join(','),
 for(final r in rows) [names[r['user_id']]??'',r['work_date'],vietnamTime(DateTime.parse(r['check_in'])).toString().substring(0,16),r['check_out']==null?'':vietnamTime(DateTime.parse(r['check_out'])).toString().substring(0,16),Attendance.fromJson(r).completedDuration.inMinutes,r['check_out']==null?'Chưa ra':'Hoàn tất'].map(csvCell).join(',')
].join('\r\n');
Future<void> editAttendance(BuildContext context,{required List<Map<String,dynamic>> people,Map<String,dynamic>? record,required Future<void> Function() onSave,bool request=false}) async {
 final saved=await Navigator.push<bool>(context,MaterialPageRoute(builder:(_)=>AttendanceEditor(people:people,record:record,request:request)));
 if(saved==true)await onSave();
}
class AttendanceEditor extends StatefulWidget {
 const AttendanceEditor({required this.people,this.record,this.request=false,super.key});
 final List<Map<String,dynamic>> people; final Map<String,dynamic>? record; final bool request;
 @override State<AttendanceEditor> createState()=>_AttendanceEditorState();
}
class _AttendanceEditorState extends State<AttendanceEditor>{
 late String user; late TextEditingController date,cin,cout,reason; bool busy=false;String? error;
 @override void initState(){super.initState();final r=widget.record;
 user=r?['user_id']??(widget.request?db.auth.currentUser!.id:widget.people.firstOrNull?['id']??'');
 date=TextEditingController(text:r?['work_date']??dateKey(DateTime.now()));
 cin=TextEditingController(text:r==null?'08:00':clockLabel(DateTime.parse(r['check_in'])));
 cout=TextEditingController(text:r==null?'17:00':r['check_out']==null?'':clockLabel(DateTime.parse(r['check_out'])));
 reason=TextEditingController();
 }
 @override void dispose(){date.dispose();cin.dispose();cout.dispose();reason.dispose();super.dispose();}
 Future<void> save() async {
  setState((){busy=true;error=null;});
  try{
   final times=parseWorkTimes(date.text,cin.text,cout.text);
   if(user.isEmpty||reason.text.trim().length<3)throw const FormatException();
   final params=<String,dynamic>{'p_date':date.text.trim(),'p_in':times.$1.toIso8601String(),'p_out':times.$2?.toIso8601String(),'p_reason':reason.text.trim()};
   if(!widget.request)params['p_user']=user;
   await db.rpc(widget.request?'request_correction':'save_attendance',params:params);
   if(mounted)Navigator.pop(context,true);
  }catch(e){if(mounted)setState(()=>error=e is FormatException?'Nhập ngày YYYY-MM-DD, giờ HH:mm và lý do ít nhất 3 ký tự.':managementError(e));}
  finally{if(mounted)setState(()=>busy=false);}
 }
 @override Widget build(BuildContext context)=>Scaffold(appBar:AppBar(title:Text(widget.request?'Yêu cầu sửa công':'Nhập / sửa công')),body:ListView(padding:const EdgeInsets.all(20),children:[
 if(!widget.request)DropdownButtonFormField<String>(initialValue:user.isEmpty?null:user,decoration:const InputDecoration(labelText:'Nhân viên'),items:[for(final p in widget.people)DropdownMenuItem(value:p['id'] as String,child:Text(p['display_name']))],onChanged:busy?null:(v)=>setState(()=>user=v!)),
 const SizedBox(height:16),TextField(controller:date,enabled:!busy,decoration:const InputDecoration(labelText:'Ngày bắt đầu (YYYY-MM-DD)')),
 const SizedBox(height:16),TextField(controller:cin,enabled:!busy,decoration:const InputDecoration(labelText:'Giờ vào (HH:mm)')),
 const SizedBox(height:16),TextField(controller:cout,enabled:!busy,decoration:const InputDecoration(labelText:'Giờ ra (HH:mm), bỏ trống nếu chưa ra')),
 const Padding(padding:EdgeInsets.symmetric(vertical:12),child:Text('Giờ Việt Nam. Giờ ra nhỏ hơn giờ vào được hiểu là ngày hôm sau. Thay đổi giờ không tự trừ giờ nghỉ.')),
 TextField(controller:reason,enabled:!busy,maxLength:1000,maxLines:3,decoration:const InputDecoration(labelText:'Lý do chỉnh sửa')),
 if(error!=null)ErrorNotice(error!),FilledButton(onPressed:busy?null:save,child:Text(busy?'Đang lưu…':widget.request?'Gửi quản lý duyệt':'Lưu thay đổi')),
 ]));
}
(DateTime,DateTime?) parseWorkTimes(String date,String cin,String cout){
 if(!RegExp(r'^\d{4}-\d{2}-\d{2}$').hasMatch(date))throw const FormatException();
 DateTime parse(String time){
  if(!RegExp(r'^([01]\d|2[0-3]):[0-5]\d$').hasMatch(time))throw const FormatException();
  final d=DateTime.parse('${date}T$time:00+07:00').toUtc();
  if(dateKey(d)!=date)throw const FormatException();
  return d;
 }
 final start=parse(cin); DateTime? end=cout.trim().isEmpty?null:parse(cout);
 if(end!=null&&end.isBefore(start))end=end.add(const Duration(days:1));
 return(start,end);
}
class RequestsPage extends StatefulWidget{
 const RequestsPage({super.key});
 @override State<RequestsPage> createState()=>_RequestsPageState();
}
class _RequestsPageState extends State<RequestsPage>{
 List<Map<String,dynamic>> rows=[];bool busy=false;String? error;
 @override void initState(){super.initState();load();}
 Future<void> load() async {
 setState(()=>busy=true);
 try{final r=await db.from('correction_requests').select().eq('user_id',db.auth.currentUser!.id).order('created_at',ascending:false).limit(100);if(mounted)setState((){rows=r;error=null;});}
 catch(e){if(mounted)setState(()=>error=managementError(e));}
 finally{if(mounted)setState(()=>busy=false);}
 }
 @override Widget build(BuildContext context)=>Scaffold(appBar:AppBar(title:const Text('Yêu cầu sửa công')),floatingActionButton:FloatingActionButton.extended(onPressed:busy?null:()=>editAttendance(context,people:const [],request:true,onSave:load),icon:const Icon(Icons.add),label:const Text('Gửi yêu cầu')),body:RefreshIndicator(onRefresh:load,child:ListView(padding:const EdgeInsets.fromLTRB(20,20,20,100),children:[
 if(busy)const LinearProgressIndicator(),if(error!=null)ErrorNotice(error!),
 const Text('100 yêu cầu gần nhất. Công chỉ thay đổi khi quản lý duyệt.'),
 if(rows.isEmpty&&!busy)const Padding(padding:EdgeInsets.all(24),child:Text('Chưa có yêu cầu.')),
 for(final r in rows)Card(child:ListTile(title:Text('${r['work_date']} · ${switch(r['status']){'approved'=>'Đã duyệt','rejected'=>'Từ chối',_=>'Chờ duyệt'}}'),subtitle:Text('${clockLabel(DateTime.parse(r['check_in']))} → ${clockLabel(r['check_out']==null?null:DateTime.parse(r['check_out']))}\n${r['reason']}'))),
 ])));
}
