$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
docker compose stop
if ($LASTEXITCODE -ne 0) {
    Write-Host 'No fue posible detener los contenedores.' -ForegroundColor Red
    exit 1
}
Write-Host 'n8n y el buzón local fueron detenidos.' -ForegroundColor Green
