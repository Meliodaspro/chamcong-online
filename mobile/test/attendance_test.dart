import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:cham_cong/models.dart';
import 'package:cham_cong/main.dart';

void main() {
  test('Vietnam date crosses month at 17:00 UTC', () {
    expect(dateKey(DateTime.parse('2026-01-31T17:05:00Z')), '2026-02-01');
    expect(clockLabel(DateTime.parse('2026-01-31T17:05:00Z')), '00:05');
  });
  test('Overnight duration is assigned to check-in date', () {
    final row = Attendance.fromJson({'id': 'a', 'work_date': '2026-01-31',
      'check_in': '2026-01-31T15:00:00Z', 'check_out': '2026-02-01T01:30:00Z'});
    expect(row.completedDuration, const Duration(hours: 10, minutes: 30));
    expect(row.workDate, '2026-01-31');
  });
  test('Open shift never counts as completed hours', () {
    final view = Overview(today: '2026-02-01', name: 'Sơn',
      serverTime: DateTime.utc(2026, 2, 1), records: [
        Attendance(id: 'a', workDate: '2026-02-01', checkIn: DateTime.utc(2026,2,1)),
        Attendance(id: 'b', workDate: '2026-01-31', checkIn: DateTime.utc(2026,1,31,1), checkOut: DateTime.utc(2026,1,31,9)),
      ]);
    expect(view.total, const Duration(hours: 8));
    expect(view.completedDays, 1);
  });
  test('Only HTTPS project origins and client keys are accepted', () {
    expect(validUrl('https://example.supabase.co'), isTrue);
    expect(validUrl('http://example.supabase.co'), isFalse);
    expect(validUrl('https://user:password@example.com'), isFalse);
    expect(validUrl('https://example.com/path'), isFalse);
    expect(validPublicKey('sb_secret_do_not_put_this_in_an_app'), isFalse);
    expect(validPublicKey('sb_publishable_example_public_key'), isTrue);
    expect(validPublicKey('a.eyJyb2xlIjoic2VydmljZV9yb2xlIn0.b'), isFalse);
    expect(validPublicKey('a.eyJyb2xlIjoiYW5vbiJ9.b'), isTrue);
  });
  testWidgets('Unconfigured app shows setup instead of fake attendance', (tester) async {
    await tester.pumpWidget(const MaterialApp(home: SetupPage(onConnect: noConnect)));
    expect(find.text('Kết nối nơi làm việc'), findsOneWidget);
    expect(find.text('Lưu và tiếp tục'), findsOneWidget);
  });
}
Future<void> noConnect(String url, String key) async {}
