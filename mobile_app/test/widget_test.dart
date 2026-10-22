import 'package:flutter_test/flutter_test.dart';
import 'package:bwis_erp_app/main.dart';

void main() {
  testWidgets('App smoke test', (WidgetTester tester) async {
    await tester.pumpWidget(const BwisErpApp());
    expect(find.byType(BwisErpApp), findsOneWidget);
  });
}
