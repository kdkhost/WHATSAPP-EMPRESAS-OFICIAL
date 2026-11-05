<?php

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $logs = DB::table('audit_logs')
            ->leftJoin('admin_users', 'admin_users.id', '=', 'audit_logs.admin_user_id')
            ->select('audit_logs.*', 'admin_users.name as admin_name', 'admin_users.email as admin_email')
            ->orderByDesc('audit_logs.created_at')
            ->paginate(50);
        return view('security::audit', compact('logs'));
    }
}
