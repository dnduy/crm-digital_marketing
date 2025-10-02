#!/bin/bash
# ==========================
# Comprehensive CRM Functionality Test
# ==========================

echo "🔧 Testing CRM Functionality..."
echo

# Clean start
rm -f cookies.txt

# 1. Test Login
echo "📝 Testing Login Process:"
echo -n "  - Getting login page: "
curl -s -c cookies.txt "http://localhost:8080/index.php?action=login" > login_page.html
if [ $? -eq 0 ]; then
    echo "✅ OK"
else
    echo "❌ FAILED"
    exit 1
fi

echo -n "  - Extracting CSRF token: "
csrf_token=$(grep -o 'value="[^"]*"' login_page.html | sed 's/value="//;s/"//')
if [ -n "$csrf_token" ]; then
    echo "✅ OK ($csrf_token)"
else
    echo "❌ FAILED"
    exit 1
fi

echo -n "  - Performing login: "
login_response=$(curl -X POST -s -b cookies.txt -c cookies.txt -w "%{http_code}" -d "username=admin&password=admin123&csrf=$csrf_token" "http://localhost:8080/index.php?action=do_login")
if [[ "$login_response" == *"302"* ]] || [[ "$login_response" == *"200"* ]]; then
    echo "✅ OK"
else
    echo "❌ FAILED (HTTP: $login_response)"
    exit 1
fi

# 2. Test Dashboard Access
echo
echo "🏠 Testing Dashboard:"
echo -n "  - Accessing dashboard: "
dashboard_title=$(curl -s -b cookies.txt "http://localhost:8080/index.php?action=dashboard" | grep -o '<title>[^<]*' | sed 's/<title>//')
if [[ "$dashboard_title" == *"Bảng điều khiển"* ]]; then
    echo "✅ OK"
else
    echo "❌ FAILED"
fi

# 3. Test Main Pages
echo
echo "📄 Testing Main Pages Access:"
pages=("contacts" "deals" "activities" "campaigns" "tasks" "reports")
for page in "${pages[@]}"; do
    echo -n "  - $page page: "
    page_status=$(curl -s -b cookies.txt -o /dev/null -w "%{http_code}" "http://localhost:8080/index.php?action=$page")
    if [ "$page_status" = "200" ]; then
        echo "✅ OK"
    else
        echo "❌ FAILED ($page_status)"
    fi
done

# 4. Test Contact Creation
echo
echo "👤 Testing Contact Creation:"
echo -n "  - Getting contact form: "
contact_form=$(curl -s -b cookies.txt "http://localhost:8080/index.php?action=contacts&op=new")
contact_csrf=$(echo "$contact_form" | grep -o 'name="csrf" value="[^"]*"' | sed 's/name="csrf" value="//;s/"//')
if [ -n "$contact_csrf" ]; then
    echo "✅ OK"
else
    echo "❌ FAILED"
fi

echo -n "  - Creating test contact: "
create_status=$(curl -X POST -s -b cookies.txt -c cookies.txt -w "%{http_code}" -o /dev/null -d "name=Test User&email=test@example.com&phone=123456789&csrf=$contact_csrf" "http://localhost:8080/index.php?action=contacts&op=create")
if [[ "$create_status" == "302" ]] || [[ "$create_status" == "200" ]]; then
    echo "✅ OK"
else
    echo "❌ FAILED ($create_status)"
fi

# 5. Test API Endpoints
echo
echo "🔌 Testing API Endpoints:"
echo -n "  - Contacts API: "
contacts_api=$(curl -s "http://localhost:8080/index.php?action=api&what=contacts")
if [[ "$contacts_api" == *"contacts"* ]]; then
    echo "✅ OK"
else
    echo "❌ FAILED"
fi

echo -n "  - Deals API: "
deals_api=$(curl -s "http://localhost:8080/index.php?action=api&what=deals")
if [[ "$deals_api" == *"deals"* ]]; then
    echo "✅ OK"
else
    echo "❌ FAILED"
fi

echo
echo "✅ Comprehensive testing completed!"

# Cleanup
rm -f cookies.txt login_page.html