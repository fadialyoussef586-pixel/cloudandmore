#!/bin/bash
# تشغيل نظام IKOS ERP محلياً
cd "$(dirname "$0")"

PHP_BIN=""
for candidate in php php84 php83 "/Users/fadialyoussef/Library/Application Support/Herd/bin/php"; do
  if command -v "$candidate" >/dev/null 2>&1; then
    if "$candidate" -v >/dev/null 2>&1; then
      PHP_BIN="$candidate"
      break
    fi
  fi
done

if [ -z "$PHP_BIN" ]; then
  echo "❌ PHP غير موجود أو معطّل على جهازك."
  echo ""
  echo "الحلول:"
  echo "  1) ثبّت PHP: brew install php"
  echo "  2) أو أصلح Laravel Herd من تطبيق Herd"
  echo "  3) أو استخدم XAMPP وضع مجلد erp داخل htdocs"
  exit 1
fi

PORT="${1:-8080}"
echo "✓ PHP: $($PHP_BIN -v | head -1)"
echo "✓ السيرفر: http://localhost:${PORT}/check.php"
echo "✓ الإعداد: http://localhost:${PORT}/setup.php"
echo "✓ الدخول:  http://localhost:${PORT}/login.php"
echo ""
echo "اضغط Ctrl+C لإيقاف السيرفر"
echo ""

exec "$PHP_BIN" -S "localhost:${PORT}" -t .
