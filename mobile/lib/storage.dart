import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

const vault = FlutterSecureStorage();
class SecureSessionStorage extends LocalStorage {
  SecureSessionStorage(this.project);
  final String project;
  String get _key => 'session:$project';
  @override
  Future<void> initialize() async {}
  @override
  Future<bool> hasAccessToken() async => await accessToken() != null;
  @override
  Future<String?> accessToken() => vault.read(key: _key);
  @override
  Future<void> persistSession(String persistSessionString) =>
      vault.write(key: _key, value: persistSessionString);
  @override
  Future<void> removePersistedSession() => vault.delete(key: _key);
}
