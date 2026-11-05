import 'package:flutter_test/flutter_test.dart';
import 'package:cham_cong/management.dart';
void main(){
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
