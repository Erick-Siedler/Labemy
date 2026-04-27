<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileSettingsService
{
    public static function defaultPreferences(): array
    {
        return [
            'theme' => 'light',
            'language' => 'pt-BR',
            'timezone' => 'America/Sao_Paulo',
            'compact' => true,
        ];
    }

    public static function defaultNotifications(): array
    {
        return [
            'email' => true,
            'calendar' => true,
            'projects' => false,
        ];
    }

    public function updateProfile(User $user, Request $request, array $options = []): User
    {
        $avatarDirectory = (string) ($options['avatar_directory'] ?? 'avatars');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore((int) $user->id),
            ],
            'phone' => 'nullable|string|max:30',
            'institution' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ((string) $data['email'] !== (string) $user->email) {
            $user->email_verified_at = null;
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'institution' => $data['institution'] ?? null,
            'bio' => $data['bio'] ?? null,
        ]);

        if ($request->hasFile('avatar')) {
            if (!empty($user->profile_photo_path)) {
                Storage::disk('public')->delete((string) $user->profile_photo_path);
            }

            $user->profile_photo_path = $request->file('avatar')->store($avatarDirectory, 'public');
        }

        $user->save();

        return $user;
    }

    public function updateSettings(User $user, Request $request, array $options = []): array
    {
        $passwordMinLength = (int) ($options['password_min_length'] ?? 8);
        $allowLogoutOtherSessions = (bool) ($options['allow_logout_other_sessions'] ?? false);
        $allowTenantLimits = (bool) ($options['allow_tenant_limits'] ?? false);

        $changedPassword = false;
        $tenantUpdated = false;
        $tenant = null;

        if ($request->filled('current_password') || $request->filled('new_password')) {
            $data = $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:' . max(1, $passwordMinLength) . '|confirmed',
            ]);

            if (!Hash::check((string) $data['current_password'], (string) $user->password)) {
                return [
                    'error' => [
                        'field' => 'current_password',
                        'message' => 'Senha atual incorreta.',
                    ],
                ];
            }

            $user->password = Hash::make((string) $data['new_password']);
            $changedPassword = true;

            if ($allowLogoutOtherSessions && $request->boolean('logout_other_sessions')) {
                Auth::logoutOtherDevices((string) $data['current_password']);
            }
        }

        if ($request->has('settings')) {
            if ($request->boolean('restore_preferences')) {
                $user->preferences = self::defaultPreferences();
            } else {
                $request->validate([
                    'settings.theme' => 'nullable|in:light,dark,auto',
                    'settings.language' => 'nullable|in:pt-BR,en,es',
                    'settings.timezone' => 'nullable|string|max:64',
                    'settings.compact' => 'nullable|boolean',
                ]);

                $defaults = self::defaultPreferences();
                $preferences = $user->preferences ?? [];
                $preferences['theme'] = $request->input('settings.theme', $preferences['theme'] ?? $defaults['theme']);
                $preferences['language'] = $request->input('settings.language', $preferences['language'] ?? $defaults['language']);
                $preferences['timezone'] = $request->input('settings.timezone', $preferences['timezone'] ?? $defaults['timezone']);
                $preferences['compact'] = $request->boolean('settings.compact');
                $user->preferences = $preferences;
            }
        }

        if ($request->has('notifications')) {
            $notifications = $user->notifications ?? [];
            $notifications['email'] = $request->boolean('notifications.email');
            $notifications['calendar'] = $request->boolean('notifications.calendar');
            $notifications['projects'] = $request->boolean('notifications.projects');
            $user->notifications = $notifications;
        }

        if ($allowTenantLimits && $request->has('tenant_limits')) {
            if ((string) $user->role !== 'owner' || (string) ($user->plan ?? '') === 'solo') {
                abort(403);
            }

            $data = $request->validate([
                'tenant_limits.max_labs' => 'nullable|integer|min:0',
                'tenant_limits.max_groups' => 'nullable|integer|min:0',
                'tenant_limits.max_projects' => 'nullable|integer|min:0',
                'tenant_limits.max_users' => 'nullable|integer|min:0',
                'tenant_limits.max_storage_mb' => 'nullable|integer|min:0',
            ]);

            $tenant = Tenant::where('creator_id', (int) $user->id)->first();
            if (!$tenant) {
                return [
                    'error' => [
                        'field' => 'tenant',
                        'message' => 'Tenant nao encontrado.',
                    ],
                ];
            }

            $settings = $tenant->settings;
            if (is_string($settings)) {
                $decoded = json_decode($settings, true);
                if (is_string($decoded)) {
                    $decoded = json_decode($decoded, true);
                }
                $settings = $decoded;
            }
            if (!is_array($settings)) {
                $settings = [];
            }

            $limits = $data['tenant_limits'] ?? [];
            $setSetting = function (string $key, $value) use (&$settings) {
                if ($value === null || $value === '') {
                    unset($settings[$key]);
                    return;
                }

                $settings[$key] = (int) $value;
            };

            $setSetting('max_labs', $limits['max_labs'] ?? null);
            $setSetting('max_groups', $limits['max_groups'] ?? null);
            $setSetting('max_projects', $limits['max_projects'] ?? null);
            $setSetting('max_users', $limits['max_users'] ?? null);
            $setSetting('max_storage_mb', $limits['max_storage_mb'] ?? null);

            $tenant->settings = $settings;
            $tenant->save();
            $tenantUpdated = true;
        }

        $user->save();

        return [
            'changedPassword' => $changedPassword,
            'tenantUpdated' => $tenantUpdated,
            'tenant' => $tenant,
            'user' => $user,
        ];
    }
}
