// ignore_for_file: curly_braces_in_flow_control_structures
import 'dart:convert';
import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:share_plus/share_plus.dart' show Share;
import 'package:cross_file/cross_file.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'models.dart';
import 'design.dart';
import 'accounts.dart';
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
 String staffFilter='all', attendanceFilter='all',requestFilter='pending';
 List<Map<String,dynamic>> todayRows=[];
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
   final history=await db.from('correction_requests').select().neq('status','pending').order('created_at',ascending:false).limit(100);
   final pending=await db.from('correction_requests').select().eq('status','pending').order('created_at');
   final req=[...pending,...history];
   final today=await db.from('attendance_sessions').select().eq('work_date',dateKey(DateTime.now())).order('check_in',ascending:false);
   final a=await db.from('admin_audit').select().order('id',ascending:false).limit(100);
   if(mounted)setState((){people=p;rows=r;requests=req;audit=a;todayRows=today;});
  }catch(e){if(mounted)setState((){error=managementError(e);people=[];rows=[];requests=[];audit=[];});}
  finally{if(mounted)setState(()=>busy=false);}
 }
 String name(dynamic id)=>people.where((p)=>p['id']==id).map((p)=>p['display_name'] as String).firstOrNull??'Nhân viên';
 List<Map<String,dynamic>> get filtered=>rows.where((r)=>(employee==null||r['user_id']==employee)&&(attendanceFilter=='all'||(r['check_out']!=null)==(attendanceFilter=='complete'))).toList()..sort((a,b)=>(b['work_date'] as String).compareTo(a['work_date'] as String));
 Future<void> act(Future<void> Function() fn) async {
  if(busy)return; setState((){busy=true;error=null;});
  try{await fn();}catch(e){if(mounted)setState(()=>error=managementError(e));}
  finally{if(mounted)setState(()=>busy=false);}
  final message=error; await load(); if(mounted&&message!=null)setState(()=>error=message);
 }
 Future<void> editPerson(Map<String,dynamic> p) async {
  final c=<String>[p['display_name']??'',p['employee_code']??'',p['phone']??'',p['position']??''];
  var active=p['is_active'] as bool; var role=p['role'] as String;
  final ok=await showDialog<bool>(context:context,builder:(ctx)=>StatefulBuilder(builder:(ctx,update)=>AlertDialog(title:const Text('Thông tin nhân viên'),content:SingleChildScrollView(child:Column(mainAxisSize:MainAxisSize.min,children:[
   for(var i=0;i<c.length;i++) Padding(padding:const EdgeInsets.only(bottom:12),child:TextFormField(readOnly:i==1,initialValue:c[i],onChanged:(v)=>c[i]=v,decoration:InputDecoration(labelText:['Họ tên','Mã nhân viên','Điện thoại','Chức vụ'][i]))),
   DropdownButtonFormField<String>(initialValue:role,items:const [DropdownMenuItem(value:'employee',child:Text('Nhân viên')),DropdownMenuItem(value:'admin',child:Text('Admin'))],onChanged:(v)=>update(()=>role=v!)),
   SwitchListTile(title:const Text('Đang hoạt động'),value:active,onChanged:(v)=>update(()=>active=v)),
  ])),actions:[TextButton(onPressed:()=>Navigator.pop(ctx,false),child:const Text('Hủy')),FilledButton(onPressed:()=>Navigator.pop(ctx,true),child:const Text('Lưu'))])));
  if(ok==true)await act(()async{await db.rpc('update_employee',params:{'p_id':p['id'],'p_name':c[0],'p_code':c[1],'p_phone':c[2],'p_position':c[3],'p_active':active,'p_role':role});});
 }
 Future<void> export() async {
  final csv=reportCsv(filtered, {for(final p in people) p['id'] as String:p['display_name'] as String});
  final box=context.findRenderObject() as RenderBox?;
  await Share.shareXFiles([XFile.fromData(Uint8List.fromList(utf8.encode('\uFEFF$csv')),mimeType:'text/csv',name:'bang-cong-${monthKey(month)}.csv')],sharePositionOrigin:box==null?null:box.localToGlobal(Offset.zero)&box.size);
 }
 @override Widget build(BuildContext context)=>Scaffold(
 appBar:AppBar(title:Text(['Tổng quan','Nhân viên','Bảng công','Phê duyệt'][tab]),actions:[IconButton(onPressed:busy?null:load,tooltip:'Tải lại',icon:const Icon(Icons.refresh_rounded)),PopupMenuButton<String>(onSelected:(v){if(v=='personal'){Navigator.push(context,MaterialPageRoute<void>(builder:(_)=>const HomePage()));}else if(v=='password'){Navigator.push(context,MaterialPageRoute<void>(builder:(_)=>const PasswordPage()));}else if(v=='audit'){showAudit();}else{db.auth.signOut(scope:SignOutScope.local);}},itemBuilder:(_)=>const [PopupMenuItem(value:'personal',child:Text('Chấm công cá nhân')),PopupMenuItem(value:'password',child:Text('Đổi mật khẩu')),PopupMenuItem(value:'audit',child:Text('Lịch sử quản trị')),PopupMenuItem(value:'logout',child:Text('Đăng xuất'))])]),
 bottomNavigationBar:NavigationBar(selectedIndex:tab,onDestinationSelected:(v)=>setState(()=>tab=v),destinations:const [NavigationDestination(icon:Icon(Icons.grid_view_rounded),label:'Tổng quan'),NavigationDestination(icon:Icon(Icons.people_outline_rounded),label:'Nhân viên'),NavigationDestination(icon:Icon(Icons.calendar_month_outlined),label:'Bảng công'),NavigationDestination(icon:Icon(Icons.task_alt_rounded),label:'Phê duyệt')]),
 floatingActionButton:tab==1?FloatingActionButton.extended(onPressed:busy?null:()=>createAccount(),backgroundColor:brand,foregroundColor:Colors.white,icon:const Icon(Icons.add),label:const Text('Thêm nhân viên')):null,
 body:RefreshIndicator(onRefresh:load,child:ListView(padding:const EdgeInsets.fromLTRB(20,12,20,100),children:[
 if(busy)const LinearProgressIndicator(),if(error!=null)ErrorNotice(error!),
 if(tab==0)...dashboard(),if(tab==1)...employees(),if(tab==2)...reports(),if(tab==3)...approvals(),
 ])));
 List<Widget> dashboard(){
 final completed=rows.where((r)=>r['check_out']!=null).map(Attendance.fromJson).fold(Duration.zero,(Duration s,r)=>s+r.completedDuration);
 final pending=requests.where((r)=>r['status']=='pending').length;
 return [
 DashboardBanner(title:'Một ngày làm việc tốt!',subtitle:'${shortDate(dateKey(DateTime.now()))} · Không gian quản lý nhân sự'),const SizedBox(height:18),
 MetricGrid(items:[(label:'Nhân sự hoạt động',value:'${people.where((p)=>p['is_active']==true).length}',icon:Icons.people_outline,color:brand),(label:'Đang trong ca',value:'${todayRows.where((r)=>r['check_out']==null).length}',icon:Icons.timelapse_rounded,color:const Color(0xFF12805C)),(label:'Chờ phê duyệt',value:'$pending',icon:Icons.fact_check_outlined,color:const Color(0xFFB77813)),(label:'Giờ tháng ${month.month}',value:compactHours(completed),icon:Icons.schedule_rounded,color:const Color(0xFF7C52CC))]),
 const SectionTitle('Thao tác nhanh'),Wrap(spacing:10,runSpacing:10,children:[FilledButton.icon(onPressed:busy?null:()=>createAccount(),icon:const Icon(Icons.person_add_alt),label:const Text('Thêm nhân viên')),OutlinedButton.icon(onPressed:()=>setState(()=>tab=2),icon:const Icon(Icons.insert_chart_outlined),label:const Text('Xem báo cáo'))]),
 SectionTitle('Chấm công hôm nay',action:Text('${todayRows.length} người',style:const TextStyle(color:textMuted,fontSize:12))),
 if(todayRows.isEmpty)const EmptyPanel('Chưa có ai chấm công hôm nay.'),
 for(final r in todayRows)attendanceTile(r),
 SectionTitle('Nhật ký gần đây',action:TextButton(onPressed:showAudit,child:const Text('Xem tất cả'))),
 if(audit.isEmpty)const EmptyPanel('Các thay đổi quản trị sẽ xuất hiện tại đây.'),
 for(final a in audit.take(3))auditTile(a),
 ];
 }
 List<Widget> employees()=>[
 Text('${people.length} thành viên',style:Theme.of(context).textTheme.headlineSmall),const SizedBox(height:6),const Text('Quản lý tài khoản, thông tin và quyền truy cập.',style:TextStyle(color:textMuted)),const SizedBox(height:20),
 TextField(decoration:const InputDecoration(hintText:'Tìm tên hoặc mã nhân viên',prefixIcon:Icon(Icons.search)),onChanged:(v)=>setState(()=>search=v.toLowerCase())),const SizedBox(height:12),
 Wrap(spacing:8,children:[for(final v in ['all','active','inactive'])ChoiceChip(label:Text(switch(v){'active'=>'Đang hoạt động','inactive'=>'Đã khóa',_=>'Tất cả'}),selected:staffFilter==v,onSelected:(_)=>setState(()=>staffFilter=v))]),const SizedBox(height:12),
 for(final p in people.where((p)=>'${p['display_name']} ${p['employee_code']??''}'.toLowerCase().contains(search)&&(staffFilter=='all'||p['is_active']==(staffFilter=='active'))))Card(child:Padding(padding:const EdgeInsets.all(14),child:Row(children:[CircleAvatar(radius:23,backgroundColor:const Color(0xFFEAF0FF),child:Text((p['display_name'] as String).isEmpty?'N':(p['display_name'] as String).characters.first.toUpperCase(),style:const TextStyle(color:brand,fontWeight:FontWeight.bold))),const SizedBox(width:12),Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(p['display_name'],style:const TextStyle(fontWeight:FontWeight.w800,fontSize:15)),const SizedBox(height:4),Text('${p['employee_code']??'Chưa cấp mã'} · ${p['role']=='admin'?'Quản trị viên':'Nhân viên'}',style:const TextStyle(fontSize:12,color:textMuted)),const SizedBox(height:7),StatusPill(p['is_active']==true?'Hoạt động':'Đã khóa',color:p['is_active']==true?const Color(0xFF12805C):const Color(0xFFCA4556))])),PopupMenuButton<String>(enabled:!busy,onSelected:(v){if(v=='edit'){editPerson(p);}else{resetPassword(p);}},itemBuilder:(_)=>[const PopupMenuItem(value:'edit',child:Text('Thông tin & quyền')),if(p['role']=='employee')const PopupMenuItem(value:'password',child:Text('Đặt lại mật khẩu'))])]))),
 ];
 List<Widget> reports(){
 final done=filtered.where((r)=>r['check_out']!=null).map(Attendance.fromJson).fold(Duration.zero,(Duration s,r)=>s+r.completedDuration);
 return [
 Card(child:Padding(padding:const EdgeInsets.all(14),child:Row(mainAxisAlignment:MainAxisAlignment.spaceBetween,children:[IconButton(onPressed:busy?null:()=>move(-1),icon:const Icon(Icons.chevron_left)),Text('Tháng ${two(month.month)} / ${month.year}',style:const TextStyle(fontWeight:FontWeight.w800,fontSize:17)),IconButton(onPressed:busy?null:()=>move(1),icon:const Icon(Icons.chevron_right))]))),const SizedBox(height:12),
 DropdownButtonFormField<String>(initialValue:employee,isExpanded:true,decoration:const InputDecoration(labelText:'Lọc nhân viên'),items:[const DropdownMenuItem<String>(value:null,child:Text('Tất cả nhân viên')),for(final p in people)DropdownMenuItem(value:p['id'] as String,child:Text('${p['employee_code']??''} · ${p['display_name']}',overflow:TextOverflow.ellipsis))],onChanged:busy?null:(v)=>setState(()=>employee=v)),const SizedBox(height:12),
 Wrap(spacing:8,children:[for(final v in ['all','complete','open'])ChoiceChip(label:Text(switch(v){'complete'=>'Đã hoàn tất','open'=>'Thiếu giờ ra',_=>'Tất cả công'}),selected:attendanceFilter==v,onSelected:(_)=>setState(()=>attendanceFilter=v))]),
 const SizedBox(height:18),MetricGrid(items:[(label:'Ca đang xem',value:'${filtered.length}',icon:Icons.event_available_outlined,color:brand),(label:'Giờ đã hoàn tất',value:compactHours(done),icon:Icons.schedule,color:const Color(0xFF12805C))]),const SizedBox(height:16),
 Wrap(spacing:8,runSpacing:8,children:[FilledButton.icon(onPressed:busy?null:()=>export().catchError((Object e){if(mounted)setState(()=>error=managementError(e));}),icon:const Icon(Icons.ios_share),label:const Text('Xuất báo cáo')),OutlinedButton.icon(onPressed:busy?null:()=>editAttendance(context,people:people,onSave:load),icon:const Icon(Icons.edit_calendar),label:const Text('Nhập công'))]),
 const SectionTitle('Chi tiết bảng công'),if(filtered.isEmpty)const EmptyPanel('Không có công phù hợp với bộ lọc.'),for(final r in filtered)attendanceTile(r),
 ];
 }
 List<Widget> approvals()=>[
 Text('Yêu cầu điều chỉnh',style:Theme.of(context).textTheme.headlineSmall),const SizedBox(height:6),const Text('Kiểm tra thời gian và lý do trước khi duyệt.',style:TextStyle(color:textMuted)),const SizedBox(height:16),
 Wrap(spacing:8,children:[for(final v in ['pending','approved','rejected','all'])ChoiceChip(label:Text(v=='all'?'Tất cả':requestLabel(v)),selected:requestFilter==v,onSelected:(_)=>setState(()=>requestFilter=v))]),const SizedBox(height:12),
 if(!requests.any((r)=>requestFilter=='all'||r['status']==requestFilter))const EmptyPanel('Không có yêu cầu trong mục này.'),
 for(final r in requests.where((r)=>requestFilter=='all'||r['status']==requestFilter))Card(child:Padding(padding:const EdgeInsets.all(18),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Row(children:[Expanded(child:Text(name(r['user_id']),style:const TextStyle(fontWeight:FontWeight.w800))),StatusPill(requestLabel(r['status']),color:requestColor(r['status']))]),const SizedBox(height:12),Text('${shortDate(r['work_date'])} · ${clockLabel(DateTime.parse(r['check_in']))} → ${clockLabel(r['check_out']==null?null:DateTime.parse(r['check_out']))}',style:const TextStyle(fontWeight:FontWeight.w600)),const SizedBox(height:8),Text(r['reason'],style:const TextStyle(color:textMuted)),if(r['status']=='pending')...[
 const Divider(height:28),Row(children:[Expanded(child:OutlinedButton(onPressed:busy?null:()=>review(r,false),child:const Text('Từ chối'))),const SizedBox(width:12),Expanded(child:FilledButton(onPressed:busy?null:()=>review(r,true),child:const Text('Duyệt công')))]),
 ]]))),const Padding(padding:EdgeInsets.only(top:16),child:Text('Hiển thị yêu cầu chờ duyệt và 100 yêu cầu đã xử lý gần nhất.',style:TextStyle(color:textMuted,fontSize:12))),
 ];
 Future<void> createAccount()async{final saved=await Navigator.push<bool>(context,MaterialPageRoute(builder:(_)=>const AccountPage()));if(saved==true&&mounted)await load();}
 Future<void> resetPassword(Map<String,dynamic> p)async{await Navigator.push<bool>(context,MaterialPageRoute(builder:(_)=>AccountPage(employee:p)));if(mounted)await load();}
 void showAudit(){Navigator.push(context,MaterialPageRoute<void>(builder:(_)=>Scaffold(appBar:AppBar(title:const Text('Lịch sử quản trị')),body:ListView(padding:const EdgeInsets.all(20),children:[const Text('100 thay đổi gần nhất',style:TextStyle(color:textMuted)),for(final a in audit)auditTile(a)]))));}
 Widget auditTile(Map<String,dynamic> a)=>Card(child:ListTile(leading:const CircleAvatar(backgroundColor:Color(0xFFF0F4FB),child:Icon(Icons.history_rounded,color:brand)),onTap:()=>showDialog<void>(context:context,builder:(ctx)=>AlertDialog(title:const Text('Chi tiết thay đổi'),content:SingleChildScrollView(child:SelectableText(const JsonEncoder.withIndent('  ').convert({'Trước':a['before_value'],'Sau':a['after_value']}))),actions:[TextButton(onPressed:()=>Navigator.pop(ctx),child:const Text('Đóng'))])),title:Text(auditLabel(a['action']),style:const TextStyle(fontWeight:FontWeight.w700,fontSize:14)),subtitle:Text('${name(a['target_id'])} · ${name(a['actor_id'])}\n${vietnamTime(DateTime.parse(a['created_at'])).toString().substring(0,16)}',style:const TextStyle(color:textMuted,fontSize:12)),trailing:const Icon(Icons.chevron_right,size:18)));
 void move(int d){setState(()=>month=DateTime(month.year,month.month+d));load();}
 Future<void> review(Map<String,dynamic> r,bool yes) async {
  final ok=await showDialog<bool>(context:context,builder:(ctx)=>AlertDialog(title:Text(yes?'Duyệt sửa công?':'Từ chối yêu cầu?'),content:Text('${name(r['user_id'])} · ${r['work_date']}\n${r['reason']}'),actions:[TextButton(onPressed:()=>Navigator.pop(ctx,false),child:const Text('Hủy')),FilledButton(onPressed:()=>Navigator.pop(ctx,true),child:const Text('Xác nhận'))]));
  if(ok==true)await act(()async{await db.rpc('review_correction',params:{'p_id':r['id'],'p_approve':yes});});
 }
 Widget attendanceTile(Map<String,dynamic> r){final a=Attendance.fromJson(r);return Card(child:InkWell(borderRadius:BorderRadius.circular(20),onTap:busy?null:()=>editAttendance(context,people:people,record:r,onSave:load),child:Padding(padding:const EdgeInsets.all(16),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Row(children:[Expanded(child:Text(name(r['user_id']),style:const TextStyle(fontWeight:FontWeight.w800))),StatusPill(a.checkOut==null?'Chưa ra ca':durationLabel(a.completedDuration),color:a.checkOut==null?const Color(0xFFB77813):const Color(0xFF12805C))]),const SizedBox(height:8),Row(children:[const Icon(Icons.calendar_today_outlined,size:14,color:textMuted),const SizedBox(width:6),Text(shortDate(a.workDate),style:const TextStyle(color:textMuted,fontSize:12)),const Spacer(),Text('${clockLabel(a.checkIn)} → ${clockLabel(a.checkOut)}',style:const TextStyle(fontWeight:FontWeight.w700,fontSize:13))])]))));}

}
String auditLabel(dynamic s)=>switch(s){'employee_update'=>'Sửa nhân viên','attendance_update'=>'Sửa công','request_review'=>'Duyệt yêu cầu','employee_create'=>'Tạo tài khoản','password_reset'=>'Đặt lại mật khẩu',_=>'Thay đổi'};
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
 Future<void> pickDate()async{
  final initial=DateTime.tryParse(date.text)??DateTime.now();
  final value=await showDatePicker(context:context,initialDate:initial,firstDate:DateTime(2000),lastDate:DateTime(2100));
  if(value!=null&&mounted)setState(()=>date.text='${value.year}-${two(value.month)}-${two(value.day)}');
 }
 Future<void> pickTime(TextEditingController c)async{
  final parts=c.text.split(':');
  final value=await showTimePicker(context:context,initialTime:TimeOfDay(hour:parts.length==2?int.tryParse(parts[0])??8:8,minute:parts.length==2?int.tryParse(parts[1])??0:0));
  if(value!=null&&mounted)setState(()=>c.text='${two(value.hour)}:${two(value.minute)}');
 }
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
 const SizedBox(height:16),TextField(controller:date,enabled:!busy,readOnly:true,onTap:pickDate,decoration:const InputDecoration(labelText:'Ngày bắt đầu (YYYY-MM-DD)')),
 const SizedBox(height:16),TextField(controller:cin,enabled:!busy,readOnly:true,onTap:()=>pickTime(cin),decoration:const InputDecoration(labelText:'Giờ vào (HH:mm)')),
 const SizedBox(height:16),TextField(controller:cout,enabled:!busy,readOnly:true,onTap:()=>pickTime(cout),decoration:InputDecoration(labelText:'Giờ ra (bỏ trống nếu chưa ra)',suffixIcon:IconButton(onPressed:busy?null:()=>setState(()=>cout.clear()),icon:const Icon(Icons.clear)))),
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
