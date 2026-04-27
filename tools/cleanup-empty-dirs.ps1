$sprintRoot = "C:\Users\User\Desktop\sprints"
Get-ChildItem -Directory $sprintRoot | ForEach-Object {
  $viewsPath = Join-Path $_.FullName "resources\views"
  if (Test-Path $viewsPath) {
    Get-ChildItem -Path $viewsPath -Directory -Recurse | Sort-Object FullName -Descending | ForEach-Object {
      if (-not (Get-ChildItem -Path $_.FullName -Force)) { Remove-Item -Force $_.FullName }
    }
  }

  $publicMain = Join-Path $_.FullName "public\main"
  if (Test-Path $publicMain) {
    Get-ChildItem -Path $publicMain -Directory -Recurse | Sort-Object FullName -Descending | ForEach-Object {
      if (-not (Get-ChildItem -Path $_.FullName -Force)) { Remove-Item -Force $_.FullName }
    }
  }
  $publicScript = Join-Path $_.FullName "public\script"
  if (Test-Path $publicScript) {
    Get-ChildItem -Path $publicScript -Directory -Recurse | Sort-Object FullName -Descending | ForEach-Object {
      if (-not (Get-ChildItem -Path $_.FullName -Force)) { Remove-Item -Force $_.FullName }
    }
  }
}
