<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Personal;
use App\Models\Branch;
use App\Models\Caja;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        if ($user->is_super_admin) {
            $totalUsers    = User::count();
            $totalPersonal = Personal::count();
            $totalBranches = Branch::count();
            $totalCajas    = Caja::count();
        } else {
            $companyId     = $company?->id;
            $totalUsers    = User::whereHas('companies', fn($q) => $q->where('companies.id', $companyId))->count();
            $totalPersonal = Personal::where('company_id', $companyId)->count();
            $totalBranches = Branch::where('company_id', $companyId)->count();
            $totalCajas    = Caja::where('company_id', $companyId)->count();
        }

        return view('dashboard.index', compact(
            'totalUsers',
            'totalPersonal',
            'totalBranches',
            'totalCajas',
            'company'
        ));
    }
}
