class UserModel {
  final int id;
  final String username;
  final String email;
  final String role;
  final String status;
  final Map<String, dynamic> profile;

  UserModel({
    required this.id,
    required this.username,
    required this.email,
    required this.role,
    required this.status,
    required this.profile,
  });

  factory UserModel.fromJson(Map<String, dynamic> json, Map<String, dynamic> profileJson) {
    return UserModel(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id'].toString()) ?? 0,
      username: json['username'] ?? '',
      email: json['email'] ?? '',
      role: json['role'] ?? 'student',
      status: json['status'] ?? 'approved',
      profile: profileJson,
    );
  }

  String get fullName => profile['full_name'] ?? username;
  String get userCode => profile['code'] ?? '';
  String get studentClass => profile['class'] ?? '';
  String get rollNo => profile['roll_no'] ?? '';
  String? get photo => profile['photo'];
  String get department => profile['department'] ?? 'Faculty';
  String get phone => profile['phone'] ?? '';
  String get assignedClass => profile['assigned_class'] ?? '';
  String get bloodGroup => profile['blood_group'] ?? 'O+';
  String get address => profile['address'] ?? '';
  String get guardianName => profile['guardian_name'] ?? '';
}

class SchoolInfo {
  final String name;
  final String city;
  final String phone;
  final String email;
  final String address;
  final String logo;
  final String academicYear;

  SchoolInfo({
    required this.name,
    required this.city,
    required this.phone,
    required this.email,
    required this.address,
    required this.logo,
    required this.academicYear,
  });

  factory SchoolInfo.fromJson(Map<String, dynamic> json) {
    return SchoolInfo(
      name: json['name'] ?? 'BELLWETHER INTERNATIONAL SCHOOL NANDURBAR',
      city: json['city'] ?? 'Nandurbar',
      phone: json['phone'] ?? '+91 6262848446',
      email: json['email'] ?? 'bwis.nandurbar@gmail.com',
      address: json['address'] ?? 'Old Nalwa Road, Nandurbar - 425412',
      logo: json['logo'] ?? '',
      academicYear: json['academic_year'] ?? '2026-2027',
    );
  }
}
