import 'package:flutter_test/flutter_test.dart';
import 'package:excel/excel.dart' as xls;
import 'package:cham_cong/management.dart';
void main(){
 test('XLSX preserves Vietnamese names, numeric minutes and text instead of formulas',(){
  final bytes=reportXlsx([{'id':'r','user_id':'u','work_date':'2026-10-31','check_in':'2026-10-31T01:00:00Z','check_out':'2026-10-31T10:00:00Z'}],{'u':'=Nguyễn Sơn'});
  final sheet=xls.Excel.decodeBytes(bytes)['Bảng công'];
  expect(sheet.rows.length,2);expect(sheet.rows[1][0]!.value,isA<xls.TextCellValue>());expect(sheet.rows[1][4]!.value,xls.IntCellValue(540));
 });

 test('Vietnam overnight shift and date validation',(){
  final t=parseWorkTimes('2026-10-31','22:00','06:00');
  expect(t.$2!.difference(t.$1).inHours,8);
  expect(parseWorkTimes('2026-10-31','08:00','').$2,isNull);
  expect(()=>parseWorkTimes('2026-02-30','08:00','17:00'),throwsFormatException);
  expect(()=>parseWorkTimes('2026-10-31','25:00','17:00'),throwsFormatException);
 });
 test('CSV quotes fields and neutralizes spreadsheet formulas',(){
  expect(csvCell('=1+1'),'"\'=1+1"');
  expect(csvCell('Nguyễn "Sơn"'),'"Nguyễn ""Sơn"""');
  expect(reportCsv([],{}).split('\r\n').length,1);
 });
}
