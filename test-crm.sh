#!/bin/bash
# ==========================
# CRM Test Script
# ==========================

echo "🔍 Testing CRM Application..."
echo

# Test API endpoints
echo "📡 Testing API endpoints:"
echo -n "  - Contacts API: "
if curl -s "http://localhost:8080/index.php?action=api&what=contacts" | grep -q "contacts"; then
    echo "✅ OK"
else
    echo "❌ FAILED"
fi

echo -n "  - Deals API: "
if curl -s "http://localhost:8080/index.php?action=api&what=deals" | grep -q "deals"; then
    echo "✅ OK"
else
    echo "❌ FAILED"
fi

# Test main pages
echo
echo "🌐 Testing main pages:"
pages=("" "?action=login" "?action=dashboard" "?action=contacts" "?action=deals" "?action=activities" "?action=campaigns" "?action=tasks" "?action=reports")

for page in "${pages[@]}"; do
    url="http://localhost:8080/index.php$page"
    echo -n "  - $page: "
    status=$(curl -s -o /dev/null -w "%{http_code}" "$url")
    if [ "$status" = "200" ] || [ "$status" = "302" ]; then
        echo "✅ OK ($status)"
    else
        echo "❌ FAILED ($status)"
    fi
done

# Test setup page
echo
echo "🔧 Testing setup page:"
echo -n "  - setup.php: "
status=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:8080/setup.php")
if [ "$status" = "200" ]; then
    echo "✅ OK ($status)"
else
    echo "❌ FAILED ($status)"
fi

echo
echo "✅ Testing completed!"