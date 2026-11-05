import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:cham_cong/design.dart';
import 'package:cham_cong/management.dart';
void main(){
 setUpAll(()async{
  SharedPreferences.setMockInitialValues({});
  await Supabase.initialize(url:'https://test.supabase.co',publishableKey:'test-public-key',httpClient:MockClient((r)async{
   Object value=[];
   if(r.url.path.endsWith('/is_admin')){value=true;}
   if(r.url.path.endsWith('/profiles')){value=[{'id':'user','display_name':'Nguyễn Ngọc Sơn','employee_code':'NV000001','role':'admin','is_active':true,'phone':'','position':'Quản lý'}];}
   return http.Response(jsonEncode(value),200,headers:{'content-type':'application/json; charset=utf-8'});
  }));
 });
 tearDownAll(()async{await Supabase.instance.dispose();});
 testWidgets('Admin navigation works on narrow screen and enlarged text without overflow',(tester)async{
  tester.view.physicalSize=const Size(360,800);tester.view.devicePixelRatio=1;
  addTearDown(tester.view.resetPhysicalSize);addTearDown(tester.view.resetDevicePixelRatio);
  await tester.pumpWidget(MaterialApp(theme:appTheme(),builder:(c,w)=>MediaQuery(data:MediaQuery.of(c).copyWith(textScaler:TextScaler.linear(1.2)),child:w!),home:const AdminPage()));
  await tester.pumpAndSettle();expect(tester.takeException(),isNull);
  expect(find.textContaining('Không thực hiện được'),findsNothing);
  await tester.tap(find.text('Nhân viên').last);await tester.pumpAndSettle();
  expect(find.text('Nguyễn Ngọc Sơn'),findsOneWidget);expect(find.text('Thêm nhân viên'),findsOneWidget);expect(tester.takeException(),isNull);
  await tester.tap(find.text('Bảng công').last);await tester.pumpAndSettle();expect(find.text('Xuất báo cáo'),findsOneWidget);expect(tester.takeException(),isNull);
  await tester.tap(find.text('Phê duyệt').last);await tester.pumpAndSettle();expect(find.text('Đã duyệt'),findsOneWidget);expect(tester.takeException(),isNull);
 });
}
