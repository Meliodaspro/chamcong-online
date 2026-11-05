// All attendance dates are interpreted in Vietnam, independent of device timezone.
DateTime vietnamTime(DateTime value) => value.toUtc().add(const Duration(hours: 7));
String two(int n) => n.toString().padLeft(2, '0');
String monthKey(DateTime month) => '${month.year}-${two(month.month)}-01';
String dateKey(DateTime value) {
  final d = vietnamTime(value);
  return '${d.year}-${two(d.month)}-${two(d.day)}';
}
String clockLabel(DateTime? value) {
  if (value == null) return '—';
  final d = vietnamTime(value);
  return '${two(d.hour)}:${two(d.minute)}';
}
String durationLabel(Duration duration) =>
    '${duration.inHours}h ${two(duration.inMinutes.remainder(60))}p';

class Attendance {
  const Attendance({required this.id, required this.workDate,
    required this.checkIn, this.checkOut});
  factory Attendance.fromJson(Map<String, dynamic> json) => Attendance(
    id: json['id'] as String,
    workDate: json['work_date'] as String,
    checkIn: DateTime.parse(json['check_in'] as String),
    checkOut: json['check_out'] == null ? null : DateTime.parse(json['check_out'] as String),
  );
  final String id;
  final String workDate;
  final DateTime checkIn;
  final DateTime? checkOut;
  Duration get completedDuration => checkOut?.difference(checkIn) ?? Duration.zero;
}

class Overview {
  Overview({required this.today, required this.name, required this.records,
    required this.serverTime, this.active, this.todayRecord});
  factory Overview.fromJson(Map<String, dynamic> json) => Overview(
    today: json['today'] as String,
    name: json['display_name'] as String,
    serverTime: DateTime.parse(json['server_time'] as String),
    todayRecord: json['today_record'] == null ? null : Attendance.fromJson(Map<String, dynamic>.from(json['today_record'] as Map)),
    active: json['active'] == null ? null : Attendance.fromJson(Map<String, dynamic>.from(json['active'] as Map)),
    records: (json['records'] as List).map((e) => Attendance.fromJson(Map<String, dynamic>.from(e as Map))).toList(),
  );
  final String today;
  final String name;
  final DateTime serverTime;
  final Attendance? active;
  final Attendance? todayRecord;
  final List<Attendance> records;
  Duration get total => records.fold(Duration.zero, (sum, row) => sum + row.completedDuration);
  int get completedDays => records.where((row) => row.checkOut != null).length;
}
