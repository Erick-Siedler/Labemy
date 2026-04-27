<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\SubUserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SubUserHomeController;
use App\Http\Controllers\SubFolderController;
use App\Http\Controllers\LogExportController;
use App\Http\Controllers\SidebarDeleteController;
use App\Http\Controllers\RequirementsController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TenantAccessController;

// Rotas publicas da landing e contratacao de planos.
Route::get('/', [TenantController::class, 'index'])->name('plans');

Route::get('/checkout/{plan}', [PaymentController::class, 'checkout'])
    ->name('payment.checkout');

Route::post('/checkout/{plan}', [PaymentController::class, 'pay'])
    ->name('payment.pay');

Route::post('/trial/cancel', [PaymentController::class, 'cancelTrial'])
    ->middleware(['auth:web', 'session.timeout'])
    ->name('trial.cancel');

// Fluxo de autenticacao e cadastro de tenant/subusuario.
Route::get('/tenant/login', [UserController::class, 'indexLogin'])
    ->name('login');

Route::post('/tenant/login/auth', [UserController::class, 'Login'])
    ->name('login-auth');

Route::get('/tenant/forgot-password', [UserController::class, 'indexForgotPassword'])
    ->name('password.request');

Route::post('/tenant/forgot-password', [UserController::class, 'sendResetLink'])
    ->middleware('throttle:6,1')
    ->name('password.email');

Route::get('/tenant/reset-password/{token}', [UserController::class, 'indexResetPassword'])
    ->name('password.reset');

Route::post('/tenant/reset-password', [UserController::class, 'resetPassword'])
    ->middleware('throttle:6,1')
    ->name('password.update');

Route::post('/logout', [UserController::class, 'logout'])
    ->name('logout');

Route::get('/tenant/register', [UserController::class, 'indexRegis'])
->name('register');

Route::post('/tenant/register', [UserController::class, 'Regis'])
->middleware('throttle:registration')
->name('register-add');

Route::view('/termos-de-uso', 'main.legal.terms-of-use')
    ->name('legal.terms');

Route::view('/politica-de-privacidade', 'main.legal.privacy-policy')
    ->name('legal.privacy');

Route::get('/tenant/select', [TenantAccessController::class, 'index'])
    ->middleware(['auth:web', 'session.timeout'])
    ->name('tenant.select.index');

Route::post('/tenant/select', [TenantAccessController::class, 'select'])
    ->middleware(['auth:web', 'session.timeout'])
    ->name('tenant.select.store');

Route::post('/tenant/relation/revoke', [TenantAccessController::class, 'revoke'])
    ->middleware(['auth:web', 'session.timeout'])
    ->name('tenant.relation.revoke');

Route::get('/invite/{token}', [SubUserController::class, 'showRegister'])
->middleware('throttle:120,1')
->name('invite-link');
Route::get('/invite/{groupSlug}/{token}', [SubUserController::class, 'showRegisterWithGroup'])
->middleware('throttle:120,1')
->name('invite-link-short');

Route::get('/subuser/register/{token}', [SubUserController::class, 'showRegister'])
->middleware('throttle:120,1')
->name('subuser-register');

Route::post('/subuser/register/{token}', [SubUserController::class, 'store'])
->middleware('throttle:registration')
->name('subuser-register-store');

// Onboarding de tenant: apenas autenticacao web.
Route::middleware(['auth:web', 'session.timeout'])->group(function () {
    Route::get('/tenant/create/{token}', [TenantController::class, 'create'])->name('tenant-create');
    Route::post('/tenant/create/{token}', [TenantController::class, 'store'])->name('tenant-store');
});

// Area protegida por tenant ativo e pagamento valido.
Route::middleware(['auth:web', 'session.timeout', 'tenant.selected', 'payment.token'])->group(function () {
    // Rotas base de dashboard/conta (inclui fluxo solo sem tenant ativo).
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('/home/solo', [HomeController::class,'indexSolo'])->name('home-solo');

    Route::get('home/user/profile', [UserController::class, 'indexProf'])->name('profile');
    Route::post('home/user/profile', [UserController::class, 'edit'])->name('profile-edit');

    Route::get('home/user/settings', [SettingController::class, 'index'])->name('settings');
    Route::post('home/user/settings', [SettingController::class, 'edit'])->name('settings-edit');

    // Owner.
    Route::middleware('tenant.role:owner')->group(function () {
        Route::delete('/versions/{version}', [ProjectController::class, 'destroyVersion'])->name('versions.destroy');
        Route::put('/versions/{version}', [ProjectController::class, 'updateVersion'])->name('versions.update');
    });

    // Teacher+.
    Route::middleware('tenant.role:teacher')->group(function () {
        Route::post('/home/event', [EventController::class, 'store'])->name('event-add');
        Route::delete('/home/event/{event}', [EventController::class, 'destroy'])->name('event-destroy');

        Route::get('/home/lab/{lab}', [LabController::class, 'index'])->name('lab.index');
        Route::post('/home/lab', [LabController::class, 'store'])->name('lab-add');
        Route::put('/home/lab/status', [LabController::class, 'update'])->name('lab-update');
        Route::delete('/home/lab/{lab}', [SidebarDeleteController::class, 'destroyLab'])->name('lab-destroy');

        Route::post('/home/lab/group', [GroupController::class, 'store'])->name('group-add');
        Route::put('/home/group/status', [GroupController::class, 'update'])->name('group-update');
        Route::put('/home/group/member-role', [GroupController::class, 'updateMemberRole'])->name('group-member-role-update');
        Route::delete('/home/group/member-relation', [GroupController::class, 'revokeMemberRelation'])->name('group-member-relation-revoke');
        Route::delete('/home/group/{group}', [SidebarDeleteController::class, 'destroyGroup'])->name('group-destroy');

        Route::delete('/home/subfolder/{subfolder}', [SidebarDeleteController::class, 'destroySubfolder'])->name('subfolder-destroy');

        Route::post('/home/students/invite', [SubUserController::class, 'sendInvite'])
            ->middleware('throttle:30,1')
            ->name('subuser-invite');
        Route::post('/home/students/invite/revoke-active', [SubUserController::class, 'revokeActiveInvites'])
            ->middleware('throttle:30,1')
            ->name('subuser-invite-revoke-active');

        Route::get('/home/logs/export', LogExportController::class)->name('logs.export');
        Route::post('/versions/{version}/comments', [ProjectController::class, 'storeComment'])->name('versions.comments.store');
    });

    // Assistant+.
    Route::middleware('tenant.role:assistant')->group(function () {
        Route::get('/home/group/{group}', [GroupController::class, 'index'])->name('group.index');
    });

    // Student+.
    Route::middleware('tenant.role:student')->group(function () {
        Route::get('/student/home', [SubUserHomeController::class, 'home'])->name('subuser-home');
        Route::get('/student/home/settings', [SettingController::class, 'indexSub'])->name('subuser-settings');
        Route::post('/student/profile', [SubUserController::class, 'updateProfile'])->name('subuser-profile-edit');
        Route::post('/student/settings', [SubUserController::class, 'updateSettings'])->name('subuser-settings-edit');
        Route::post('/student/logout', [SubUserController::class, 'logout'])->name('subuser-logout');

        Route::get('/home/project/{project}', [ProjectController::class, 'index'])->name('project.index');
        Route::post('/home/lab/group/project', [ProjectController::class, 'store'])->name('project-add');
        Route::delete('/home/project/{project}', [SidebarDeleteController::class, 'destroyProject'])->name('project-destroy');
        Route::put('/home/project/status', [ProjectController::class, 'update'])->name('project-update');

        Route::get('/projects/{project}/requirements', [RequirementsController::class, 'index'])->name('requirements.index');
        Route::get('/projects/{project}/requirements/export', [RequirementsController::class, 'export'])->name('requirements.export');
        Route::post('/projects/{project}/requirements/func', [RequirementsController::class, 'storeFunc'])->name('requirements.func.store');
        Route::put('/projects/{project}/requirements/func/{funcReq}', [RequirementsController::class, 'updateFunc'])->name('requirements.func.update');
        Route::delete('/projects/{project}/requirements/func/{funcReq}', [RequirementsController::class, 'destroyFunc'])->name('requirements.func.destroy');
        Route::post('/projects/{project}/requirements/nonfunc', [RequirementsController::class, 'storeNonFunc'])->name('requirements.nonfunc.store');
        Route::put('/projects/{project}/requirements/nonfunc/{nonFuncReq}', [RequirementsController::class, 'updateNonFunc'])->name('requirements.nonfunc.update');
        Route::delete('/projects/{project}/requirements/nonfunc/{nonFuncReq}', [RequirementsController::class, 'destroyNonFunc'])->name('requirements.nonfunc.destroy');

        Route::get('/home/subfolder/{subfolder}', [SubFolderController::class, 'index'])->name('subfolder-index');
        Route::post('home/subfolder', [SubFolderController::class, 'store'])->name('subfolder-add');
        Route::put('/home/subfolder/status', [SubFolderController::class, 'update'])->name('subfolder-update');

        Route::post('/home/project/version', [ProjectController::class, 'storeVersion'])->name('project-version-add');
        Route::post('/home/project/version/chunk/status', [ProjectController::class, 'chunkUploadStatus'])
            ->name('project-version-chunk-status');
        Route::post('/home/project/version/chunk', [ProjectController::class, 'chunkUpload'])
            ->name('project-version-chunk');
        Route::post('/home/project/version/chunk/complete', [ProjectController::class, 'completeChunkUpload'])
            ->name('project-version-chunk-complete');
        Route::get('/home/project/version-file/{projectFile}', [ProjectController::class, 'downloadVersionFile'])
            ->name('project-version-file-download');

        Route::post('/home/notification/destroy', [NotificationController::class, 'destroy'])->name('not-destroy');
        Route::post('/home/notification/destroy-all', [NotificationController::class, 'destroyAll'])->name('not-destroy-all');

        Route::get('/versions/{version}/files', [App\Http\Controllers\VersionBrowserController::class, 'index'])->name('versions.files');
        Route::get('/versions/{version}/view', [App\Http\Controllers\VersionBrowserController::class, 'view'])->name('versions.view');
        Route::get('/versions/{version}/raw', [App\Http\Controllers\VersionBrowserController::class, 'raw'])->name('versions.raw');

        Route::post('/tasks/add', [TaskController::class, 'store'])->name('task-add');
        Route::patch('/tasks/status/{task}', [TaskController::class, 'updateStatus'])->name('task-status-update');
        Route::put('/tasks/edit/{task}', [TaskController::class, 'edit'])->name('task-edit');
        Route::delete('/tasks/delete/{task}', [TaskController::class, 'destroy'])->name('task-destroy');
    });
});
