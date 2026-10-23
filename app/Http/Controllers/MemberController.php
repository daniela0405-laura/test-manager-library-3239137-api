<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\LoanItem;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

// HU-05. Contrato §3.5. CA-05.3: no hay método para eliminar socios.
class MemberController extends Controller
{
    // CA-05.2: lista y busca por nombre o documento, paginado.
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'size' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $page = (int) ($data['page'] ?? 1);
        $size = (int) ($data['size'] ?? 20);

        $query = Member::query();
        if (! empty($data['q'])) {
            $q = '%'.$data['q'].'%';
            $query->where(fn ($w) => $w->where('full_name', 'ilike', $q)->orWhere('document_number', 'ilike', $q));
        }

        $total = (clone $query)->count();
        $items = $query->orderBy('full_name')->forPage($page, $size)->get();

        return ['items' => $items, 'page' => $page, 'size' => $size, 'total' => $total];
    }

    // Detalle con active_loans: libros sin devolver.
    public function show(string $id)
    {
        if (! Str::isUuid($id) || ! $member = Member::find($id)) {
            throw new BusinessException('NOT_FOUND', 'El socio no existe.', 404);
        }

        $active = LoanItem::whereNull('returned_at')
            ->whereHas('loan', fn ($q) => $q->where('member_id', $member->id))
            ->count();

        return [...$member->toArray(), 'active_loans' => $active];
    }

    // CA-05.1: documento único; repetido responde 409.
    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:200'],
            'document_number' => ['required', 'string', 'max:30'],
        ]);

        $document = trim($data['document_number']);
        if (Member::where('document_number', $document)->exists()) {
            throw new BusinessException('DOCUMENT_TAKEN', 'Ya existe un socio con ese documento.', 409);
        }

        $member = Member::create([
            'full_name' => trim($data['full_name']),
            'document_number' => $document,
        ]);

        return response()->json($member, 201);
    }
}