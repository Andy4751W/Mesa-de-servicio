$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

function New-HexSecret {
    $bytes = New-Object byte[] 32
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $generator.GetBytes($bytes)
    }
    finally {
        $generator.Dispose()
    }

    return ([System.BitConverter]::ToString($bytes)).Replace('-', '').ToLowerInvariant()
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    Write-Host 'Docker no esta instalado o no esta disponible en PATH.' -ForegroundColor Red
    Write-Host 'Instale Docker Desktop y vuelva a ejecutar este archivo.'
    exit 1
}

docker compose version | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Host 'Docker Compose no esta disponible.' -ForegroundColor Red
    exit 1
}

if (-not (Test-Path '.env')) {
    $contenido = @(
        "N8N_ENCRYPTION_KEY=$(New-HexSecret)"
        "MESA_LOCAL_WEBHOOK_SECRET=$(New-HexSecret)"
        "MESA_LOCAL_RECOVERY_PEPPER=$(New-HexSecret)"
    )
    Set-Content -Path '.env' -Value $contenido -Encoding UTF8
    Write-Host 'Se creo n8n/local/.env con secretos locales.' -ForegroundColor Green
}

docker compose up -d
if ($LASTEXITCODE -ne 0) {
    Write-Host 'No fue posible iniciar los contenedores. Verifique Docker Desktop.' -ForegroundColor Red
    exit 1
}

Write-Host ''
Write-Host 'Ambiente local iniciado:' -ForegroundColor Green
Write-Host '  n8n:    http://localhost:5678'
Write-Host '  Correos: http://localhost:8025'
Write-Host ''
Write-Host 'Continúe con docs/PRUEBA_LOCAL_N8N.md.'
