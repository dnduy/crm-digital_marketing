<?php
// ==========================
// FILE: /views/analytics.php - Advanced Marketing Analytics
// ==========================
function view_analytics($op){
global $db; 

// Handle demo data creation BEFORE any output
if($op === 'simulate_data') {
    // Generate attribution touchpoints demo data
    $channels = ['google_ads', 'facebook_ads', 'email', 'organic_search', 'direct', 'referral'];
    $campaigns_result = q($db, "SELECT id FROM campaigns");
    $contacts_result = q($db, "SELECT id FROM contacts");
    $deals_result = q($db, "SELECT id FROM deals");
    
    $campaigns = $campaigns_result->fetchAll(PDO::FETCH_COLUMN);
    $contacts = $contacts_result->fetchAll(PDO::FETCH_COLUMN);
    $deals = $deals_result->fetchAll(PDO::FETCH_COLUMN);
    
    $created = 0;
    
    // For each contact, create a journey
    foreach($contacts as $contact_id) {
        $journey_length = rand(2, 8); // 2-8 touchpoints per journey
        $deal_id = rand(0, 1) ? ($deals[array_rand($deals)] ?? null) : null;
        $total_value = $deal_id ? rand(500000, 5000000) : 0;
        
        for($i = 0; $i < $journey_length; $i++) {
            $channel = $channels[array_rand($channels)];
            $campaign_id = $campaigns[array_rand($campaigns)] ?? null;
            $timestamp = date('Y-m-d H:i:s', strtotime("-".rand(1,30)." days -".rand(0,23)." hours"));
            $attribution_weight = $journey_length > 0 ? round(1.0 / $journey_length, 4) : 0;
            $revenue_attributed = $total_value * $attribution_weight;
            
            q($db, "INSERT INTO attribution_touchpoints(contact_id,deal_id,campaign_id,touchpoint_type,utm_source,utm_medium,utm_campaign,page_url,device_type,location,attribution_weight,revenue_attributed,occurred_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)", [
                $contact_id, $deal_id, $campaign_id, 'interaction', $channel, 'digital', 'campaign_'.$campaign_id, '/landing', 'desktop', 'vietnam', $attribution_weight, $revenue_attributed, $timestamp
            ]);
            $created++;
        }
    }
    
    // Generate campaign metrics demo data for last 30 days
    foreach($campaigns as $campaign_id) {
        for($day = 0; $day < 30; $day++) {
            $date = date('Y-m-d', strtotime("-$day days"));
            $impressions = rand(1000, 10000);
            $clicks = rand(50, (int)($impressions * 0.1));
            $conversions = rand(1, max(1, (int)($clicks * 0.05)));
            $cost = rand(500000, 2000000);
            $revenue = $conversions * rand(100000, 500000);
            $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;
            $cpc = $clicks > 0 ? round($cost / $clicks, 2) : 0;
            $roas = $cost > 0 ? round($revenue / $cost, 2) : 0;
            
            q($db, "INSERT OR REPLACE INTO campaign_metrics(campaign_id,date,impressions,clicks,conversions,cost,revenue,ctr,cpc,roas) VALUES(?,?,?,?,?,?,?,?,?,?)", [
                $campaign_id, $date, $impressions, $clicks, $conversions, $cost, $revenue, $ctr, $cpc, $roas
            ]);
        }
    }
    
    $_SESSION['flash'] = "Đã tạo $created attribution touchpoints và 30 ngày campaign metrics demo data";
    header('Location: ?action=analytics');
    exit;
}

layout_header('Marketing Analytics');

// Attribution Overview
if($op==='' || $op==='attribution'){
    echo '<div class="card" style="background:linear-gradient(135deg,#10b981,#059669);color:#fff">';
    echo '<h2>🎯 Multi-Touch Attribution Analysis</h2>';
    echo '<p>Phân tích đầy đủ customer journey và đóng góp của từng touchpoint trong quá trình chuyển đổi</p>';
    echo '</div>';
    
    // Attribution Summary
    $attribution_summary = q($db, "SELECT 
        COUNT(DISTINCT contact_id) as total_contacts,
        COUNT(DISTINCT deal_id) as total_deals,
        COUNT(*) as total_touchpoints,
        SUM(revenue_attributed) as total_attributed_revenue,
        AVG(revenue_attributed) as avg_revenue_per_touchpoint
        FROM attribution_touchpoints WHERE deal_id IS NOT NULL")->fetch(PDO::FETCH_ASSOC);
    
    echo '<div class="grid cols-4">';
    echo '<div class="card"><div>Unique Contacts</div><div class="kpi">'.number_format($attribution_summary['total_contacts']??0).'</div></div>';
    echo '<div class="card"><div>Converted Deals</div><div class="kpi" style="color:#10b981">'.number_format($attribution_summary['total_deals']??0).'</div></div>';
    echo '<div class="card"><div>Total Touchpoints</div><div class="kpi">'.number_format($attribution_summary['total_touchpoints']??0).'</div></div>';
    echo '<div class="card"><div>Attributed Revenue</div><div class="kpi" style="color:#10b981">'.number_format($attribution_summary['total_attributed_revenue']??0,0).'đ</div></div>';
    echo '</div>';
    
    // Attribution by Channel
    $channel_attribution = q($db, "SELECT 
        utm_source as channel,
        COUNT(*) as touchpoints,
        COUNT(DISTINCT contact_id) as unique_contacts,
        COUNT(DISTINCT deal_id) as converted_deals,
        SUM(revenue_attributed) as total_revenue,
        AVG(revenue_attributed) as avg_revenue_per_touchpoint,
        COUNT(DISTINCT deal_id) * 100.0 / COUNT(DISTINCT contact_id) as conversion_rate
        FROM attribution_touchpoints 
        WHERE utm_source IS NOT NULL AND utm_source != ''
        GROUP BY utm_source 
        ORDER BY total_revenue DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    if($channel_attribution) {
        echo '<div class="card"><h4>📊 Attribution by Channel</h4>';
        echo '<table>';
        echo '<tr><th>Channel</th><th>Touchpoints</th><th>Unique Contacts</th><th>Conversions</th><th>Conversion Rate</th><th>Total Revenue</th><th>Revenue per Touchpoint</th></tr>';
        foreach($channel_attribution as $channel) {
            echo '<tr>';
            echo '<td><strong>'.h($channel['channel']).'</strong></td>';
            echo '<td>'.number_format($channel['touchpoints']).'</td>';
            echo '<td>'.number_format($channel['unique_contacts']).'</td>';
            echo '<td>'.number_format($channel['converted_deals']).'</td>';
            echo '<td>'.number_format($channel['conversion_rate']??0,1).'%</td>';
            echo '<td>'.number_format($channel['total_revenue'],0).'đ</td>';
            echo '<td>'.number_format($channel['avg_revenue_per_touchpoint'],0).'đ</td>';
            echo '</tr>';
        }
        echo '</table></div>';
    }
    
    // Customer Journey Analysis
    $journey_analysis = q($db, "SELECT 
        contact_id,
        COUNT(*) as touchpoint_count,
        MIN(occurred_at) as first_touch,
        MAX(occurred_at) as last_touch,
        SUM(revenue_attributed) as total_revenue,
        GROUP_CONCAT(utm_source, ' → ') as journey_path
        FROM attribution_touchpoints 
        WHERE deal_id IS NOT NULL
        GROUP BY contact_id 
        ORDER BY total_revenue DESC 
        LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    
    if($journey_analysis) {
        echo '<div class="card"><h4>🛤️ Customer Journey Analysis (Top 20 by Revenue)</h4>';
        echo '<table>';
        echo '<tr><th>Contact ID</th><th>Touchpoints</th><th>Journey Duration</th><th>Channel Path</th><th>Total Revenue</th></tr>';
        foreach($journey_analysis as $journey) {
            $duration = round((strtotime($journey['last_touch']) - strtotime($journey['first_touch'])) / 86400);
            echo '<tr>';
            echo '<td><a href="?action=contacts&op=edit&id='.(int)$journey['contact_id'].'">#'.(int)$journey['contact_id'].'</a></td>';
            echo '<td>'.number_format($journey['touchpoint_count']).'</td>';
            echo '<td>'.$duration.' ngày</td>';
            echo '<td><small>'.h(substr($journey['journey_path'], 0, 50)).'...</small></td>';
            echo '<td>'.number_format($journey['total_revenue'],0).'đ</td>';
            echo '</tr>';
        }
        echo '</table></div>';
    }
    
    echo '<div class="card">';
    echo '<a class="btn" href="?action=analytics&op=simulate_data">Tạo Demo Data</a> ';
    echo '<a class="btn secondary" href="?action=analytics&op=funnel">Funnel Analysis</a> ';
    echo '<a class="btn secondary" href="?action=analytics&op=cohort">Cohort Analysis</a>';
    echo '</div>';
}

// Funnel Analysis
if($op==='funnel'){
    echo '<div class="card" style="background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff">';
    echo '<h2>🔄 Conversion Funnel Analysis</h2>';
    echo '<p>Phân tích tỷ lệ chuyển đổi qua các stage của customer journey</p>';
    echo '</div>';
    
    // Calculate funnel metrics
    $funnel_data = [
        'awareness' => (int)q($db, "SELECT COUNT(DISTINCT contact_id) FROM attribution_touchpoints")->fetchColumn(),
        'interest' => (int)q($db, "SELECT COUNT(DISTINCT contact_id) FROM attribution_touchpoints WHERE utm_source IS NOT NULL")->fetchColumn(),
        'consideration' => (int)q($db, "SELECT COUNT(DISTINCT contact_id) FROM attribution_touchpoints GROUP BY contact_id HAVING COUNT(*) >= 2")->fetchColumn(),
        'conversion' => (int)q($db, "SELECT COUNT(DISTINCT contact_id) FROM attribution_touchpoints WHERE deal_id IS NOT NULL")->fetchColumn(),
        'retention' => (int)q($db, "SELECT COUNT(DISTINCT contact_id) FROM attribution_touchpoints WHERE deal_id IS NOT NULL GROUP BY contact_id HAVING COUNT(*) >= 3")->fetchColumn()
    ];
    
    echo '<div class="card"><h4>🔄 Conversion Funnel</h4>';
    echo '<div style="display:flex;align-items:end;gap:20px;margin:20px 0">';
    
    $stages = [
        'awareness' => ['label' => 'Awareness', 'color' => '#3b82f6'],
        'interest' => ['label' => 'Interest', 'color' => '#22d3ee'],
        'consideration' => ['label' => 'Consideration', 'color' => '#fbbf24'],
        'conversion' => ['label' => 'Conversion', 'color' => '#10b981'],
        'retention' => ['label' => 'Retention', 'color' => '#8b5cf6']
    ];
    
    $max_value = max($funnel_data);
    foreach($stages as $key => $stage) {
        $height = $max_value > 0 ? ($funnel_data[$key] / $max_value) * 200 : 0;
        $conversion_rate = $key === 'awareness' ? 100 : (($funnel_data[$key] / $funnel_data['awareness']) * 100);
        
        echo '<div style="text-align:center">';
        echo '<div style="background:'.$stage['color'].';width:80px;height:'.$height.'px;margin:0 auto;border-radius:4px;display:flex;align-items:end;justify-content:center;color:#fff;font-weight:bold">';
        echo number_format($funnel_data[$key]);
        echo '</div>';
        echo '<div style="margin-top:8px;font-size:12px">'.$stage['label'].'</div>';
        echo '<div style="font-size:11px;color:#94a3b8">'.number_format($conversion_rate,1).'%</div>';
        echo '</div>';
    }
    echo '</div></div>';
    
    // Funnel drop-off analysis
    echo '<div class="card"><h4>📉 Drop-off Analysis</h4>';
    echo '<table>';
    echo '<tr><th>Stage</th><th>Count</th><th>Conversion Rate</th><th>Drop-off Rate</th><th>Drop-off Count</th></tr>';
    $prev_count = 0;
    foreach($stages as $key => $stage) {
        $count = $funnel_data[$key];
        $conversion_rate = $key === 'awareness' ? 100 : (($count / $funnel_data['awareness']) * 100);
        $drop_off_count = $prev_count > 0 ? ($prev_count - $count) : 0;
        $drop_off_rate = $prev_count > 0 ? (($drop_off_count / $prev_count) * 100) : 0;
        
        echo '<tr>';
        echo '<td><strong>'.$stage['label'].'</strong></td>';
        echo '<td>'.number_format($count).'</td>';
        echo '<td>'.number_format($conversion_rate,1).'%</td>';
        echo '<td>'.($drop_off_count > 0 ? number_format($drop_off_rate,1).'%' : '-').'</td>';
        echo '<td>'.($drop_off_count > 0 ? number_format($drop_off_count) : '-').'</td>';
        echo '</tr>';
        
        $prev_count = $count;
    }
    echo '</table></div>';
}

// Show demo data creation option if no data exists
$has_data = q($db, "SELECT COUNT(*) FROM attribution_touchpoints")->fetchColumn();
if($has_data == 0) {
    echo '<div class="card" style="background:#f59e0b;color:#fff">';
    echo '<h3>🎲 Tạo Demo Data</h3>';
    echo '<p>Chưa có dữ liệu attribution. Tạo dữ liệu demo để test các tính năng analytics.</p>';
    echo '<form method="post" action="?action=analytics&op=simulate_data">'; csrf_field();
    echo '<button class="btn" style="background:#fff;color:#f59e0b">Tạo Demo Data</button>';
    echo '</form></div>';
}

if(!empty($_SESSION['flash'])){
    echo '<div class="card" style="background:#059669;color:#fff">'.h($_SESSION['flash']).'</div>';
    unset($_SESSION['flash']);
}

layout_footer();
}
?>