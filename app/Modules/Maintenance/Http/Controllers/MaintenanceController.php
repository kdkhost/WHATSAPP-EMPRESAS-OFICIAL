<?php

namespace App\Modules\Maintenance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Services\SettingsManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceController extends Controller
{
    public function index(SettingsManager $s)
    {
        $settings = [
            'enabled'            => $s->get('maintenance.enabled', false),
            'title'              => $s->get('maintenance.title', 'Em manutencao'),
            'message'            => $s->get('maintenance.message', ''),
            'until_at'           => $s->get('maintenance.until_at'),
            'background_image'   => $s->get('maintenance.background_image', ''),
            'background_opacity' => $s->get('maintenance.background_opacity', 50),
            'overlay_color'      => $s->get('maintenance.overlay_color', '#000000'),
            'text_color'         => $s->get('maintenance.text_color', '#ffffff'),
        ];
        $devices = DB::table('trusted_devices')->orderByDesc('id')->get();
        return view('admin.maintenance.index', compact('settings', 'devices'));
    }

    public function update(Request $request, SettingsManager $s)
    {
        $data = $request->validate([
            'enabled'            => 'required|boolean',
            'title'              => 'required|string|max:120',
            'message'            => 'nullable|string',
            'until_at'           => 'nullable|date',
            'background_image'   => 'nullable|string|max:500',
            'background_opacity' => 'nullable|integer|min:0|max:100',
            'overlay_color'      => 'nullable|string|max:7',
            'text_color'         => 'nullable|string|max:7',
        ]);
        foreach ($data as $k => $v) {
            $type = match($k) {
                'enabled' => 'bool',
                'background_opacity' => 'int',
                default => 'string',
            };
            $s->set("maintenance.{$k}", $v, $type);
        }
        return response()->json(['ok' => true]);
    }

    public function storeDevice(Request $request)
    {
        $data = $request->validate([
            'label'       => 'nullable|string|max:120',
            'ip'          => 'nullable|ip',
            'mac'         => 'nullable|string|max:30',
            'fingerprint' => 'nullable|string|max:80',
        ]);
        DB::table('trusted_devices')->insert($data + ['active' => true, 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function destroyDevice(int $id)
    {
        DB::table('trusted_devices')->where('id', $id)->delete();
        return response()->json(['ok' => true]);
    }
}
