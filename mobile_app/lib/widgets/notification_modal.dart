import 'package:flutter/material.dart';
import '../theme/app_theme.dart';
import '../services/api_service.dart';

class NotificationModal extends StatefulWidget {
  const NotificationModal({super.key});

  static void show(BuildContext context, {VoidCallback? onDismiss}) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const NotificationModal(),
    ).then((_) {
      if (onDismiss != null) onDismiss();
    });
  }

  @override
  State<NotificationModal> createState() => _NotificationModalState();
}

class _NotificationModalState extends State<NotificationModal> {
  List<dynamic> _notifications = [];
  int _unreadCount = 0;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadNotifications();
  }

  Future<void> _loadNotifications() async {
    final res = await ApiService().fetchNotifications();
    if (mounted) {
      setState(() {
        _notifications = res['notifications'] as List<dynamic>? ?? [];
        _unreadCount = res['unread_count'] as int? ?? 0;
        _isLoading = false;
      });
    }
  }

  Future<void> _markRead([int? id]) async {
    await ApiService().markNotificationRead(id);
    _loadNotifications();
  }

  @override
  Widget build(BuildContext context) {
    final height = MediaQuery.of(context).size.height * 0.78;

    return Container(
      height: height,
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      child: Column(
        children: [
          // Drag Handle
          const SizedBox(height: 12),
          Container(
            width: 44,
            height: 4,
            decoration: BoxDecoration(
              color: AppTheme.slate200,
              borderRadius: BorderRadius.circular(10),
            ),
          ),
          const SizedBox(height: 16),

          // Header
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: AppTheme.navyDark.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Icon(Icons.notifications_active_rounded, color: AppTheme.navyDark, size: 20),
                    ),
                    const SizedBox(width: 10),
                    const Text(
                      'Notifications',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                        color: AppTheme.slate900,
                      ),
                    ),
                    if (_unreadCount > 0) ...[
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(
                          color: AppTheme.rose,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Text(
                          '$_unreadCount new',
                          style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                        ),
                      ),
                    ],
                  ],
                ),
                if (_notifications.isNotEmpty)
                  TextButton(
                    onPressed: () => _markRead(null),
                    child: const Text('Mark all read', style: TextStyle(color: AppTheme.navyMedium, fontSize: 12, fontWeight: FontWeight.bold)),
                  ),
              ],
            ),
          ),
          const Divider(height: 24, thickness: 1, color: AppTheme.slate100),

          // Content List
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: AppTheme.navyDark))
                : _notifications.isEmpty
                    ? Center(
                        child: Padding(
                          padding: const EdgeInsets.all(32),
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Container(
                                width: 68,
                                height: 68,
                                decoration: BoxDecoration(
                                  color: AppTheme.slate100,
                                  shape: BoxShape.circle,
                                ),
                                child: const Icon(Icons.notifications_none_rounded, color: AppTheme.slate400, size: 36),
                              ),
                              const SizedBox(height: 16),
                              const Text(
                                'No notifications yet',
                                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: AppTheme.slate800),
                              ),
                              const SizedBox(height: 4),
                              const Text(
                                'Assignments, notes, attendance and grading alerts will appear here in real time.',
                                textAlign: TextAlign.center,
                                style: TextStyle(fontSize: 12, color: AppTheme.slate500),
                              ),
                            ],
                          ),
                        ),
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
                        itemCount: _notifications.length,
                        separatorBuilder: (context, index) => const SizedBox(height: 10),
                        itemBuilder: (context, index) {
                          final item = _notifications[index];
                          final isUnread = (item['is_read'] == 0 || item['is_read'] == false);
                          final type = item['type'] ?? 'general';

                          IconData icon = Icons.notifications_rounded;
                          Color iconColor = AppTheme.navyDark;
                          Color iconBg = AppTheme.indigoLight;

                          if (type == 'assignment_new') {
                            icon = Icons.assignment_rounded;
                            iconColor = AppTheme.purple;
                            iconBg = AppTheme.purpleLight;
                          } else if (type == 'assignment_checked') {
                            icon = Icons.verified_rounded;
                            iconColor = AppTheme.emerald;
                            iconBg = AppTheme.emeraldLight;
                          } else if (type == 'assignment_solution') {
                            icon = Icons.lightbulb_rounded;
                            iconColor = AppTheme.amber;
                            iconBg = AppTheme.amberLight;
                          } else if (type == 'note_new') {
                            icon = Icons.menu_book_rounded;
                            iconColor = AppTheme.cyan;
                            iconBg = AppTheme.cyanLight;
                          }

                          return InkWell(
                            onTap: () {
                              if (isUnread) _markRead(item['id']);
                            },
                            borderRadius: BorderRadius.circular(16),
                            child: Container(
                              padding: const EdgeInsets.all(14),
                              decoration: BoxDecoration(
                                color: isUnread ? Colors.white : AppTheme.bgLight,
                                borderRadius: BorderRadius.circular(16),
                                border: Border.all(
                                  color: isUnread ? AppTheme.navyDark.withValues(alpha: 0.25) : AppTheme.slate100,
                                  width: isUnread ? 1.5 : 1,
                                ),
                                boxShadow: isUnread
                                    ? [
                                        BoxShadow(
                                          color: AppTheme.navyDark.withValues(alpha: 0.06),
                                          blurRadius: 10,
                                          offset: const Offset(0, 4),
                                        ),
                                      ]
                                    : null,
                              ),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Container(
                                    width: 42,
                                    height: 42,
                                    decoration: BoxDecoration(
                                      color: iconBg,
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                    child: Icon(icon, color: iconColor, size: 22),
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Row(
                                          children: [
                                            Expanded(
                                              child: Text(
                                                item['title'] ?? 'Notification',
                                                style: TextStyle(
                                                  fontWeight: isUnread ? FontWeight.bold : FontWeight.w600,
                                                  fontSize: 13,
                                                  color: AppTheme.slate900,
                                                ),
                                              ),
                                            ),
                                            if (isUnread)
                                              Container(
                                                width: 8,
                                                height: 8,
                                                decoration: const BoxDecoration(
                                                  color: AppTheme.rose,
                                                  shape: BoxShape.circle,
                                                ),
                                              ),
                                          ],
                                        ),
                                        const SizedBox(height: 4),
                                        Text(
                                          item['message'] ?? '',
                                          style: const TextStyle(
                                            color: AppTheme.slate600,
                                            fontSize: 12,
                                            height: 1.3,
                                          ),
                                        ),
                                        const SizedBox(height: 6),
                                        Text(
                                          item['created_at'] ?? 'Just now',
                                          style: const TextStyle(
                                            color: AppTheme.slate400,
                                            fontSize: 10,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
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
