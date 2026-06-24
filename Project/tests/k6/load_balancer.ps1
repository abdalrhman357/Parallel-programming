$servers = @(
    "http://127.0.0.1:8001",
    "http://127.0.0.1:8002",
    "http://127.0.0.1:8003"
)
$total = 60
$hits = @{}
Write-Host "Sending $total requests across 3 servers..." -ForegroundColor Cyan

for ($i = 0; $i -lt $total; $i++) {
    $server = $servers[$i % $servers.Count]
    $res = curl.exe -s "$server/api/system/server-info" | ConvertFrom-Json
    $node = $res.app_instance
    if ($hits.ContainsKey($node)) { $hits[$node]++ } else { $hits[$node] = 1 }
    Write-Host "Request $($i+1) -> $node"
}

Write-Host "`n===== Request Distribution =====" -ForegroundColor Green
$hits.GetEnumerator() | Sort-Object Name | ForEach-Object {
    Write-Host ("{0} : {1} requests" -f $_.Name, $_.Value)
}