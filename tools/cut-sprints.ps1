$sprintRoot = "C:\Users\User\Desktop\sprints"

function Remove-IfExists($path) {
  if (Test-Path $path) { Remove-Item -Recurse -Force $path }
}

Get-ChildItem -Directory $sprintRoot | ForEach-Object {
  $sprintName = $_.Name
  $idx = [int]($sprintName -replace 'sprint_','')
  $base = $_.FullName

  # Controllers
  $keepControllers = @('Controller.php','TenantController.php','UserController.php')
  if ($idx -ge 1) { $keepControllers += 'PaymentController.php' }
  if ($idx -ge 3) { $keepControllers += 'HomeController.php' }
  if ($idx -ge 4) { $keepControllers += 'LabController.php' }
  if ($idx -ge 5) { $keepControllers += 'GroupController.php' }
  if ($idx -ge 6) { $keepControllers += 'ProjectController.php' }
  if ($idx -ge 8) { $keepControllers += 'EventController.php' }
  if ($idx -ge 9) { $keepControllers += 'NotificationController.php' }
  if ($idx -ge 10) { $keepControllers += 'SubUserController.php' }
  if ($idx -ge 12) { $keepControllers += 'SettingController.php' }

  $controllersPath = Join-Path $base "app\Http\Controllers"
  if (Test-Path $controllersPath) {
    Get-ChildItem -Path $controllersPath -File | ForEach-Object {
      if ($keepControllers -notcontains $_.Name) { Remove-IfExists $_.FullName }
    }
  }

  # Services
  $servicesPath = Join-Path $base "app\Services"
  if ($idx -lt 3) { Remove-IfExists (Join-Path $servicesPath "HomeOwnerDataService.php") }

  # Middleware
  $middlewarePath = Join-Path $base "app\Http\Middleware"
  if ($idx -lt 2) { Remove-IfExists (Join-Path $middlewarePath "PaymentTokenMiddleware.php") }

  # Models
  $keepModels = @('User.php','Tenant.php','Payment.php')
  if ($idx -ge 3) { $keepModels += @('Lab.php','Project.php','Event.php','Log.php','Notification.php','SubUsers.php') }
  if ($idx -ge 5) { $keepModels += 'Group.php' }
  if ($idx -ge 7) { $keepModels += @('ProjectVersion.php','ProjectFile.php') }
  if ($idx -ge 10) { $keepModels += 'SubUserInvite.php' }

  $modelsPath = Join-Path $base "app\Models"
  if (Test-Path $modelsPath) {
    Get-ChildItem -Path $modelsPath -File | ForEach-Object {
      if ($keepModels -notcontains $_.Name) { Remove-IfExists $_.FullName }
    }
  }

  # Migrations
  $migrationKeep = @(
    '*create_cache_table.php',
    '*create_jobs_table.php',
    '*create_users_table.php',
    '*create_tenants_table.php',
    '*create_payments_table.php'
  )
  if ($idx -ge 3) { $migrationKeep += @('*create_logs_table.php') }
  if ($idx -ge 4) { $migrationKeep += @('*create_labs_table.php') }
  if ($idx -ge 5) { $migrationKeep += @('*create_groups_table.php') }
  if ($idx -ge 6) { $migrationKeep += @('*create_projects_table.php') }
  if ($idx -ge 7) { $migrationKeep += @('*create_project_versions_table.php','*create_project_files_table.php') }
  if ($idx -ge 8) { $migrationKeep += @('*create_events_table.php') }
  if ($idx -ge 9) { $migrationKeep += @('*create_notifications_table.php') }
  if ($idx -ge 10) { $migrationKeep += @('*create_sub_users_table.php','*create_sub_user_invites_table.php') }

  $migrationsPath = Join-Path $base "database\migrations"
  if (Test-Path $migrationsPath) {
    Get-ChildItem -Path $migrationsPath -File | ForEach-Object {
      $keep = $false
      foreach ($pattern in $migrationKeep) {
        if ($_.Name -like $pattern) { $keep = $true; break }
      }
      if (-not $keep) { Remove-IfExists $_.FullName }
    }
  }

  # Views
  $viewsKeep = @(
    "resources\views\layouts\master.blade.php",
    "resources\views\main\index-plans.blade.php",
    "resources\views\main\login_regis\index-login.blade.php",
    "resources\views\main\login_regis\index-regis.blade.php"
  )
  if ($idx -ge 1) { $viewsKeep += "resources\views\main\payment\checkout.blade.php" }
  if ($idx -ge 2) { $viewsKeep += "resources\views\main\tenant\tenant-form.blade.php" }
  if ($idx -ge 3) {
    $viewsKeep += @(
      "resources\views\layouts\header-side-not.blade.php",
      "resources\views\main\home\index-home-owner.blade.php",
      "resources\views\main\home\index-home-student.blade.php",
      "resources\views\main\home\index-home.blade.php"
    )
  }
  if ($idx -ge 4) { $viewsKeep += "resources\views\main\home\labs-groups-projects\index-lab.blade.php" }
  if ($idx -ge 5) { $viewsKeep += "resources\views\main\home\labs-groups-projects\index-group.blade.php" }
  if ($idx -ge 6) { $viewsKeep += "resources\views\main\home\labs-groups-projects\index-project.blade.php" }
  if ($idx -ge 10) {
    $viewsKeep += @(
      "resources\views\main\subuser\register.blade.php",
      "resources\views\emails\subuser-invite.blade.php"
    )
  }
  if ($idx -ge 12) { $viewsKeep += "resources\views\main\users\index-user.blade.php" }

  $viewsPath = Join-Path $base "resources\views"
  if (Test-Path $viewsPath) {
    Get-ChildItem -Path $viewsPath -File -Recurse | ForEach-Object {
      $rel = $_.FullName.Substring($base.Length + 1)
      if ($viewsKeep -notcontains $rel) { Remove-IfExists $_.FullName }
    }
  }

  # Public assets
  $cssKeep = @('planos.css','log_reg.css')
  if ($idx -ge 1) { $cssKeep += 'pagamento.css' }
  if ($idx -ge 2) { $cssKeep += 'tenant-form.css' }
  if ($idx -ge 3) { $cssKeep += 'home.css' }
  if ($idx -ge 4) { $cssKeep += 'lab.css' }
  if ($idx -ge 5) { $cssKeep += 'group.css' }
  if ($idx -ge 6) { $cssKeep += 'proj.css' }
  if ($idx -ge 12) { $cssKeep += 'user.css' }

  $jsKeep = @()
  if ($idx -ge 3) { $jsKeep += 'home.js' }
  if ($idx -ge 4) { $jsKeep += 'lab.js' }
  if ($idx -ge 5) { $jsKeep += 'group.js' }
  if ($idx -ge 6) { $jsKeep += 'proj.js' }

  $publicMain = Join-Path $base "public\main"
  if (Test-Path $publicMain) {
    Get-ChildItem -Path $publicMain -File | ForEach-Object {
      if ($cssKeep -notcontains $_.Name) { Remove-IfExists $_.FullName }
    }
  }
  $publicScript = Join-Path $base "public\script"
  if (Test-Path $publicScript) {
    Get-ChildItem -Path $publicScript -File | ForEach-Object {
      if ($jsKeep -notcontains $_.Name) { Remove-IfExists $_.FullName }
    }
  }
}
