import 'dart:convert';
import 'dart:io';
import 'package:flutter/services.dart';
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
  if(const bool.fromEnvironment('CAPTURE_PREVIEW')){
   final font=FontLoader('PreviewSans')..addFont(Future.value(ByteData.sublistView(await File('/tmp/chamcong-preview.ttf').readAsBytes())));
   await font.load();
  }
  await Supabase.initialize(url:'https://test.supabase.co',publishableKey:'test-public-key',httpClient:MockClient((r)async{
   Object value=[];
   if(r.url.path.endsWith('/is_admin')){value=true;}
   if(r.url.path.endsWith('/profiles')){value=[{'id':'user','display_name':'Nguyễn Ngọc Sơn','employee_code':'NV000001','role':'admin','is_active':true,'phone':'','position':'Quản lý'}];}
   return http.Response(jsonEncode(value),200,request:r,headers:{'content-type':'application/json; charset=utf-8'});
  }));
  await Supabase.instance.client.rpc('is_admin');
  await Supabase.instance.client.from('profiles').select();
 });
 tearDownAll(()async{await Supabase.instance.dispose();});
 testWidgets('Admin navigation works on narrow screen and enlarged text without overflow',(tester)async{
  tester.view.physicalSize=const Size(360,800);tester.view.devicePixelRatio=1;
  addTearDown(tester.view.resetPhysicalSize);addTearDown(tester.view.resetDevicePixelRatio);
  final errors=<Object>[];
  await tester.pumpWidget(MaterialApp(theme:appTheme().copyWith(textTheme:appTheme().textTheme.apply(fontFamily:const bool.fromEnvironment('CAPTURE_PREVIEW')?'PreviewSans':null)),builder:(c,w)=>MediaQuery(data:MediaQuery.of(c).copyWith(textScaler:TextScaler.linear(1.2)),child:w!),home:AdminPage(onLoadError:errors.add)));
  await tester.pumpAndSettle();expect(tester.takeException(),isNull);
  expect(errors,isEmpty,reason:'$errors');
  expect(find.textContaining('Không thực hiện được'),findsNothing);
  if(const bool.fromEnvironment('CAPTURE_PREVIEW'))await expectLater(find.byType(AdminPage),matchesGoldenFile('previews/admin.png'));
  await tester.tap(find.text('Nhân viên').last);await tester.pumpAndSettle();
  expect(find.text('Nguyễn Ngọc Sơn'),findsOneWidget);expect(find.text('Thêm nhân viên'),findsOneWidget);expect(tester.takeException(),isNull);
  await tester.tap(find.text('Bảng công').last);await tester.pumpAndSettle();expect(find.text('Xuất báo cáo'),findsOneWidget);expect(tester.takeException(),isNull);
  await tester.tap(find.text('Phê duyệt').last);await tester.pumpAndSettle();expect(find.text('Đã duyệt'),findsOneWidget);expect(tester.takeException(),isNull);
 });
}
