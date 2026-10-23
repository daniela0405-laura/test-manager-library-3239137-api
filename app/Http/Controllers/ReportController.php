<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// HU-10. Contrato §3.7. Agrega por los valores congelados de la línea; sin socios ni bibliotecarios.
class ReportController extends Controller
{
    public function mostBorrowed(Request $request)
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
        ]);

        // CA-10.2: fin anterior al inicio responde 422 INVALID_DATE_RANGE.
        if ($data['to'] < $data['from']) {
            throw new BusinessException('INVALID_DATE_RANGE', 'La fecha final es anterior a la inicial.', 422);
        }

        $end = now()->parse($data['to'])->addDay()->toDateString();

        $rows = DB::table('loan_item')
            ->join('loan', 'loan.id', '=', 'loan_item.loan_id')
            ->where('loan.loaned_at', '>=', $data['from'].' 00:00:00+00')
            ->where('loan.loaned_at', '<', $end.' 00:00:00+00')
            ->groupBy('loan_item.book_id', 'loan_item.book_title', 'loan_item.category_name')
            ->orderByDesc('times_borrowed')
            ->orderBy('loan_item.book_title')
            ->get([
                'loan_item.book_id',
                'loan_item.book_title',
                'loan_item.category_name',
                DB::raw('COUNT(*)::int as times_borrowed'),
            ]);

        return ['from' => $data['from'], 'to' => $data['to'], 'rows' => $rows];
    }
}