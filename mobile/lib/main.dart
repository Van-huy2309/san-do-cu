import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sign_in_with_apple/sign_in_with_apple.dart';

/// Override: flutter run --dart-define=API_URL=http://10.0.2.2:8000/api
const apiUrl = String.fromEnvironment(
  'API_URL',
  defaultValue: 'http://127.0.0.1:8000/api',
);

void main() => runApp(const RelicApp());

class RelicApp extends StatelessWidget {
  const RelicApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Relic',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF7C5CFC),
          brightness: Brightness.dark,
        ),
        useMaterial3: true,
      ),
      home: const Gate(),
    );
  }
}

class Api {
  String? token;

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
      };

  Future<dynamic> get(String path, [Map<String, String>? query]) async {
    final uri = Uri.parse('$apiUrl$path').replace(queryParameters: query);
    final res = await http.get(uri, headers: _headers);
    return jsonDecode(res.body);
  }

  Future<dynamic> post(String path, Map<String, dynamic> body) async {
    final res = await http.post(
      Uri.parse('$apiUrl$path'),
      headers: _headers,
      body: jsonEncode(body),
    );
    return {'status': res.statusCode, 'body': jsonDecode(res.body)};
  }
}

final api = Api();

class Gate extends StatefulWidget {
  const Gate({super.key});
  @override
  State<Gate> createState() => _GateState();
}

class _GateState extends State<Gate> {
  @override
  void initState() {
    super.initState();
    _boot();
  }

  Future<void> _boot() async {
    final prefs = await SharedPreferences.getInstance();
    api.token = prefs.getString('token');
    if (!mounted) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(
        builder: (_) => api.token == null ? const LoginPage() : const HomePage(),
      ),
    );
  }

  @override
  Widget build(BuildContext context) =>
      const Scaffold(body: Center(child: CircularProgressIndicator()));
}

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});
  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final email = TextEditingController();
  final password = TextEditingController();
  final phone = TextEditingController();
  final otp = TextEditingController();
  String? error;

  Future<void> _save(String token) async {
    api.token = token;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('token', token);
    if (!mounted) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => const HomePage()),
    );
  }

  Future<void> _emailLogin() async {
    final res = await api.post('/auth/login', {
      'email': email.text,
      'password': password.text,
    });
    if (res['status'] == 200) {
      await _save(res['body']['token']);
    } else {
      setState(() => error = res['body']['message']?.toString() ?? 'Lỗi đăng nhập');
    }
  }

  Future<void> _sendOtp() async {
    final res = await api.post('/auth/otp/send', {'phone': phone.text});
    setState(() => error = res['body']['debug_code'] != null
        ? 'OTP (log): ${res['body']['debug_code']}'
        : res['body']['message']?.toString());
  }

  Future<void> _verifyOtp() async {
    final res = await api.post('/auth/otp/verify', {
      'phone': phone.text,
      'code': otp.text,
    });
    if (res['status'] == 200) {
      await _save(res['body']['token']);
    } else {
      setState(() => error = res['body']['message']?.toString());
    }
  }

  Future<void> _google() async {
    try {
      final google = GoogleSignIn(scopes: ['email', 'profile']);
      final user = await google.signIn();
      final auth = await user?.authentication;
      if (auth?.idToken == null) return;
      final res = await api.post('/auth/google', {'id_token': auth!.idToken});
      if (res['status'] == 200) await _save(res['body']['token']);
    } catch (e) {
      setState(() => error = e.toString());
    }
  }

  Future<void> _apple() async {
    try {
      final cred = await SignInWithApple.getAppleIDCredential(
        scopes: [AppleIDAuthorizationScopes.email, AppleIDAuthorizationScopes.fullName],
      );
      final res = await api.post('/auth/apple', {'id_token': cred.identityToken});
      if (res['status'] == 200) await _save(res['body']['token']);
    } catch (e) {
      setState(() => error = e.toString());
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Relic')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          if (error != null) Text(error!, style: const TextStyle(color: Colors.orange)),
          TextField(controller: email, decoration: const InputDecoration(labelText: 'Email')),
          TextField(
            controller: password,
            obscureText: true,
            decoration: const InputDecoration(labelText: 'Mật khẩu'),
          ),
          const SizedBox(height: 12),
          FilledButton(onPressed: _emailLogin, child: const Text('Đăng nhập')),
          const Divider(height: 32),
          TextField(controller: phone, decoration: const InputDecoration(labelText: 'SĐT OTP')),
          TextField(controller: otp, decoration: const InputDecoration(labelText: 'Mã OTP')),
          Row(
            children: [
              Expanded(child: OutlinedButton(onPressed: _sendOtp, child: const Text('Gửi OTP'))),
              const SizedBox(width: 8),
              Expanded(child: FilledButton(onPressed: _verifyOtp, child: const Text('Xác nhận'))),
            ],
          ),
          const SizedBox(height: 16),
          OutlinedButton(onPressed: _google, child: const Text('Google')),
          OutlinedButton(onPressed: _apple, child: const Text('Apple')),
        ],
      ),
    );
  }
}

class HomePage extends StatefulWidget {
  const HomePage({super.key});
  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  List items = [];
  String q = '';
  String? error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    Position? pos;
    try {
      final perm = await Geolocator.requestPermission();
      if (perm == LocationPermission.always || perm == LocationPermission.whileInUse) {
        pos = await Geolocator.getCurrentPosition();
      }
    } catch (_) {}
    final query = {
      if (q.isNotEmpty) 'q': q,
      if (pos != null) 'lat': '${pos.latitude}',
      if (pos != null) 'lng': '${pos.longitude}',
      if (pos != null) 'radius': '25',
      if (pos != null) 'sort': 'nearby',
      if (pos != null) 'gps': '1',
    };
    try {
      final json = await api.get('/listings', query);
      setState(() => items = json['data'] as List? ?? []);
    } catch (e) {
      setState(() => error = e.toString());
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Chợ Relic'),
        actions: [
          IconButton(
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ChatListPage())),
            icon: const Icon(Icons.chat_bubble_outline),
          ),
          IconButton(
            onPressed: () async {
              final prefs = await SharedPreferences.getInstance();
              await prefs.remove('token');
              api.token = null;
              if (!context.mounted) return;
              Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => const LoginPage()));
            },
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: TextField(
              decoration: const InputDecoration(hintText: 'Tìm máy... (Elasticsearch nếu bật)'),
              onSubmitted: (v) {
                q = v;
                _load();
              },
            ),
          ),
          if (error != null) Text(error!),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _load,
              child: ListView.builder(
                itemCount: items.length,
                itemBuilder: (_, i) {
                  final it = items[i] as Map;
                  return ListTile(
                    leading: it['cover'] != null
                        ? Image.network(it['cover'], width: 56, height: 56, fit: BoxFit.cover)
                        : const Icon(Icons.devices),
                    title: Text(it['title'] ?? ''),
                    subtitle: Text(
                      '${it['formatted_price'] ?? ''} · ${it['city'] ?? ''}'
                      '${it['distance_km'] != null ? ' · ${it['distance_km']} km' : ''}',
                    ),
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => ListingPage(slug: it['slug'])),
                    ),
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class ListingPage extends StatefulWidget {
  const ListingPage({required this.slug, super.key});
  final String slug;
  @override
  State<ListingPage> createState() => _ListingPageState();
}

class _ListingPageState extends State<ListingPage> {
  Map? data;
  final chat = TextEditingController();

  @override
  void initState() {
    super.initState();
    api.get('/listings/${widget.slug}').then((json) => setState(() => data = json as Map));
  }

  @override
  Widget build(BuildContext context) {
    if (data == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    return Scaffold(
      appBar: AppBar(title: Text(data!['title'] ?? '')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (data!['cover'] != null) Image.network(data!['cover']),
          Text(data!['formatted_price'] ?? '', style: Theme.of(context).textTheme.headlineSmall),
          Text('${data!['city']} · ${data!['condition']}'),
          const SizedBox(height: 12),
          Text(data!['description'] ?? ''),
          const SizedBox(height: 16),
          TextField(controller: chat, decoration: const InputDecoration(labelText: 'Nhắn người bán')),
          FilledButton(
            onPressed: () async {
              final res = await api.post('/listings/${widget.slug}/chat', {'body': chat.text});
              if (!context.mounted) return;
              if (res['status'] == 200) {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => ThreadPage(id: res['body']['conversation_id']),
                  ),
                );
              }
            },
            child: const Text('Chat realtime'),
          ),
        ],
      ),
    );
  }
}

class ChatListPage extends StatefulWidget {
  const ChatListPage({super.key});
  @override
  State<ChatListPage> createState() => _ChatListPageState();
}

class _ChatListPageState extends State<ChatListPage> {
  List rows = [];
  @override
  void initState() {
    super.initState();
    api.get('/conversations').then((json) => setState(() => rows = json['data'] as List? ?? []));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Tin nhắn')),
      body: ListView(
        children: [
          for (final row in rows)
            ListTile(
              title: Text(row['title'] ?? ''),
              subtitle: Text(row['last'] ?? ''),
              onTap: () => Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => ThreadPage(id: row['id'])),
              ),
            ),
        ],
      ),
    );
  }
}

class ThreadPage extends StatefulWidget {
  const ThreadPage({required this.id, super.key});
  final int id;
  @override
  State<ThreadPage> createState() => _ThreadPageState();
}

class _ThreadPageState extends State<ThreadPage> {
  List messages = [];
  final body = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final json = await api.get('/conversations/${widget.id}');
    setState(() => messages = json['data'] as List? ?? []);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Chat')),
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(12),
              children: [
                for (final m in messages)
                  Align(
                    alignment: m['mine'] == true ? Alignment.centerRight : Alignment.centerLeft,
                    child: Container(
                      margin: const EdgeInsets.symmetric(vertical: 4),
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: m['mine'] == true ? const Color(0xFF5B3DF5) : const Color(0xFF161924),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(m['body'] ?? ''),
                    ),
                  ),
              ],
            ),
          ),
          Row(
            children: [
              Expanded(child: TextField(controller: body)),
              IconButton(
                onPressed: () async {
                  await api.post('/conversations/${widget.id}', {'body': body.text});
                  body.clear();
                  await _load();
                },
                icon: const Icon(Icons.send),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
