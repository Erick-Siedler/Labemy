<?php

use App\Services\EventReminderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('events:dispatch-reminders', function (EventReminderService $eventReminderService) {
    $result = $eventReminderService->dispatch();

    $this->info('Eventos processados: ' . $result['events_processed']);
    $this->info('Notificacoes criadas: ' . $result['notifications_created']);
    $this->info('Notificacoes ignoradas: ' . $result['notifications_skipped']);
})->purpose('Dispara lembretes diarios de eventos vencendo hoje e eventos vencidos');

Schedule::command('events:dispatch-reminders')->dailyAt('07:00');
