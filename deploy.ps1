#Requires -Version 5.1
<#
.SYNOPSIS
    Deploy completo: git add, commit, push e deploy remoto automatico.
.DESCRIPTION
    Faz staging de todas as alteracoes, commit com mensagem fornecida,
    push para origin main e, se bem-sucedido, executa o deploy remoto
    via SSH usando node deploy_temp.js.
.EXAMPLE
    .\deploy.ps1 -Message "Corrige bug no login"
    .\deploy.ps1                    # usa mensagem padrao com timestamp
#>
[CmdletBinding()]
param(
    [string]$Message = "",
    [switch]$SkipGit = $false
)

$ErrorActionPreference = "Stop"
$repoPath = Split-Path -Parent $MyInvocation.MyCommand.Definition
Set-Location $repoPath

# Cores para output
function Write-Info  ($msg) { Write-Host "[INFO]  $msg" -ForegroundColor Cyan }
function Write-Ok    ($msg) { Write-Host "[OK]    $msg" -ForegroundColor Green }
function Write-Warn  ($msg) { Write-Host "[WARN]  $msg" -ForegroundColor Yellow }
function Write-Error ($msg) { Write-Host "[ERROR] $msg" -ForegroundColor Red }

if (-not $SkipGit) {
    # 1. Verificar se ha mudancas nao commitadas
    $status = git status --short 2>$null
    if (-not [string]::IsNullOrWhiteSpace($status)) {
        Write-Info "Alteracoes detectadas:"
        git status --short | ForEach-Object { Write-Host "        $_" }

        # 2. Commit
        if ([string]::IsNullOrWhiteSpace($Message)) {
            $ts = Get-Date -Format "yyyy-MM-dd HH:mm"
            $Message = "Atualizacao automatica - $ts"
        }

        Write-Info "Adicionando arquivos..."
        git add -A
        if ($LASTEXITCODE -ne 0) { throw "Falha no git add" }

        Write-Info "Commit: '$Message'"
        git commit -m "$Message"
        if ($LASTEXITCODE -ne 0) { throw "Falha no git commit" }
    }

    # 3. Verificar se ha commits locais para enviar (ahead)
    $unpushed = git log origin/main..HEAD --oneline 2>$null
    if (-not [string]::IsNullOrWhiteSpace($unpushed)) {
        Write-Info "Commits locais pendentes de envio detectados:"
        $unpushed | ForEach-Object { Write-Host "        $_" }
        Write-Info "Push para origin main..."
        git push origin main
        if ($LASTEXITCODE -ne 0) { throw "Falha no git push" }
        Write-Ok "Push realizado com sucesso."
    } else {
        Write-Info "Nenhum commit pendente de envio para o Git."
    }
}

# 4. Deploy remoto
Write-Info "Iniciando deploy remoto via SSH..."
& node "$repoPath\deploy.js"
if ($LASTEXITCODE -ne 0) { throw "Falha no deploy remoto" }

Write-Ok "Deploy concluido!"
