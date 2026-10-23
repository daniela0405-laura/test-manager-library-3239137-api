<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\Book;
use App\Models\Loan;
use App\Models\LoanItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// HU-06 a HU-09. Contrato §3.6.
class LoanController extends Controller
{
    // HU-06: registrar un préstamo. Todo en una transacción (CA-06.3).
    public function store(Request $request)
    {
        $data = $request->validate([
            'member_id' => ['required', 'uuid', 'exists:member,id'],
            'items' => ['required', 'array', 'min:1', 'max:3'],
            'items.*.book_id' => ['required', 'uuid'],
        ]);

        $bookIds = array_column($data['items'], 'book_id');

        // CA-06.2: el mismo libro no se repite.
        if (count($bookIds) !== count(array_unique($bookIds))) {
            throw new BusinessException('DUPLICATE_BOOK_IN_LOAN', 'No se puede repetir un libro en el préstamo.', 422);
        }

        $loan = DB::transaction(function () use ($data, $bookIds, $request) {
            $open = LoanItem::whereNull('returned_at')
                ->whereHas('loan', fn ($q) => $q->where('member_id', $data['member_id']));

            // CA-06.5: socio con vencidos no pide más.
            if ((clone $open)->where('due_at', '<', now())->exists()) {
                throw new BusinessException('MEMBER_HAS_OVERDUE', 'El socio tiene libros vencidos.', 409);
            }

            // CA-06.4: máximo 3 libros sin devolver, con el conteo actual.
            $current = (clone $open)->count();
            if ($current + count($bookIds) > 3) {
                throw new BusinessException('MEMBER_LIMIT_EXCEEDED',
                    "El socio ya tiene {$current} libros sin devolver; el máximo es 3.", 422,
                    [['field' => 'items', 'issue' => "sin devolver: {$current}"]]);
            }

            $loanedAt = now('UTC');
            $loan = Loan::create([
                'member_id' => $data['member_id'],
                'loaned_by_user_id' => $request->user()->id,
                'loaned_at' => $loanedAt,
            ]);

            foreach ($bookIds as $i => $bookId) {
                // RNF-2: descuento condicional. Si dos piden el último ejemplar, solo uno afecta la fila.
                $updated = Book::whereKey($bookId)->where('available_copies', '>', 0)
                    ->decrement('available_copies');

                if ($updated === 0) {
                    throw new BusinessException('BOOK_NOT_AVAILABLE', 'No quedan ejemplares disponibles.', 409,
                        [['field' => "items[{$i}].book_id", 'issue' => 'sin ejemplares']]);
                }

                $book = Book::with('category')->find($bookId);

                // CA-06.6 y RN-4: la línea congela título, ISBN y categoría.
                LoanItem::create([
                    'loan_id' => $loan->id,
                    'book_id' => $book->id,
                    'book_title' => $book->title,
                    'isbn' => $book->isbn,
                    'category_name' => $book->category->name,
                    'due_at' => $loanedAt->copy()->addDays(14),
                    'returned_at' => null,
                ]);
            }

            return $loan;
        });

        return response()->json($loan->load('items'), 201);
    }

    // HU-08: listar por rango, estado y socio. El estado se calcula, no se guarda.
    public function index(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'in:open,overdue,closed'],
            'member_id' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1'],
            'size' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $page = (int) ($data['page'] ?? 1);
        $size = (int) ($data['size'] ?? 20);

        $query = Loan::with('items');
        if (! empty($data['from'])) {
            $query->where('loaned_at', '>=', $data['from'].' 00:00:00+00');
        }
        if (! empty($data['to'])) {
            $query->where('loaned_at', '<', now()->parse($data['to'])->addDay()->toDateString().' 00:00:00+00');
        }
        if (! empty($data['member_id'])) {
            $query->where('member_id', $data['member_id']);
        }

        $openItem = fn ($q) => $q->whereNull('returned_at');
        $lateItem = fn ($q) => $q->whereNull('returned_at')->where('due_at', '<', now());
        match ($data['status'] ?? null) {
            'closed' => $query->whereDoesntHave('items', $openItem),
            'overdue' => $query->whereHas('items', $lateItem),
            'open' => $query->whereHas('items', $openItem)->whereDoesntHave('items', $lateItem),
            default => null,
        };

        $total = (clone $query)->count();
        $items = $query->orderByDesc('loaned_at')->forPage($page, $size)->get();

        return ['items' => $items, 'page' => $page, 'size' => $size, 'total' => $total];
    }

    // CA-08.1: préstamo con líneas y estado calculado.
    public function show(string $id)
    {
        $loan = Str::isUuid($id) ? Loan::with('items')->find($id) : null;
        if (! $loan) {
            throw new BusinessException('NOT_FOUND', 'El préstamo no existe.', 404);
        }
        return $loan;
    }

    // HU-07: devolver una línea. Nunca suma dos veces (CA-07.2).
    public function returnItem(string $loanId, string $itemId)
    {
        if (! Str::isUuid($loanId) || ! Str::isUuid($itemId)) {
            throw new BusinessException('NOT_FOUND', 'La línea no existe.', 404);
        }

        $item = DB::transaction(function () use ($loanId, $itemId) {
            // Actualización condicional: solo si aún no está devuelta.
            $updated = LoanItem::whereKey($itemId)->where('loan_id', $loanId)
                ->whereNull('returned_at')->update(['returned_at' => now('UTC')]);

            if ($updated === 0) {
                if (LoanItem::whereKey($itemId)->where('loan_id', $loanId)->exists()) {
                    throw new BusinessException('ITEM_ALREADY_RETURNED', 'La línea ya fue devuelta.', 409);
                }
                throw new BusinessException('NOT_FOUND', 'La línea no existe.', 404);
            }

            $item = LoanItem::find($itemId);
            // Suma 1 disponible. El CHECK de la base impide superar el total (CA-07.3).
            Book::withTrashed()->whereKey($item->book_id)->increment('available_copies');
            return $item;
        });

        return $item;
    }

    // HU-09: líneas vencidas, más días de retraso primero.
    public function overdue()
    {
        $rows = DB::table('loan_item')
            ->join('loan', 'loan.id', '=', 'loan_item.loan_id')
            ->join('member', 'member.id', '=', 'loan.member_id')
            ->whereNull('loan_item.returned_at')
            ->where('loan_item.due_at', '<', now())
            ->orderBy('loan_item.due_at')
            ->get(['loan_item.loan_id', 'loan_item.id as item_id', 'loan_item.book_title',
                'member.full_name as member_name', 'loan_item.due_at']);

        $items = $rows->map(fn ($r) => [
            'loan_id' => $r->loan_id,
            'item_id' => $r->item_id,
            'book_title' => $r->book_title,
            'member_name' => $r->member_name,
            'due_at' => now()->parse($r->due_at)->utc()->format('Y-m-d\TH:i:s\Z'),
            'days_late' => (int) now()->parse($r->due_at)->diffInDays(now(), true),
        ]);

        return ['items' => $items];
    }
}