<?php
// ==========================
// Setup script to initialize the CRM database
// ==========================

// Start session and include required files
session_start();
require __DIR__.'/src/lib/db.php';

echo "<h1>CRM Setup Script</h1>\n";

try {
    // Check if admin user exists
    $admin_count = q($db, "SELECT COUNT(*) FROM users WHERE username = 'admin'")->fetchColumn();
    
    if ($admin_count == 0) {
        // Create admin user
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        q($db, "INSERT INTO users (username, password_hash) VALUES (?, ?)", ['admin', $admin_password]);
        echo "<p>✅ Admin user created successfully!</p>\n";
        echo "<p>📝 Username: <strong>admin</strong></p>\n";
        echo "<p>📝 Password: <strong>admin123</strong></p>\n";
    } else {
        echo "<p>ℹ️ Admin user already exists</p>\n";
    }
    
    // Check tables
    $tables = ['users', 'contacts', 'deals', 'activities', 'campaigns', 'tasks', 'settings'];
    echo "<h2>Database Tables Status:</h2>\n";
    foreach ($tables as $table) {
        try {
            $count = q($db, "SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "<p>✅ Table '$table': $count records</p>\n";
        } catch (Exception $e) {
            echo "<p>❌ Table '$table': Error - " . $e->getMessage() . "</p>\n";
        }
    }
    
    // Insert sample data if tables are empty
    $contacts_count = q($db, "SELECT COUNT(*) FROM contacts")->fetchColumn();
    if ($contacts_count == 0) {
        // Insert sample contacts
        $sample_contacts = [
            ['Nguyen Van A', 'nguyenvana@example.com', '0912345678', 'Company A', 'Google Ads', 'hot,lead', 'google', 'cpc', 'brand-campaign'],
            ['Tran Thi B', 'tranthib@example.com', '0987654321', 'Company B', 'Facebook', 'warm', 'facebook', 'social', 'awareness'],
            ['Le Van C', 'levanc@example.com', '0901234567', 'Company C', 'SEO', 'cold', 'organic', 'search', 'content-marketing']
        ];
        
        foreach ($sample_contacts as $contact) {
            q($db, "INSERT INTO contacts (name, email, phone, company, source, tags, utm_source, utm_medium, utm_campaign) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", $contact);
        }
        echo "<p>✅ Sample contacts added</p>\n";
        
        // Insert sample deals
        q($db, "INSERT INTO deals (contact_id, title, value, currency, stage, channel, utm_source, utm_medium, utm_campaign) VALUES (1, 'Website Development', 5000, 'USD', 'Qualified', 'google-ads', 'google', 'cpc', 'brand-campaign')");
        q($db, "INSERT INTO deals (contact_id, title, value, currency, stage, channel, utm_source, utm_medium, utm_campaign) VALUES (2, 'Digital Marketing Package', 3000, 'USD', 'Proposal', 'facebook-ads', 'facebook', 'social', 'awareness')");
        echo "<p>✅ Sample deals added</p>\n";
        
        // Insert sample campaign
        q($db, "INSERT INTO campaigns (name, channel, budget, spent, status, start_date, end_date, notes) VALUES ('Q4 Brand Campaign', 'google', 10000, 2500, 'Active', '2024-10-01', '2024-12-31', 'Focus on brand awareness')");
        echo "<p>✅ Sample campaign added</p>\n";
    }
    
    echo "<h2>🎉 Setup completed successfully!</h2>\n";
    echo "<p><a href='src/index.php'>Go to CRM Application</a></p>\n";
    
} catch (Exception $e) {
    echo "<p>❌ Error during setup: " . $e->getMessage() . "</p>\n";
}
?>