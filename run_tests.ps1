$baseUrl = "http://localhost:8000/analyze-ticket"
$testDir = "E:\codex_contest\postman_tests"
$results = @()

$files = Get-ChildItem $testDir -Filter "*.json" | Sort-Object Name
foreach ($file in $files) {
    $body = Get-Content $file.FullName -Raw
    $name = $file.BaseName
    try {
        $start = Get-Date
        $resp = Invoke-WebRequest -Uri $baseUrl -Method POST -Body $body -ContentType "application/json" -TimeoutSec 35 -UseBasicParsing -ErrorAction Stop
        $elapsed = ((Get-Date) - $start).TotalSeconds
        $status = $resp.StatusCode
        $json = $resp.Content | ConvertFrom-Json
        $results += [PSCustomObject]@{
            Test=$name; HTTP=$status; Time=[math]::Round($elapsed,1)
            case_type=$json.case_type; verdict=$json.evidence_verdict
            dept=$json.department; severity=$json.severity
            review=$json.human_review_required; txn=$json.relevant_transaction_id
        }
    } catch {
        $status = $_.Exception.Response.StatusCode.value__
        $elapsed = 0
        $results += [PSCustomObject]@{
            Test=$name; HTTP=$status; Time=0
            case_type="-"; verdict="-"; dept="-"; severity="-"; review="-"; txn="-"
        }
    }
    Write-Host "$name -> HTTP $status (${elapsed}s)"
}

$results | Format-Table -AutoSize
