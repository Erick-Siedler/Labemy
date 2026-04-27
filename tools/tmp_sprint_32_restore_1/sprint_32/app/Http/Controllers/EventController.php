<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use App\Models\Lab;
use App\Models\SubUsers;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityService;

class EventController extends Controller
{
    function store(Request $request){
        $subUser = Auth::guard('subusers')->user();
        if ($subUser) {
            if ($subUser->role !== 'teacher') {
                abort(403);
            }
            $tenantId = $subUser->tenant_id ?: Lab::where('id', $subUser->lab_id)->value('tenant_id');
            $tenant = Tenant::where('id', $tenantId)->firstOrFail();
            $creatorId = $tenant->creator_id;
        } else {
            $tenant = Tenant::where('creator_id', Auth::id())->firstOrFail();
            $creatorId = Auth::id();
        }

        $validator = Validator::make($request->all(), [
            'lab_id' => 'required',
            'title' => 'required|min:3',
            'description' => 'required|min:3',
            'color' => 'required',
            'due' => 'required',
            'is_mandatory' => 'required'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator, 'event')->withInput();
        }

        $data = $validator->validated();
        
        if ($data['lab_id'] !== 'all') {
            $labQuery = Lab::where('tenant_id', $tenant->id)
                ->where('id', $data['lab_id']);

            if ($subUser) {
                $labQuery->where('creator_subuser_id', $subUser->id);
            }

            $lab = $labQuery->first();

            if (!$lab) {
                return back()->withErrors([
                    'lab_id' => 'Laboratório inválido.',
                ], 'event')->withInput();
            }
        }
        
        if($data['lab_id'] === 'all'){
            if ($subUser) {
                return back()->withErrors([
                    'lab_id' => 'Selecione um laboratório válido.',
                ], 'event')->withInput();
            }
            $labs = Lab::where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->get();

            $actor = ActivityService::resolveActor();
            foreach ($labs as $lab){
                $event = Event::create([
                    'tenant_id' => $tenant->id,
                    'lab_id' => $lab->id,
                    'created_by' => $creatorId,
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'color' => $data['color'],
                    'due' => $data['due'],
                    'is_mandatory' => $data['is_mandatory']
                ]);

                if (!empty($actor['tenant_id'])) {
                    ActivityService::log(
                        (int) $actor['tenant_id'],
                        (int) $actor['actor_id'],
                        (string) $actor['actor_role'],
                        'event_create',
                        'event',
                        (int) $event->id,
                        'Evento criado: ' . $event->title . ' (Lab: ' . $lab->name . ').'
                    );
                }
            }

            $message = 'Novo evento criado para todos os laboratórios: ' . $data['title'] . ' (Vencimento: ' . $data['due'] . ').';
            $subUsers = SubUsers::where('tenant_id', $tenant->id)->get(['id']);
            ActivityService::notifySubUsers($subUsers, $message, 'alert');

            return $subUser
                ? redirect()->route('subuser-home')
                : redirect()->route('home');
            
        }else{
            $event = Event::create([
                'tenant_id' => $tenant->id,
                'lab_id' => $data['lab_id'],
                'created_by' => $creatorId,
                'title' => $data['title'],
                'description' => $data['description'],
                'color' => $data['color'],
                'due' => $data['due'],
                'is_mandatory' => $data['is_mandatory']
            ]);

            $actor = ActivityService::resolveActor();
            if (!empty($actor['tenant_id'])) {
                $labName = Lab::where('id', $data['lab_id'])->value('name') ?? 'N/A';
                ActivityService::log(
                    (int) $actor['tenant_id'],
                    (int) $actor['actor_id'],
                    (string) $actor['actor_role'],
                    'event_create',
                    'event',
                    (int) $event->id,
                    'Evento criado: ' . $event->title . ' (Lab: ' . $labName . ').'
                );
            }

            if (!$subUser) {
                $labName = Lab::where('id', $data['lab_id'])->value('name') ?? 'N/A';
                $message = 'Novo evento criado: ' . $data['title'] . ' (Lab: ' . $labName . ' / Vencimento: ' . $data['due'] . ').';
                $subUsers = SubUsers::where('tenant_id', $tenant->id)->get(['id']);
                ActivityService::notifySubUsers($subUsers, $message, 'alert');
            }

            return $subUser
                ? redirect()->route('subuser-home')
                : redirect()->route('home');
        }
        
    }
}
