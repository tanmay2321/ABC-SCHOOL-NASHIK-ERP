import 'package:flutter/material.dart';
import '../theme/app_theme.dart';
import '../services/api_service.dart';

class NoticesScreen extends StatefulWidget {
  const NoticesScreen({super.key});

  @override
  State<NoticesScreen> createState() => _NoticesScreenState();
}

class _NoticesScreenState extends State<NoticesScreen> {
  List<dynamic> _notices = [];
  bool _isLoading = true;
  String _selectedCategory = 'All';

  final List<String> _categories = ['All', 'Academic', 'Sports', 'Holidays', 'Circular'];

  @override
  void initState() {
    super.initState();
    _loadNotices();
  }

  Future<void> _loadNotices() async {
    final list = await ApiService().fetchNotices();
    if (mounted) {
      setState(() {
        _notices = list;
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final filtered = _selectedCategory == 'All'
        ? _notices
        : _notices.where((n) => (n['category'] ?? '').toString().toLowerCase() == _selectedCategory.toLowerCase()).toList();

    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: const Text('Digital Notice Board & Diary'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.navyDark))
          : Column(
              children: [
                // Category Filter Chips
                Container(
                  color: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  child: SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: Row(
                      children: _categories.map((c) {
                        final isSelected = _selectedCategory == c;
                        return Padding(
                          padding: const EdgeInsets.only(right: 8),
                          child: FilterChip(
                            label: Text(c),
                            selected: isSelected,
                            onSelected: (_) => setState(() => _selectedCategory = c),
                            selectedColor: AppTheme.navyDark,
                            labelStyle: TextStyle(
                              color: isSelected ? Colors.white : AppTheme.slate700,
                              fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                              fontSize: 12,
                            ),
                            backgroundColor: AppTheme.slate50,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                          ),
                        );
                      }).toList(),
                    ),
                  ),
                ),

                // Notices List
                Expanded(
                  child: filtered.isEmpty
                      ? const Center(child: Text('No notices found in this category.'))
                      : ListView.builder(
                          padding: const EdgeInsets.all(18),
                          itemCount: filtered.length,
                          itemBuilder: (ctx, idx) {
                            final n = filtered[idx];
                            return Container(
                              margin: const EdgeInsets.only(bottom: 14),
                              padding: const EdgeInsets.all(18),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(22),
                                border: Border.all(color: AppTheme.slate100),
                                boxShadow: [
                                  BoxShadow(
                                    color: AppTheme.navyDark.withValues(alpha: 0.03),
                                    blurRadius: 10,
                                    offset: const Offset(0, 4),
                                  ),
                                ],
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                        decoration: BoxDecoration(
                                          color: AppTheme.indigoLight,
                                          borderRadius: BorderRadius.circular(8),
                                        ),
                                        child: Text(
                                          n['category'] ?? 'Circular',
                                          style: const TextStyle(
                                            color: AppTheme.navyMedium,
                                            fontSize: 10,
                                            fontWeight: FontWeight.bold,
                                          ),
                                        ),
                                      ),
                                      Text(
                                        n['created_at'] != null ? n['created_at'].toString().split(' ')[0] : 'Recent',
                                        style: const TextStyle(color: AppTheme.slate400, fontSize: 11),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 10),
                                  Text(
                                    n['title'] ?? '',
                                    style: const TextStyle(
                                      color: AppTheme.slate900,
                                      fontSize: 15,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                  const SizedBox(height: 6),
                                  Text(
                                    n['content'] ?? '',
                                    style: const TextStyle(color: AppTheme.slate600, fontSize: 13, height: 1.4),
                                  ),
                                ],
                              ),
                            );
                          },
                        ),
                ),
              ],
            ),
    );
  }
}
