#Requires -Version 5.1
[CmdletBinding()]
param(
    [string]$Message = "",
    [switch]$SkipGit = $false
)
& "$PSScriptRoot\deploy.ps1" -Message $Message -SkipGit:$SkipGit
