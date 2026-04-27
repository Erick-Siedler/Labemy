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

Route::get('/', [TenantController::class, 'index'])->name('plans');

Route::get('/checkout/{plan}', [PaymentController::class, 'checkout'])
    ->name('payment.checkout');

Route::post('/checkout/{plan}', [PaymentController::class, 'pay'])
    ->name('payment.pay');

Route::post('/trial/cancel', [PaymentController::class, 'cancelTrial'])
    ->middleware('auth:web')
    ->name('trial.cancel');

Route::get('/tenant/login', [UserController::class, 'indexLogin'])
    ->name('login');

Route::post('/tenant/login/auth', [UserController::class, 'Login'])
    ->name('login-auth');

Route::post('/logout', [UserController::class, 'logout'])
    ->name('logout');

Route::get('/tenant/register', [UserController::class, 'indexRegis'])
->name('register');

Route::post('/tenant/register', [UserController::class, 'Regis'])
->middleware('throttle:registration')
->name('register-add');

Route::get('/subuser/register/{token}', [SubUserController::class, 'showRegister'])
->name('subuser-register');

Route::post('/subuser/register/{token}', [SubUserController::class, 'store'])
->middleware('throttle:registration')
->name('subuser-register-store');

Route::middleware(['auth:web,subusers', 'payment.token'])->group(function () {
    Route::get('/student/home', [SubUserHomeController::class, 'home'])->name('subuser-home');
    Route::get('/student/home/settings', [SettingController::class, 'indexSub'])->name('subuser-settings');
    Route::post('/student/profile', [SubUserController::class, 'updateProfile'])->name('subuser-profile-edit');
    Route::post('/student/settings', [SubUserController::class, 'updateSettings'])->name('subuser-settings-edit');
    Route::post('/student/logout', [SubUserController::class, 'logout'])->name('subuser-logout');

    //Users
    Route::get('/tenant/create/{token}', [TenantController::class, 'create'])->name('tenant-create');
    Route::post('/tenant/create/{token}', [TenantController::class, 'store'])->name('tenant-store');

    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('/home/solo', [HomeController::class,'indexSolo'])->name('home-solo');

    Route::get('home/user/profile', [UserController::class, 'indexProf'])->name('profile');
    Route::post('home/user/profile', [UserController::class, 'edit'])->name('profile-edit');

    Route::get('home/user/settings', [SettingController::class, 'index'])->name('settings');
    Route::post('home/user/settings', [SettingController::class, 'edit'])->name('settings-edit');

    Route::post('/home/event', [EventController::class, 'store'])->name('event-add');

    Route::get('/home/lab/{lab}', [LabController::class, 'index'])->name('lab.index');
    Route::post('/home/lab', [LabController::class, 'store'])->name('lab-add');
    Route::put('/home/lab/status', [LabController::class, 'update'])->name('lab-update');

    Route::get('/home/group/{group}', [GroupController::class, 'index'])->name('group.index');
    Route::post('/home/lab/group', [GroupController::class, 'store'])->name('group-add');
    Route::put('/home/group/status', [GroupController::class, 'update'])->name('group-update');
    Route::put('/home/group/member-role', [GroupController::class, 'updateMemberRole'])->name('group-member-role-update');

    Route::get('/home/project/{project}', [ProjectController::class, 'index'])->name('project.index');
    Route::post('/home/lab/group/project', [ProjectController::class, 'store'])->name('project-add');

    Route::post('/home/project/version', [ProjectController::class, 'storeVersion'])->name('project-version-add');
    Route::get('/home/project/version-file/{projectFile}', [ProjectController::class, 'downloadVersionFile'])
        ->name('project-version-file-download');
    Route::put('/home/project/status', [ProjectController::class, 'update'])->name('project-update');
    Route::post('/home/students/invite', [SubUserController::class, 'sendInvite'])->name('subuser-invite');

    Route::post('/home/notification/destroy', [NotificationController::class, 'destroy'])->name('not-destroy');

    Route::get('/versions/{version}/files', [App\Http\Controllers\VersionBrowserController::class, 'index'])
        ->name('versions.files');

    Route::get('/versions/{version}/view', [App\Http\Controllers\VersionBrowserController::class, 'view'])
        ->name('versions.view');

    Route::get('/versions/{version}/raw', [App\Http\Controllers\VersionBrowserController::class, 'raw'])
        ->name('versions.raw');

    Route::post('/versions/{version}/comments', [ProjectController::class, 'storeComment'])
        ->name('versions.comments.store');

    Route::delete('/versions/{version}', [ProjectController::class, 'destroyVersion'])
        ->name('versions.destroy');

    Route::put('/versions/{version}', [ProjectController::class, 'updateVersion'])
        ->name('versions.update');

});
