$ErrorActionPreference = 'SilentlyContinue'

# 1. Build a single PID -> process map
$procs = Get-CimInstance Win32_Process
$map = @{}
$procs | ForEach-Object { $map[$_.ProcessId] = $_ }

function Get-ProcessById([int]$id) {
    if ($map.ContainsKey($id)) { return $map[$id] }
    return $null
}

# 2. Port-8000 listeners as they exist RIGHT NOW
'=== port 8000 listeners (current) ==='
Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue |
    Where-Object { $_.LocalPort -eq 8000 } |
    ForEach-Object {
        $pid = $_.OwningProcess
        $p = Get-ProcessById ([int]$pid)
        '  {0}:{1} pid={2} [{3}]' -f $_.LocalAddress, $_.LocalPort, $pid, $(if ($p) { $p.Name } else { '?' })
    }

# 3. Highest ancestor(s) above each listener (nssm? cmd? php?)
'=== ancestry of each listener (up to nssm/root) ==='
Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue |
    Where-Object { $_.LocalPort -eq 8000 } |
    ForEach-Object {
        "--- listener $($_.LocalAddress):8000 pid=$($_.OwningProcess) ---"
        $cur = [int]$_.OwningProcess
        $guard = 0
        while ($cur -gt 0 -and -not $map.ContainsKey($cur) -and $guard -lt 30) {
            $bid = (Get-CimInstance Win32_Process -Filter "ProcessId=$cur" -ErrorAction SilentlyContinue)
            if (-not $bid) { "    '$cur' (gone)" ; break }
            $map[$cur] = $bid
        }
        while ($cur -gt 0 -and $guard -lt 30) {
            $p = Get-ProcessById $cur
            $name = if ($p) { $p.Name } else { '?' }
            $cmd  = if ($p) { [string]$p.CommandLine } else { '' }
            if ($cmd.Length -gt 160) { $cmd = $cmd.Substring(0,160) + '...' }
            "    [$cur] $name parent=$($p.ParentProcessId) :: $cmd"
            if ($name -eq 'nssm.exe' -or $name -eq 'services.exe') { "      ^^^ reached top of service chain"; break }
            $cur = [int]$p.ParentProcessId
            $guard++
        }
    }
