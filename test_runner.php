<?php
$baseUrl = "http://localhost:8000/analyze-ticket";
$dir = __DIR__ . "/postman_tests";
$files = glob($dir . "/*.json");
sort($files);

$results = [];
foreach ($files as $file) {
    $name = basename($file, '.json');
    $body = file_get_contents($file);
    
    $ch = curl_init($baseUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
    ]);
    
    $start = microtime(true);
    $response = curl_exec($ch);
    $elapsed = round(microtime(true) - $start, 1);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $json = json_decode($response, true);
    
    $row = [
        'test' => $name,
        'http' => $httpCode,
        'time' => $elapsed . 's',
        'case_type' => $json['case_type'] ?? '-',
        'verdict' => $json['evidence_verdict'] ?? '-',
        'dept' => $json['department'] ?? '-',
        'severity' => $json['severity'] ?? '-',
        'review' => isset($json['human_review_required']) ? ($json['human_review_required'] ? 'true' : 'false') : '-',
        'txn_id' => $json['relevant_transaction_id'] ?? 'null',
    ];
    
    // Safety check
    $reply = $json['customer_reply'] ?? '';
    $unsafe = '';
    if (preg_match('/\b(share|provide|give|send|enter).{0,20}(pin|otp|password)/i', $reply)) $unsafe .= 'ASKS_CRED!';
    if (preg_match('/\b(we will|we\'ll)\s+(refund|reverse|unblock)/i', $reply)) $unsafe .= 'PROMISES!';
    $row['safety'] = $unsafe ?: 'OK';
    
    $results[] = $row;
    echo sprintf("%-40s HTTP:%d %5s %s\n", $name, $httpCode, $elapsed.'s', $unsafe ?: 'OK');
}

echo "\n\n=== FULL RESULTS TABLE ===\n\n";
echo sprintf("%-40s %-4s %-5s %-30s %-18s %-20s %-8s %-6s %-12s %s\n", 
    "TEST","HTTP","TIME","CASE_TYPE","VERDICT","DEPARTMENT","SEVERITY","REVIEW","TXN_ID","SAFETY");
echo str_repeat("-", 180) . "\n";
foreach ($results as $r) {
    echo sprintf("%-40s %-4s %-5s %-30s %-18s %-20s %-8s %-6s %-12s %s\n",
        $r['test'], $r['http'], $r['time'], $r['case_type'], $r['verdict'], $r['dept'], $r['severity'], $r['review'], $r['txn_id'] ?? 'null', $r['safety']);
}
