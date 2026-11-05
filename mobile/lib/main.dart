import 'dart:async';
import 'dart:convert';
import 'dart:math';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'models.dart';
import 'storage.dart';

const ink = Color(0xFF101C30);
const teal = Color(0xFF12BFA4);
String projectHost = '';
const defaultProjectUrl = 'https://dakfmywklmcynwavpepu.supabase.co';
// Client key is public. Row-level security protects employee data.
const defaultPublishableKey = 'sb_publishable_8qIRFhT1Dxt45vAvHirWtA_qwtzzTCt';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const AttendanceApp());
}

bool validUrl(String value) {
  final uri = Uri.tryParse(value.trim());
  return uri != null && uri.scheme == 'https' && uri.host.isNotEmpty &&
      uri.userInfo.isEmpty && (uri.path.isEmpty || uri.path == '/') &&
      !uri.hasQuery && !uri.hasFragment;
}
bool validPublicKey(String value) {
  if (value.startsWith('sb_publishable_')) return value.length > 20;
  // Old anon JWTs are supported. Reject service-role or other privileged keys.
  try {
    final payload = jsonDecode(utf8.decode(base64Url.decode(base64Url.normalize(value.split('.')[1])))) as Map;
    return payload['role'] == 'anon';
  } catch (_) { return false; }
}
String friendlyError(Object error) {
  final raw = error.toString();
  const messages = {
    'ACCOUNT_DISABLED': 'Tài khoản chưa được kích hoạt hoặc đã bị vô hiệu hóa. Liên hệ quản lý.',
    'AUTH_REQUIRED': 'Phiên đăng nhập đã hết hạn. Hãy đăng nhập lại.',
    'ALREADY_CHECKED_IN': 'Bạn đang có ca chưa kết thúc. Hãy tải lại dữ liệu.',
    'DAY_COMPLETED': 'Bạn đã hoàn tất ca hôm nay.',
    'NO_OPEN_SESSION': 'Không có ca đang làm. Hãy tải lại dữ liệu.',
    'REQUEST_MISMATCH': 'Yêu cầu không khớp. Hãy liên hệ quản lý.',
    'Invalid login credentials': 'Email hoặc mật khẩu chưa đúng.',
    'Email not confirmed': 'Email chưa được xác nhận. Liên hệ quản lý.',
    'over_email_send_rate_limit': 'Đã gửi quá nhiều email. Vui lòng thử lại sau.',
  };
  for (final entry in messages.entries) {
    if (raw.contains(entry.key)) return entry.value;
  }
  if (error is AuthException) return 'Không đăng nhập được. Kiểm tra tài khoản hoặc thử lại sau.';
  return 'Không kết nối được. Kiểm tra mạng và cấu hình Supabase rồi thử lại.';
}

class AttendanceApp extends StatefulWidget {
  const AttendanceApp({super.key});
  @override
  State<AttendanceApp> createState() => _AttendanceAppState();
}
class _AttendanceAppState extends State<AttendanceApp> {
  bool _loading = true;
  bool _ready = false;
  String? _error;
  @override
  void initState() { super.initState(); unawaited(_restore()); }
  Future<void> _restore() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final url = prefs.getString('supabase_url') ?? defaultProjectUrl;
      final key = prefs.getString('supabase_key') ?? defaultPublishableKey;
      if (validUrl(url) && validPublicKey(key)) await _connect(url, key);
    } catch (_) { _error = 'Không thể khởi tạo kết nối. Hãy kiểm tra cấu hình.'; }
    if (mounted) setState(() => _loading = false);
  }
  Future<void> _connect(String url, String key) async {
    await Supabase.initialize(
      url: url.trim().replaceAll(RegExp(r'/$'), ''),
      publishableKey: key.trim(),
      debug: false,
      authOptions: FlutterAuthClientOptions(
        localStorage: SecureSessionStorage(Uri.parse(url).host),
        detectSessionInUri: false,
      ),
    );
    projectHost = Uri.parse(url).host;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('supabase_url', url.trim());
    await prefs.setString('supabase_key', key.trim());
    if (mounted) setState(() { _ready = true; _error = null; });
  }
  @override
  Widget build(BuildContext context) => MaterialApp(
    debugShowCheckedModeBanner: false,
    title: 'Chấm Công',
    theme: ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      colorScheme: ColorScheme.fromSeed(seedColor: teal, brightness: Brightness.dark),
      scaffoldBackgroundColor: const Color(0xFF091220),
      inputDecorationTheme: const InputDecorationTheme(border: OutlineInputBorder()),
      cardTheme: const CardThemeData(color: ink, elevation: 0),
    ),
    home: _loading ? const Scaffold(body: Center(child: CircularProgressIndicator()))
      : !_ready ? SetupPage(onConnect: _connect, initialError: _error)
      : const AuthGate(),
  );
}

class SetupPage extends StatefulWidget {
  const SetupPage({required this.onConnect, this.initialError, super.key});
  final Future<void> Function(String, String) onConnect;
  final String? initialError;
  @override
  State<SetupPage> createState() => _SetupPageState();
}
class _SetupPageState extends State<SetupPage> {
  final _url = TextEditingController();
  final _key = TextEditingController();
  bool _busy = false;
  String? _error;
  @override
  void dispose() { _url.dispose(); _key.dispose(); super.dispose(); }
  Future<void> _save() async {
    if (!validUrl(_url.text) || !validPublicKey(_key.text.trim())) {
      setState(() => _error = 'Nhập Project URL HTTPS và publishable key (hoặc anon key). Không dùng secret/service_role key.');
      return;
    }
    setState(() { _busy = true; _error = null; });
    try { await widget.onConnect(_url.text.trim(), _key.text.trim()); }
    catch (_) { if (mounted) setState(() => _error = 'Không thiết lập được. Kiểm tra URL/key và kết nối mạng.'); }
    finally { if (mounted) setState(() => _busy = false); }
  }
  @override
  Widget build(BuildContext context) => Scaffold(body: SafeArea(child: Center(child: SingleChildScrollView(
    padding: const EdgeInsets.all(24), child: ConstrainedBox(constraints: const BoxConstraints(maxWidth: 460), child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        const Icon(Icons.cloud_done_outlined, size: 64, color: teal),
        const SizedBox(height: 24),
        Text('Kết nối nơi làm việc', style: Theme.of(context).textTheme.headlineMedium),
        const SizedBox(height: 12),
        const Text('Dán thông tin dự án Supabase của bạn. Chỉ cần thiết lập một lần trên điện thoại này.'),
        const SizedBox(height: 28),
        TextField(controller: _url, enabled: !_busy, keyboardType: TextInputType.url,
          autocorrect: false, decoration: const InputDecoration(labelText: 'Project URL', hintText: 'https://…supabase.co')),
        const SizedBox(height: 16),
        TextField(controller: _key, enabled: !_busy, autocorrect: false,
          decoration: const InputDecoration(labelText: 'Publishable key / anon key')),
        const SizedBox(height: 16),
        if (_error ?? widget.initialError case final String message) ErrorNotice(message),
        FilledButton(onPressed: _busy ? null : _save, child: Text(_busy ? 'Đang kết nối…' : 'Lưu và tiếp tục')),
        const SizedBox(height: 16),
        const Text('Chưa có dự án? Tạo tại supabase.com, chạy schema.sql và tạo tài khoản đăng nhập theo hướng dẫn đi kèm.', style: TextStyle(color: Colors.white60)),
      ],
    )),
  ))));
}

class AuthGate extends StatelessWidget {
  const AuthGate({super.key});
  @override
  Widget build(BuildContext context) {
    final auth = Supabase.instance.client.auth;
    return StreamBuilder<AuthState>(stream: auth.onAuthStateChange,
      builder: (context, snapshot) => auth.currentSession == null
        ? const LoginPage() : HomePage(key: ValueKey(auth.currentUser!.id)),
    );
  }
}

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});
  @override
  State<LoginPage> createState() => _LoginPageState();
}
class _LoginPageState extends State<LoginPage> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _busy = false;
  bool _hidden = true;
  String? _error;
  @override
  void dispose() { _email.dispose(); _password.dispose(); super.dispose(); }
  Future<void> _login() async {
    if (!_email.text.contains('@') || _password.text.isEmpty) {
      setState(() => _error = 'Nhập email và mật khẩu được quản lý cấp.'); return;
    }
    FocusScope.of(context).unfocus();
    setState(() { _busy = true; _error = null; });
    try {
      await Supabase.instance.client.auth.signInWithPassword(email: _email.text.trim(), password: _password.text);
      _password.clear();
    } catch (e) { if (mounted) setState(() => _error = friendlyError(e)); }
    finally { if (mounted) setState(() => _busy = false); }
  }
  @override
  Widget build(BuildContext context) => Scaffold(body: SafeArea(child: Center(child: SingleChildScrollView(
    padding: const EdgeInsets.all(24), child: ConstrainedBox(constraints: const BoxConstraints(maxWidth: 460),
    child: AutofillGroup(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      const Align(alignment: Alignment.centerLeft, child: Icon(Icons.fingerprint, size: 68, color: teal)),
      const SizedBox(height: 28),
      Text('Chấm Công', style: Theme.of(context).textTheme.displaySmall?.copyWith(fontWeight: FontWeight.bold)),
      const SizedBox(height: 8),
      const Text('Một ngày làm việc, ghi nhận rõ ràng.', style: TextStyle(color: Colors.white60)),
      const SizedBox(height: 36),
      TextField(controller: _email, enabled: !_busy, keyboardType: TextInputType.emailAddress,
        autofillHints: const [AutofillHints.username], autocorrect: false,
        decoration: const InputDecoration(labelText: 'Email', prefixIcon: Icon(Icons.mail_outline))),
      const SizedBox(height: 16),
      TextField(controller: _password, enabled: !_busy, obscureText: _hidden,
        autofillHints: const [AutofillHints.password], onSubmitted: (_) { if (!_busy) _login(); },
        decoration: InputDecoration(labelText: 'Mật khẩu', prefixIcon: const Icon(Icons.lock_outline),
          suffixIcon: IconButton(onPressed: () => setState(() => _hidden = !_hidden),
            tooltip: _hidden ? 'Hiện mật khẩu' : 'Ẩn mật khẩu', icon: Icon(_hidden ? Icons.visibility_outlined : Icons.visibility_off_outlined)))),
      const SizedBox(height: 16),
      if (_error case final String message) ErrorNotice(message),
      FilledButton(onPressed: _busy ? null : _login,
        child: Padding(padding: const EdgeInsets.all(12), child: Text(_busy ? 'Đang đăng nhập…' : 'Đăng nhập'))),
      const SizedBox(height: 24),
      const Text('Tài khoản do quản lý cấp. Nếu quên mật khẩu, liên hệ quản lý để đặt lại.', style: TextStyle(color: Colors.white60)),
      const SizedBox(height: 12),
      TextButton(onPressed: _busy ? null : () => showDialog<void>(context: context, builder: (context) => AlertDialog(
        title: const Text('Đổi nơi làm việc'),
        content: const Text('Để nhập lại URL/key, vào Cài đặt Android → Ứng dụng → Chấm Công → Bộ nhớ → Xóa dữ liệu. Dữ liệu công trên server vẫn được giữ.'),
        actions: [TextButton(onPressed: () => Navigator.pop(context), child: const Text('Đã hiểu'))],
      )), child: const Text('Cần đổi cấu hình kết nối?')),
    ])),
  )))));
}

class HomePage extends StatefulWidget {
  const HomePage({super.key});
  @override
  State<HomePage> createState() => _HomePageState();
}
class _HomePageState extends State<HomePage> with WidgetsBindingObserver {
  final _client = Supabase.instance.client;
  late DateTime _month;
  Overview? _data;
  String? _error;
  Map<String, dynamic>? _pending;
  bool _loading = true;
  bool _busy = false;
  int _tab = 0;
  int _generation = 0;
  String get _pendingKey => 'pending:${projectHost}:${_client.auth.currentUser!.id}';
  @override
  void initState() {
    super.initState(); WidgetsBinding.instance.addObserver(this);
    final now = vietnamTime(DateTime.now());
    _month = DateTime(now.year, now.month);
    unawaited(_start());
  }
  @override
  void dispose() { WidgetsBinding.instance.removeObserver(this); super.dispose(); }
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && !_busy) unawaited(_load());
  }
  Future<void> _start() async {
    try {
      final saved = await vault.read(key: _pendingKey);
      if (saved != null) _pending = Map<String, dynamic>.from(jsonDecode(saved) as Map);
      await _load(initial: true);
    } catch (_) { if (mounted) setState(() { _loading = false; _error = 'Không đọc được bộ nhớ an toàn. Hãy mở khóa điện thoại và thử lại.'; }); }
  }
  Future<void> _load({bool initial = false}) async {
    final generation = ++_generation;
    final requested = _month;
    if (mounted) setState(() { _loading = true; _error = null; });
    try {
      var raw = await _client.rpc('month_overview', params: {'p_month': monthKey(requested)});
      var data = Overview.fromJson(Map<String, dynamic>.from(raw as Map));
      if (initial) {
        final server = vietnamTime(data.serverTime);
        final actual = DateTime(server.year, server.month);
        if (monthKey(actual) != monthKey(requested)) {
          raw = await _client.rpc('month_overview', params: {'p_month': monthKey(actual)});
          data = Overview.fromJson(Map<String, dynamic>.from(raw as Map));
        }
        if (mounted && generation == _generation) _month = actual;
      }
      if (mounted && generation == _generation) setState(() => _data = data);
    } catch (e) { if (mounted && generation == _generation) setState(() { _error = friendlyError(e); _data = null; }); }
    finally { if (mounted && generation == _generation) setState(() => _loading = false); }
  }
  String _requestId() {
    final random = Random.secure();
    final bytes = List.generate(16, (_) => random.nextInt(256));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    final h = bytes.map((n) => n.toRadixString(16).padLeft(2, '0')).join();
    return '${h.substring(0,8)}-${h.substring(8,12)}-${h.substring(12,16)}-${h.substring(16,20)}-${h.substring(20)}';
  }
  Future<void> _punch(String action) async {
    if (_busy || _loading) return;
    if (_pending == null) {
      final confirmed = await showDialog<bool>(context: context, builder: (context) => AlertDialog(
        title: Text(action == 'in' ? 'Bắt đầu ca làm?' : 'Kết thúc ca làm?'),
        content: const Text('Thời gian sẽ được ghi nhận theo server, múi giờ Việt Nam.'),
        actions: [TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Hủy')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Xác nhận'))],
      ));
      if (confirmed != true || !mounted) return;
    }
    if (_busy || !mounted) return;
    setState(() { _busy = true; _error = null; });
    try {
      _pending ??= {'p_action': action, 'p_request_id': _requestId()};
      await vault.write(key: _pendingKey, value: jsonEncode(_pending));
      await _client.rpc('punch', params: _pending!);
      await vault.delete(key: _pendingKey);
      _pending = null;
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Đã ghi nhận công trên server.')));
    } on PostgrestException catch (e) {
      // Explicit server rejection means the transaction did not commit.
      if (e.code == 'P0001') { await vault.delete(key: _pendingKey); _pending = null; }
      if (mounted) setState(() => _error = friendlyError(e));
    } catch (_) {
      if (mounted) setState(() => _error = 'Chưa xác nhận được kết quả. Bấm “Kiểm tra / gửi lại” để thử lại cùng yêu cầu, tránh chấm trùng.');
    } finally { if (mounted) setState(() => _busy = false); }
    // Keep the punch error visible after refreshing status.
    final punchError = _error;
    await _load();
    if (mounted && punchError != null) setState(() => _error = punchError);
  }
  Future<void> _logout() async {
    if (_busy) return;
    try { await _client.auth.signOut(scope: SignOutScope.local); }
    catch (_) { if (mounted) setState(() => _error = 'Chưa đăng xuất được. Vui lòng thử lại.'); }
  }
  void _moveMonth(int delta) {
    if (_busy || _loading) return;
    setState(() => _month = DateTime(_month.year, _month.month + delta));
    unawaited(_load());
  }
  @override
  Widget build(BuildContext context) {
    final data = _data;
    return Scaffold(
      appBar: AppBar(title: const Text('Chấm Công'), actions: [
        IconButton(onPressed: _busy || _loading ? null : _load, tooltip: 'Tải lại', icon: const Icon(Icons.refresh)),
        IconButton(onPressed: _busy ? null : _logout, tooltip: 'Đăng xuất', icon: const Icon(Icons.logout)),
      ]),
      bottomNavigationBar: NavigationBar(selectedIndex: _tab,
        onDestinationSelected: (value) => setState(() => _tab = value),
        destinations: const [NavigationDestination(icon: Icon(Icons.fingerprint), label: 'Chấm công'),
          NavigationDestination(icon: Icon(Icons.calendar_month_outlined), label: 'Bảng công')]),
      body: SafeArea(child: RefreshIndicator(onRefresh: () async { if (!_busy) await _load(); }, child: ListView(
        physics: const AlwaysScrollableScrollPhysics(), padding: const EdgeInsets.all(20), children: [
          if (_loading) const LinearProgressIndicator(),
          if (_error case final String message) ErrorNotice(message),
          if (_pending != null) Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              const Text('Có yêu cầu chưa xác nhận kết quả. Chưa tạo yêu cầu mới cho đến khi xử lý xong.'),
              const SizedBox(height: 8),
              FilledButton(onPressed: _busy || _loading ? null : () => _punch(_pending!['p_action'] as String),
                child: Text(_busy ? 'Đang kiểm tra…' : 'Kiểm tra / gửi lại')),
            ]))),
          if (data != null && _tab == 0) ...[
            const SizedBox(height: 16),
            Text('Xin chào, ${data.name}', style: Theme.of(context).textTheme.headlineSmall),
            const SizedBox(height: 8),
            Text('Ngày ${data.today.split('-').reversed.join('/')} · Giờ Việt Nam', style: const TextStyle(color: Colors.white60)),
            const SizedBox(height: 24),
            _todayCard(data),
            const SizedBox(height: 24),
            const ListTile(contentPadding: EdgeInsets.zero, leading: Icon(Icons.verified_user_outlined, color: teal),
              title: Text('Đồng bộ trực tuyến'), subtitle: Text('Chỉ báo thành công khi server đã ghi nhận. Mỗi ngày một ca; hỗ trợ ra ca qua đêm.')),
            const ListTile(contentPadding: EdgeInsets.zero, leading: Icon(Icons.info_outline),
              title: Text('Bản thử nghiệm 0.1'), subtitle: Text('Chưa tính lương, giờ nghỉ, tăng ca hoặc xác minh vị trí. Khi quên chấm, liên hệ quản lý.')),
          ],
          if (data != null && _tab == 1) ...[
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              IconButton(onPressed: _busy || _loading ? null : () => _moveMonth(-1), icon: const Icon(Icons.chevron_left), tooltip: 'Tháng trước'),
              Text('Tháng ${two(_month.month)}/${_month.year}', style: Theme.of(context).textTheme.titleLarge),
              IconButton(onPressed: _busy || _loading ? null : () => _moveMonth(1), icon: const Icon(Icons.chevron_right), tooltip: 'Tháng sau'),
            ]),
            Card(child: Padding(padding: const EdgeInsets.all(20), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('TỔNG GIỜ ĐÃ HOÀN TẤT', style: TextStyle(color: Colors.white60)),
              const SizedBox(height: 10),
              Text(durationLabel(data.total), style: Theme.of(context).textTheme.headlineLarge?.copyWith(color: teal)),
              Text('${data.completedDays} ca hoàn tất · Chưa trừ giờ nghỉ'),
            ]))),
            const SizedBox(height: 16),
            if (data.records.isEmpty) const Padding(padding: EdgeInsets.all(32), child: Center(child: Text('Chưa có dữ liệu tháng này.'))),
            for (final row in data.records) Card(child: ListTile(
              leading: const Icon(Icons.event_available_outlined, color: teal),
              title: Text(row.workDate.split('-').reversed.join('/')),
              subtitle: Text('${clockLabel(row.checkIn)} → ${clockLabel(row.checkOut)}${row.checkOut != null && dateKey(row.checkOut!) != row.workDate ? ' (qua ngày)' : ''}'),
              trailing: Text(row.checkOut == null ? 'Chưa ra ca' : durationLabel(row.completedDuration)),
            )),
            const SizedBox(height: 12),
            const Text('Ca qua đêm được tính vào ngày bắt đầu ca. Tổng giờ chỉ cộng ca đã có giờ ra.', style: TextStyle(color: Colors.white60)),
          ],
          if (!_loading && data == null) OutlinedButton(onPressed: _busy ? null : _load, child: const Text('Thử tải lại')),
        ],
      ))),
    );
  }
  Widget _todayCard(Overview data) {
    final row = data.active ?? data.todayRecord;
    final working = data.active != null;
    final done = !working && data.todayRecord?.checkOut != null;
    return Card(child: Padding(padding: const EdgeInsets.all(24), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Icon(working ? Icons.timelapse : done ? Icons.task_alt : Icons.work_outline, size: 56, color: teal),
      const SizedBox(height: 20),
      Text(working ? 'Bạn đang trong ca làm' : done ? 'Đã hoàn tất hôm nay' : 'Sẵn sàng cho ngày mới', textAlign: TextAlign.center,
        style: Theme.of(context).textTheme.titleLarge),
      const SizedBox(height: 24),
      Row(mainAxisAlignment: MainAxisAlignment.spaceAround, children: [
        Column(children: [const Text('Giờ vào'), Text(clockLabel(row?.checkIn), style: const TextStyle(fontSize: 28))]),
        Column(children: [const Text('Giờ ra'), Text(clockLabel(row?.checkOut), style: const TextStyle(fontSize: 28))]),
      ]),
      if (working && data.active!.workDate != data.today) Padding(padding: const EdgeInsets.only(top: 12),
        child: Text('Ca bắt đầu ngày ${data.active!.workDate}. Bấm ra ca để kết thúc.')),
      const SizedBox(height: 28),
      FilledButton.icon(onPressed: done || _busy || _loading || _pending != null ? null : () => _punch(working ? 'out' : 'in'),
        icon: Icon(working ? Icons.logout : Icons.login),
        label: Padding(padding: const EdgeInsets.symmetric(vertical: 12), child: Text(_busy ? 'Đang ghi nhận…' : done ? 'Đã ghi nhận đủ vào / ra' : working ? 'Chấm ra' : 'Chấm vào'))),
    ])));
  }
}

class ErrorNotice extends StatelessWidget {
  const ErrorNotice(this.message, {super.key});
  final String message;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14), margin: const EdgeInsets.symmetric(vertical: 12),
    decoration: BoxDecoration(color: Colors.orange.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(12)),
    child: Text(message, style: const TextStyle(color: Color(0xFFFFD18A))),
  );
}
